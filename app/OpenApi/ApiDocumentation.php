<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'COMFACA Crédito API',
    description: 'API para la gestión de solicitudes de crédito de COMFACA'
)]
#[OA\Server(url: '/api')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]

final class ApiDocumentation {}
