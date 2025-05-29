<?php

namespace App\Jobs;

use App\Models\Company;
use App\Jobs\FineTuneCompanyJob;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;

class CheckAndRetryFineTuneJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $companies = Company::where('fine_tuned_model', 'like', 'pending:%')->get();

        foreach ($companies as $company) {
            $jobId = str_replace('pending:', '', $company->fine_tuned_model);

            Log::info("🔍 Checking status of job {$jobId} for {$company->name}");

            $response = Http::withToken(config('services.openai.key'))
                ->get("https://api.openai.com/v1/fine_tuning/jobs/{$jobId}");

            if (! $response->ok()) {
                Log::error("❌ Failed to check job status for {$company->name}.");
                continue;
            }

            $status = $response->json('status');

            if ($status === 'succeeded') {
                $model = $response->json('fine_tuned_model');
                $company->update(['fine_tuned_model' => $model]);
                Log::info("✅ Updated fine-tuned model for {$company->name}: {$model}");
            }

            if ($status === 'failed') {
                $company->update(['fine_tuned_model' => null]);
                Log::warning("❌ Fine-tuning failed. Requeuing for {$company->name}");
                FineTuneCompanyJob::dispatch($company->id)->delay(now()->addMinutes(1));
            }
        }
    }
}
