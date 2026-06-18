<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSkill extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['post_id', 'skill_name', 'is_required', 'level'];

    /**
     * Get the post that owns the skill.
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}