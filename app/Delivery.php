<?php

namespace App;


use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $table = 'delivery';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'seller_id', 'buyer_id', 'product_id', 'shipping_address', 'state'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'seller_id' => 'integer',
        'buyer_id' => 'integer',
        'product_id' => 'integer'
    ];

}