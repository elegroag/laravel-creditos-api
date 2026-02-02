<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
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
            'success' => $this->resource['success'] ?? true,
            'message' => $this->resource['message'] ?? 'Operación exitosa',
            'timestamp' => now()->toISOString(),
            'data' => $this->resource['data'] ?? null,
        ];
    }

    /**
     * Create a success response
     *
     * @param mixed $data
     * @param string $message
     * @return static
     */
    public static function success($data = null, string $message = 'Operación exitosa')
    {
        return new static([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Create an error response
     *
     * @param string $error
     * @param mixed $trace
     * @param array $additionalData
     * @return static
     */
    public static function error(string $error, $trace = null, array $additionalData = [])
    {
        $data = [
            'success' => false,
            'message' => $error,
        ];

        if ($trace !== null) {
            $data['trace'] = $trace;
        }

        if (!empty($additionalData)) {
            $data = array_merge($data, $additionalData);
        }

        return new static($data);
    }
}
