<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Datos de la escuela
            'nombre_escuela' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:escuelas,slug', 'alpha_dash'],
            'cct' => ['required', 'string', 'size:10', 'unique:escuelas,cct', 'regex:/^[0-9]{2}[A-Z]{3}[0-9]{4}[A-Z]$/'],
            'rfc' => ['nullable', 'string', 'size:13', 'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/'],
            'email_escuela' => ['required', 'email', 'max:255'],

            // Datos del usuario director
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cct.regex' => 'El CCT debe tener el formato: 2 dígitos, 3 letras, 4 dígitos y 1 letra (ej: 14DPR0001X)',
            'rfc.regex' => 'El RFC no tiene un formato válido',
            'slug.alpha_dash' => 'El slug solo puede contener letras, números, guiones y guiones bajos',
        ];
    }
}
