<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatLog;
use Illuminate\Support\Str;
use App\Models\Lead;
use Carbon\Carbon;

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

    // Public chat older method
    // public function publicChat(Request $request, \App\Models\Company $company)
    // {
    //     $request->validate([
    //         'message' => 'required|string',
    //     ]);

    //     $message = $request->input('message');

    //     // Optional: Log anonymously or store IP
    //     // AI reply
    //     $response = Http::withHeaders([
    //         'Authorization' => 'Bearer ' . config('services.openai.key'),
    //         'Content-Type' => 'application/json',
    //     ])->post('https://api.openai.com/v1/chat/completions', [
    //         'model' => $company->fine_tuned_model ?: 'gpt-3.5-turbo',
    //         'messages' => [
    //             ['role' => 'system', 'content' => "You are an AI assistant for {$company->name}. Be helpful and polite."],
    //             ['role' => 'user', 'content' => $message],
    //         ],
    //         'temperature' => 0.3, //Adjust temeprature to get consistent replies lower is more consistent, higher is more creative
    //     ]);

    //     if ($response->failed()) {
    //         return response()->json(['error' => 'AI response failed'], 500);
    //     }

    //     return response()->json([
    //         'reply' => $response->json()['choices'][0]['message']['content']
    //     ]);
    // }

    public function publicChat(Request $request, \App\Models\Company $company)
    {
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'nullable|uuid',
        ]);

        $message = $request->input('message');
        $conversationId = $request->input('conversation_id') ?? Str::uuid()->toString();

        // Fetch last 5 message pairs (user + assistant = 10 entries max)
        $historyLogs = \App\Models\ChatLog::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->reverse(); // to maintain chronological order

        $chatHistory = [];

        foreach ($historyLogs as $entry) {
            $chatHistory[] = ['role' => 'user', 'content' => $entry->question];
            $chatHistory[] = ['role' => 'assistant', 'content' => $entry->answer];
        }

        // Add current user message
        $chatHistory[] = ['role' => 'user', 'content' => $message];

        // Prepare system prompt and final message list
        $messages = array_merge([
            ['role' => 'system', 'content' => "You are an AI assistant for {$company->name}. Be helpful, professional, and polite. Answer questions related to the company's services and products. Your target is generate lead and get the user's contact information also you need to schedule booking/meeting or appointment. Once booking/meeting details is provided, thanks them and let them know that an agent will contact them soon."],
        ], $chatHistory);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $company->fine_tuned_model ?: 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.3,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'AI response failed'], 500);
        }

        $reply = $response->json()['choices'][0]['message']['content'] ?? 'Sorry, I couldn’t process your request.';

        // Save to chat log
        // dd($company->user_id);
        \App\Models\ChatLog::create([
            'conversation_id' => $conversationId,
            'company_id' => $company->id,
            'user_id' => null,
            'question' => $message,
            'answer' => $reply,
        ]);

         if ($this->containsContactInfo($message)) {
            Lead::create([
                'company_id' => $company->id,
                'description' => $message,
            ]);
        }

        return response()->json([
            'reply' => $reply,
            'conversation_id' => $conversationId,
        ]);
    }

    protected function containsContactInfo(string $text): bool
    {
        return preg_match('/\b\d{10,}\b/', $text) ||                     // phone
            preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}\b/i', $text) || // email
            // $this->containsDate($text) ||           // using carbon to parse dates
            preg_match('/\b((?:[01]?\d|2[0-3]):[0-5]\d(?:\s?[APap][Mm])?|\b(?:[1-9]|1[0-2])\s?[APap][Mm])\b/', $text); //advanced regex for times
    }

    // protected function containsContactInfo(string $text): bool
    // {
    //     // Match phone numbers (10+ digits)
    //     $hasPhone = preg_match('/\b\d{10,}\b/', $text);

    //     // Match email addresses
    //     $hasEmail = preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}\b/i', $text);

    //     // Match dates: YYYY-MM-DD, DD/MM/YYYY, or "1st July 2025"
    //     $hasDate = preg_match('/\b(\d{4}-\d{2}-\d{2}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|\d{1,2}(st|nd|rd|th)?\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4})\b/i', $text);

    //     // Match times: 14:30, 2:30 PM, 10am, etc.
    //     $hasTime = preg_match('/\b(\d{1,2}:\d{2}(\s?[ap]m)?|\d{1,2}\s?[ap]m)\b/i', $text);

    //     return $hasPhone || $hasEmail || $hasDate || $hasTime;
    // }

    // protected function containsDate(string $text): bool
    // {
    //     // Break text into words and try to parse each one or group
    //     $phrases = explode(' ', $text);

    //     foreach ($phrases as $i => $word) {
    //         // Try current and next 1-2 words to form phrases like "12 June", "next Friday", etc.
    //         for ($len = 1; $len <= 3; $len++) {
    //             $phrase = implode(' ', array_slice($phrases, $i, $len));
    //             $parsed = Carbon::parse($phrase, now()->timezone)->toDateTimeString();

    //             // Check if parsed result is a valid future or current date
    //             if ($parsed && strtotime($parsed) > strtotime('-1 day')) {
    //                 return true;
    //             }
    //         }
    //     }

    //     return false;
    // }

}