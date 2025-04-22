<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatLog;

class ChatController extends Controller
{
    //OpenAI api related all method will be bere
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user = $request->user();
        $company = $user->company;

        if (! $company) {
            return response()->json(['message' => 'User is not associated with any company'], 403);
        }

        $response = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant for a company chatbot.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $request->message,
                    ],
                ],
            ]);

        return response()->json([
            'reply' => $response->json()['choices'][0]['message']['content'] ?? 'No reply received.',
        ]);

        // Logging the chat
        ChatLog::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'question' => $request->message,
            'answer' => $response->message,
        ]);

        // For debugging purpose return the full response
        // return $response->json();
    }
}
