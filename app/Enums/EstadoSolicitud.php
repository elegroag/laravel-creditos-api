<?php

namespace App\Enums;

enum EstadoSolicitud: string
{
    case POSTULADO = 'POSTULADO';
    case DOCUMENTOS_CARGADOS = 'DOCUMENTOS_CARGADOS';
    case ENVIADO_VALIDACION = 'ENVIADO_VALIDACION';
    case PENDIENTE_FIRMADO = 'PENDIENTE_FIRMADO';
    case FIRMADO = 'FIRMADO';
    case ENVIADO_PENDIENTE_APROBACION = 'ENVIADO_PENDIENTE_APROBACION';
    case APROBADO = 'APROBADO';
    case DESEMBOLSADO = 'DESEMBOLSADO';
    case FINALIZADO = 'FINALIZADO';
    case RECHAZADO = 'RECHAZADO';
    case DESISTE = 'DESISTE';
    case REQUIRE_CORRECCION = 'REQUIRE_CORRECCION';

    public function getLabel(): string
    {
        return match ($this) {
            self::POSTULADO => 'Postulado',
            self::DOCUMENTOS_CARGADOS => 'Documentos cargados',
            self::ENVIADO_VALIDACION => 'Enviado para validación',
            self::PENDIENTE_FIRMADO => 'Pendiente de firmado',
            self::FIRMADO => 'Firmado',
            self::ENVIADO_PENDIENTE_APROBACION => 'Enviado (pendiente de aprobación)',
            self::APROBADO => 'Aprobado',
            self::DESEMBOLSADO => 'Desembolsado',
            self::FINALIZADO => 'Finalizado',
            self::RECHAZADO => 'Rechazado',
            self::DESISTE => 'Desiste',
            self::REQUIRE_CORRECCION => 'Requiere correccion'
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::POSTULADO => '#6B7280',
            self::DOCUMENTOS_CARGADOS => '#3B82F6',
            self::ENVIADO_VALIDACION => '#F59E0B',
            self::PENDIENTE_FIRMADO => '#f5e20bff',
            self::FIRMADO => '#0D9488',
            self::ENVIADO_PENDIENTE_APROBACION => '#8B5CF6',
            self::APROBADO => '#10B981',
            self::DESEMBOLSADO => '#059669',
            self::FINALIZADO => '#6366F1',
            self::RECHAZADO => '#EF4444',
            self::DESISTE => '#F97316',
            self::REQUIRE_CORRECCION => '#16a6f9ff'
        };
    }

    public function getOrden(): int
    {
        return match ($this) {
            self::POSTULADO => 1,
            self::DOCUMENTOS_CARGADOS => 2,
            self::ENVIADO_VALIDACION => 3,
            self::PENDIENTE_FIRMADO => 4,
            self::FIRMADO => 5,
            self::ENVIADO_PENDIENTE_APROBACION => 6,
            self::APROBADO => 7,
            self::DESEMBOLSADO => 8,
            self::FINALIZADO => 9,
            self::RECHAZADO => 10,
            self::DESISTE => 11,
            self::REQUIRE_CORRECCION => 12
        };
    }

    public function getDescripcion(): string
    {
        return match ($this) {
            self::POSTULADO => 'Solicitud recién creada y postulada',
            self::DOCUMENTOS_CARGADOS => 'Todos los documentos han sido cargados',
            self::ENVIADO_VALIDACION => 'Enviado para validación de asesores',
            self::PENDIENTE_FIRMADO => 'Solicitud en proceso de firmado de solicitud de crédito',
            self::FIRMADO => 'Documentos de crédito firmados',
            self::ENVIADO_PENDIENTE_APROBACION => 'Solicitud enviada y pendiente de aprobación',
            self::APROBADO => 'Solicitud aprobada y lista para desembolso',
            self::DESEMBOLSADO => 'Crédito desembolsado al solicitante',
            self::FINALIZADO => 'Crédito pagado y finalizado',
            self::RECHAZADO => 'Solicitud rechazada por no cumplir requisitos',
            self::DESISTE => 'El solicitante desiste de continuar con la solicitud',
            self::REQUIRE_CORRECCION => 'El solicitante debe corregir los datos para poder continuar con la solicitud'
        };
    }

    public function canTransitionTo(self $newState): bool
    {
        return match ($this) {
            self::POSTULADO => in_array($newState, [self::DOCUMENTOS_CARGADOS, self::ENVIADO_VALIDACION, self::RECHAZADO, self::DESISTE, self::REQUIRE_CORRECCION]),
            self::DOCUMENTOS_CARGADOS => in_array($newState, [self::PENDIENTE_FIRMADO, self::ENVIADO_VALIDACION, self::RECHAZADO, self::DESISTE, self::REQUIRE_CORRECCION]),
            self::ENVIADO_VALIDACION => in_array($newState, [self::PENDIENTE_FIRMADO, self::RECHAZADO, self::DESISTE, self::REQUIRE_CORRECCION]),
            self::PENDIENTE_FIRMADO => in_array($newState, [self::FIRMADO, self::RECHAZADO, self::DESISTE, self::REQUIRE_CORRECCION]),
            self::FIRMADO => in_array($newState, [self::ENVIADO_PENDIENTE_APROBACION, self::RECHAZADO, self::DESISTE, self::REQUIRE_CORRECCION]),
            self::ENVIADO_PENDIENTE_APROBACION => in_array($newState, [self::APROBADO, self::RECHAZADO, self::DESISTE, self::REQUIRE_CORRECCION]),
            self::APROBADO => in_array($newState, [self::DESEMBOLSADO, self::FINALIZADO, self::RECHAZADO, self::DESISTE]),
            self::DESEMBOLSADO => in_array($newState, [self::FINALIZADO]),
            self::FINALIZADO, self::RECHAZADO, self::DESISTE, self::REQUIRE_CORRECCION => false
        };
    }

    public static function getAll(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->getLabel(),
            'color' => $case->getColor(),
            'orden' => $case->getOrden(),
            'descripcion' => $case->getDescripcion()
        ], self::cases());
    }

    public static function getActiveStates(): array
    {
        return [
            self::POSTULADO,
            self::DOCUMENTOS_CARGADOS,
            self::ENVIADO_VALIDACION,
            self::PENDIENTE_FIRMADO,
            self::FIRMADO,
            self::ENVIADO_PENDIENTE_APROBACION,
            self::APROBADO,
            self::DESEMBOLSADO
        ];
    }

    public static function getFinalStates(): array
    {
        return [
            self::FINALIZADO,
            self::RECHAZADO,
            self::DESISTE
        ];
    }

    public function isActive(): bool
    {
        return in_array($this, self::getActiveStates());
    }

    public function isFinal(): bool
    {
        return in_array($this, self::getFinalStates());
    }

    public function requiresAction(): bool
    {
        return in_array($this, [self::REQUIRE_CORRECCION, self::PENDIENTE_FIRMADO, self::ENVIADO_VALIDACION]);
    }
}
