<?php

namespace App\Enums;

enum EstadoPostulacion: string
{
    case POSTULADO = 'POSTULADO';
    case EN_REVISION = 'EN_REVISION';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case CANCELADO = 'CANCELADO';

    public function getLabel(): string
    {
        return match($this) {
            self::POSTULADO => 'Postulado',
            self::EN_REVISION => 'En Revisión',
            self::APROBADO => 'Aprobado',
            self::RECHAZADO => 'Rechazado',
            self::CANCELADO => 'Cancelado'
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::POSTULADO => 'gray',
            self::EN_REVISION => 'blue',
            self::APROBADO => 'green',
            self::RECHAZADO => 'red',
            self::CANCELADO => 'orange'
        };
    }

    public function canTransitionTo(self $newState): bool
    {
        return match($this) {
            self::POSTULADO => in_array($newState, [self::EN_REVISION, self::CANCELADO]),
            self::EN_REVISION => in_array($newState, [self::APROBADO, self::RECHAZADO, self::CANCELADO]),
            self::APROBADO, self::RECHAZADO, self::CANCELADO => false
        };
    }

    public static function getAll(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->getLabel(),
            'color' => $case->getColor()
        ], self::cases());
    }

    public static function getActiveStates(): array
    {
        return [
            self::POSTULADO,
            self::EN_REVISION
        ];
    }

    public static function getFinalStates(): array
    {
        return [
            self::APROBADO,
            self::RECHAZADO,
            self::CANCELADO
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
}
