<?php

namespace App\Enums;

enum RolEnum: string
{
    case DIRECTOR = 'director';
    case ADMIN = 'admin';
    case MAESTRO = 'maestro';
    case PADRE = 'padre';

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
            self::DIRECTOR => 'Director',
            self::ADMIN => 'Administrativo',
            self::MAESTRO => 'Maestro',
            self::PADRE => 'Padre/Tutor',
        };
    }
}
