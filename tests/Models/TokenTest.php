<?php

use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;
use Whyounes\TFAuth\Contracts\VerificationCodeSender;
use Whyounes\TFAuth\Models\Token;
use Mockery as m;

class TokenTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function codesProvider()
    {
        return [
            [4, 1000, 9999],
            [6, 100000, 999999],
            [8, 10000000, 99999999]
        ];
    }

    /**
     * @test
     * @dataProvider codesProvider
     */
    public function test_generate_code($codeLength, $min, $max)
    {
        Config::set("auth.verification_code_length", $codeLength);
        $token = new Token;
        $code = $token->generateCode();

        $this->assertNotEmpty($code);
        $this->assertGreaterThanOrEqual($min, $code);
        $this->assertLessThanOrEqual($max, $code);
    }

    /**
     * @test
     * @expectedException Whyounes\TFAuth\Exceptions\UserNotFoundException
     */
    public function assert_send_code_throws_exception_on_no_attached_user()
    {
        $token = new Token;
        $token->user = null;
        $token->sendCode();
    }

    /**
     * @test
     * @expectedException Whyounes\TFAuth\Exceptions\TFANotEnabledException
     */
    public function assert_send_code_throws_exception_on_user_tfa_not_enabled()
    {
        $stubUser = m::mock(stdClass::class);
        $stubUser->shouldReceive('hasTFAEnabled')
            ->once()
            ->andReturn(false);
        $token = new Token;
        $token->user = $stubUser;
        $token->sendCode();
    }

    /**
     * @test
     */
    public function assert_send_code_success()
    {
        $stubVerificationCodeSender = m::mock(VerificationCodeSender::class);
        $stubVerificationCodeSender->shouldReceive('sendCodeViaSMS')
            ->once()
            ->andReturn(true);
        $this->app->instance(VerificationCodeSender::class, $stubVerificationCodeSender);

        $stubUser = m::mock(stdClass::class);
        $stubUser->shouldReceive('hasTFAEnabled')
            ->once()
            ->andReturn(true);
        $stubUser->shouldReceive('getPhoneNumber')
            ->once()
            ->andReturn('123456789');
        $token = new Token;
        $token->user = $stubUser;

        $this->assertTrue($token->sendCode());
    }
}
