<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateAutoTrainingData extends Command
{
    protected $signature = 'openai:auto-generate-training';
    protected $description = 'Automatically generate 10 training examples from company description using OpenAI';

    public function handle()
    {
        $companies = Company::whereNotNull('description')->get();

        foreach ($companies as $company) {
            $this->info("Generating for: {$company->name}");

            $tone = $company->tone ?? 'professional';
            $systemMessage = "You are an assistant for {$company->name}. Speak in a {$tone} tone. Be informative and helpful.";

            $prompt = <<<EOT
    Generate 10 JSONL-formatted chat examples to fine-tune a GPT assistant for a company named "{$company->name}".
    Company description: {$company->description}

    Each example should follow this structure:
    {
    "messages": [
        {"role": "system", "content": "{$systemMessage}"},
        {"role": "user", "content": "<user question>"},
        {"role": "assistant", "content": "<assistant answer>"}
    ]
    }

    Return a JSONL file with 10 lines. One JSON object per line, no array.
    EOT;

            $response = Http::withToken(config('services.openai.key'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a data generator for fine-tuning GPT models.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                ]);

            if (! $response->ok()) {
                $this->error("❌ Failed for {$company->name}");
                continue;
            }

            $content = $response->json()['choices'][0]['message']['content'] ?? null;

            if (! $content) {
                $this->error("❌ Empty response for {$company->name}");
                continue;
            }

            $slug = Str::slug($company->slug);
            $filename = "private/fine-tune/company_{$slug}.jsonl";
            Storage::disk('local')->put($filename, $content);

            $this->info("✅ Saved training file for {$company->name}");
        }

        $this->info("🎉 All done.");
    }

}
