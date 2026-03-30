<?php

namespace App\Services;

use App\Models\CicloEscolar;
use App\Models\Grado;
use App\Models\Grupo;
use App\Models\ImportSession;
use Illuminate\Support\Facades\Storage;

/**
 * Valida y parsea los alumnos de un Excel importado durante el onboarding.
 * Aplica el mapeo de columnas configurado por el usuario.
 * Detecta grupos que necesitan crearse y duplicados de CURP.
 */
class AlumnoImportParserService
{
    private const CURP_REGEX = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/';

    public function __construct(
        private readonly ExcelParserService $excelParser,
    ) {}

    /**
     * Parsea y valida el archivo de la sesión.
     *
     * @return array {
     *   validos: int,
     *   errores: [{temp_id, fila, campo, motivo}],
     *   omitidos: int,
     *   alumnos: [{temp_id, ...campos, es_valido, errores[]}],
     *   grupos_a_crear: [{grado_nombre, grupo_letra, grado_id, grado_orden}]
     * }
     */
    public function parse(ImportSession $session): array
    {
        $filePath    = Storage::disk('local')->path($session->archivo_path);
        $hoja        = $session->hoja_seleccionada ?? 'Hoja1';
        $mapeo       = $session->mapeo_columnas ?? [];
        $cicloId     = $session->ciclo_escolar_id;

        $rawRows = $this->excelParser->readSheetRows($filePath, $hoja);

        $ciclo = CicloEscolar::findOrFail($cicloId);
        [$gradosLookup, $gruposLookup] = $this->buildLookups($ciclo);

        $alumnos     = [];
        $erroresGlobal = [];
        $omitidos    = 0;
        $curpsVistos = []; // CURP → temp_id (para detectar duplicados dentro del archivo)
        $gruposACrear = []; // clave "gradoId_letraGrupo" => info

        foreach ($rawRows as $index => $rawRow) {
            $fila   = $index + 2; // +2 porque fila 1 = encabezados
            $tempId = "row_{$fila}";

            // Aplicar mapeo de columnas
            $datos = $this->aplicarMapeo($rawRow, $mapeo);

            // Fila completamente vacía → omitir silenciosamente
            $valoresNoVacios = array_filter($datos, fn($v) => $v !== null && trim((string) $v) !== '');
            if (empty($valoresNoVacios)) {
                $omitidos++;
                continue;
            }

            $erroresFila = [];

            // Validar nombre
            if (empty(trim((string) ($datos['nombre'] ?? '')))) {
                $erroresFila[] = ['campo' => 'nombre', 'motivo' => 'El nombre es obligatorio'];
            }

            // Validar apellido_paterno
            if (empty(trim((string) ($datos['apellido_paterno'] ?? '')))) {
                $erroresFila[] = ['campo' => 'apellido_paterno', 'motivo' => 'El apellido paterno es obligatorio'];
            }

            // Validar grado
            $gradoId = null;
            if (empty($datos['grado'])) {
                $erroresFila[] = ['campo' => 'grado', 'motivo' => 'El grado es obligatorio'];
            } else {
                $gradoId = $this->resolverGradoId((string) $datos['grado'], $gradosLookup);
                if ($gradoId === null) {
                    $erroresFila[] = [
                        'campo'  => 'grado',
                        'motivo' => "No se reconoce el grado '{$datos['grado']}' para este ciclo",
                    ];
                }
            }

            // Validar grupo
            $grupoId = null;
            $grupoLetra = null;
            if (empty($datos['grupo'])) {
                $erroresFila[] = ['campo' => 'grupo', 'motivo' => 'El grupo es obligatorio'];
            } else {
                $grupoLetra = strtoupper(trim((string) $datos['grupo']));
                if ($gradoId !== null) {
                    $grupoId = $gruposLookup[$gradoId][$grupoLetra] ?? null;
                    if ($grupoId === null) {
                        // Grupo no existe → registrar para crear
                        $claveGrupo = "{$gradoId}_{$grupoLetra}";
                        if (!isset($gruposACrear[$claveGrupo])) {
                            $gradoInfo = $gradosLookup['_info'][$gradoId] ?? [];
                            $gruposACrear[$claveGrupo] = [
                                'grado_nombre'  => $gradoInfo['nombre'] ?? "Grado {$gradoId}",
                                'grupo_letra'   => $grupoLetra,
                                'grado_id'      => $gradoId,
                                'grado_orden'   => $gradoInfo['orden'] ?? 0,
                            ];
                        }
                    }
                }
            }

            // Validar CURP (opcional pero con formato si viene)
            $curp = null;
            if (!empty($datos['curp'])) {
                $curp = strtoupper(trim((string) $datos['curp']));
                if (!preg_match(self::CURP_REGEX, $curp)) {
                    $erroresFila[] = ['campo' => 'curp', 'motivo' => "CURP '{$curp}' no tiene formato válido"];
                    $curp = null;
                } elseif (isset($curpsVistos[$curp])) {
                    $erroresFila[] = [
                        'campo'  => 'curp',
                        'motivo' => "CURP duplicado en el archivo (también en {$curpsVistos[$curp]})",
                    ];
                    $curp = null;
                } else {
                    $curpsVistos[$curp] = $tempId;
                }
            }

            // Validar fecha_nacimiento (opcional, verificar formato si viene)
            $fechaNacimiento = null;
            if (!empty($datos['fecha_nacimiento'])) {
                $fechaNacimiento = $this->parsearFecha((string) $datos['fecha_nacimiento']);
                if ($fechaNacimiento === null) {
                    // No es error bloqueante, solo warning — se importa sin fecha
                    $erroresFila[] = [
                        'campo'  => 'fecha_nacimiento',
                        'motivo' => "Formato de fecha no reconocido: '{$datos['fecha_nacimiento']}' — se importará sin fecha",
                    ];
                }
            }

            $esValido = empty($erroresFila) || $this->soloWarnings($erroresFila);

            $alumno = [
                'temp_id'           => $tempId,
                'fila'              => $fila,
                'nombre'            => isset($datos['nombre']) ? trim((string) $datos['nombre']) : null,
                'apellido_paterno'  => isset($datos['apellido_paterno']) ? trim((string) $datos['apellido_paterno']) : null,
                'apellido_materno'  => isset($datos['apellido_materno']) ? trim((string) $datos['apellido_materno']) : null,
                'curp'              => $curp,
                'fecha_nacimiento'  => $fechaNacimiento,
                'email_contacto'    => isset($datos['email_contacto']) ? trim((string) $datos['email_contacto']) : null,
                'telefono_contacto' => isset($datos['telefono_contacto']) ? trim((string) $datos['telefono_contacto']) : null,
                'grado_raw'         => $datos['grado'] ?? null,
                'grupo_raw'         => $datos['grupo'] ?? null,
                'grado_id'          => $gradoId,
                'grupo_letra'       => $grupoLetra,
                'grupo_id'          => $grupoId, // null si hay que crearlo
                'es_valido'         => $esValido,
                'errores'           => $erroresFila,
            ];

            $alumnos[] = $alumno;

            if (!$esValido) {
                foreach ($erroresFila as $error) {
                    $erroresGlobal[] = array_merge(['temp_id' => $tempId, 'fila' => $fila], $error);
                }
            }
        }

        $validos = count(array_filter($alumnos, fn($a) => $a['es_valido']));

        return [
            'validos'        => $validos,
            'errores'        => $erroresGlobal,
            'omitidos'       => $omitidos,
            'alumnos'        => $alumnos,
            'grupos_a_crear' => array_values($gruposACrear),
        ];
    }

