<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

class DebugException extends Exception
{
    static $errors = array();
    protected $orderId;

    public function context()
    {
        return ['order_id' => $this->orderId];
    }

    public function render(): JsonResponse
    {
        return response()->json(array(
            "success" => false,
            "message" => $this->getMessage(),
            'trace' => $this->getTrace(),
            'out' => [
                'code' => $this->getCode(),
                'file' => basename($this->getFile()),
                'line' => $this->getLine()
            ]
        ), 203);
    }

    public static function add($key,  $collect)
    {
        self::$errors[$key] = $collect;
    }

    public static function item($key)
    {
        return (isset(self::$errors[$key])) ? self::$errors[$key] : "";
    }
}
