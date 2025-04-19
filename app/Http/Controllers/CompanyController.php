<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use Illuminate\Support\Str;

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
}
