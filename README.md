# Laravel Two Factor Authentication

Two Factor Authentication for Laravel 5.3+

<p>
    <a href="https://travis-ci.org/Whyounes/laravel-passwordless-auth">
        <img src="https://travis-ci.org/Whyounes/laravel-passwordless-auth.svg?branch=master" alt="Build status" />
    </a>
    <a href="https://insight.sensiolabs.com/projects/8c7964bf-58d5-4229-928b-d57010f71977">
        <img src="https://insight.sensiolabs.com/projects/8c7964bf-58d5-4229-928b-d57010f71977/mini.png" />
    </a>
</p>

## Installation

Add the package to your project using Composer:

```bash
composer require whyounes/laravel-two-factor-auth
```

Publish package assets:

```php
php artisan vendor:publish
```

Run the migrations:

```php
php artisan migrate
```

Add it to you providers list:

```php
// config/app.php

// ...
'providers' => [
    // ...
    Whyounes\TFAuth\TwoFAProvider::class,
};
```

Add the `TFAuthTrait` trait to your user model:

```php
// app/User.php

class User extends Authenticatable
{
    use \Whyounes\TFAuth\Models\TFAuthTrait;

    // ...
}
```

## Configurations

If you don't want to use the user email along with the token, you can change it by overriding the following method:

```php
// app/User.php

class User extends Authenticatable
{
    use Whyounes\Passwordless\Traits\Passwordless;

    // ...
    
    protected function getIdentifierKey()
    {
        return 'email';
    }
}
```

You can change the expiration time inside the `config/passwordless.php` file:

```php
// config/passwordless.php

return [
    'expire_in' => 15, // Minutes
    'empty_tokens_after_login' => true // Empty user tokens after login
];
```

You can set the `empty_tokens_after_login` config to false if you don't want to delete unused tokens from DB.

## Example

Display the login form for user to type the email:

```php
// routes/web.php

Route::post('/login/direct', function() {
    return view('login.direct');
});
```

Catch the form submission:

```php
// routes/web.php

Route::post('/login/direct', function(Request $request) {
    // send link to user mail
    $user = App\User::where('email', $request->get('email'))->first();
    if (!$user) {
        return redirect()->back(404)->with('error', 'User not found');
    }

    // generate token and save it
    $token = $user->generateToken(true);

    // send email to user
    \Mail::send("mails.login", ['token' => $token], function($message) use($token) {
        $message->to($token->user->email);
    });
});
```

Catch the login link request:

```php
// routes/web.php

Route::get('/login/{token}', function(Request $request, $token) {
    $user = App\User::where('email', $request->get('email'))->first();

    if (!$user) {
        dd('User not found');
    }

    if($user->isValidToken($token))
    {
        // Login user
        Auth::login($user);
    } else {
        dd("Invalid token");
    }
});
```

Or, if you like working with exceptions:

```php
// routes/web.php

Route::get('/login/{token}', function(Request $request, $token) {
    try {
        $user = App\User::where('email', $request->get('email'))->firstOrFail();
        $user->validateToken($token);

        Auth::login($user);
    } catch(Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
        dd('User not found');
    } catch(Whyounes\Passwordless\Exceptions\InvalidTokenException $ex) {
        dd("Invalid token");
    }
});
```
