<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSignature
{
    /**
     * Valida la firma HMAC-SHA256 del webhook
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $provider
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $provider = 'firmaplus'): Response
    {
        $secret = config("services.{$provider}.webhook_secret");
        
        if (!$secret) {
            Log::error("Webhook secret not configured for provider: {$provider}");
            return response()->json([
                'success' => false,
                'error' => 'Webhook secret not configured'
            ], 500);
        }

        // Obtener firma del header
        $signature = $request->header('X-Signature') ?? $request->header('X-Webhook-Signature');
        
        if (!$signature) {
            Log::warning('Webhook received without signature', [
                'ip' => $request->ip(),
                'provider' => $provider,
                'headers' => $request->headers->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Missing signature header (X-Signature or X-Webhook-Signature)'
            ], 401);
        }

        // Calcular firma esperada
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Comparación segura contra timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            Log::error('Invalid webhook signature', [
                'provider' => $provider,
                'ip' => $request->ip(),
                'received_signature' => substr($signature, 0, 10) . '...',
                'expected_signature' => substr($expectedSignature, 0, 10) . '...',
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Invalid signature'
            ], 401);
        }

        Log::info('Webhook signature validated successfully', [
            'provider' => $provider,
            'ip' => $request->ip()
        ]);

        return $next($request);
    }
}
