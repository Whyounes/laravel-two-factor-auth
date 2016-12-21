<?php

namespace Whyounes\TFAuth\Contracts;

/**
 * Interface VerificationCodeSender
 * Use this interface to create a bridge to your verification provider. (e.g: Twilio)
 *
 * @package Whyounes\TFAuth\Contracts
 */
interface VerificationCodeSenderInterface
{

    /**
     * Send the code via SMS
     *
     * @param        string $code Verification code
     * @param        string $phone User phone number
     * @param        string $message Message format, will be passed to printf
     * @return bool
     */
    public function sendCodeViaSMS($code, $phone, $message = "Your verification code is %s");

    /**
     * Send code via Phone call
     *
     * @param        string $code Verification code
     * @param        string $phone User phone number
     * @param        string $message Message format, will be passed to printf
     * @return bool
     */
    public function sendCodeViaPhone($code, $phone, $message = "Your verification code is %s");
}
