<?php

use Illuminate\Support\Facades\Route;
use Mockery as m;
use Orchestra\Testbench\TestCase;
use Twilio\Rest\Client;
use Whyounes\TFAuth\Services\Twilio;

class TwilioTest extends TestCase
{
    private $client;

    public function setUp()
    {
        parent::setUp();

        $this->client = $this->getClientMock();

        // Mock Twilio say text route
        Route::get('/twilio/test', ["as" => "tfa.services.twilio.say", "uses" => "TwilioTest@twilioTestRoute"]);
    }

    public function getClientMock()
    {
        $messages = m::mock(stdClass::class);
        $client = m::mock(Client::class);
        $calls = m::mock(stdClass::class);

        $messages->shouldReceive('create')
            ->zeroOrMoreTimes()
            ->andReturn(true);
        $calls->shouldReceive('create')
            ->zeroOrMoreTimes()
            ->andReturn(true);
        $client->messages = $messages;
        $client->account = new stdClass();
        $client->account->calls = $calls;

        return $client;
    }

    public function twilioTestRoute()
    {
    }

    /**
     * @test
     */
    public function test_send_code_via_sms_success()
    {
        $twilio = new Twilio($this->client);
        $response = $twilio->sendCodeViaSMS("AAA", "123456789");
        $this->assertTrue($response);
    }

    /**
     * @test
     */
    public function test_send_code_via_phone_success()
    {
        $twilio = new Twilio($this->client);
        $response = $twilio->sendCodeViaPhone("AAA", "123456789");
        $this->assertTrue($response);
    }
}