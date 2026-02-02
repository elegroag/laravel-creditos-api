<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class LoginResource extends ApiResource
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
            'message' => 'Login exitoso',
            'timestamp' => now()->toISOString(),
            'data' => [
                'access_token' => $this->resource['access_token'],
                'token_type' => 'bearer',
                'expires_in' => $this->resource['expires_in'] ?? 3600,
                'user' => $this->resource['user'],
                'trabajador' => $this->resource['user']['trabajador'] ?? null,
            ]
        ];
    }

    /**
     * Create a login success response
     *
     * @param array $authResult
     * @return static
     */
    public static function loginResponse(array $authResult)
    {
        return new static($authResult);
    }
}
