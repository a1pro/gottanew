<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Name is too long',
            'phone.max' => 'Phone number is too long',
            'avatar.image' => 'Avatar must be an image',
            'avatar.mimes' => 'Avatar must be jpeg, png, or jpg',
            'avatar.max' => 'Avatar size must be less than 2MB',
        ];
    }
}