<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Statut;
use App\Models\ApplicationComment;
use App\Models\ApplicationStatusHistory;

class Application extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidate_id',
        'post_id',
        'current_status_id',
        'source_id',
        'referral_by',
        'expected_salary',
        'notes',
        'internal_note',
        'assigned_to',
        'created_by',
        'documents',
        'offer_proposed',
        'offer_salary',
        'offer_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expected_salary' => 'decimal:2',
        'documents' => 'array',
        'offer_proposed' => 'boolean',
        'offer_salary' => 'decimal:2',
        'offer_expires_at' => 'datetime',
    ];

    // ==================== RELATIONS ====================

    /**
     * Relation avec le candidat
     */
    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Relation avec le poste
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Relation avec le statut actuel
     */
    public function currentStatus()
    {
        return $this->belongsTo(Statut::class, 'current_status_id');
    }

    /**
     * Relation avec la source de candidature
     */
    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Relation avec l'utilisateur qui a créé la candidature
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation avec l'historique des statuts
     */
    public function statusHistory()
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderByDesc('created_at');
    }

    /**
     * Relation avec les commentaires
     */
    public function comments()
    {
        return $this->hasMany(ApplicationComment::class)->orderByDesc('created_at');
    }

    /**
     * Relation avec les documents
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Relation avec les entretiens
     */
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    // relations avec les evaluation 
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }


    /**
     * Relation avec la shortlist
     */
    public function shortlists()
    {
        return $this->hasMany(Shortlist::class);
    }

    /**
     * Relation avec les favoris
     */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Relation avec les tags (many-to-many)
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'application_tags');
    }

    /**
     * Relation avec le motif de refus (si refusé)
     */
    public function refusal()
    {
        return $this->hasOne(Refusal::class);
    }


    // ==================== SCOPES ====================

    /**
     * Filtrer par statut
     */
    public function scopeByStatus($query, $statusName)
    {
        return $query->whereHas('currentStatus', function($q) use ($statusName) {
            $q->where('name', $statusName);
        });
    }

    /**
     * Filtrer par poste
     */
    public function scopeByPost($query, $postId)
    {
        return $query->where('post_id', $postId);
    }

    /**
     * Filtrer par candidat
     */
    public function scopeByCandidate($query, $candidateId)
    {
        return $query->where('candidate_id', $candidateId);
    }

    /**
     * Candidatures en attente (Reçue ou En cours)
     */
    public function scopePending($query)
    {
        return $query->whereHas('currentStatus', function($q) {
            $q->whereIn('name', ['Reçue', 'En cours']);
        });
    }

    // ==================== ACCESSORS ====================

    /**
     * Récupère le nom complet du candidat
     */
    public function getCandidateNameAttribute()
    {
        return $this->candidate ? $this->candidate->full_name : 'N/A';
    }

    /**
     * Récupère le titre du poste
     */
    public function getPostTitleAttribute()
    {
        return $this->post ? $this->post->title : 'N/A';
    }

    /**
     * Récupère le nom du statut actuel
     */
    public function getStatusNameAttribute()
    {
        return $this->currentStatus ? $this->currentStatus->name : 'N/A';
    }


    /**
     * Récupère la couleur du statut
     */
    public function getStatusColorAttribute()
    {
        return $this->currentStatus ? $this->currentStatus->color : '#gray';
    }







    // relation avec le departement (via le poste)
    public function department()
    {
        return $this->hasOneThrough(Department::class, Post::class, 'id', 'id', 'post_id', 'department_id');
    }

    // ==================== METHODS ====================

    /**
     * Changer le statut de la candidature
     *
     * @param int $newStatusId
     * @param int $userId
     * @param string|null $comment
     * @return bool
     */
    public function changeStatus($newStatusId, $userId, $comment = null)
    {
        $oldStatusId = $this->current_status_id;

        // Créer l'historique
        StatusHistory::create([
            'application_id' => $this->id,
            'status_id' => $newStatusId,
            'previous_status_id' => $oldStatusId,
            'changed_by' => $userId,
            'comment' => $comment,
        ]);

        // Mettre à jour le statut actuel
        $this->update(['current_status_id' => $newStatusId]);

        // Si le statut est "Accepté", fermer le poste
        $newStatus = Status::find($newStatusId);
        if ($newStatus && $newStatus->name === 'Accepté') {
            $this->post->close();
        }

        // Déclencher un événement (optionnel)
        // event(new ApplicationStatusChanged($this, $oldStatusId, $newStatusId, $userId));

        return true;
    }

    /**
     * Ajouter un commentaire à la candidature
     *
     * @param int $userId
     * @param string $comment
     * @return Comment
     */
    public function addComment($userId, $comment)
    {
        return Comment::create([
            'application_id' => $this->id,
            'user_id' => $userId,
            'comment' => $comment,
        ]);
    }

    /**
     * Vérifier si la candidature est en shortlist pour un utilisateur
     *
     * @param int $userId
     * @return bool
     */
    public function isInShortlist($userId)
    {
        return $this->shortlists()->where('user_id', $userId)->exists();
    }

    /**
     * Ajouter la candidature à la shortlist d'un utilisateur
     *
     * @param int $userId
     * @param string|null $notes
     * @return Shortlist
     */
    public function addToShortlist($userId, $notes = null)
    {
        return $this->shortlists()->create([
            'user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * Retirer la candidature de la shortlist d'un utilisateur
     *
     * @param int $userId
     * @return bool
     */
    public function removeFromShortlist($userId)
    {
        return $this->shortlists()->where('user_id', $userId)->delete();
    }

    /**
     * Calculer le temps passé depuis la candidature (en jours)
     *
     * @return int
     */
    public function getDaysSinceApplication()
    {
        return $this->created_at->diffInDays(now());
    }


    // recupere la dernier evaluation a

     public function latestEvaluation()
    {
        return $this->hasOne(Evaluation::class)->latestOfMany();
    }

    //Accesseur pour le statut label
    public function getStatusLabelAttribute()
    {
        return $this->currentStatus?->name ?? $this->status ?? 'Inconnu';
    }
}
