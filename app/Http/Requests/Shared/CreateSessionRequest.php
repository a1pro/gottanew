<?php
// app/Http/Requests/Shared/CreateSessionRequest.php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;

class CreateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient(); // Only clients can book sessions
    }

    public function rules(): array
    {
        return [
            'coach_id' => ['required', 'exists:users,id'],
            'created_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'coach_id.required' => 'Please select a coach',
            'coach_id.exists' => 'Selected coach not found',
            'created_at.required' => 'Please select a date and time',
            'created_at.after' => 'Session must be scheduled in the future',
        ];
    }

    /**
     * Additional validation after rules pass
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if coach is actually a coach
            $coach = User::withRole('coach')->find($this->coach_id);
            
            if (!$coach) {
                $validator->errors()->add('coach_id', 'Selected user is not a coach');
            }
        });
    }
}