<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponseAnswer extends Model
{
    protected $fillable = ['survey_response_id', 'survey_question_id', 'answer_text'];

    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function question()
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }
}
