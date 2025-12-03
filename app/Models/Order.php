<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'items',
        'subtotal',
        'delivery_fee',
        'total',
        'payment_method',
        'payment_status',
        'order_status',

        'delivery_first_name',
        'delivery_last_name',
        'delivery_email',
        'delivery_phone',
        'delivery_address',
        'delivery_city',
        'delivery_state',
        'delivery_zip',
        'delivery_country',
    ];

    protected $casts = [
        'items' => 'array',
    ];
      public function user()
    {
        return $this->belongsTo(User::class);
    }

}
