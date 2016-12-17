<?php


namespace Whyounes\TFAuth\Controllers;


use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Whyounes\TFAuth\Models\Token;

trait TFAController
{
    protected static $verificationCodeRoute = 'login/tfa';

    /**
     * Get verification route
     *
     * @return string
     */
    public static function getVerificationCodeRoute()
    {
        return static::$verificationCodeRoute;
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            $this->sendVerificationCodeRequest($request);

            return redirect(static::$verificationCodeRoute);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Send a verification token to the authenticated user.
     *
     * @param Request $request
     * @return null|Token
     */
    public function sendVerificationCodeRequest(Request $request)
    {
        /** @var $auth UserProvider */
        $auth = App::make('auth')->getProvider();
        if ($user = $auth->retrieveByCredentials($request->only('email', 'password'))) {
            $token = Token::create([
                'user_id' => $user->id
            ]);

            if ($token->sendCode()) {
                $session = session();
                $session->set("token_id", $token->id);
                $session->set("user_id", $user->id);
                $session->set("remember", $session->get('remember', false));

                return $token;
            }

            // delete token because it can't be sent
            $token->delete();

            return null;
        }

        return null;
    }

    /**
     * Show second factor form
     *
     * @return mixed
     */
    public function showCodeForm()
    {
        if (!session()->has("token_id", "user_id")) {
            return redirect("login");
        }

        return view("tfa::verification_code");
    }

    /**
     * Store and verify user second factor.
     *
     * @param Request $request
     * @return mixed
     */
    public function storeCodeForm(Request $request)
    {
        if (!session()->has("token_id", "user_id")) {
            return redirect("login");
        }

        $this->validate($request, [
            'code' => 'required'
        ]);

        $useThrottlesLogins = $this instanceof ThrottlesLogins;
        if (($useThrottlesLogins) && $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        /** @var $token Token */
        $token = Token::find(session()->get("token_id"));
        if (!$token ||
            !$token->user ||
            !$token->isValid() ||
            $request->get('code') !== $token->code ||
            (int)session()->get("user_id") !== $token->user->id
        ) {
            if ($useThrottlesLogins) {
                $this->incrementLoginAttempts($request);
            }

            return redirect()
                ->back()
                ->withInput($request->only('code'))
                ->withErrors([Lang::get('auth.failed')]);
        }

        $token->used = true;
        $token->save();

        $session = session();
        $session->guard()->login($token->user, $session->get('remember', false));
        $session->forget('token_id', 'user_id', 'remember');

        return ($useThrottlesLogins) ? $this->sendLoginResponse($request) : redirect($this->redirectTo ?: '/home');
    }
}