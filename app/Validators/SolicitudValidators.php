<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;

class SolicitudValidators
{
    /**
     * Validar creación de solicitud de crédito.
     */
    public static function validateCreate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'solicitante.tipo_identificacion' => 'required|string|in:cedula,pasaporte,nit',
            'solicitante.numero_identificacion' => 'required|string|max:20',
            'solicitante.nombres_apellidos' => 'required|string|max:200',
            'solicitante.email' => 'required|email|max:255',
            'solicitante.telefono_movil' => 'required|string|regex:/^\+?[0-9\s\-\(\)]{8,20}$/',
            'solicitante.direccion' => 'nullable|string|max:300',
            'solicitante.ciudad' => 'nullable|string|max:100',
            'solicitante.pais' => 'nullable|string|max:100',
            'monto_solicitado' => 'required|numeric|min:100000|max:1000000000',
            'plazo_meses' => 'required|integer|min:1|max:84',
            'tasa_interes' => 'nullable|numeric|min:0|max:100',
            'destino_credito' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:1000',
            'documentos' => 'nullable|array',
            'documentos.*.nombre' => 'required|string|max:200',
            'documentos.*.tipo' => 'required|string|max:50',
            'documentos.*.url' => 'required|url',
            'documentos.*.tamano' => 'required|integer|min:0'
        ], [
            'solicitante.tipo_identificacion.required' => 'El tipo de identificación del solicitante es requerido',
            'solicitante.tipo_identificacion.in' => 'El tipo de identificación debe ser: cedula, pasaporte o nit',
            'solicitante.numero_identificacion.required' => 'El número de identificación del solicitante es requerido',
            'solicitante.numero_identificacion.max' => 'El número de identificación no puede exceder 20 caracteres',
            'solicitante.nombres_apellidos.required' => 'Los nombres y apellidos del solicitante son requeridos',
            'solicitante.nombres_apellidos.max' => 'Los nombres y apellidos no pueden exceder 200 caracteres',
            'solicitante.email.required' => 'El correo electrónico del solicitante es requerido',
            'solicitante.email.email' => 'El formato del correo electrónico es inválido',
            'solicitante.telefono_movil.required' => 'El teléfono móvil del solicitante es requerido',
            'solicitante.telefono_movil.regex' => 'El formato del teléfono móvil es inválido',
            'monto_solicitado.required' => 'El monto solicitado es requerido',
            'monto_solicitado.min' => 'El monto mínimo solicitado es $100.000',
            'monto_solicitado.max' => 'El monto máximo solicitado es $1.000.000.000',
            'plazo_meses.required' => 'El plazo en meses es requerido',
            'plazo_meses.min' => 'El plazo mínimo es 1 mes',
            'plazo_meses.max' => 'El plazo máximo es 84 meses (7 años)',
            'tasa_interes.min' => 'La tasa de interés no puede ser negativa',
            'tasa_interes.max' => 'La tasa de interés no puede exceder 100%',
            'destino_credito.required' => 'El destino del crédito es requerido',
            'destino_credito.max' => 'El destino del crédito no puede exceder 200 caracteres'
        ]);
    }

    /**
     * Validar actualización de solicitud.
     */
    public static function validateUpdate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'estado' => 'nullable|string|in:POSTULADO,DOCUMENTOS_CARGADOS,FIRMADO,ENVIADO_PENDIENTE_APROBACION,EN_VALIDACION,APROBADO,DESEMBOLSADO,FINALIZADO,RECHAZADO,DESISTE',
            'monto_aprobado' => 'nullable|numeric|min:0|max:1000000000',
            'tasa_interes' => 'nullable|numeric|min:0|max:100',
            'documentos' => 'nullable|array',
            'documentos.*.nombre' => 'required|string|max:200',
            'documentos.*.tipo' => 'required|string|max:50',
            'documentos.*.url' => 'required|url',
            'documentos.*.tamano' => 'required|integer|min:0',
            'detalle_actualizacion' => 'nullable|string|max:500'
        ], [
            'estado.in' => 'El estado debe ser uno de: POSTULADO, DOCUMENTOS_CARGADOS, FIRMADO, ENVIADO_PENDIENTE_APROBACION, EN_VALIDACION, APROBADO, DESEMBOLSADO, FINALIZADO, RECHAZADO, DESISTE',
            'monto_aprobado.min' => 'El monto aprobado no puede ser negativo',
            'monto_aprobado.max' => 'El monto aprobado no puede exceder $1.000.000.000',
            'tasa_interes.min' => 'La tasa de interés no puede ser negativa',
            'tasa_interes.max' => 'La tasa de interés no puede exceder 100%',
            'detalle_actualizacion.max' => 'El detalle de actualización no puede exceder 500 caracteres'
        ]);
    }

    /**
     * Validar postulación.
     */
    public static function validatePostulacion(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'solicitante.tipo_identificacion' => 'required|string|in:cedula,pasaporte,nit',
            'solicitante.numero_identificacion' => 'required|string|max:20',
            'solicitante.nombres_apellidos' => 'required|string|max:200',
            'solicitante.email' => 'required|email|max:255',
            'solicitante.telefono_movil' => 'required|string|regex:/^\+?[0-9\s\-\(\)]{8,20}$/',
            'monto_solicitado' => 'required|numeric|min:100000|max:1000000000',
            'plazo_meses' => 'required|integer|min:1|max:84',
            'descripcion' => 'nullable|string|max:1000'
        ], [
            'solicitante.tipo_identificacion.required' => 'El tipo de identificación del solicitante es requerido',
            'solicitante.tipo_identificacion.in' => 'El tipo de identificación debe ser: cedula, pasaporte o nit',
            'solicitante.numero_identificacion.required' => 'El número de identificación del solicitante es requerido',
            'solicitante.nombres_apellidos.required' => 'Los nombres y apellidos del solicitante son requeridos',
            'solicitante.email.required' => 'El correo electrónico del solicitante es requerido',
            'solicitante.email.email' => 'El formato del correo electrónico es inválido',
            'solicitante.telefono_movil.required' => 'El teléfono móvil del solicitante es requerido',
            'solicitante.telefono_movil.regex' => 'El formato del teléfono móvil es inválido',
            'monto_solicitado.required' => 'El monto solicitado es requerido',
            'monto_solicitado.min' => 'El monto mínimo solicitado es $100.000',
            'monto_solicitado.max' => 'El monto máximo solicitado es $1.000.000.000',
            'plazo_meses.required' => 'El plazo en meses es requerido',
            'plazo_meses.min' => 'El plazo mínimo es 1 mes',
            'plazo_meses.max' => 'El plazo máximo es 84 meses (7 años)'
        ]);
    }

    /**
     * Validar actualización de estado de postulación.
     */
    public static function validatePostulacionEstado(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'estado' => 'required|string|in:POSTULADO,EN_REVISION,APROBADO,RECHAZADO,CANCELADO',
            'detalle' => 'required|string|max:500'
        ], [
            'estado.required' => 'El estado es requerido',
            'estado.in' => 'El estado debe ser uno de: POSTULADO, EN_REVISION, APROBADO, RECHAZADO, CANCELADO',
            'detalle.required' => 'El detalle del cambio de estado es requerido',
            'detalle.max' => 'El detalle no puede exceder 500 caracteres'
        ]);
    }

    /**
     * Validar entidad digital.
     */
    public static function validateEntidadDigital(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'username' => 'required|string|max:50',
            'tipo_identificacion' => 'required|string|in:cedula,pasaporte,nit',
            'numero_identificacion' => 'required|string|max:20',
            'documentos.frente' => 'required|url',
            'documentos.reverso' => 'nullable|url',
            'selfie' => 'nullable|url',
            'clave_firma' => 'required|string|min:8|max:255',
            'overwrite' => 'nullable|boolean'
        ], [
            'username.required' => 'El nombre de usuario es requerido',
            'tipo_identificacion.required' => 'El tipo de identificación es requerido',
            'tipo_identificacion.in' => 'El tipo de identificación debe ser: cedula, pasaporte o nit',
            'numero_identificacion.required' => 'El número de identificación es requerido',
            'documentos.frente.required' => 'El documento frontal es requerido',
            'documentos.frente.url' => 'La URL del documento frontal debe ser válida',
            'documentos.reverso.url' => 'La URL del documento reverso debe ser válida',
            'selfie.url' => 'La URL de la selfie debe ser válida',
            'clave_firma.required' => 'La clave de firma es requerida',
            'clave_firma.min' => 'La clave de firma debe tener al menos 8 caracteres',
            'clave_firma.max' => 'La clave de firma no puede exceder 255 caracteres'
        ]);
    }

    /**
     * Validar línea de inversión.
     */
    public static function validateLineaInversion(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'id' => 'required|integer|min:1',
            'linea_credito' => 'required|string|max:200',
            'monto_maximo_pesos' => 'required|integer|min:100000|max:1000000000',
            'plazo_maximo' => 'required|string|max:50',
            'tasas_interes_anual.categoria_a' => 'required|string|max:20',
            'tasas_interes_anual.categoria_b' => 'required|string|max:20',
            'tasas_interes_anual.categoria_c' => 'required|string|max:20',
            'requisitos' => 'required|array|min:1',
            'requisitos.*' => 'required|string|max:300',
            'categoria' => 'nullable|string|in:A,B,C',
            'descripcion' => 'nullable|string|max:1000'
        ], [
            'id.required' => 'El ID de la línea es requerido',
            'id.min' => 'El ID debe ser un número positivo',
            'linea_credito.required' => 'El nombre de la línea de crédito es requerido',
            'linea_credito.max' => 'El nombre de la línea no puede exceder 200 caracteres',
            'monto_maximo_pesos.required' => 'El monto máximo es requerido',
            'monto_maximo_pesos.min' => 'El monto mínimo es $100.000',
            'monto_maximo_pesos.max' => 'El monto máximo es $1.000.000.000',
            'plazo_maximo.required' => 'El plazo máximo es requerido',
            'plazo_maximo.max' => 'El plazo máximo no puede exceder 50 caracteres',
            'tasas_interes_anual.categoria_a.required' => 'La tasa para categoría A es requerida',
            'tasas_interes_anual.categoria_b.required' => 'La tasa para categoría B es requerida',
            'tasas_interes_anual.categoria_c.required' => 'La tasa para categoría C es requerida',
            'requisitos.required' => 'Los requisitos son requeridos',
            'requisitos.min' => 'Debe especificar al menos un requisito',
            'requisitos.*.required' => 'Cada requisito es requerido',
            'requisitos.*.max' => 'Cada requisito no puede exceder 300 caracteres',
            'categoria.in' => 'La categoría debe ser A, B o C'
        ]);
    }

    /**
     * Validar documentos del postulante.
     */
    public static function validateDocumentoPostulante(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'username' => 'required|string|max:50',
            'tipo_identificacion' => 'required|string|in:cedula,pasaporte,nit',
            'numero_identificacion' => 'required|string|max:20',
            'documentos' => 'nullable|array',
            'documentos.*' => 'required|string|max:255',
            'selfie' => 'nullable|string|max:255'
        ], [
            'username.required' => 'El nombre de usuario es requerido',
            'tipo_identificacion.required' => 'El tipo de identificación es requerido',
            'tipo_identificacion.in' => 'El tipo de identificación debe ser: cedula, pasaporte o nit',
            'numero_identificacion.required' => 'El número de identificación es requerido',
            'documentos.*.required' => 'Cada documento es requerido',
            'documentos.*.max' => 'La ruta del documento no puede exceder 255 caracteres'
        ]);
    }
}
