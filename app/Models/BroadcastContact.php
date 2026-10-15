<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastContact extends Model
{
    protected $fillable = [
        'campaign_id', 'name', 'phone_raw', 'phone_normalized', 'is_valid',
        'invalid_reason', 'send_status', 'send_error', 'sent_at',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(BroadcastCampaign::class, 'campaign_id');
    }
}
