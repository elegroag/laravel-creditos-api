<?php

if (!function_exists('api_dd')) {
    /**
     * API Debug & Dump - dd() para APIs
     * Funciona como dd() pero devuelve JSON en lugar de HTML
     * 
     * @param string $message Mensaje descriptivo
     * @param mixed $data Datos a mostrar (opcional)
     * @param bool $includeBacktrace Incluir backtrace (default: true)
     * @return \Illuminate\Http\JsonResponse
     */
    function api_dd(string $message, mixed $data = null, bool $includeBacktrace = true): \Illuminate\Http\JsonResponse
    {
        $debugData = [
            'debug' => true,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ];

        if ($includeBacktrace) {
            $debugData['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        }

        return response()->json($debugData, 200, [], JSON_PRETTY_PRINT);
    }
}

if (!function_exists('api_dd_if_local')) {
    /**
     * API Debug condicional - solo en development
     * 
     * @param string $message Mensaje descriptivo
     * @param mixed $data Datos a mostrar (opcional)
     * @return \Illuminate\Http\JsonResponse|null
     */
    function api_dd_if_local(string $message, mixed $data = null): ?\Illuminate\Http\JsonResponse
    {
        if (app()->environment('local')) {
            return api_dd($message, $data);
        }
        
        return null;
    }
}

if (!function_exists('api_dump')) {
    /**
     * API Dump simple - sin backtrace
     * 
     * @param string $message Mensaje descriptivo
     * @param mixed $data Datos a mostrar (opcional)
     * @return \Illuminate\Http\JsonResponse
     */
    function api_dump(string $message, mixed $data = null): \Illuminate\Http\JsonResponse
    {
        return api_dd($message, $data, false);
    }
}

if (!function_exists('api_dd_conditional')) {
    /**
     * API Debug con condición personalizada
     * 
     * @param bool $condition Condición para mostrar debug
     * @param string $message Mensaje descriptivo
     * @param mixed $data Datos a mostrar (opcional)
     * @return \Illuminate\Http\JsonResponse|null
     */
    function api_dd_conditional(bool $condition, string $message, mixed $data = null): ?\Illuminate\Http\JsonResponse
    {
        if ($condition) {
            return api_dd($message, $data);
        }
        
        return null;
    }
}

if (!function_exists('api_dd_env')) {
    /**
     * API Debug para environment específico
     * 
     * @param string|array $environments Entornos donde mostrar debug
     * @param string $message Mensaje descriptivo
     * @param mixed $data Datos a mostrar (opcional)
     * @return \Illuminate\Http\JsonResponse|null
     */
    function api_dd_env(string|array $environments, string $message, mixed $data = null): ?\Illuminate\Http\JsonResponse
    {
        $envs = is_array($environments) ? $environments : [$environments];
        
        if (app()->environment($envs)) {
            return api_dd($message, $data);
        }
        
        return null;
    }
}
