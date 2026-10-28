<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'site_code',
        'name',
        'provider',
        'fee',
        'status',
    ];
}
