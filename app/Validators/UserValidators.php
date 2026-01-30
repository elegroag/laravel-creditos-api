<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserValidators
{
    /**
     * Validar datos de login.
     */
    public static function validateLogin(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'username' => 'required|string|min:3|max:50|regex:/^[a-z0-9_\.-]+$/',
            'password' => 'required|string|min:8'
        ], [
            'username.required' => 'El nombre de usuario es requerido',
            'username.min' => 'El nombre de usuario debe tener al menos 3 caracteres',
            'username.max' => 'El nombre de usuario no puede exceder 50 caracteres',
            'username.regex' => 'El nombre de usuario solo puede contener letras, números, puntos, guiones bajos y guiones',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres'
        ]);
    }

    /**
     * Validar datos de registro.
     */
    public static function validateRegister(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'tipo_documento' => 'required|string|in:cedula,pasaporte,nit',
            'numero_documento' => 'required|string|max:20',
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'telefono' => 'nullable|string|regex:/^\+?[0-9\s\-\(\)]{8,20}$/',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            'password_confirmation' => 'required|same:password'
        ], [
            'tipo_documento.required' => 'El tipo de documento es requerido',
            'tipo_documento.in' => 'El tipo de documento debe ser: cedula, pasaporte o nit',
            'numero_documento.required' => 'El número de documento es requerido',
            'numero_documento.max' => 'El número de documento no puede exceder 20 caracteres',
            'nombres.required' => 'Los nombres son requeridos',
            'nombres.max' => 'Los nombres no pueden exceder 100 caracteres',
            'apellidos.required' => 'Los apellidos son requeridos',
            'apellidos.max' => 'Los apellidos no pueden exceder 100 caracteres',
            'telefono.regex' => 'El formato del teléfono es inválido',
            'email.required' => 'El correo electrónico es requerido',
            'email.email' => 'El formato del correo electrónico es inválido',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula y un número',
            'password_confirmation.required' => 'La confirmación de contraseña es requerida',
            'password_confirmation.same' => 'Las contraseñas no coinciden'
        ]);
    }

    /**
     * Validar datos de actualización.
     */
    public static function validateUpdate(array $data, string $excludeId = null): \Illuminate\Validation\Validator
    {
        $rules = [
            'tipo_documento' => 'sometimes|string|in:cedula,pasaporte,nit',
            'numero_documento' => 'sometimes|string|max:20',
            'nombres' => 'sometimes|string|max:100',
            'apellidos' => 'sometimes|string|max:100',
            'telefono' => 'nullable|string|regex:/^\+?[0-9\s\-\(\)]{8,20}$/',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($excludeId)
            ],
            'full_name' => 'sometimes|string|max:200',
            'phone' => 'nullable|string|max:20'
        ];

        $messages = [
            'tipo_documento.in' => 'El tipo de documento debe ser: cedula, pasaporte o nit',
            'numero_documento.max' => 'El número de documento no puede exceder 20 caracteres',
            'nombres.max' => 'Los nombres no pueden exceder 100 caracteres',
            'apellidos.max' => 'Los apellidos no pueden exceder 100 caracteres',
            'telefono.regex' => 'El formato del teléfono es inválido',
            'email.email' => 'El formato del correo electrónico es inválido',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'full_name.max' => 'El nombre completo no puede exceder 200 caracteres',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres'
        ];

        return Validator::make($data, $rules, $messages);
    }

    /**
     * Validar fuerza de contraseña.
     */
    public static function validatePassword(string $password): bool
    {
        // Al menos 8 caracteres, una mayúscula, una minúscula y un número
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
    }

    /**
     * Generar username a partir de nombres y apellidos.
     */
    public static function generateUsername(string $nombres, string $apellidos): string
    {
        // Limpiar y normalizar nombres
        $nombresLimpios = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($nombres));
        $apellidosLimpios = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($apellidos));

        // Tomar primeras letras
        $username = substr($nombresLimpios, 0, 3) . substr($apellidosLimpios, 0, 3);

        // Si es muy corto, agregar números aleatorios
        if (strlen($username) < 6) {
            $username .= rand(100, 999);
        }

        // Asegurar que no sea demasiado largo
        $username = substr($username, 0, 20);

        return $username;
    }

    /**
     * Validar formato de username.
     */
    public static function validateUsernameFormat(string $username): bool
    {
        return preg_match('/^[a-z0-9_\.-]{3,50}$/', $username);
    }
}
