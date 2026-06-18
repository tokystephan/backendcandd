<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'name',
        'description',
        'manager_id',
    ];

    /**
     * ✅ CORRECTION : Retirer 'manager' du tableau $with pour éviter les boucles infinies
     * Le tableau $with charge automatiquement les relations à chaque requête.
     * Cela peut causer des problèmes si la relation manager charge aussi department.
     */
    // protected $with = ['manager'];  // ← À COMMENTER ou SUPPRIMER

    // ==================== RELATIONS ====================

    /**
     * Relation avec les utilisateurs du département
     * Un département a plusieurs utilisateurs
     */
    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    /**
     * ✅ CORRECTION : Relation avec les consultants (role_id=3) et managers (role_id=4)
     * Utiliser role_id au lieu de 'role' (colonne textuelle)
     */
    public function consultants()
    {
        return $this->users()->whereIn('role_id', [3, 4]); // 3=Consultant, 4=Manager
    }

    /**
     * Relation avec les postes du département
     * Un département a plusieurs postes
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'department_id');
    }

    /**
     * Relation avec les postes actifs du département
     */
    public function activePosts()
    {
        return $this->posts()->where('status', 'ouvert');
    }

    /**
     * ✅ CORRECTION : Relation avec les candidatures via les postes
     * Un département a plusieurs candidatures (via les postes)
     */
    public function applications()
    {
        return $this->hasManyThrough(
            Application::class,  // Table finale
            Post::class,         // Table intermédiaire
            'department_id',     // Clé étrangère sur posts
            'post_id',           // Clé étrangère sur applications
            'id',                // Clé locale sur departments
            'id'                 // Clé locale sur posts
        );
    }

    /**
     * Relation avec le manager (responsable du département)
     * Un département appartient à un manager (User)
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // ==================== ACCESSOIRES (ATTRIBUTS DYNAMIQUES) ====================

    /**
     * Compter les candidatures du département
     */
    public function getApplicationsCountAttribute()
    {
        return $this->applications()->count();
    }

    /**
     * Compter les postes actifs du département
     */
    public function getActivePostsCountAttribute()
    {
        return $this->posts()->where('status', 'ouvert')->count();
    }

    /**
     * Compter les consultants du département (role_id=3 et 4)
     */
    public function getConsultantsCountAttribute()
    {
        return $this->consultants()->count();
    }

    /**
     * Compter les candidatures en attente d'évaluation technique
     * Statuts: 2=En cours, 3=Entretien RH, 4=Entretien technique
     */
    public function getPendingEvaluationsCountAttribute()
    {
        return $this->applications()
            ->whereIn('current_status_id', [2, 3, 4])
            ->count();
    }

    /**
     * ✅ AJOUT : Compter les entretiens à venir
     */
    public function getUpcomingInterviewsCountAttribute()
    {
        return $this->applications()
            ->whereHas('events', function($q) {
                $q->where('start_datetime', '>', now())
                  ->where('status', 'planifie');
            })
            ->count();
    }

    /**
     * ✅ AJOUT : Taux de conversion (acceptés / total)
     */
    public function getConversionRateAttribute()
    {
        $total = $this->applications()->count();
        if ($total == 0) return 0;
        
        $accepted = $this->applications()
            ->where('current_status_id', 5) // 5 = Accepté
            ->count();
        
        return round(($accepted / $total) * 100, 2);
    }

    // ==================== SCOPES ====================

    /**
     * Scope pour filtrer par nom (recherche)
     */
    public function scopeByName($query, $name)
    {
        if ($name) {
            return $query->where('name', 'like', "%{$name}%");
        }
        return $query;
    }

    /**
     * Scope pour les départements avec postes actifs
     */
    public function scopeWithActivePosts($query)
    {
        return $query->whereHas('posts', function($q) {
            $q->where('status', 'ouvert');
        });
    }

    /**
     * ✅ AJOUT : Scope pour les départements qui ont un manager
     */
    public function scopeWithManager($query)
    {
        return $query->whereNotNull('manager_id');
    }

    /**
     * ✅ AJOUT : Scope pour les départements sans manager
     */
    public function scopeWithoutManager($query)
    {
        return $query->whereNull('manager_id');
    }
}