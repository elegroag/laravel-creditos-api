<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudPayload extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'solicitud_payload';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'solicitud_id',
        'version',
        'encabezado',
        'solicitud',
        'producto_solicitado',
        'solicitante',
        'informacion_laboral',
        'ingresos_descuentos',
        'informacion_economica',
        'propiedades',
        'deudas',
        'referencias',
        'linea_credito',
        'save_xml'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encabezado' => 'json',
            'solicitud' => 'json',
            'producto_solicitado' => 'json',
            'solicitante' => 'json',
            'informacion_laboral' => 'json',
            'ingresos_descuentos' => 'json',
            'informacion_economica' => 'json',
            'propiedades' => 'json',
            'deudas' => 'json',
            'referencias' => 'json',
            'linea_credito' => 'json',
            'save_xml' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the solicitud that owns the payload.
     */
    public function solicitud()
    {
        return $this->belongsTo(SolicitudCredito::class, 'solicitud_id');
    }

    /**
     * Get a specific value from the JSON data.
     */
    public function getValor(string $clave, mixed $default = null): mixed
    {
        $datos = $this->datos_json ?? [];
        return data_get($datos, $clave, $default);
    }

    /**
     * Set a specific value in the JSON data.
     */
    public function setValor(string $clave, mixed $valor): void
    {
        $datos = $this->datos_json ?? [];
        data_set($datos, $clave, $valor);
        $this->datos_json = $datos;
        $this->save();
    }

    /**
     * Get applicant data from payload.
     */
    public function getDatosSolicitante(): array
    {
        return $this->getValor('solicitante', []);
    }

    /**
     * Get financial data from payload.
     */
    public function getDatosFinancieros(): array
    {
        return $this->getValor('datos_financieros', []);
    }

    /**
     * Get employment data from payload.
     */
    public function getDatosLaborales(): array
    {
        return $this->getValor('datos_laborales', []);
    }

    /**
     * Get references data from payload.
     */
    public function getReferencias(): array
    {
        return $this->getValor('referencias', []);
    }

    /**
     * Get additional data from payload.
     */
    public function getDatosAdicionales(): array
    {
        return $this->getValor('datos_adicionales', []);
    }

    /**
     * Set applicant data in payload.
     */
    public function setDatosSolicitante(array $datos): void
    {
        $this->setValor('solicitante', $datos);
    }

    /**
     * Set financial data in payload.
     */
    public function setDatosFinancieros(array $datos): void
    {
        $this->setValor('datos_financieros', $datos);
    }

    /**
     * Set employment data in payload.
     */
    public function setDatosLaborales(array $datos): void
    {
        $this->setValor('datos_laborales', $datos);
    }

    /**
     * Set references data in payload.
     */
    public function setReferencias(array $datos): void
    {
        $this->setValor('referencias', $datos);
    }

    /**
     * Set additional data in payload.
     */
    public function setDatosAdicionales(array $datos): void
    {
        $this->setValor('datos_adicionales', $datos);
    }

    /**
     * Get full name from applicant data.
     */
    public function getNombreCompleto(): string
    {
        $solicitante = $this->getDatosSolicitante();
        return trim(($solicitante['nombres'] ?? '') . ' ' . ($solicitante['apellidos'] ?? ''));
    }

    /**
     * Get identification from applicant data.
     */
    public function getIdentificacion(): array
    {
        $solicitante = $this->getDatosSolicitante();
        return [
            'tipo' => $solicitante['tipo_documento'] ?? '',
            'numero' => $solicitante['numero_documento'] ?? ''
        ];
    }

    /**
     * Get contact information from applicant data.
     */
    public function getContacto(): array
    {
        $solicitante = $this->getDatosSolicitante();
        return [
            'email' => $solicitante['email'] ?? '',
            'telefono' => $solicitante['telefono'] ?? '',
            'celular' => $solicitante['celular'] ?? ''
        ];
    }

    /**
     * Get address information from applicant data.
     */
    public function getDireccion(): array
    {
        $solicitante = $this->getDatosSolicitante();
        return [
            'direccion' => $solicitante['direccion'] ?? '',
            'ciudad' => $solicitante['ciudad'] ?? '',
            'departamento' => $solicitante['departamento'] ?? '',
            'codigo_postal' => $solicitante['codigo_postal'] ?? ''
        ];
    }

    /**
     * Check if payload has complete applicant data.
     */
    public function tieneDatosCompletos(): bool
    {
        $solicitante = $this->getDatosSolicitante();
        $camposRequeridos = ['nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'email', 'telefono'];

        foreach ($camposRequeridos as $campo) {
            if (empty($solicitante[$campo])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate applicant data structure.
     */
    public function validarEstructura(): array
    {
        $errores = [];
        $solicitante = $this->getDatosSolicitante();

        // Validar campos obligatorios
        $camposObligatorios = [
            'nombres' => 'Nombres',
            'apellidos' => 'Apellidos',
            'tipo_documento' => 'Tipo de documento',
            'numero_documento' => 'Número de documento',
            'email' => 'Correo electrónico',
            'telefono' => 'Teléfono'
        ];

        foreach ($camposObligatorios as $campo => $label) {
            if (empty($solicitante[$campo])) {
                $errores[] = "El campo {$label} es obligatorio";
            }
        }

        // Validar formato de email
        if (!empty($solicitante['email']) && !filter_var($solicitante['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El correo electrónico no tiene un formato válido";
        }

        return $errores;
    }

    /**
     * Merge payload data with new data.
     */
    public function fusionarDatos(array $nuevosDatos): void
    {
        $datosActuales = $this->datos_json ?? [];
        $datosFusionados = array_merge_recursive($datosActuales, $nuevosDatos);
        $this->datos_json = $datosFusionados;
        $this->save();
    }

    /**
     * Create payload for solicitud.
     */
    public static function crearPayload(int $solicitudId, array $datos): self
    {
        return static::create([
            'solicitud_id' => $solicitudId,
            'datos_json' => $datos
        ]);
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'datos_json' => $this->datos_json,
            'datos_solicitante' => $this->getDatosSolicitante(),
            'datos_financieros' => $this->getDatosFinancieros(),
            'datos_laborales' => $this->getDatosLaborales(),
            'referencias' => $this->getReferencias(),
            'datos_adicionales' => $this->getDatosAdicionales(),
            'nombre_completo' => $this->getNombreCompleto(),
            'identificacion' => $this->getIdentificacion(),
            'contacto' => $this->getContacto(),
            'direccion' => $this->getDireccion(),
            'tiene_datos_completos' => $this->tieneDatosCompletos(),
            'errores_validacion' => $this->validarEstructura(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get payload summary for solicitud.
     */
    public function getResumen(): array
    {
        $solicitante = $this->getDatosSolicitante();

        return [
            'nombre_completo' => $this->getNombreCompleto(),
            'identificacion' => $this->getIdentificacion(),
            'contacto' => $this->getContacto(),
            'datos_completos' => $this->tieneDatosCompletos(),
            'cantidad_referencias' => count($this->getReferencias()),
            'tiene_datos_financieros' => !empty($this->getDatosFinancieros()),
            'tiene_datos_laborales' => !empty($this->getDatosLaborales())
        ];
    }
}
