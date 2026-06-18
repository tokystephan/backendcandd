<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statut extends Model
{
    use HasFactory;

    protected $table = 'statuses';

    protected $fillable = [
        'name',
        'label',
    ];

    /**
     * Couleur (optionnel) associée au statut.
     */
    public function getColorAttribute(): ?string
    {
        if ($this->name === 'Reçue') {
            return '#22C55E';
        }

        if ($this->name === 'En cours') {
            return '#F59E0B';
        }

        if ($this->name === 'Accepté' || $this->name === 'Acceptées') {
            return '#2E7D32';
        }

        if ($this->name === 'Refusé' || $this->name === 'Refusées') {
            return '#EF4444';
        }

        return null;
    }

    /**
     * Historique des changements de statut.
     * Correspond à la table `application_status_history`.
     */
    public function statusHistories()
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'status_id');
    }

    // Nombre de candidatures avec ce statut
public function applications()
{
    return $this->hasMany(Application::class, 'current_status_id');
}
}

