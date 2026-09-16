<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use App\Models\SurveyResponse;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function handle(Request $request)
    {
        if ($request->isMethod('post')) {
            $action = $request->input('action');
            if ($action === 'store_survey') return $this->storeSurvey($request);
            if ($action === 'update_survey') return $this->updateSurvey($request);
            if ($action === 'delete_survey') return $this->deleteSurvey($request);
            if ($action === 'add_question') return $this->addQuestion($request);
            if ($action === 'update_question') return $this->updateQuestion($request);
            if ($action === 'delete_question') return $this->deleteQuestion($request);
            if ($action === 'add_option') return $this->addOption($request);
            if ($action === 'update_option') return $this->updateOption($request);
            if ($action === 'delete_option') return $this->deleteOption($request);
        }

        $assistantId = (int) $request->query('assistant_id');
        $assistant = Assistant::findOrFail($assistantId);

        $surveys = Survey::where('assistant_id', $assistantId)->orderBy('name')->get();

        $editingSurveyId = (int) $request->query('survey_id');
        $editingSurvey = $editingSurveyId
            ? Survey::with('questions.options')->where('assistant_id', $assistantId)->find($editingSurveyId)
            : null;

        if ($editingSurvey && $request->query('export') === 'csv') {
            return $this->exportResponsesCsv($editingSurvey);
        }

        $responses = $editingSurvey
            ? SurveyResponse::with('answers')
                ->where('survey_id', $editingSurvey->id)
                ->where('status', 'completed')
                ->latest('completed_at')
                ->get()
            : collect();

        $currentView = 'surveys';

        return view('surveys.index', compact('assistant', 'surveys', 'editingSurvey', 'responses', 'currentView'));
    }

    private function exportResponsesCsv(Survey $survey)
    {
        $questions = $survey->questions;
        $responses = SurveyResponse::with('answers')
            ->where('survey_id', $survey->id)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->get();

        $filename = 'pesquisa_' . preg_replace('/[^A-Za-z0-9_]/', '_', $survey->tag) . '.csv';

        return response()->streamDownload(function () use ($questions, $responses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM pra acentuação abrir certo no Excel

            $header = ['Cliente', 'Telefone', 'Concluído em'];
            foreach ($questions as $q) {
                $header[] = $q->question_text;
            }
            fputcsv($out, $header, ';');

            foreach ($responses as $response) {
                $row = [
                    $response->client_name ?: '',
                    $response->phone_number,
                    optional($response->completed_at)->format('d/m/Y H:i'),
                ];
                foreach ($questions as $q) {
                    $answer = $response->answers->firstWhere('survey_question_id', $q->id);
                    $row[] = $answer ? $answer->answer_text : '';
                }
                fputcsv($out, $row, ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function redirectBack(int $assistantId, ?int $surveyId = null)
    {
        $url = "/?view=surveys&assistant_id={$assistantId}";
        if ($surveyId) $url .= "&survey_id={$surveyId}";
        return redirect($url);
    }

    private function storeSurvey(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $request->validate([
            'name' => 'required|string|max:255',
            'tag' => 'required|string|max:100|regex:/^[A-Za-z0-9_]+$/',
        ]);

        $tag = strtoupper(trim($request->input('tag')));

        if (Survey::where('assistant_id', $assistantId)->where('tag', $tag)->exists()) {
            return $this->redirectBack($assistantId)->with('error', 'Já existe uma pesquisa com essa tag para este assistente.');
        }

        $survey = Survey::create([
            'assistant_id' => $assistantId,
            'name' => trim($request->input('name')),
            'tag' => $tag,
            'trigger_context' => trim($request->input('trigger_context') ?? '') ?: null,
            'is_active' => true,
        ]);

        return $this->redirectBack($assistantId, $survey->id)->with('success', 'Pesquisa criada! Agora adicione as perguntas.');
    }

    private function updateSurvey(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $surveyId = (int) $request->input('survey_id');
        $request->validate([
            'name' => 'required|string|max:255',
            'tag' => 'required|string|max:100|regex:/^[A-Za-z0-9_]+$/',
        ]);

        $tag = strtoupper(trim($request->input('tag')));

        if (Survey::where('assistant_id', $assistantId)->where('tag', $tag)->where('id', '!=', $surveyId)->exists()) {
            return $this->redirectBack($assistantId, $surveyId)->with('error', 'Já existe uma pesquisa com essa tag para este assistente.');
        }

        Survey::where('id', $surveyId)->where('assistant_id', $assistantId)->update([
            'name' => trim($request->input('name')),
            'tag' => $tag,
            'trigger_context' => trim($request->input('trigger_context') ?? '') ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->redirectBack($assistantId, $surveyId)->with('success', 'Pesquisa atualizada!');
    }

    private function deleteSurvey(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $surveyId = (int) $request->input('survey_id');

        Survey::where('id', $surveyId)->where('assistant_id', $assistantId)->delete();

        return $this->redirectBack($assistantId)->with('success', 'Pesquisa excluída!');
    }

    private function addQuestion(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $surveyId = (int) $request->input('survey_id');
        $request->validate([
            'question_text' => 'required|string|max:500',
            'type' => 'required|in:multiple_choice,free_text',
        ]);

        $nextOrder = ((int) SurveyQuestion::where('survey_id', $surveyId)->max('sort_order')) + 1;

        SurveyQuestion::create([
            'survey_id' => $surveyId,
            'question_text' => trim($request->input('question_text')),
            'type' => $request->input('type'),
            'sort_order' => $nextOrder,
        ]);

        return $this->redirectBack($assistantId, $surveyId)->with('success', 'Pergunta adicionada!');
    }

    private function updateQuestion(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $surveyId = (int) $request->input('survey_id');
        $questionId = (int) $request->input('question_id');
        $request->validate([
            'question_text' => 'required|string|max:500',
            'type' => 'required|in:multiple_choice,free_text',
        ]);

        SurveyQuestion::where('id', $questionId)->where('survey_id', $surveyId)->update([
            'question_text' => trim($request->input('question_text')),
            'type' => $request->input('type'),
        ]);

        return $this->redirectBack($assistantId, $surveyId)->with('success', 'Pergunta atualizada!');
    }

    private function deleteQuestion(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $surveyId = (int) $request->input('survey_id');
        $questionId = (int) $request->input('question_id');

        SurveyQuestion::where('id', $questionId)->where('survey_id', $surveyId)->delete();

        return $this->redirectBack($assistantId, $surveyId)->with('success', 'Pergunta removida!');
    }

    private function addOption(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $surveyId = (int) $request->input('survey_id');
        $questionId = (int) $request->input('question_id');
        $request->validate(['option_text' => 'required|string|max:255']);

        $nextOrder = ((int) SurveyQuestionOption::where('survey_question_id', $questionId)->max('sort_order')) + 1;

        SurveyQuestionOption::create([
            'survey_question_id' => $questionId,
            'option_text' => trim($request->input('option_text')),
            'sort_order' => $nextOrder,
        ]);

        return $this->redirectBack($assistantId, $surveyId)->with('success', 'Opção adicionada!');
    }

    private function updateOption(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $surveyId = (int) $request->input('survey_id');
        $optionId = (int) $request->input('option_id');
        $request->validate(['option_text' => 'required|string|max:255']);

        SurveyQuestionOption::where('id', $optionId)->update([
            'option_text' => trim($request->input('option_text')),
        ]);

        return $this->redirectBack($assistantId, $surveyId)->with('success', 'Opção atualizada!');
    }

    private function deleteOption(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $surveyId = (int) $request->input('survey_id');
        $optionId = (int) $request->input('option_id');

        SurveyQuestionOption::where('id', $optionId)->delete();

        return $this->redirectBack($assistantId, $surveyId)->with('success', 'Opção removida!');
    }
}
