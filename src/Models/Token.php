<?php

namespace Whyounes\TFAuth\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Whyounes\TFAuth\Contracts\VerificationCodeSenderInterface;
use Whyounes\TFAuth\Exceptions\TFANotEnabledException;
use Whyounes\TFAuth\Exceptions\UserNotFoundException;

/**
 * Class Token
 *
 * @package Whyounes\TFAuth\Models
 */
class Token extends Model
{
    const EXPIRATION_TIME = 15; // minutes

    protected $table = 'tfa_tokens';

    protected $fillable = [
        'code',
        'user_id',
        'used'
    ];

    public function __construct(array $attributes = [])
    {
        if (!isset($attributes['code'])) {
            $attributes['code'] = $this->generateCode();
        }

        parent::__construct($attributes);
    }

    /**
     * Generate a six digits code
     *
     * @return string
     */
    public function generateCode()
    {
        $codeLength = (int) config('auth.verification_code_length', 6) - 1;
        $min = pow(10, $codeLength);
        $max = $min * 10 - 1;
        $code = mt_rand($min, $max);

        return $code;
    }

    /**
     * User tokens relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config("auth.providers.users.model"));
    }

    /**
     * Send code to user
     *
     * @return bool
     * @throws \Exception
     */
    public function sendCode()
    {
        if (!$this->user) {
            throw new UserNotFoundException;
        }

        if (!$this->user->hasTFAEnabled()) {
            throw new TFANotEnabledException;
        }

        if (!$this->code) {
            $this->code = $this->generateCode();
        }

        /** @var $codeSender VerificationCodeSenderInterface */
        $codeSender = App::make(VerificationCodeSenderInterface::class);

        return $codeSender->sendCodeViaSMS($this->code, $this->user->getPhoneNumber());
    }

    /**
     * True if the token is not used nor expired
     *
     * @return bool
     */
    public function isValid()
    {
        return !$this->isUsed() && !$this->isExpired();
    }

    /**
     * Is the current token used
     *
     * @return bool
     */
    public function isUsed()
    {
        return $this->used;
    }

    /**
     * Is the current token expired
     *
     * @return bool
     */
    public function isExpired()
    {
        return $this->created_at->diffInMinutes(Carbon::now()) > static::EXPIRATION_TIME;
    }
}
