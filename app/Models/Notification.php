<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * Types de notifications disponibles
     */
    const TYPES = [
        'nouvelle_candidature',
        'changement_statut',
        'entretien_planifie',
        'rappel_entretien',
        'offre_validation',
        'nouveau_commentaire',
        'system_alert',
        'nouvel_utilisateur',
        'poste_validation',
        'decision_direction',
    ];

    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'data',
        'is_read',
        'read_at',
    ];

    /**
     * Les attributs qui doivent être castés.
     */
    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Les valeurs par défaut des attributs.
     */
    protected $attributes = [
        'is_read' => false,
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: obtenir les notifications non lues
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope: obtenir les notifications lues
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope: obtenir les notifications d'un utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: obtenir les notifications par type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: obtenir les notifications récentes
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Marquer comme lue
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        return $this;
    }

    /**
     * Marquer comme non lue
     */
    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
        return $this;
    }

    /**
     * Vérifier si la notification est lue
     */
    public function isRead(): bool
    {
        return $this->is_read;
    }

    /**
     * Vérifier si la notification est non lue
     */
    public function isUnread(): bool
    {
        return !$this->is_read;
    }
}