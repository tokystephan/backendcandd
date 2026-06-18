<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $table = 'evaluations';

    protected $fillable = [
        'application_id',
        'user_id',
        'technical',
        'communication',
        'motivation',
        'culture',
        'recommendation',
        'strengths',
        'weaknesses',
        'comment',
    ];

    protected $casts = [
        'technical' => 'integer',
        'communication' => 'integer',
        'motivation' => 'integer',
        'culture' => 'integer',
    ];

    /**
     * Relation avec la candidature
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Relation avec l'utilisateur (consultant)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculer la note moyenne
     */
    public function getAverageScoreAttribute()
    {
        $scores = [$this->technical, $this->communication, $this->motivation, $this->culture];
        $validScores = array_filter($scores, fn($s) => $s > 0);
        
        if (empty($validScores)) return 0;
        
        return round(array_sum($validScores) / count($validScores), 1);
    }

    /**
     * Obtenir le libellé de la recommandation
     */
    public function getRecommendationLabelAttribute()
    {
        $labels = [
            'favorable' => '✅ Favorable',
            'reserve' => '⚠️ Réservé',
            'defavorable' => '❌ Défavorable',
        ];
        return $labels[$this->recommendation] ?? $this->recommendation;
    }
}