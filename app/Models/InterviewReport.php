<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewReport extends Model
{
    protected $table = 'interview_reports';

    protected $fillable = [
        'event_id', 'evaluation_notes', 'strengths', 'weaknesses',
        'next_steps', 'recommendation', 'created_by', 'validated_by', 'validated_at'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}