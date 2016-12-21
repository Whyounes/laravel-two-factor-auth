# Laravel Two Factor Authentication

Two Factor Authentication for Laravel 5.3+

<p>
    <a href="https://travis-ci.org/Whyounes/laravel-two-factor-auth">
        <img src="https://travis-ci.org/Whyounes/laravel-two-factor-auth.svg?branch=master" alt="Build status" />
    </a>
    <a href="https://insight.sensiolabs.com/projects/c5adaac8-2a85-4e05-ac95-c635ff4c8a23">
        <img src="https://insight.sensiolabs.com/projects/c5adaac8-2a85-4e05-ac95-c635ff4c8a23/mini.png" />
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

There are only two configurations that you can set:

- `delete_verification_code_after_auth`: Set it to `true` if you want to delete unused verification codes after login.
- `verification_code_length`: How long the verification code is.

## Verification Code Sender

By default, the package uses [Twilio](http://twilio.com/) to send verification codes (SMS and Phone). You can easily change it like this:

```php
use Whyounes\TFAuth\Contracts\VerificationCodeSenderInterface;

class MyService implements VerificationCodeSenderInterface
{
    public function sendCodeViaSMS($code, $phone, $message = "Your verification code is %s")
    {
        // Send code and return boolean for status
    }

    public function sendCodeViaPhone($code, $phone, $message = "Your verification code is %s")
    {
        // Send code and return boolean for status
    }
}
```

Next we should switch implementation in the container:

```php
use Whyounes\TFAuth\Contracts\VerificationCodeSenderInterface;

class AppProvider extends ServiceProvider
{
    public function register()
    {
        // ...
        $this->app->bind(VerificationCodeSenderInterface::class, MyService::class);
    }
}
```

That's it, your new service is going to be used for sending verification codes. If you add a new service implementation, you can submit a new pull request and I'll add it to the package :)

## Example

Check [this repository](https://github.com/Whyounes/laravel-twilio-2fa) for a demo application using the package.
