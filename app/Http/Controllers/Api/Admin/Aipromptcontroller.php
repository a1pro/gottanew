<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Ai\AiPrompt;
use App\Services\Ai\AiPromptResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiPromptController extends BaseController
{
    public function __construct(private AiPromptResolver $resolver)
    {
    }

    /**
     * List all known AI prompt areas. Includes keys that don't have a DB row
     * yet (falls back to the hardcoded default so the admin UI always shows
     * every available prompt area, even right after a fresh install).
     */
    public function index()
    {
        $existing = AiPrompt::query()->get()->keyBy('key');

        $rows = collect(AiPromptResolver::registry())->map(function ($default, $key) use ($existing) {
            $record = $existing->get($key);

            return [
                'key' => $key,
                'label' => $record->label ?? $default['label'],
                'description' => $record->description ?? $default['description'],
                'system_prompt' => $record->system_prompt ?? $default['system_prompt'],
                'max_tokens' => $record->max_tokens ?? $default['max_tokens'],
                'temperature' => $record ? (float) $record->temperature : $default['temperature'],
                'model' => $record->model ?? $default['model'],
                'is_active' => $record ? (bool) $record->is_active : true,
                'is_customized' => (bool) $record,
                'updated_at' => optional($record?->updated_at)?->toISOString(),
            ];
        })->values();

        return $this->success($rows);
    }

    public function show(string $key)
    {
        $this->assertKnownKey($key);

        $record = AiPrompt::query()->where('key', $key)->first();
        $default = AiPromptResolver::registry()[$key];

        return $this->success([
            'key' => $key,
            'label' => $record->label ?? $default['label'],
            'description' => $record->description ?? $default['description'],
            'system_prompt' => $record->system_prompt ?? $default['system_prompt'],
            'max_tokens' => $record->max_tokens ?? $default['max_tokens'],
            'temperature' => $record ? (float) $record->temperature : $default['temperature'],
            'model' => $record->model ?? $default['model'],
            'is_active' => $record ? (bool) $record->is_active : true,
            'is_customized' => (bool) $record,
        ]);
    }

    public function update(Request $request, string $key)
    {
        $this->assertKnownKey($key);

        $validated = $request->validate([
            'system_prompt' => ['required', 'string', 'min:20'],
            'max_tokens' => ['required', 'integer', 'min:100', 'max:8000'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'model' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $default = AiPromptResolver::registry()[$key];

        $record = AiPrompt::query()->updateOrCreate(
            ['key' => $key],
            array_merge($validated, [
                'label' => $default['label'],
                'description' => $default['description'],
                'updated_by' => Auth::id(),
            ])
        );

        $this->resolver->forget($key);

        return $this->success($record->fresh(), 'Prompt updated successfully');
    }

    /**
     * Reset a prompt back to the hardcoded default (deletes the DB override).
     */
    public function reset(string $key)
    {
        $this->assertKnownKey($key);

        AiPrompt::query()->where('key', $key)->delete();
        $this->resolver->forget($key);

        return $this->success($this->resolver->defaultFor($key), 'Prompt reset to default');
    }

    private function assertKnownKey(string $key): void
    {
        abort_unless(array_key_exists($key, AiPromptResolver::registry()), 404, "Unknown prompt key [{$key}]");
    }
}