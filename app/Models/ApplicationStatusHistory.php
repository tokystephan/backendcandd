<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'application_status_history';

    protected $fillable = [
        'application_id',
        'status_id',
        'status',
        'changed_by',
        'changed_by_name',
        'note',
        'notes',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function statusRecord()
    {
        return $this->belongsTo(Statut::class, 'status_id');
    }
}
