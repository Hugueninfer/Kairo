<?php

namespace App\Http\Requests\Auth;

use App\DTOs\Auth\RegisterUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function toData(): RegisterUserData
    {
        return new RegisterUserData(
            name: $this->string('name')->trim()->toString(),
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
