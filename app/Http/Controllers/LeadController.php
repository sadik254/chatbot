<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user(); // or auth()->user()

        if (!$user->company) {
            return response()->json(['message' => 'No company associated with user'], 404);
        }

        // $perPage = $request->get('per_page', 15);

        $leads = \App\Models\Lead::where('company_id', $user->company->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($leads);
    }

}
