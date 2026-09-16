<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    protected $fillable = [
        'survey_id', 'assistant_id', 'phone_number', 'client_name',
        'status', 'current_question_id', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function currentQuestion()
    {
        return $this->belongsTo(SurveyQuestion::class, 'current_question_id');
    }

    public function answers()
    {
        return $this->hasMany(SurveyResponseAnswer::class);
    }
}
