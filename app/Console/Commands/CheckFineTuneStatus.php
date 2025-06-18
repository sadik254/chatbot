<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use App\Jobs\FineTuneCompanyJob;

class CheckFineTuneStatus extends Command
{
    protected $signature = 'openai:check-fine-tune-status';
    protected $description = 'Check fine-tuning job status for each company and update model name when completed or failed';

    public function handle()
    {

        // Log::info("🤖 Scheduled command \"openai:check-fine-tune-status\" is running.");
        Log::info("🟢 Command is running and starting fine-tune status check.");

        $companies = Company::where('fine_tuned_model', 'like', 'pending:%')->get();

        foreach ($companies as $company) {
            $jobId = str_replace('pending:', '', $company->fine_tuned_model);
            Log::info("🔍 Checking job ID: {$jobId} for {$company->name}");

            $response = Http::withToken(config('services.openai.key'))
                ->get("https://api.openai.com/v1/fine_tuning/jobs/{$jobId}");

            if (! $response->ok()) {
                Log::error("❌ Failed to fetch job status for {$jobId}");
                $this->error("Failed to fetch job status for {$jobId}");
                continue;
            }

            $jobData = $response->json();
            $status = $jobData['status'];

            Log::info("ℹ️ Status for {$company->name}: {$status}");

            if ($status === 'succeeded') {
                $modelName = $jobData['fine_tuned_model'];
                $company->update(['fine_tuned_model' => $modelName]);
                $this->info("✅ Model ready: {$modelName} for {$company->name}");
                Log::info("✅ Fine-tune succeeded for {$company->name}: {$modelName}");
            } elseif ($status === 'failed') {
                $error = $jobData['error']['message'] ?? 'Unknown error';
                $company->update(['fine_tuned_model' => null]);
                $this->warn("❌ Fine-tune failed for {$company->name}: {$error}");
                Log::warning("❌ Fine-tune failed for {$company->name}: {$error}");
                FineTuneCompanyJob::dispatch($company->id);
                Log::info("🔁 Retrying fine-tune for {$company->name} by dispatching FineTuneCompanyJob.");
            } else {
                $this->line("⏳ Still in progress: {$status}");
            }
        }

        $this->info("🏁 Finished checking fine-tune jobs.");
    }

    // public function schedule(Schedule $schedule): void
    // {
    //     // 🔁 Run every 10 minutes in production
    //     $schedule->command(static::class)->everyTenMinutes();
    // }
}
