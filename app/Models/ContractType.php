<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractType extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'description'];

    /**
     * Get the posts for the contract type.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}