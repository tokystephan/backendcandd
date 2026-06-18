<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Statistic extends Model
{
    protected $table = 'statistics';

    protected $fillable = [
        'stat_date',
        'stat_type',
        'stat_value',
        'context',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'calculated_at' => 'datetime',
        'context' => 'array',
    ];
}
