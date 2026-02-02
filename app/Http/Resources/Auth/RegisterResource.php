<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class RegisterResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'timestamp' => now()->toISOString(),
            'data' => [
                'user' => $this->resource['user'],
                'access_token' => $this->resource['access_token'],
                'token_type' => 'bearer',
                'expires_in' => $this->resource['expires_in'] ?? 3600,
                'pin' => $this->resource['pin'] ?? null,
                'verification_required' => $this->resource['verification_required'] ?? false,
            ]
        ];
    }

    /**
     * Create a register success response
     *
     * @param array $registerResult
     * @return static
     */
    public static function registerResponse(array $registerResult)
    {
        return new static($registerResult);
    }
}
