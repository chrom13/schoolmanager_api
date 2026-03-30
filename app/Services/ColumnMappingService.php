<?php

namespace App\Services;

/**
 * Sugiere automáticamente el mapeo entre columnas de un Excel externo
 * y los campos del modelo Alumno, usando un diccionario de sinónimos
 * y similitud de strings como fallback.
 */
class ColumnMappingService
{
    // Campos destino del modelo Alumno
    public const CAMPOS_OBLIGATORIOS = ['nombre', 'apellido_paterno', 'grado', 'grupo'];
    public const CAMPOS_OPCIONALES   = [
        'apellido_materno',
        'curp',
        'fecha_nacimiento',
        'email_contacto',
        'telefono_contacto',
    ];

    public const TODOS_LOS_CAMPOS = [
        ...self::CAMPOS_OBLIGATORIOS,
        ...self::CAMPOS_OPCIONALES,
    ];

    /**
     * Diccionario de sinónimos: campo_destino => [variantes conocidas en minúsculas sin acentos]
     */
    private const SINONIMOS = [
        'nombre' => [
            'nombre', 'name', 'nombre alumno', 'nombre del alumno', 'nombres',
            'primer nombre', 'first name', 'given name',
        ],
        'apellido_paterno' => [
            'apellido paterno', 'apellido_paterno', 'ap paterno', 'primer apellido',
            'paterno', 'last name', 'apellido', 'surname',
        ],
        'apellido_materno' => [
            'apellido materno', 'apellido_materno', 'ap materno', 'segundo apellido',
            'materno', 'second last name', 'apellidos',
        ],
        'curp' => [
            'curp', 'clave unica', 'clave única', 'clave unica de registro de poblacion',
            'clave de registro',
        ],
        'grado' => [
            'grado', 'grade', 'año', 'ano', 'nivel', 'grado escolar',
            'year', 'curso', 'degree',
        ],
        'grupo' => [
            'grupo', 'group', 'salon', 'salón', 'sección', 'seccion',
            'clase', 'class', 'aula', 'classroom',
        ],
        'fecha_nacimiento' => [
            'fecha nacimiento', 'fecha_nacimiento', 'nacimiento', 'birthday',
            'fecha de nacimiento', 'birth date', 'birthdate', 'dob',
            'f. nacimiento', 'f.nacimiento',
        ],
        'email_contacto' => [
            'email', 'correo', 'mail', 'correo electronico', 'correo electrónico',
            'e-mail', 'email contacto', 'correo contacto', 'email mama',
            'correo mama', 'email papa', 'correo papa', 'email tutor',
        ],
        'telefono_contacto' => [
            'telefono', 'teléfono', 'tel', 'celular', 'phone', 'movil', 'móvil',
            'tel mama', 'tel papa', 'telefono mama', 'telefono papa',
            'cel mama', 'cel papa', 'contacto', 'numero', 'número',
        ],
    ];

    private const SIMILITUD_THRESHOLD = 80; // % mínimo para similitud

    /**
     * Sugiere el mapeo para un array de columnas del Excel.
     *
     * @param  array  $excelColumns  Nombres de columnas del archivo
     * @return array  {excel_col => campo_alumno|null}
     */
    public function suggest(array $excelColumns): array
    {
        $mapping = [];
        $camposUsados = [];

        foreach ($excelColumns as $col) {
            $normalizado = $this->normalizar($col);
            $campo = $this->encontrarCampo($normalizado, $camposUsados);
            $mapping[$col] = $campo;

            if ($campo !== null) {
                $camposUsados[] = $campo;
            }
        }

        return $mapping;
    }

    // -------------------------------------------------------------------------
    // Privados
    // -------------------------------------------------------------------------

    private function encontrarCampo(string $normalizado, array $camposUsados): ?string
    {
        // 1. Buscar en sinónimos (match exacto)
        foreach (self::SINONIMOS as $campo => $sinonimos) {
            if (in_array($campo, $camposUsados)) {
                continue; // ya mapeado
            }
            if (in_array($normalizado, $sinonimos)) {
                return $campo;
            }
        }

        // 2. Buscar por similitud de string
        $mejorCampo = null;
        $mejorPorcentaje = 0;

        foreach (self::SINONIMOS as $campo => $sinonimos) {
            if (in_array($campo, $camposUsados)) {
                continue;
            }

            foreach ($sinonimos as $sinonimo) {
                similar_text($normalizado, $sinonimo, $porcentaje);
                if ($porcentaje > $mejorPorcentaje && $porcentaje >= self::SIMILITUD_THRESHOLD) {
                    $mejorPorcentaje = $porcentaje;
                    $mejorCampo = $campo;
                }
            }
        }

        return $mejorCampo;
    }

    /**
     * Normaliza un string: minúsculas, sin acentos, sin caracteres especiales extra.
     */
    private function normalizar(string $str): string
    {
        $str = mb_strtolower(trim($str));

        // Reemplazar caracteres acentuados
        $desde = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'];
        $hacia  = ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n'];
        $str = str_replace($desde, $hacia, $str);

        // Colapsar espacios múltiples
        $str = preg_replace('/\s+/', ' ', $str);

        return $str;
    }
}
