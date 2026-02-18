<?php
// app/Http/Requests/Coach/CoachProfileRequest.php

namespace App\Http\Requests\Coach;

use Illuminate\Foundation\Http\FormRequest;

class CoachProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCoach(); // Only coaches can update their profile
    }

    public function rules(): array
    {
        return [
            'bio' => ['required', 'string', 'min:50', 'max:1000'],
            'expertise' => ['required', 'array', 'min:1', 'max:5'],
            'expertise.*' => ['string', 'in:Career,Life,Business,Wellness,Leadership,Mindfulness,Communication,Technology,Skills,Strategy,Relationships'],
            'coaching_styles' => ['required', 'array', 'min:1', 'max:3'],
            'coaching_styles.*' => ['string', 'in:supportive,directive,analytical,challenging,motivational,structured,reflective'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'bio.required' => 'Please tell us about yourself',
            'bio.min' => 'Bio should be at least 50 characters',
            'expertise.required' => 'Select at least one area of expertise',
            'expertise.*.in' => 'Invalid expertise area selected',
            'coaching_styles.required' => 'Select your coaching styles',
            'hourly_rate.required' => 'Please set your hourly rate',
        ];
    }
}