    // -------------------------------------------------------------------------
    // Lookups de grados y grupos
    // -------------------------------------------------------------------------

    /**
     * Construye lookup de grados y grupos para el ciclo.
     *
     * @return array [
     *   gradosLookup: {
     *     '_info': {gradoId: {nombre, orden}},
     *     'ordenNormalizado': gradoId,
     *     ...
     *   },
     *   gruposLookup: {gradoId: {letraGrupo: grupoId}}
     * ]
     */
    private function buildLookups(CicloEscolar $ciclo): array
    {
        $gradosLookup = ['_info' => []];
        $gruposLookup = [];

        // Cargar grados del nivel del ciclo
        $grados = Grado::where('nivel_id', $ciclo->nivel_id)->get();
        foreach ($grados as $grado) {
            $gradosLookup['_info'][$grado->id] = [
                'nombre' => $grado->nombre,
                'orden'  => $grado->orden,
            ];
            // Indexar por orden (numérico) y por número extraído del nombre
            $orden = (string) $grado->orden;
            $gradosLookup[$orden] = $grado->id;

            // Extraer número del nombre (ej: "1° Preescolar" → "1")
            if (preg_match('/^(\d+)/', $grado->nombre, $matches)) {
                $gradosLookup[$matches[1]] = $grado->id;
            }
        }

        // Cargar grupos del ciclo
        $grupos = Grupo::where('ciclo_escolar_id', $ciclo->id)->get();
        foreach ($grupos as $grupo) {
            $gruposLookup[$grupo->grado_id][strtoupper($grupo->nombre)] = $grupo->id;
        }

        return [$gradosLookup, $gruposLookup];
    }

