<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $table = 'events';

    protected $fillable = [
        'application_id', 'candidate_id', 'event_type', 'title', 'description',
        'start_datetime', 'end_datetime', 'actual_start', 'actual_end',
        'location_type', 'location', 'meeting_link', 'phone_number',
        'status', 'cancellation_reason', 'rescheduled_from', 'created_by'
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
    ];

    // Relations
    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    // relation avec les paerticipants
    public function participants()
    {
        return $this->hasMany(EventParticipant::class);
    }

    // relation avec la rapportt de'enttretien 
    public function report()
    {
        return $this->hasOne(InterviewReport::class, 'event_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rescheduledFrom()
    {
        return $this->belongsTo(Event::class, 'rescheduled_from');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    // verifier si l'users est participants
    public function isParticipant($userId)
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    //Récupérer la réponse d'un participant
    public function getParticipantResponse($userId)
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        return $participant ? $participant->invitation_status : null;
    }

    // Scopes
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>', now())->where('status', 'planifie');
    }
}