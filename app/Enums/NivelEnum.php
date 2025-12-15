<?php

namespace App\Enums;

enum NivelEnum: string
{
    case PREESCOLAR = 'preescolar';
    case PRIMARIA = 'primaria';
    case SECUNDARIA = 'secundaria';
    case PREPARATORIA = 'preparatoria';

    /**
     * Obtener todos los valores del enum
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Obtener etiquetas legibles
     */
    public function label(): string
    {
        return match($this) {
            self::PREESCOLAR => 'Preescolar',
            self::PRIMARIA => 'Primaria',
            self::SECUNDARIA => 'Secundaria',
            self::PREPARATORIA => 'Preparatoria',
        };
    }
}
