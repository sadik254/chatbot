<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatLog;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|uuid',
        ]);

        $user = $request->user();
        $company = $user->company;

        if (! $company) {
            return response()->json(['message' => 'User is not associated with any company'], 403);
        }
        
        try {
            // Prepare company context instructions
            $companyInstructions = "You are an AI assistant for {$company->name}. ";
            $companyInstructions .= "Always provide helpful information related to {$company->name}'s products and services. ";
            $companyInstructions .= "Maintain a professional and friendly tone in all responses.";
            
            // Make API request with proper context structure
            // $response = Http::withToken(config('services.openai.key'))
            $response = Http::withHeaders([
                'User-Agent' => 'ChatBot/1.0',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . config('services.openai.key'),
            ])
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $company->fine_tuned_model ?? 'gpt-3.5-turbo',
                    'instructions' => $companyInstructions,
                    'input' => [
                        [
                            'role' => 'developer',
                            'content' => "# Company Context\n<company_info>\nCompany Name: {$company->name}\n</company_info>"
                        ],
                        [
                            'role' => 'user',
                            'content' => $request->message
                        ]
                    ]
                ]);
            
            // Handle potential errors
            if ($response->failed()) {
                return response()->json([
                    'error' => 'API request failed',
                    'details' => $response->json()
                ], $response->status());
            }
            
            // Extract output text safely
            $responseData = $response->json();
            $outputText = null;
            
            // Properly navigate the response structure to find text outputs
            if (isset($responseData['output']) && is_array($responseData['output'])) {
                foreach ($responseData['output'] as $outputItem) {
                    if (isset($outputItem['content']) && is_array($outputItem['content'])) {
                        foreach ($outputItem['content'] as $contentItem) {
                            if (isset($contentItem['type']) && $contentItem['type'] === 'output_text') {
                                $outputText = $contentItem['text'];
                                break 2; // Break both loops once we find text
                            }
                        }
                    }
                }
            }
            
            // Default message if we couldn't find output text
            if ($outputText === null) {
                $outputText = 'Unable to process your request at this time.';
            }

            $conversationId = $request->input('conversation_id') ?? Str::uuid()->toString();
            
            // Log the chat
            ChatLog::create([
                'conversation_id' => $conversationId,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'question' => $request->message,
                'answer' => $outputText,
            ]);
            // dd ($conversationId);
            
            // For production use, return just the reply
            return response()->json([
                'reply' => $outputText,
                'conversation_id' => $conversationId,
            ]);
            
            // For debugging, uncomment below to return full response
            // return response()->json([
            //     'response' => $responseData,
            // ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing your request',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $chats = ChatLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10); // You can customize the per-page count

        return response()->json($chats);
    }

    public function conversationHistory(Request $request)
    {
        $user = $request->user();

        $chats = ChatLog::where('conversation_id', $request->conversation_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($chats);
    }

    public function publicChat(Request $request, \App\Models\Company $company)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = $request->input('message');

        // Optional: Log anonymously or store IP
        // AI reply
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $company->fine_tuned_model ?: 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => "You are an AI assistant for {$company->name}. Be helpful and polite."],
                ['role' => 'user', 'content' => $message],
            ],
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'AI response failed'], 500);
        }

        return response()->json([
            'reply' => $response->json()['choices'][0]['message']['content']
        ]);
    }


}