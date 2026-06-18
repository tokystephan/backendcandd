<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'source', 'source_id', 'cv_path', 'motivation_letter_path',
        'documents',
    ];

    protected $casts = [
        'documents' => 'array',
    ];

    public function skills()
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function candidateDocuments()
    {
        return $this->hasMany(Document::class);
    }

    // Scopes
    public function scopeSearch($query, $term)
    {
        return $query->where('first_name', 'LIKE', "%{$term}%")
                     ->orWhere('last_name', 'LIKE', "%{$term}%")
                     ->orWhere('email', 'LIKE', "%{$term}%");
    }

    // Accessor
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}