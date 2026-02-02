<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class VerifyResource extends ApiResource
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
            'message' => 'Token válido',
            'timestamp' => now()->toISOString(),
            'data' => [
                'valid' => true,
                'user' => $this->resource['user'],
                'expires_at' => $this->resource['expires_at'] ?? null,
            ]
        ];
    }

    /**
     * Create a verify success response
     *
     * @param array $verifyResult
     * @return static
     */
    public static function verifyResponse(array $verifyResult)
    {
        return new static($verifyResult);
    }
}
