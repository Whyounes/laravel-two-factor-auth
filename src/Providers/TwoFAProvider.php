<?php

namespace Whyounes\TFAuth;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Twilio\Rest\Client;
use Whyounes\TFAuth\Contracts\VerificationCodeSender;

/**
 * Class TwoFAProvider
 *
 * @package Whyounes\TFAuth
 */
class TwoFAProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/auth.php', 'auth'
        );
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/services.php', 'services'
        );

        $this->loadViewsFrom(__DIR__.'/resources/views', 'tfa');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/tfa'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerBindings();
        $this->registerEvents();
        $this->registerRoutes();
    }

    /**
     * Register container bindings
     */
    public function registerBindings()
    {
        $this->app->singleton(Client::class, function () {
            return new Client(config('services.twilio.sid'), config('services.twilio.token'));
        });

        $this->app->bind(VerificationCodeSender::class, Client::class);
    }

    /**
     * Register application event listeners
     */
    public function registerEvents()
    {
        // Delete user tokens after login
        if (config('auth.delete_verification_code_after_auth', false) === true) {
            Event::listen(
                Authenticated::class,
                function (Authenticated $event) {
                    $event->user->tokens()
                        ->delete();
                }
            );
        }
    }

    /**
     * Register routes
     */
    public function registerRoutes()
    {
        /** @var $router Router */
        $router = App::make("router");
        $router->get("/tfa/services/twilio/say/{text}", function ($text) {
            $response = "<Response><Say>" . $text . "</Say></Response>";

            return $response;
        })->name('tfa.services/twilio.say');

    }
}
