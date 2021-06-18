<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model {
	
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'ref_id', 'title', 'message'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'ref_id' => 'integer',
    ];
}