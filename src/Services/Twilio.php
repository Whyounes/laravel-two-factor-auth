<?php


namespace Whyounes\TFAuth\Services;


use Twilio\Rest\Client;
use Whyounes\TFAuth\Contracts\VerificationCodeSender;

/**
 * Class Twilio
 *
 * @package Whyounes\TFAuth\Services
 */
class Twilio implements VerificationCodeSender
{
    /**
     * Twilio client
     *
     * @var Client
     */
    protected $client;

    /**
     * Phone number to send from
     *
     * @var string
     */
    protected $number;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->number = config("services.twilio.number");
    }

    public function sendCodeViaSMS($code, $phone, $message = "Your verification code is %s")
    {
        try {
            $this->client->messages->create($phone, [
                "from" => $this->number,
                "body" => printf($message, $code)
            ]);
        } catch (\Exception $ex) {
            return false;
        }

        return true;
    }

    public function sendCodeViaPhone($code, $phone, $message = "Your verification code is %s")
    {
        try {
            $this->client->account->calls->create(
                $phone,
                $this->number,
                ["url" => route('tfa.services/twilio.say', ["text" => printf($message, $code)])]
            );
        } catch (\Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Client $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @return mixed
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param mixed $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }
}