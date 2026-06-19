<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    
    protected $fillable = [
        'username', 'email', 'password', 'first_name', 'last_name',
        'role_id', 'department_id', 'is_active', 'last_login', 'profile_image', 'approval_status'
    ];
    
    protected $hidden = ['password', 'remember_token'];
    
    protected $casts = [
        'is_active' => 'boolean',
        'last_login' => 'datetime',
    ];
    
    // ========== RELATIONS ==========
    
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    
    public function managedDepartment()
    {
        return $this->hasOne(Department::class, 'manager_id');
    }
    
    // ========== ACCESSORS ==========
    
    /**
     * ✅ AJOUT OBLIGATOIRE : Accesseur pour profile_image_url
     * Résout l'erreur: Call to undefined method getProfileImageUrlAttribute()
     */
    public function getProfileImageUrlAttribute()
    {
        // Si une image est stockée
        if ($this->profile_image) {
            // Vérifier si c'est une URL complète
            if (filter_var($this->profile_image, FILTER_VALIDATE_URL)) {
                return $this->profile_image;
            }
            
            // Chemin local stocké dans storage/app/public
            return asset('storage/' . $this->profile_image);
        }
        
        // Image par défaut (avatar avec initiales)
        return $this->getDefaultAvatarUrl();
    }
    
    /**
     * ✅ AJOUT : Générer une URL d'avatar par défaut
     */
    public function getDefaultAvatarUrl()
    {
        $name = $this->full_name;
        $encodedName = urlencode($name);
        return "https://ui-avatars.com/api/?name={$encodedName}&background=2A5C8E&color=fff&size=100";
    }
    
    public function getRoleNameAttribute(): string
    {
        return $this->role ? $this->role->name : 'Inconnu';
    }
    
    /**
     * ✅ CORRECTION : Retourne le slug normalisé (minuscule)
     * Utilisé par le frontend pour la redirection
     */
    public function getRoleSlugAttribute(): string
    {
        $roleMapping = [
            1 => 'admin',
            2 => 'assistant',
            3 => 'manager',
            4 => 'manager',
            5 => 'direction',
        ];
        
        return $roleMapping[$this->role_id] ?? 'user';
    }
    
    public function getDepartmentNameAttribute(): string
    {
        return $this->department ? $this->department->name : 'Aucun';
    }
    
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name) ?: $this->username;
    }
    
    /**
     * ✅ AJOUT : Accesseur pour le nom complet (alias)
     */
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }
    
    // ========== ROLE CHECKS ==========
    
    public function isAdmin(): bool
    {
        return $this->role_id === 1;
    }
    
    public function isAssistant(): bool
    {
        return $this->role_id === 2;
    }
    
    public function isConsultant(): bool
    {
        return in_array((int) $this->role_id, [3, 4], true);
    }
    
    public function isManager(): bool
    {
        return in_array((int) $this->role_id, [3, 4], true);
    }
    
    public function isDirection(): bool
    {
        return $this->role_id === 5;
    }
    
    /**
     * ✅ CORRECTION : Vérifier si l'utilisateur a besoin d'un département
     */
    public function requiresDepartment(): bool
    {
        return in_array((int) $this->role_id, [3, 4], true);
    }
    
    /**
     * ✅ CORRECTION : Vérifier si l'utilisateur a un département valide
     */
    public function hasValidDepartment(): bool
    {
        if (!$this->requiresDepartment()) {
            return true;
        }
        
        return !is_null($this->department_id) && !is_null($this->department);
    }
    
    public function hasRole(int|string $role): bool
    {
        if (is_numeric($role)) {
            return $this->role_id === $role;
        }
        
        return $this->role_slug === $role;
    }
    
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }
    
    // ========== DEPARTMENT CHECKS ==========
    
    public function hasDepartment(): bool
    {
        return !is_null($this->department_id);
    }
    
    public function isManagerOfDepartment(): bool
    {
        return $this->isManager() && $this->managedDepartment !== null;
    }
    
    /**
     * ✅ CORRECTION : Route de redirection basée sur role_slug
     */
    public function getDashboardRoute(): string
    {
        $routes = [
            'admin' => '/dashboard/admin',
            'assistant' => '/dashboard/assistant',
            'manager' => '/dashboard/manager',
            'direction' => '/dashboard/direction',
        ];
        
        return $routes[$this->role_slug] ?? '/dashboard';
    }
    
    // ========== SCOPES ==========
    
    public function scopeByRole($query, int $roleId)
    {
        return $query->where('role_id', $roleId);
    }
    
    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * ✅ AJOUT : Scope pour les utilisateurs en attente d'approbation
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }
    
    /**
     * ✅ AJOUT : Scope pour les utilisateurs approuvés
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }
}
