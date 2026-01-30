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
     * Validar actualización de usuario.
     */
    public static function validateUpdate(array $data, string $userId): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'email' => 'nullable|email|max:255|unique:users,email,' . $userId,
            'full_name' => 'nullable|string|max:200',
            'phone' => 'nullable|string|regex:/^\+?[0-9\s\-\(\)]{8,20}$/',
            'roles' => 'nullable|array',
            'roles.*' => 'string|in:user_trabajador,user_empresa,adviser,administrator',
            'disabled' => 'nullable|boolean'
        ], [
            'email.email' => 'El formato del correo electrónico es inválido',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'full_name.max' => 'El nombre completo no puede exceder 200 caracteres',
            'phone.regex' => 'El formato del teléfono es inválido',
            'roles.array' => 'Los roles deben ser un arreglo',
            'roles.*.in' => 'Los roles válidos son: user_trabajador, user_empresa, adviser, administrator'
        ]);
    }

    /**
     * Validar documento de identificación.
     */
    public static function validateIdentification(string $type, string $number): bool
    {
        return match($type) {
            'cedula' => preg_match('/^[0-9]{6,12}$/', $number),
            'pasaporte' => preg_match('/^[A-Z0-9]{6,20}$/', $number),
            'nit' => preg_match('/^[0-9]{9,12}$/', $number),
            default => false
        };
    }

    /**
     * Validar teléfono.
     */
    public static function validatePhone(string $phone): bool
    {
        $cleaned = preg_replace('/[^0-9+() -]/', '', $phone);
        return preg_match('/^\+?[0-9\s\-\(\)]{8,20}$/', $cleaned);
    }

    /**
     * Validar contraseña.
     */
    public static function validatePassword(string $password): bool
    {
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }

    /**
     * Normalizar username.
     */
    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    /**
     * Generar username desde nombres y apellidos.
     */
    public static function generateUsername(string $nombres, string $apellidos): string
    {
        $nombreParts = explode(' ', trim($nombres));
        $apellidoParts = explode(' ', trim($apellidos));
        
        $firstName = strtolower(self::removeAccents($nombreParts[0] ?? ''));
        $firstSurname = strtolower(self::removeAccents($apellidoParts[0] ?? ''));
        
        $baseUsername = substr($firstName, 0, 1) . $firstSurname;
        
        // Si es muy corto, agregar más caracteres
        if (strlen($baseUsername) < 3) {
            $baseUsername = $firstName . substr($firstSurname, 0, 2);
        }
        
        return preg_replace('/[^a-z0-9]/', '', $baseUsername);
    }

    /**
     * Remover acentos de una cadena.
     */
    private static function removeAccents(string $string): string
    {
        $accents = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'Ñ' => 'N'
        ];
        
        return strtr($string, $accents);
    }

    /**
     * Validar username.
     */
    public static function isValidUsername(string $username): bool
    {
        return preg_match('/^[a-z0-9_\.-]+$/', $username) && strlen($username) >= 3;
    }
}
