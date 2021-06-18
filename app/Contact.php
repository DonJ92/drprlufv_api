<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model {
	
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sender', 'receiver'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'sender' => 'integer',
        'receiver' => 'integer',
    ];
}