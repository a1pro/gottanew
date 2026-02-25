<?php
// app/Http/Requests/Auth/RegisterRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public access
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', 'string', 'in:client,coach'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];

        // Add coach-specific validation rules when role is coach
        if ($this->input('role') === 'coach') {
            $rules = array_merge($rules, [
                'experience' => ['required', 'string'],
                'specialties' => ['required', 'string'],
                'reason' => ['required', 'string'],
                'certification' => ['nullable', 'string'],
            ]);
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'name.required' => 'Please provide your name',
            'email.required' => 'Email address is required',
            'email.unique' => 'This email is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 6 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'role.required' => 'Please select a role',
            'role.in' => 'Role must be either client or coach',
        ];

        // Add coach-specific messages
        if ($this->input('role') === 'coach') {
            $messages = array_merge($messages, [
                'experience.required' => 'Please provide your coaching experience',
                'specialties.required' => 'Please provide your coaching specialties',
                'reason.required' => 'Please provide your reason for becoming a coach',
            ]);
        }

        return $messages;
    }
}