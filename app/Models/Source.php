<?php
// app/Models/Source.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $fillable = ['name', 'is_active'];

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}