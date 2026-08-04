<?php

namespace Database\Seeders;

use App\Models\Ai\AiPrompt;
use App\Services\Ai\AiPromptResolver;
use Illuminate\Database\Seeder;

class AiPromptSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AiPromptResolver::registry() as $key => $default) {
            AiPrompt::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $default['label'],
                    'description' => $default['description'],
                    'system_prompt' => $default['system_prompt'],
                    'max_tokens' => $default['max_tokens'],
                    'temperature' => $default['temperature'],
                    'model' => $default['model'],
                    'is_active' => true,
                ]
            );
        }
    }
}