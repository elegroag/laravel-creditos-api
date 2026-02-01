<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\LineasCreditoController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SimuladorController extends Controller
{
    protected LineasCreditoController $lineasCreditoController;

    public function __construct(LineasCreditoController $lineasCreditoController)
    {
        $this->lineasCreditoController = $lineasCreditoController;
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('simulador.lineas_credito');
    }

    public function lineasCredito(): Response
    {
        try {
            // Por ahora, devolver datos de ejemplo hasta que la API externa esté configurada
            $data = [
                'success' => true,
                'data' => [
                    [
                        'tipcre' => 'HIPOTECARIO',
                        'nombre' => 'Crédito Hipotecario',
                        'descripcion' => 'Financiamiento para compra de vivienda',
                        'tasa_nominal_anual' => 12.5,
                        'tasa_efectiva_anual' => 13.2,
                        'plazo_minimo' => 60,
                        'plazo_maximo' => 360,
                        'monto_minimo' => 20000000,
                        'monto_maximo' => 500000000,
                        'cuota_minima' => 500000,
                        'activo' => true
                    ],
                    [
                        'tipcre' => 'LIBRE_INVERSION',
                        'nombre' => 'Crédito de Libre Inversión',
                        'descripcion' => 'Crédito sin destino específico',
                        'tasa_nominal_anual' => 18.5,
                        'tasa_efectiva_anual' => 20.1,
                        'plazo_minimo' => 12,
                        'plazo_maximo' => 84,
                        'monto_minimo' => 1000000,
                        'monto_maximo' => 50000000,
                        'cuota_minima' => 100000,
                        'activo' => true
                    ],
                    [
                        'tipcre' => 'VEHICULO',
                        'nombre' => 'Crédito para Vehículo',
                        'descripcion' => 'Financiamiento para compra de vehículo',
                        'tasa_nominal_anual' => 15.2,
                        'tasa_efectiva_anual' => 16.8,
                        'plazo_minimo' => 12,
                        'plazo_maximo' => 60,
                        'monto_minimo' => 5000000,
                        'monto_maximo' => 150000000,
                        'cuota_minima' => 200000,
                        'activo' => true
                    ]
                ]
            ];

            return Inertia::render('simulador/lineas-credito', [
                'lineasCreditoData' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener líneas de crédito para vista web', [
                'error' => $e->getMessage()
            ]);

            return Inertia::render('simulador/lineas-credito', [
                'lineasCreditoData' => [
                    'success' => false,
                    'message' => 'Error al cargar las líneas de crédito',
                    'data' => []
                ]
            ]);
        }
    }

    public function showLinea(string $tipcre): Response
    {
        try {
            // Datos de ejemplo para las líneas de crédito
            $lineasDeCredito = [
                [
                    'tipcre' => 'HIPOTECARIO',
                    'nombre' => 'Crédito Hipotecario',
                    'descripcion' => 'Financiamiento para compra de vivienda',
                    'tasa_nominal_anual' => 12.5,
                    'tasa_efectiva_anual' => 13.2,
                    'plazo_minimo' => 60,
                    'plazo_maximo' => 360,
                    'monto_minimo' => 20000000,
                    'monto_maximo' => 500000000,
                    'cuota_minima' => 500000,
                    'activo' => true
                ],
                [
                    'tipcre' => 'LIBRE_INVERSION',
                    'nombre' => 'Crédito de Libre Inversión',
                    'descripcion' => 'Crédito sin destino específico',
                    'tasa_nominal_anual' => 18.5,
                    'tasa_efectiva_anual' => 20.1,
                    'plazo_minimo' => 12,
                    'plazo_maximo' => 84,
                    'monto_minimo' => 1000000,
                    'monto_maximo' => 50000000,
                    'cuota_minima' => 100000,
                    'activo' => true
                ],
                [
                    'tipcre' => 'VEHICULO',
                    'nombre' => 'Crédito para Vehículo',
                    'descripcion' => 'Financiamiento para compra de vehículo',
                    'tasa_nominal_anual' => 15.2,
                    'tasa_efectiva_anual' => 16.8,
                    'plazo_minimo' => 12,
                    'plazo_maximo' => 60,
                    'monto_minimo' => 5000000,
                    'monto_maximo' => 150000000,
                    'cuota_minima' => 200000,
                    'activo' => true
                ]
            ];

            // Buscar la línea específica
            $lineaSeleccionada = null;
            foreach ($lineasDeCredito as $linea) {
                if ($linea['tipcre'] === $tipcre) {
                    $lineaSeleccionada = $linea;
                    break;
                }
            }

            $data = [
                'success' => true,
                'data' => $lineasDeCredito
            ];

            return Inertia::render('simulador/tipcre', [
                'tipcre' => $tipcre,
                'lineaCreditoData' => $data,
                'lineaSeleccionada' => $lineaSeleccionada
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener línea de crédito para simulador', [
                'tipcre' => $tipcre,
                'error' => $e->getMessage()
            ]);

            return Inertia::render('simulador/tipcre', [
                'tipcre' => $tipcre,
                'lineaCreditoData' => [
                    'success' => false,
                    'message' => 'Error al cargar la línea de crédito',
                    'data' => []
                ],
                'lineaSeleccionada' => null
            ]);
        }
    }
}
