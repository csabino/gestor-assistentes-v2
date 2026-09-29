<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationFollowup extends Model
{
    protected $fillable = ['assistant_id', 'phone_number', 'send_target', 'attempts_sent', 'last_activity_at'];

    protected $casts = ['last_activity_at' => 'datetime'];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }
}
