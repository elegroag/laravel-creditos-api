<?php

namespace App\Exceptions;

use Exception;

class InvalidSolicitudStateError extends Exception
{
    protected $fromEstado;
    protected $toEstado;

    public function __construct(string $message, string $fromEstado = '', string $toEstado = '')
    {
        parent::__construct($message);
        $this->fromEstado = $fromEstado;
        $this->toEstado = $toEstado;
    }

    public function getFromEstado(): string
    {
        return $this->fromEstado;
    }

    public function getToEstado(): string
    {
        return $this->toEstado;
    }

    public function toArray(): array
    {
        return [
            'error' => 'InvalidSolicitudStateError',
            'message' => $this->getMessage(),
            'from_estado' => $this->fromEstado,
            'to_estado' => $this->toEstado
        ];
    }
}
