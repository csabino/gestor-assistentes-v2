<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    protected $fillable = ['assistant_id', 'name', 'tag', 'trigger_context', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function assistant()
    {
        return $this->belongsTo(Assistant::class);
    }

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sort_order');
    }
}
