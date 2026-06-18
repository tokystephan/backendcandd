<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ApprovalHistory;

class Validation extends Model
{
    protected $table = 'validations';

    protected $fillable = [
        'post_id',
        'application_id',
        'validator_id',
        'validation_type',
        'status',
        'comment',
        'validated_at',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function approvalHistory()
    {
        return $this->hasMany(ApprovalHistory::class);
    }
}
