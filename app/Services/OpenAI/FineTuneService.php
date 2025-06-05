<?php

namespace App\Services\OpenAI;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FineTuneService
{
    public function generateAndUploadTrainingData(Company $company): ?string
    {
        if (! $company->description) return null;

        $tone = $company->tone ?? 'professional';
        $slug = Str::slug($company->slug);
        $filename = "private/fine-tune/company_{$slug}.jsonl";

        // 🔥 Auto-generate 10 examples using GPT
        $prompt = "Generate minimum 20 JSONL-formatted fine-tune examples for a company named '{$company->name}'. No Information should be left out. Use the following details:
    Company Name: {$company->name}
    Company Phone: {$company->phone}
    Company Email: {$company->email}
    Description: {$company->description}
    Tone: {$tone}

    Each line should be a separate JSON object with:
    {
    \"messages\": [
        {\"role\": \"system\", \"content\": \"You are an assistant for {$company->name}. Speak in a {$tone} tone. Be informative and helpful.\"},
        {\"role\": \"user\", \"content\": \"<customer question>\"},
        {\"role\": \"assistant\", \"content\": \"<correct helpful reply>\"}
    ]
    }
    Return minimum 20 lines, no surrounding array, no comments. Also make sure include all the possible questions and answers based on the written text, no information should be left,";

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(60) // <-- increase timeout to 60 seconds
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a fine-tune dataset generator.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.4,
            ]);

        if (! $response->ok()) return null;

        $jsonlContent = $response->json()['choices'][0]['message']['content'] ?? null;
        if (! $jsonlContent) return null;

        // Save and validate the content line by line
        $lines = preg_split("/\r\n|\n|\r/", trim($jsonlContent));

        $validLines = collect($lines)->filter(function ($line) use ($company) {
            $decoded = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['messages'])) {
                \Log::warning("⚠️ Invalid JSON line for {$company->name}: " . json_last_error_msg());
                return false;
            }
            return true;
        });

        if ($validLines->count() < 20) {
            \Log::error("❌ Not enough valid training examples for {$company->name}. Aborting fine-tuning.");
            return null;
        }

        // ✅ Save file
        // Storage::disk('local')->put($filename, $jsonlContent);
        Storage::disk('local')->put($filename, $validLines->implode("\n"));

        // 🛰️ Upload to OpenAI
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

        // 🚀 Start fine-tuning
        $train = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/fine_tuning/jobs', [
                'training_file' => $fileId,
                'model' => 'gpt-3.5-turbo',
            ]);

        return $train->ok() ? $train->json()['id'] : null;
    }
}