    /**
     * Intenta resolver el número de grado del Excel a un grado_id.
     * Acepta: "1", "1°", "Primero", "1er Grado", "1° Preescolar", etc.
     */
    private function resolverGradoId(string $gradoRaw, array $gradosLookup): ?int
    {
        $normalizado = trim($gradoRaw);

        // Intento 1: match directo con clave del lookup
        if (isset($gradosLookup[$normalizado])) {
            return $gradosLookup[$normalizado];
        }

        // Intento 2: extraer número y buscar
        if (preg_match('/(\d+)/', $normalizado, $matches)) {
            $num = $matches[1];
            if (isset($gradosLookup[$num])) {
                return $gradosLookup[$num];
            }
        }

        // Intento 3: palabras ordinales en español
        $ordinales = [
            'primero' => '1', 'primer' => '1', 'primera' => '1',
            'segundo' => '2', 'segunda' => '2',
            'tercero' => '3', 'tercer' => '3', 'tercera' => '3',
            'cuarto'  => '4', 'cuarta'  => '4',
            'quinto'  => '5', 'quinta'  => '5',
            'sexto'   => '6', 'sexta'   => '6',
        ];
        $lower = mb_strtolower($normalizado);
        foreach ($ordinales as $palabra => $numero) {
            if (str_contains($lower, $palabra) && isset($gradosLookup[$numero])) {
                return $gradosLookup[$numero];
            }
        }

        return null;
    }

    /**
     * Aplica el mapeo de columnas a una fila raw del Excel.
     * Retorna un array con keys = campo_alumno.
     */
    private function aplicarMapeo(array $rawRow, array $mapeo): array
    {
        $resultado = [];
        foreach ($mapeo as $excelCol => $campoAlumno) {
            if ($campoAlumno === null || $campoAlumno === '') {
                continue;
            }
            $valor = $rawRow[$excelCol] ?? null;
            // Si ya existe la clave (columna duplicada mapeada al mismo campo), usar la primera no vacía
            if (!isset($resultado[$campoAlumno]) || $resultado[$campoAlumno] === null || $resultado[$campoAlumno] === '') {
                $resultado[$campoAlumno] = $valor;
            }
        }
        return $resultado;
    }

    /**
     * Intenta parsear una fecha en múltiples formatos.
     * Retorna string 'Y-m-d' o null si no reconoce.
     */
    private function parsearFecha(string $valor): ?string
    {
        $valor = trim($valor);

        // Formatos a probar
        $formatos = [
            'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d',
            'd/m/y', 'd-m-y', 'm/d/Y', 'm-d-Y',
        ];

        foreach ($formatos as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $valor);
            if ($fecha && $fecha->format($formato) === $valor) {
                return $fecha->format('Y-m-d');
            }
        }

        // Intento con strtotime como fallback
        $timestamp = strtotime($valor);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Determina si todos los errores son solo warnings (no bloquean el import).
     * Actualmente: fechas inválidas y CURPs con formato incorrecto son warnings.
     */
    private function soloWarnings(array $errores): bool
    {
        $camposBloqueantes = ['nombre', 'apellido_paterno', 'grado'];
        foreach ($errores as $error) {
            if (in_array($error['campo'], $camposBloqueantes)) {
                return false;
            }
        }
        return true;
    }
}
