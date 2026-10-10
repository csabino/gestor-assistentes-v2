<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestRun extends Model
{
    protected $fillable = [
        'name', 'target_label', 'target_phone_number', 'harness_assistant_id',
        'target_prompt_snapshot', 'target_knowledge_snapshot', 'status',
        'current_scenario_index', 'current_scenario_turn',
        'scenarios', 'transcript', 'report',
        'last_message_sent_at', 'started_at', 'completed_at', 'created_by_user_id',
    ];

    protected $casts = [
        'scenarios' => 'array',
        'transcript' => 'array',
        'report' => 'array',
        'last_message_sent_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function harnessAssistant()
    {
        return $this->belongsTo(Assistant::class, 'harness_assistant_id');
    }
}
