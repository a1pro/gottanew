<?php
// app/Http/Requests/Client/ClientGoalsRequest.php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class ClientGoalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isClient();
    }

    public function rules(): array
    {
        return [
            'goals' => ['required', 'array', 'min:1', 'max:5'],
            'goals.*.area' => ['required', 'string', 'in:Career,Life,Business,Wellness,Relationships,Leadership,Mindfulness,Communication'],
            'goals.*.description' => ['required', 'string', 'max:500'],
            'goals.*.priority' => ['required', 'string', 'in:high,medium,low'],
        ];
    }

    public function messages(): array
    {
        return [
            'goals.required' => 'Please share your coaching goals',
            'goals.*.area.required' => 'Select goal area',
            'goals.*.description.required' => 'Describe your goal',
            'goals.*.priority.required' => 'Set goal priority',
        ];
    }
}