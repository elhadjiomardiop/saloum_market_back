<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'description',
        'discount',
        'for_new_user',
        'for_member',
        'is_public',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'for_new_user' => 'boolean',
            'for_member' => 'boolean',
            'is_public' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }
}
