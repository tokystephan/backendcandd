<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventParticipant extends Model
{
    protected $table = 'event_participants';

    protected $fillable = [
        'event_id', 'user_id', 'is_organizer', 'role_in_interview',
        'invitation_status', 'response_comment', 'notified_at', 'actual_attendance'
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}