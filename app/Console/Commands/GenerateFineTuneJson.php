<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class GenerateFineTuneJson extends Command
{
    protected $signature = 'openai:generate-finetune-json';
    protected $description = 'Generate JSONL files for company fine-tuning data';

    public function handle()
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            if (!$company->description) {
                $this->warn("Skipping {$company->name} (no description)");
                continue;
            }
        
            $tone = $company->tone ?? 'professional';
        
            $messages = [
                [
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "You are an assistant for {$company->name}. Speak in a {$tone} tone. Be informative and helpful."
                        ],
                        [
                            'role' => 'user',
                            'content' => "What does {$company->name} do?"
                        ],
                        [
                            'role' => 'assistant',
                            'content' => $company->description
                        ]
                    ]
                ]
            ];
        
            $filename = "fine-tune/company_{$company->slug}.jsonl";
            $jsonl = collect($messages)->map(fn($m) => json_encode($m))->implode("\n");
        
            Storage::disk('local')->put($filename, $jsonl);
            $this->info("Created: storage/app/{$filename}");
        }
        

        $this->info("Done!");
    }
}

