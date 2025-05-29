<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;

class FineTuneCompanyModel extends Command
{
    protected $signature = 'openai:fine-tune-company-models';
    protected $description = 'Upload company JSONL files and start fine-tuning via OpenAI API';

    public function handle()
    {
        $companies = Company::whereNull('fine_tuned_model')->get();

        foreach ($companies as $company) {
            $slug = $company->slug;
            $filePath = storage_path("app/private/fine-tune/company_{$slug}.jsonl");

            if (!file_exists($filePath)) {
                $this->warn("File missing for {$slug}");
                continue;
            }

            $this->info("Uploading file for: {$company->name}");

            // Upload file to OpenAI
            $fileResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.key'),
                'OpenAI-Organization' => config('services.openai.org'),
            ])->attach(
                'file', file_get_contents($filePath), "company_{$slug}.jsonl"
            )->post('https://api.openai.com/v1/files', [
                'purpose' => 'fine-tune',
            ]);

            if (! $fileResponse->ok()) {
                $this->error("File upload failed for {$slug}");
                continue;
            }

            $fileId = $fileResponse->json()['id'];
            $this->info("Uploaded file ID: {$fileId}");

            // Start fine-tuning
            $fineTuneResponse = Http::withToken(config('services.openai.key'))
                ->post('https://api.openai.com/v1/fine_tuning/jobs', [
                    'training_file' => $fileId,
                    'model' => 'gpt-3.5-turbo',
                ]);

            if (! $fineTuneResponse->ok()) {
                $this->error("Fine-tune request failed for {$slug}");
                continue;
            }

            $jobId = $fineTuneResponse->json()['id'];
            $company->update(['fine_tuned_model' => "pending:{$jobId}"]);
            $this->info("Fine-tuning started for {$company->name} (Job ID: {$jobId})");
        }

        $this->info("✅ Done");
    }
}
