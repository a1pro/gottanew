<?php
// app/Http/Requests/Coach/CoachAvailabilityRequest.php

namespace App\Http\Requests\Coach;

use Illuminate\Foundation\Http\FormRequest;

class CoachAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCoach();
    }

    public function rules(): array
    {
        return [
            'availability' => ['required', 'array', 'min:1'],
            'availability.*.day' => ['required', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'availability.*.start_time' => ['required', 'date_format:H:i'],
            'availability.*.end_time' => ['required', 'date_format:H:i', 'after:availability.*.start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'availability.required' => 'Please set your availability',
            'availability.*.day.required' => 'Select a day',
            'availability.*.day.in' => 'Invalid day selected',
            'availability.*.start_time.required' => 'Start time is required',
            'availability.*.end_time.required' => 'End time is required',
            'availability.*.end_time.after' => 'End time must be after start time',
        ];
    }
}