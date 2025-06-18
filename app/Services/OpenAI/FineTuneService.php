<?php

namespace App\Services\OpenAI;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FineTuneService
{

    public $tries = 1; // ← only try once, no retries
    public $timeout = 120; // increase timeout

    public function generateAndUploadTrainingData(Company $company): ?string
    {
        if (! $company->description) return null;

        $tone = $company->tone ?? 'professional';
        $slug = Str::slug($company->slug);
        $filename = "private/fine-tune/company_{$slug}.jsonl";

        // 🔥 Auto-generate 10 examples using GPT
        $prompt = <<<EOT
        You are an expert assistant trainer. Generate exactly 20 lines of fine-tuning examples in strict JSONL forsmat (one JSON object per line, no commas, no array). Use this structure:

        {"messages": [{"role": "system", "content": "You are an assistant for {$company->name}. Speak in a {$tone} tone. Be informative and helpful."}, {"role": "user", "content": "<example user question>"}, {"role": "assistant", "content": "<accurate, complete, helpful reply>"}]}

        Company Info:
        - Name: {$company->name}
        - Phone: {$company->phone}
        - Email: {$company->email}
        - Description: {$company->description}
        - Tone: {$tone}

        Rules:
        - Include a diverse set of 20 realistic customer questions and helpful assistant replies.
        - Cover all the information from the description.
        - Do NOT include markdown, comments, or surrounding arrays.
        - ONLY output the JSONL lines. No explanation or commentary.

        EOT;

        \Log::info("🧠 Sending prompt to GPT for company: {$company->name}");

        try {
        \Log::info("⏳ GPT call started...");
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(60) // <-- increase timeout to 60 seconds
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a fine-tune dataset generator.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.4,
            ]);
            \Log::info("✅ GPT completion response received: " . $response->status());
        } catch (\Exception $e) {
            \Log::error("❌ GPT API call failed: " . $e->getMessage());
            return null;
        }
        // if (! $response->ok()) return null;
        if (! $response->ok()) {
        \Log::error("❌ OpenAI response failed: " . $response->body());
        return null;
    }

        $jsonlContent = $response->json()['choices'][0]['message']['content'] ?? null;
        if (! $jsonlContent) {
            \Log::error("❌ GPT returned empty content for {$company->name}. Raw response: " . $response->body());
            return null;
        }


         \Log::info("📄 GPT response received. Processing lines...");

        // Save and validate the content line by line
        $lines = preg_split("/\r\n|\n|\r/", trim($jsonlContent));

        $validLines = collect($lines)->filter(function ($line) use ($company) {
            $decoded = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['messages'])) {
                \Log::warning("⚠️ Invalid JSON line for {$company->name}: " . json_last_error_msg());
                \Log::warning("⚠️ Invalid JSON line: $line | Error: " . json_last_error_msg());
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
        if (!Storage::exists($filename)) {
        \Log::error("❌ Failed to write training file for {$company->name}.");
        } else {
            \Log::info("✅ JSONL training file saved: {$filename}");
        }


        // 🛰️ Upload to OpenAI
        $upload = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
            'OpenAI-Organization' => config('services.openai.org'),
        ])->attach(
            'file', Storage::get($filename), "company_{$slug}.jsonl"
        )->post('https://api.openai.com/v1/files', [
            'purpose' => 'fine-tune',
        ]);

        // if (! $upload->ok()) return null;
        if (! $upload->ok()) {
            \Log::error("❌ File upload failed: " . $upload->body());
            return null;
        }

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
