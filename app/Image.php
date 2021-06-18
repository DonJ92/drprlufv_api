<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Image extends Model {
	
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id', 'filename'
    ];

    /**
     * Get the user record associated with the task.
     */
    public function user()
    {
        return $this->hasOne('App\Product');
    }
}