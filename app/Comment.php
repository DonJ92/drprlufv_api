<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model {
	
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id', 'user_id', 'parent_id', 'text',
    ];

    /**
     * Get the user record associated with the task.
     */
    public function user()
    {
        return $this->hasOne('App\Product');
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'product_id' => 'integer',
        'user_id' => 'integer',
        'parent_id' => 'integer'
    ];
}