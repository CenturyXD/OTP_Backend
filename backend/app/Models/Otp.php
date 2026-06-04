<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    //
    protected $table = 'otp';
    protected $fillable = [
        'email',
        'service',
        'password',
        'is_verified',
        'expires_at',
        'owner',
        'mail_type',
    ];
}
