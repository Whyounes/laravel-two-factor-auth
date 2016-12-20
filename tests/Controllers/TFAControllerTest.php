<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Foundation\Testing\Concerns\InteractsWithContainer;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewFactory;
use Mockery as m;
use Orchestra\Testbench\TestCase;
use Whyounes\TFAuth\Controllers\TFAController;
use Whyounes\TFAuth\Models\Token;

class TFAControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function setUp()
    {
        parent::setUp();
    }

    /**
     * @test
     * @expectedException Illuminate\Validation\ValidationException
     */
    public function assert_throws_validation_exception_on_missing_data()
    {
        $controller = new TFAControllerStub;
        $controller->login(Request::capture());
    }

    /**
     * @test
     */
    public function assert_invalid_credentials_on_login()
    {
        $credentials = [
            'email' => "younes@gmail.com",
            'password' => "younes"
        ];
        $request = Request::capture();
        $request['email'] = $credentials['email'];
        $request['password'] = $credentials['password'];

        // Mock user
        $user = m::mock(Authenticatable::class);
        $user->shouldReceive('getAuthIdentifier')
            ->zeroOrMoreTimes()
            ->andReturn(1);

        // Mock Token class
        $token = m::mock(Token::class . "[save, delete, sendCode]");
        $token->shouldReceive('save')
            ->twice()
            ->andReturn(true);
        $token->shouldReceive('delete')
            ->once()
            ->andReturn(true);
        $token->shouldReceive('sendCode')
            ->once()
            ->andReturn(true);
        $token->shouldReceive('sendCode')
            ->once()
            ->andReturn(false);
        $token->shouldReceive('user')
            ->andReturn($user);
        $token->id = 1;
        $this->app->instance(Token::class, $token);

        // mock the guard
        $guard = $this->mockGuard();
        $auth = $this->app->make('auth');

        $auth->getProvider()->shouldReceive('retrieveByCredentials')
            ->with($credentials)
            ->andReturn($user);

        // Run controller
        $controller = new TFAControllerStub;
        $response = $controller->login($request);
        $session = $response->getSession();

        $this->assertTrue($response->isRedirection());
        $this->assertEquals(url(TFAControllerStub::getVerificationCodeRoute()), $response->getTargetUrl());
        $this->assertTrue($session->has('token_id'));
        $this->assertTrue($session->has('user_id'));

        // sendCode is false now
        $response = $controller->login($request);
        $this->assertTrue($response->isRedirection());
        $this->assertGreaterThanOrEqual(1, $response->getSession()->get('errors')->count());
    }

    /**
     * Mock guard and auth
     *
     * @return m\MockInterface
     */
    protected function mockGuard()
    {
        $guard = m::mock(Guard::class);
        $provider = m::mock(UserProvider::class);
        $auth = m::mock(AuthManager::class);

        $auth->shouldReceive('getProvider')
            ->zeroOrMoreTimes()
            ->andReturn($provider);

        $auth->shouldReceive('guard')
            ->zeroOrMoreTimes()
            ->andReturn($guard);

        $this->app->instance('auth', $auth);

        return $guard;
    }

    /**
     * @test
     */
    public function test_show_verification_code_form()
    {
        View::addNamespace("tfa", __DIR__ . "/../../resources/views/");
        $this->session([
            "token_id" => 1,
            "user_id" => 1
        ]);

        $controller = new TFAControllerStub;
        $response = $controller->showVerificationCodeForm();

        $this->assertInstanceOf(ViewFactory::class, $response);
    }

    /**
     * @test
     */
    public function test_redirect_from_verification_code_form_if_no_session_isset()
    {
        View::addNamespace("tfa", __DIR__ . "/../../resources/views/");

        $controller = new TFAControllerStub;
        $response = $controller->showVerificationCodeForm();

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * @test
     */
    public function test_verification_code_form_submission_redirect_if_session_not_set()
    {
        $request = Request::capture();
        $controller = new TFAControllerStub;
        $response = $controller->storeVerificationCodeForm($request);

        // redirect if no session set
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * @test
     * @expectedException Illuminate\Validation\ValidationException
     */
    public function test_verification_code_form_submission_throws_validation_exception()
    {
        $this->session([
            "token_id" => 1,
            "user_id" => 1
        ]);
        $request = Request::capture();
        $controller = new TFAControllerStub;
        $controller->storeVerificationCodeForm($request);
    }

    /**
     * When the token doesn't exist on database
     *
     * @test
     */
    public function test_verification_code_form_submission_redirect_on_invalid_token()
    {
        $this->session([
            "token_id" => 1,
            "user_id" => 1
        ]);
        $request = Request::capture();
        $request['code'] = 'AAA';

        // Mock Token class
        $token = m::mock(Token::class . "[find]");
        $token->shouldReceive('find')
            ->once()
            ->andReturn(null);
        $this->app->instance(Token::class, $token);

        $controller = new TFAControllerStub;
        $response = $controller->storeVerificationCodeForm($request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        // mock token and guard

    }

    /**
     * When token doesn't belong to a user
     *
     * @test
     */
    public function test_verification_code_form_submission_redirect_on_invalid_token2()
    {
        $this->session([
            "token_id" => 1,
            "user_id" => 1
        ]);
        $request = Request::capture();
        $request['code'] = 'AAA';

        // Mock Token class
        $token = m::mock(Token::class . "[find]");
        $token->shouldReceive('find')
            ->once()
            ->andReturn($token);
        $token->user_id = 1;
        $token->code = "AAA";
        $token->user = null;
        $this->app->instance(Token::class, $token);

        $controller = new TFAControllerStub;
        $response = $controller->storeVerificationCodeForm($request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * When Token::isvalid() method returns false
     *
     * @test
     */
    public function test_verification_code_form_submission_redirect_on_invalid_token3()
    {
        $this->session([
            "token_id" => 1,
            "user_id" => 1
        ]);
        $request = Request::capture();
        $request['code'] = 'AAA';

        // Mock Token class
        $token = m::mock(Token::class . "[find, isValid]");
        $token->shouldReceive('find')
            ->once()
            ->andReturn($token);
        $token->shouldReceive('isValid')
            ->once()
            ->andReturn(false);
        $token->user = 1;
        $this->app->instance(Token::class, $token);

        $controller = new TFAControllerStub;
        $response = $controller->storeVerificationCodeForm($request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * When token code is different to the request code value
     *
     * @test
     */
    public function test_verification_code_form_submission_redirect_on_invalid_token4()
    {
        $this->session([
            "token_id" => 1,
            "user_id" => 1
        ]);
        $request = Request::capture();
        $request['code'] = 'AAA';

        // Mock Token class
        $token = m::mock(Token::class . "[find, isValid]");
        $token->shouldReceive('find')
            ->once()
            ->andReturn($token);
        $token->shouldReceive('isValid')
            ->once()
            ->andReturn(true);
        $token->user_id = 1;
        $token->code = "BBB";
        $token->user = (object)['id' => 1];
        $this->app->instance(Token::class, $token);

        $controller = new TFAControllerStub;
        $response = $controller->storeVerificationCodeForm($request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * When retrieved token does not belong to this user
     *
     * @test
     */
    public function test_verification_code_form_submission_redirect_on_invalid_token5()
    {
        $this->session([
            "token_id" => 1,
            "user_id" => 1
        ]);
        $request = Request::capture();
        $request['code'] = 'AAA';

        // Mock Token class
        $token = m::mock(Token::class . "[find, isValid]");
        $token->shouldReceive('find')
            ->once()
            ->andReturn($token);
        $token->shouldReceive('isValid')
            ->once()
            ->andReturn(true);
        $token->user_id = 1;
        $token->code = "AAA";
        $token->user = (object)['id' => 2];
        $this->app->instance(Token::class, $token);

        $controller = new TFAControllerStub;
        $response = $controller->storeVerificationCodeForm($request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * @test
     */
    public function test_verification_code_form_submission_passes()
    {
        $this->session([
            "token_id" => 1,
            "user_id" => 1
        ]);
        $request = Request::capture();
        $request['code'] = 'AAA';

        // Mock Token class
        $token = m::mock(Token::class . "[save, find, isValid]");
        $token->shouldReceive('find')
            ->once()
            ->andReturn($token);
        $token->shouldReceive('isValid')
            ->once()
            ->andReturn(true);
        $token->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $auth = $this->mockGuard();
        $auth->shouldReceive('login')
            ->andReturn(true);

        $token->user_id = 1;
        $token->code = "AAA";
        $token->user = (object)['id' => 1];
        $this->app->instance(Token::class, $token);

        $controller = new TFAControllerStub;
        $response = $controller->storeVerificationCodeForm($request);
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * @test
     */
    public function test_resend_verification_code()
    {
        $this->session([
            "token_id" => 1
        ]);

        // Mock Token class
        $token = m::mock(Token::class . "[sendCode, find]");
        $token->shouldReceive('find')
            ->once()
            ->andReturn($token);
        $token->shouldReceive('find')
            ->once()
            ->andReturn(null);
        $token->shouldReceive('sendCode')
            ->once()
            ->andReturn(true);
        $this->app->instance(Token::class, $token);

        $controller = new TFAControllerStub;

        // Verify valid token
        $response = $controller->resendVerificationCode();
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertFalse($response['error']);

        // Verify invalid token
        $response = $controller->resendVerificationCode();
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertTrue($response['error']);
    }
}

class TFAControllerStub extends Controller
{
    use InteractsWithContainer, ValidatesRequests, AuthenticatesUsers, TFAController {
        TFAController::login insteadof AuthenticatesUsers;
    }
}