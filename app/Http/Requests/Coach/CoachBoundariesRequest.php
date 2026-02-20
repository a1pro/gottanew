<?php
// app/Http/Requests/Coach/CoachBoundariesRequest.php

namespace App\Http\Requests\Coach;

use Illuminate\Foundation\Http\FormRequest;

class CoachBoundariesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCoach();
    }

    public function rules(): array
    {
        return [
            'boundaries' => ['required', 'array'],
            'boundaries.session_duration' => ['required', 'integer', 'min:30', 'max:120'],
            'boundaries.cancellation_policy' => ['required', 'string', 'max:500'],
            'boundaries.topics_to_avoid' => ['nullable', 'array'],
            'boundaries.topics_to_avoid.*' => ['string', 'max:50'],
            'ethics_acknowledged' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'boundaries.session_duration.required' => 'Please set session duration',
            'boundaries.cancellation_policy.required' => 'Please provide cancellation policy',
            'ethics_acknowledged.accepted' => 'You must acknowledge the ethics agreement',
        ];
    }
}