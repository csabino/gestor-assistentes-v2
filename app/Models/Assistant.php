<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assistant extends Model
{
    protected $fillable = [
        'name', 'company_name', 'provider', 'model', 'system_prompt',
        'openai_api_key', 'gemini_api_key', 'anthropic_api_key', 'grok_api_key',
        'whatsapp_provider', 'whatsapp_url', 'whatsapp_instance', 'whatsapp_token', 'whatsapp_verify_token',
        'knowledge_files', 'is_active'
    ];

    protected $casts = [
        'knowledge_files' => 'array',
        'is_active' => 'boolean',
    ];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }
}