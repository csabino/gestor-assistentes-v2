<?php

namespace App\Services;

use App\Models\Assistant;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoogleCalendarService
{
    private function getAccessToken(int $assistantId): ?string
    {
        $clientId = Setting::where('assistant_id', $assistantId)->where('key', 'google_client_id')->value('value');
        $clientSecret = Setting::where('assistant_id', $assistantId)->where('key', 'google_client_secret')->value('value');
        $refreshToken = Setting::where('assistant_id', $assistantId)->where('key', 'google_refresh_token')->value('value');

        if (!$clientId || !$clientSecret || !$refreshToken) {
            Log::error("Credenciais do Google Calendar incompletas para o assistente #{$assistantId}");
            return null;
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error("Erro na renovação do Token Google para assistente #{$assistantId}: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("Exceção ao obter access token Google: " . $e->getMessage());
        }

        return null;
    }

    public function createMeeting(
        int $assistantId,
        string $title,
        string $description,
        string $startDateTime,
        string $endDateTime,
        string $agentEmail,
        string $clientEmail,
        array $additionalEmails = []
    ): ?array {
        $accessToken = $this->getAccessToken($assistantId);
        if (!$accessToken) return null;

        $calendarId = Setting::where('assistant_id', $assistantId)->where('key', 'google_calendar_id')->value('value') ?? 'primary';
        if (empty($calendarId)) $calendarId = 'primary';

        $rawEmails = array_merge([$agentEmail, $clientEmail], $additionalEmails);
        $attendees = [];

        // Filtra para remover o e-mail do organizador (calendarId) da lista de convidados
        $filteredEmails = array_unique(array_filter($rawEmails, function ($email) use ($calendarId) {
            $clean = strtolower(trim($email));
            if (empty($clean)) return false;
            
            // Se o calendarId for o próprio e-mail da conta do Google, não inclui como convidado
            if (filter_var($calendarId, FILTER_VALIDATE_EMAIL) && $clean === strtolower(trim($calendarId))) {
                return false;
            }
            return true;
        }));

        foreach ($filteredEmails as $email) {
            $cleanEmail = trim($email);
            if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                $attendees[] = ['email' => $cleanEmail];
            }
        }

        $startCarbon = Carbon::parse($startDateTime)->setTimezone('America/Sao_Paulo');
        $endCarbon = Carbon::parse($endDateTime)->setTimezone('America/Sao_Paulo');

        $payload = [
            'summary' => $title,
            'description' => $description,
            'start' => [
                'dateTime' => $startCarbon->format('Y-m-d\TH:i:sP'),
                'timeZone' => 'America/Sao_Paulo',
            ],
            'end' => [
                'dateTime' => $endCarbon->format('Y-m-d\TH:i:sP'),
                'timeZone' => 'America/Sao_Paulo',
            ],
            'attendees' => $attendees,
            'guestsCanSeeOtherGuests' => true,
            'guestsCanInviteOthers' => true,
            'guestsCanModify' => false,
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => 'req_' . time() . '_' . rand(1000, 9999),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet'
                    ]
                ]
            ]
        ];

        try {
            $url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events?conferenceDataVersion=1&sendUpdates=all";
            
            $response = Http::withToken($accessToken)
                ->contentType('application/json')
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $meetLink = $data['hangoutLink'] ?? ($data['conferenceData']['entryPoints'][0]['uri'] ?? null);

                return [
                    'event_id' => $data['id'] ?? null,
                    'meet_link' => $meetLink,
                    'raw' => $data
                ];
            }

            Log::error("Erro na criação do evento Google Calendar: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("Exceção ao criar evento no Google Calendar: " . $e->getMessage());
        }

        return null;
    }

    /**
     * @return array|false|null Array em caso de sucesso; false quando o evento não existe mais no
     *   Google (404/410 - seguro criar um novo no lugar); null em qualquer outra falha (erro real
     *   de comunicação, onde NÃO é seguro assumir que dá pra recriar sem duplicar a reunião).
     */
    public function updateMeeting(
        int $assistantId,
        string $eventId,
        string $startDateTime,
        string $endDateTime,
        ?string $newAgentEmail = null
    ): array|false|null {
        if (empty($eventId)) return null;

        $accessToken = $this->getAccessToken($assistantId);
        if (!$accessToken) return null;

        $calendarId = Setting::where('assistant_id', $assistantId)->where('key', 'google_calendar_id')->value('value') ?? 'primary';
        if (empty($calendarId)) $calendarId = 'primary';

        $startCarbon = Carbon::parse($startDateTime)->setTimezone('America/Sao_Paulo');
        $endCarbon = Carbon::parse($endDateTime)->setTimezone('America/Sao_Paulo');

        $payload = [
            'start' => [
                'dateTime' => $startCarbon->format('Y-m-d\TH:i:sP'),
                'timeZone' => 'America/Sao_Paulo',
            ],
            'end' => [
                'dateTime' => $endCarbon->format('Y-m-d\TH:i:sP'),
                'timeZone' => 'America/Sao_Paulo',
            ],
        ];

        try {
            $url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events/" . urlencode($eventId) . "?sendUpdates=all";

            if (!empty($newAgentEmail) && filter_var($newAgentEmail, FILTER_VALIDATE_EMAIL)) {
                $getRes = Http::withToken($accessToken)->get("https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events/" . urlencode($eventId));
                if ($getRes->successful()) {
                    $eventData = $getRes->json();
                    $attendees = $eventData['attendees'] ?? [];
                    
                    $hasAgent = false;
                    foreach ($attendees as $att) {
                        if (strtolower(trim($att['email'] ?? '')) === strtolower(trim($newAgentEmail))) {
                            $hasAgent = true;
                            break;
                        }
                    }
                    if (!$hasAgent) {
                        $attendees[] = ['email' => trim($newAgentEmail)];
                        $payload['attendees'] = $attendees;
                    }
                }
            }

            $response = Http::withToken($accessToken)
                ->contentType('application/json')
                ->patch($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $meetLink = $data['hangoutLink'] ?? ($data['conferenceData']['entryPoints'][0]['uri'] ?? null);

                return [
                    'event_id' => $data['id'] ?? $eventId,
                    'meet_link' => $meetLink,
                    'raw' => $data
                ];
            }

            if (in_array($response->status(), [404, 410])) {
                Log::warning("Evento Google Calendar não encontrado ao atualizar (provavelmente apagado manualmente): " . $eventId);
                return false;
            }

            Log::error("Erro na atualização do evento Google Calendar: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("Exceção ao atualizar evento no Google Calendar: " . $e->getMessage());
        }

        return null;
    }

    public function cancelMeeting(int $assistantId, string $eventId): bool
    {
        if (empty($eventId)) return false;

        $accessToken = $this->getAccessToken($assistantId);
        if (!$accessToken) return false;

        $calendarId = Setting::where('assistant_id', $assistantId)->where('key', 'google_calendar_id')->value('value') ?? 'primary';
        if (empty($calendarId)) $calendarId = 'primary';

        try {
            $url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events/" . urlencode($eventId) . "?sendUpdates=all";
            
            $response = Http::withToken($accessToken)->delete($url);

            if ($response->successful() || $response->status() === 404) {
                return true;
            }

            Log::error("Erro ao cancelar evento Google Calendar: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("Exceção ao cancelar evento no Google Calendar: " . $e->getMessage());
        }

        return false;
    }
}