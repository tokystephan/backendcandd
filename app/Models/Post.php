<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title', 'department_id', 'contract_type_id',
        'description', 'requirements', 'status',
        'created_by', 'closed_at', 'is_archived'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'closed_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    // ==================== CONSTANTES ====================
    const STATUS_OPEN = 'ouvert';
    const STATUS_CLOSED = 'ferme';
    const STATUS_PENDING = 'en_attente';

    // ==================== RELATIONS ====================
    
    /**
     * Get the department that owns the post.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the contract type that owns the post.
     */
    public function contractType()
    {
        return $this->belongsTo(ContractType::class);
    }

    /**
     * Get the user who created the post.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the skills for the post.
     */
    public function skills()
    {
        return $this->hasMany(PostSkill::class);
    }

    /**
     * Get the applications for the post.
     */
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    // ==================== SCOPES ====================
    
    /**
     * Scope a query to only include open posts.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope a query to only include posts by department.
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    // ==================== ACCESSORS ====================
    
    /**
     * Get the applications count.
     */
    public function getApplicationsCountAttribute()
    {
        return $this->applications()->count();
    }

    /**
     * Get the accepted applications count.
     */
    public function getAcceptedCountAttribute()
    {
        return $this->applications()
            ->whereHas('currentStatus', function($q) {
                $q->where('name', 'Accepté');
            })->count();
    }

    // ==================== METHODS ====================
    
    /**
     * Close the post.
     */
    public function close()
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    /**
     * Open the post.
     */
    public function open()
    {
        $this->update([
            'status' => self::STATUS_OPEN,
            'closed_at' => null,
        ]);
    }
}