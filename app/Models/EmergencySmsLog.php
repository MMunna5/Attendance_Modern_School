<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencySmsLog extends Model
{
    protected $fillable = ['sms_segments', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
