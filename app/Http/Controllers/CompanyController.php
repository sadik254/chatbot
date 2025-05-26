<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use Illuminate\Support\Str;
use App\Services\OpenAI\FineTuneService;

class CompanyController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',

        ]);

        $user = $request->user();

        if ($user->company) {
            return response()->json([
                'message' => 'You have already created a company.',
            ], 400);
        }

        $company = Company::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'industry' => $request->industry,
            'email' => $request->email ?? $user->email,
            'phone' => $request->phone,
            'address' => $request->address,


        ]);

        $user->company()->associate($company);
        $user->save();

        return response()->json([
            'message' => 'Company created successfully.',
            'company' => $company,
        ]);
    }

    // Updating company description aka training data and tone
    public function updateDescription(Request $request, FineTuneService $fineTuneService)
    {
        $request->validate([
            'description' => 'required|string',
            'tone' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $company = $user->company;

        if (! $company) {
            return response()->json(['message' => 'No company associated with this user.'], 404);
        }

        $company->update([
            'description' => $request->description,
            'tone' => $request->input('tone', $company->tone),
        ]);

        $jobId = $fineTuneService->generateAndUploadTrainingData($company);

        if ($jobId) {
            $company->update(['fine_tuned_model' => 'pending:' . $jobId]);
        }

        return response()->json([
            'message' => 'Company description updated and fine-tuning started.',
            'company' => $company,
            'fine_tune_job_id' => $jobId,
        ]);
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (! $company) {
            return response()->json(['message' => 'No company associated with this user.'], 404);
        }

        return response()->json([
            'company' => $company,
        ]);
    }
    public function destroy(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (! $company) {
            return response()->json(['message' => 'No company associated with this user.'], 404);
        }

        // Detach the company from the user
        $user->company()->dissociate();
        $user->save();

        // Delete the company
        $company->delete();

        return response()->json([
            'message' => 'Company deleted successfully.',
        ]);
    }

}
