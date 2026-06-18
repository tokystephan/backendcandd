<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Availability extends Model
{
    protected $table = 'availability';

    protected $fillable = [
        'user_id', 'availability_type', 'day_of_week', 'start_time', 'end_time',
        'specific_date', 'specific_start', 'specific_end', 'unavailable_start',
        'unavailable_end', 'unavailable_reason', 'valid_from', 'valid_until'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'specific_date' => 'date',
        'unavailable_start' => 'datetime',
        'unavailable_end' => 'datetime',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope pour les disponibilités valides à une date donnée
    public function scopeValidAt($query, $date)
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $date);
            });
    }
}