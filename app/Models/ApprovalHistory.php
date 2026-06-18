<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalHistory extends Model
{
    protected $table = 'approval_history';

    protected $fillable = [
        'validation_id',
        'approver_id',
        'step_name',
        'status',
        'comment',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function validation()
    {
        return $this->belongsTo(Validation::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
