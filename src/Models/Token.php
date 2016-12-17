<?php

namespace Whyounes\TFAuth\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Whyounes\TFAuth\Contracts\VerificationCodeSender;
use Whyounes\TFAuth\Exceptions\UserNotFoundException;

/**
 * Class Token
 *
 * @package Whyounes\TFAuth\Models
 */
class Token extends Model
{
    const EXPIRATION_TIME = 15; // minutes

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
        $code = mt_rand(1000, 9999);

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

        if (!$this->code) {
            $this->code = $this->generateCode();
        }

        /** @var $codeSender VerificationCodeSender */
        $codeSender = App::make(VerificationCodeSender::class);
        $codeSender->sendCodeViaSMS($this->code, $this->user->getPhoneNumber());

        return true;
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
