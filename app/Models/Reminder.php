<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $table = 'reminders';

    protected $fillable = [
        'event_id', 'user_id', 'reminder_type', 'reminder_time_minutes',
        'reminder_content', 'is_sent', 'sent_at', 'error_message'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope pour les rappels non envoyés
    public function scopePending($query)
    {
        return $query->where('is_sent', false);
    }
}