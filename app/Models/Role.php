<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'permissions'];
    
    protected $casts = [
        'permissions' => 'array',
    ];
    
    public function users()
    {
        return $this->hasMany(User::class);
    }
    
    /**
     * Obtenir le nom du rôle pour le frontend
     */
    public function getFrontendRoleNameAttribute()
    {
        $mapping = [
            1 => 'admin',
            2 => 'assistant',
            3 => 'consultant',
            4 => 'manager',
            5 => 'direction',
        ];
        
        return $mapping[$this->id] ?? $this->slug ?? 'user';
    }
} 