<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'human_agent_id', 'user_id', 'start_time', 'end_time',
        'client_name', 'client_phone', 'client_email',
        'guest_emails', 'status'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'guest_emails' => 'array',
    ];

    public function agent()
    {
        return $this->belongsTo(HumanAgent::class, 'human_agent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}