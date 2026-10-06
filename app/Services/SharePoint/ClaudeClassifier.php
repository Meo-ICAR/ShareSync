<?php

namespace App\Services\SharePoint;

use Illuminate\Support\Facades\Http;

class ClaudeClassifier implements AiClassifier
{
    public function classify(string $path, array $candidates): array
    {
        $list = collect($candidates)->map(fn ($c) => "{$c['id']}: {$c['name']}")->implode("\n");

        $prompt = <<<PROMPT
        Sei un assistente che classifica documenti aziendali (collaboratori, fornitori, istituti, documenti societari) a partire dal percorso del file.

        Percorso file: {$path}

        Tipi di documento candidati (id: nome):
        {$list}

        Indica quali tipi di documento contiene il file. Un file può contenere più documenti
        (es. "Casellario giudiziale e carichi pendenti" = due tipi). Usa solo gli id elencati.
        Rispondi SOLO con un array JSON, senza testo aggiuntivo, nel formato:
        [{"document_type_id": <id>, "confidence": <0-100>}]
        Se nessun tipo è plausibile rispondi [].
        PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 500,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $response->throw();

        $text = (string) $response->json('content.0.text');
        if (! preg_match('/\[.*\]/s', $text, $m)) {
            return [];
        }

        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $row) {
            if (is_array($row) && isset($row['document_type_id'], $row['confidence'])) {
                $out[] = [
                    'document_type_id' => (int) $row['document_type_id'],
                    'confidence' => max(0, min(100, (int) $row['confidence'])),
                ];
            }
        }

        return $out;
    }
}
