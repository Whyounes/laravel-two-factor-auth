<?php

namespace Whyounes\TFAuth\Controllers;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Whyounes\TFAuth\Models\Token;

trait TFAController
{
    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request $request
     * @return mixed
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if ($this->hasThrottleLoginTrait() && $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        $token = $this->sendVerificationCode($request);
        if ($token) {
            return redirect(static::getVerificationCodeRoute());
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        if ($this->hasThrottleLoginTrait()) {
            $this->incrementLoginAttempts($request);
        }

        return redirect()->back()
            ->withInput($request->all())
            ->withErrors([
                Lang::has('auth.failed')
            ]);
    }

    /**
     * If this class implement the ThrottleLogins Trait
     *
     * @return bool
     */
    protected function hasThrottleLoginTrait()
    {
        return $this instanceof ThrottlesLogins;
    }

    /**
     * Send a verification token to the authenticated user.
     *
     * @param Request $request
     * @return null|Token
     */
    public function sendVerificationCode(Request $request)
    {
        /** @var $auth UserProvider */
        $auth = App::make('auth')->getProvider();
        if ($user = $auth->retrieveByCredentials($request->only('email', 'password'))) {
            $token = App::make(Token::class);
            $token->user_id = $user->getAuthIdentifier();
            $token->save();

            if ($token->sendCode()) {
                $session = session();
                $session->set("token_id", $token->id);
                $session->set("user_id", $user->getAuthIdentifier());
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
     * Get verification route
     *
     * @return string
     */
    public static function getVerificationCodeRoute()
    {
        return 'login/tfa';
    }

    /**
     * Re-send verification code
     *
     * @return mixed
     */
    public function resendVerificationCode()
    {
        $tokenId = session()->get("token_id");
        $token = App::make(Token::class)->find($tokenId);

        if (!$token || !$token->sendCode()) {
            return [
                'error' => true,
                'message' => 'Invalid token ID'
            ];
        }

        return [
            'error' => false,
            'message' => ''
        ];
    }

    /**
     * Show second factor form
     *
     * @return mixed
     */
    public function showVerificationCodeForm()
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
    public function storeVerificationCodeForm(Request $request)
    {
        if (!session()->has("token_id", "user_id")) {
            return redirect("login");
        }

        $this->validate($request, [
            'code' => 'required'
        ]);

        if ($this->hasThrottleLoginTrait() && $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        /** @var $token Token */
        $token = App::make(Token::class)->find(session()->get("token_id"));
        if (!$token ||
            !$token->user ||
            !$token->isValid() ||
            $request->get('code') !== $token->code ||
            (int)session()->get("user_id") !== $token->user->id
        ) {
            if ($this->hasThrottleLoginTrait()) {
                $this->incrementLoginAttempts($request);
            }

            return redirect()
                ->back()
                ->withInput($request->only('code'))
                ->withErrors([Lang::get('auth.invalid_token')]);
        }

        $token->used = true;
        $token->save();

        $session = session();
        App::make('auth')->guard()->login($token->user, $session->get('remember', false));
        $session->forget('token_id', 'user_id', 'remember');

        if ($this->hasThrottleLoginTrait()) {
            $request->session()->regenerate();
            $this->clearLoginAttempts($request);
        }

        return redirect(isset($this->redirectTo) ? $this->redirectTo : '/home');
    }
}
