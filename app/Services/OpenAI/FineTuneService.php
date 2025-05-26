<?php

namespace App\Services\OpenAI;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FineTuneService
{
    public function generateAndUploadTrainingData(Company $company): ?string
    {
        if (!$company->description) return null;

        $slug = $company->slug;
        $filename = "fine-tune/company_{$slug}.jsonl";

        $tone = $company->tone ?? 'professional';

        $messages = [
            [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are an assistant for {$company->name}. Speak in a {$tone} tone. Be informative and helpful."
                    ],
                    [
                        'role' => 'user',
                        'content' => "What does {$company->name} do?"
                    ],
                    [
                        'role' => 'assistant',
                        'content' => $company->description
                    ]
                ]
            ]
        ];

        $jsonl = collect($messages)->map(fn($m) => json_encode($m))->implode("\n");
        Storage::put($filename, $jsonl);

        // Upload to OpenAI
        $upload = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
            'OpenAI-Organization' => config('services.openai.org'),
        ])->attach(
            'file', Storage::get($filename), "company_{$slug}.jsonl"
        )->post('https://api.openai.com/v1/files', [
            'purpose' => 'fine-tune',
        ]);

        if (! $upload->ok()) return null;

        $fileId = $upload->json()['id'];

        // Start fine-tuning
        $train = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/fine_tuning/jobs', [
                'training_file' => $fileId,
                'model' => 'gpt-3.5-turbo',
            ]);

        return $train->ok() ? $train->json()['id'] : null;
    }
}
