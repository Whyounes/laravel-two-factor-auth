<?php

namespace Whyounes\TFAuth\Models;

/**
 * Class TFAuthTrait
 *
 * @package Whyounes\TFAuth\Models
 *
 * @property string country_code
 * @property string phone
 */
trait TFAuthTrait
{
    /**
     * User tokens relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tokens()
    {
        return $this->hasMany(Token::class);
    }

    /**
     * Return the country code and phone number concatenated
     *
     * @return string
     */
    public function getPhoneNumber()
    {
        return $this->country_code.$this->phone;
    }

    /**
     * If this user has two authentication enabled
     *
     * @return bool
     */
    public function hasTFAEnabled()
    {
        return true;
    }
}
