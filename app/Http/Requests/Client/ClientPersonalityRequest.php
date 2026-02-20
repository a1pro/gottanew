<?php
// app/Http/Requests/Client/ClientPersonalityRequest.php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class ClientPersonalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'personality_traits' => ['required', 'array'],
            'personality_traits.communication_style' => ['required', 'string', 'in:direct,reflective,visual,analytical'],
            'personality_traits.decision_making' => ['required', 'string', 'in:intuitive,logical,balanced'],
            'personality_traits.challenge_readiness' => ['required', 'integer', 'between:1,10'],
            'personality_traits.support_preference' => ['required', 'string', 'in:guidance,accountability,encouragement,challenge'],
        ];
    }

    public function messages(): array
    {
        return [
            'personality_traits.communication_style.required' => 'Please select your communication style',
            'personality_traits.decision_making.required' => 'Please select your decision making style',
            'personality_traits.challenge_readiness.required' => 'Please rate your readiness for challenge',
            'personality_traits.support_preference.required' => 'Please select your support preference',
        ];
    }
}