<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AudioService
{
    public function transcribeAudio(string $audioFilePath, string $apiKey, string $provider = 'openai'): ?string
    {
        try {
            if (!file_exists($audioFilePath) || filesize($audioFilePath) < 100) {
                Log::error("Arquivo de áudio inválido ou muito pequeno para transcrição.");
                return null;
            }

            if ($provider === 'openai') {
                $response = Http::withToken($apiKey)
                    ->attach(
                        'file', 
                        file_get_contents($audioFilePath), 
                        'audio.ogg', 
                        ['Content-Type' => 'audio/ogg']
                    )
                    ->post('https://api.openai.com/v1/audio/transcriptions', [
                        'model' => 'whisper-1',
                    ]);

                if ($response->successful()) {
                    return trim($response->json('text') ?? '');
                }

                Log::error("Erro na API Whisper OpenAI: " . $response->body());
            }

            return null;

        } catch (\Throwable $e) {
            Log::error("Exceção na transcrição de áudio: " . $e->getMessage());
            return null;
        }
    }

    public function textToSpeech(
        string $text, 
        string $googleApiKey, 
        string $voiceName = 'pt-BR-Chirp3-HD-Erinome', 
        string $gender = 'FEMALE', 
        string $langCode = 'pt-BR'
    ): ?array {
        if (empty(trim($text)) || empty($googleApiKey)) {
            Log::error("Google TTS abortado: texto ou chave API vazios.");
            return null;
        }

        try {
            // 1. Remove formatações Markdown
            $cleanText = str_replace(['*', '#', '_', '`', '~'], '', $text);

            // 2. Remove todos os Emojis do texto que será narrado
            $cleanText = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}]/u', '', $cleanText);

            // 3. Limpa quebras de linha duplas
            $cleanText = preg_replace("/\n{2,}/", "\n", $cleanText);
            $cleanText = trim($cleanText);

            $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key={$googleApiKey}";

            $data = [
                "input" => [
                    "text" => $cleanText
                ],
                "voice" => [
                    "languageCode" => $langCode,
                    "name" => $voiceName,
                    "ssmlGender" => $gender
                ],
                "audioConfig" => [
                    "audioEncoding" => "MP3",
                    "speakingRate" => 1.0,
                    "pitch" => 0
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($url, $data);

            if ($response->failed()) {
                Log::error("Erro na API Google TTS: " . $response->body());
                return null;
            }

            $resJson = $response->json();
            $audioBase64 = $resJson['audioContent'] ?? null;

            if (!$audioBase64) {
                return null;
            }

            $microTime = microtime(true);
            $composeFileName = date('dmyHis', (int)$microTime) . sprintf('%03d', ($microTime - (int)$microTime) * 1000);
            $fileName = "ia_" . $composeFileName . ".mp3";
            $relativePath = "audio_ia/" . $fileName;

            Storage::disk('public')->put($relativePath, base64_decode($audioBase64));

            return [
                'url' => Storage::disk('public')->url($relativePath),
                'base64' => $audioBase64
            ];

        } catch (\Throwable $e) {
            Log::error("Exceção ao gerar voz Google TTS: " . $e->getMessage());
            return null;
        }
    }

    public function separateLinksFromText(string $aiResponseText): array
    {
        $links = [];
        
        $textWithoutMarkdownLinks = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/', function ($matches) use (&$links) {
            $label = trim($matches[1]);
            $url = trim($matches[2]);
            $links[] = "• {$label}: {$url}";
            return $label;
        }, $aiResponseText);

        $textClean = preg_replace_callback('/https?:\/\/[^\s]+/', function ($matches) use (&$links) {
            $url = trim($matches[0]);
            if (!in_array("• " . $url, $links)) {
                $links[] = "• " . $url;
            }
            return '';
        }, $textWithoutMarkdownLinks);

        $textClean = trim($textClean);

        if (!empty($links)) {
            $textClean .= "\n\nSeguem os links citados em texto logo abaixo:";
        }

        return [
            'audio_text' => $textClean,
            'extracted_links' => !empty($links) ? implode("\n", $links) : null
        ];
    }
}