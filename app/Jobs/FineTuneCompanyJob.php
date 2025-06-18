<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\OpenAI\FineTuneService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Http;

class FineTuneCompanyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    protected int $companyId;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    public function handle(FineTuneService $fineTuneService): void
    {
        $company = Company::find($this->companyId);

        if (! $company) {
            Log::error("❌ Company ID {$this->companyId} not found.");
            return;
        }

    Log::info("▶️ Handling fine-tune job for company: {$company->name}");
        if (! $company->description) {
            Log::warning("⚠️ Company '{$company->name}' has no description. Skipping.");
            return;
        }

        // ✅ Check if job is marked as pending in DB
        if ($company->fine_tuned_model && str_starts_with($company->fine_tuned_model, 'pending:')) {
            $jobId = str_replace('pending:', '', $company->fine_tuned_model);
            // Log::info("🔍This enters the first if check");
            $check = Http::withToken(config('services.openai.key'))
                ->get("https://api.openai.com/v1/fine_tuning/jobs/{$jobId}");

            if ($check->failed()) {
                Log::error("❌ Failed to check fine-tune job status for job ID {$jobId}. Response: " . $check->body());
                return;
            }

            $status = $check->json('status');
            Log::info("ℹ️ OpenAI job status for {$company->name} ({$jobId}): {$status}");

            if ($status === 'running' || $status === 'pending') {
                Log::info("⏭️ Fine-tune still in progress for {$company->name} (Status: {$status}). Skipping.");
                return;
            }

            if ($status === 'succeeded') {
                $modelName = $check->json('fine_tuned_model');
                $company->update(['fine_tuned_model' => $modelName]);
                Log::info("✅ Fine-tune already completed for {$company->name}. Model: {$modelName}");
                return;
            }

            if ($status === 'failed') {
                Log::warning("❌ Previous fine-tune failed for {$company->name}. Retrying...");
                $company->update(['fine_tuned_model' => null]); // Clear for retry
            }
        }

        // 🚀 Start a new fine-tune job
        Log::info("🔥 Starting fine-tune for: {$company->name}");
        Log::info("🚀 Calling generateAndUploadTrainingData for {$company->name}");

        $jobId = $fineTuneService->generateAndUploadTrainingData($company);

        Log::info("🧪 generateAndUploadTrainingData result: " . ($jobId ?? 'null'));


        if ($jobId) {
            $company->update(['fine_tuned_model' => "pending:{$jobId}"]);
            Log::info("✅ Fine-tune started for {$company->name}. Job ID: {$jobId}");
        } else {
            Log::error("❌ Fine-tune failed to start for {$company->name}");

            // 🚨 Throw exception so Laravel retries the job
            throw new \Exception("Fine-tune failed for {$company->name}");
        }

    }

    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(1, 600))->by($this->companyId)
        ];
    }
}

