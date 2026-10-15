<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastCampaign extends Model
{
    protected $fillable = [
        'assistant_id', 'name', 'status', 'message_selector', 'message_preview',
        'batch_size', 'raw_contacts_count', 'valid_contacts_count', 'invalid_contacts_count',
        'sent_count', 'failed_count', 'sanitized_at', 'scheduled_at', 'started_at',
        'completed_at', 'created_by_user_id',
    ];

    protected $casts = [
        'sanitized_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'raw_contacts_count' => 'integer',
        'valid_contacts_count' => 'integer',
        'invalid_contacts_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }

    public function contacts()
    {
        return $this->hasMany(BroadcastContact::class, 'campaign_id');
    }

    public function isMetaAssistant(): bool
    {
        return $this->assistant && $this->assistant->whatsapp_provider === 'meta';
    }
}
