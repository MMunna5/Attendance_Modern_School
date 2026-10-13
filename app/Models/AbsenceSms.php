<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsenceSms extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    protected $table = 'absence_sms';

    protected $fillable = [
        'student_id', 'date', 'status', 'attempts',
        'next_attempt_at', 'last_error', 'sent_at', 'sms_segments',
    ];

    protected $casts = [
        'date' => 'date',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
