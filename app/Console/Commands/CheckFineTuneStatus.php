<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Console\Scheduling\Schedule;

class CheckFineTuneStatus extends Command
{
    protected $signature = 'openai:check-fine-tune-status';
    protected $description = 'Check fine-tuning job status for each company and update model name when completed';

    public function handle()
    {
        $companies = Company::where('fine_tuned_model', 'like', 'pending:%')->get();

        foreach ($companies as $company) {
            $jobId = str_replace('pending:', '', $company->fine_tuned_model);

            $this->info("Checking job ID: $jobId for {$company->name}");

            $response = Http::withToken(config('services.openai.key'))
                ->get("https://api.openai.com/v1/fine_tuning/jobs/{$jobId}");

            if (! $response->ok()) {
                $this->error("Failed to get status for job {$jobId}");
                continue;
            }

            $jobData = $response->json();

            if ($jobData['status'] === 'succeeded') {
                $fineTunedModel = $jobData['fine_tuned_model'];
                $company->update(['fine_tuned_model' => $fineTunedModel]);
                $this->info("✅ Model ready: {$fineTunedModel} for {$company->name}");
            } elseif ($jobData['status'] === 'failed') {
                $this->warn("❌ Fine-tuning failed for {$company->name}");
                $company->update(['fine_tuned_model' => null]); // or log error separately
            } else {
                $this->line("Still pending: {$jobData['status']}");
            }
        }

        $this->info("Finished checking fine-tune jobs.");
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class)->hourly();
    }

}
