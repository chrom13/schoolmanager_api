<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Padre;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;

class AlumnoImportService
{
    private array $gruposLookup = [];
    private int $escuelaId;

    /**
     * Importa alumnos desde un archivo CSV.
     *
     * @return array{importados: int, actualizados: int, errores: array}
     */
    public function import(UploadedFile $archivo, int $cicloEscolarId, int $escuelaId): array
    {
        $this->escuelaId   = $escuelaId;
        $this->gruposLookup = $this->buildGruposLookup($escuelaId);

        $rows = SimpleExcelReader::create($archivo->getPathname(), 'csv')
            ->trimHeaderRow()
            ->getRows()
            ->collect();

        $importados  = 0;
        $actualizados = 0;
        $errores     = [];

        // Procesar en chunks de 50 con una transacción por chunk
        $chunks = $rows->chunk(50);

        foreach ($chunks as $chunkIndex => $chunk) {
            $rowsValidas = [];

            // Fase 1: validación en memoria (sin DB) para cada fila del chunk
            foreach ($chunk as $localIndex => $row) {
                $rowNumber  = ($chunkIndex * 50) + $localIndex + 2; // +2: cabecera + índice 0
                $rowErrors  = $this->validateRow($row, $rowNumber);

                if (!empty($rowErrors)) {
                    $errores = array_merge($errores, $rowErrors);
                } else {
                    $rowsValidas[] = ['data' => $row, 'numero' => $rowNumber];
                }
            }

            if (empty($rowsValidas)) {
                continue;
            }

            // Fase 2: escritura en DB dentro de una transacción por chunk
            DB::transaction(function () use ($rowsValidas, $cicloEscolarId, &$importados, &$actualizados) {
                foreach ($rowsValidas as $item) {
                    $accion = $this->writeRow($item['data'], $cicloEscolarId);
                    if ($accion === 'created') {
                        $importados++;
                    } else {
                        $actualizados++;
                    }
                }
            });
        }

        return [
            'importados'  => $importados,
            'actualizados' => $actualizados,
            'errores'     => $errores,
        ];
    }

    /**
     * Pre-carga todos los grupos de la escuela en un mapa de lookup
     * con clave "nombre_grado|nombre_grupo" (en minúsculas).
     */
    private function buildGruposLookup(int $escuelaId): array
    {
        return Grupo::with('grado')
            ->where('escuela_id', $escuelaId)
            ->get()
            ->mapWithKeys(fn (Grupo $grupo) => [
                mb_strtolower(trim($grupo->grado->nombre) . '|' . trim($grupo->nombre)) => $grupo,
            ])
            ->all();
    }

    /**
     * Valida una fila sin tocar la DB.
     *
     * @return array  Lista de errores (vacía si la fila es válida)
     */
    private function validateRow(array $row, int $rowNumber): array
    {
        $errores = [];

        $required = [
            'nombre',
            'apellido_paterno',
            'fecha_nacimiento',
            'grado_nombre',
            'grupo_nombre',
            'padre_nombre',
            'padre_email',
        ];

        foreach ($required as $campo) {
            if (empty(trim($row[$campo] ?? ''))) {
                $errores[] = [
                    'fila'    => $rowNumber,
                    'campo'   => $campo,
                    'mensaje' => "El campo '{$campo}' es requerido",
                ];
                // Devolvemos al primer campo faltante para no acumular errores en cascada
                return $errores;
            }
        }

        // CURP: formato válido si se proporciona
        if (!empty($row['curp'] ?? '')) {
            $curp = strtoupper(trim($row['curp']));
            if (!preg_match('/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/', $curp)) {
                $errores[] = [
                    'fila'    => $rowNumber,
                    'campo'   => 'curp',
                    'mensaje' => 'CURP con formato inválido (18 caracteres alfanuméricos)',
                ];
            }
        }

        // Email del padre
        if (!filter_var(trim($row['padre_email']), FILTER_VALIDATE_EMAIL)) {
            $errores[] = [
                'fila'    => $rowNumber,
                'campo'   => 'padre_email',
                'mensaje' => 'Email del padre con formato inválido',
            ];
        }

        // Fecha de nacimiento (YYYY-MM-DD)
        if (!empty($row['fecha_nacimiento'] ?? '')) {
            $fecha = Carbon::createFromFormat('Y-m-d', trim($row['fecha_nacimiento']));
            if (!$fecha) {
                $errores[] = [
                    'fila'    => $rowNumber,
                    'campo'   => 'fecha_nacimiento',
                    'mensaje' => 'Fecha de nacimiento inválida — use formato YYYY-MM-DD',
                ];
            }
        }

        // Grado + grupo: deben existir en el lookup pre-cargado
        $key = mb_strtolower(trim($row['grado_nombre'] ?? '') . '|' . trim($row['grupo_nombre'] ?? ''));
        if (!isset($this->gruposLookup[$key])) {
            $errores[] = [
                'fila'    => $rowNumber,
                'campo'   => 'grado_nombre',
                'mensaje' => "No se encontró grado '{$row['grado_nombre']}' con grupo '{$row['grupo_nombre']}'",
            ];
        }

        return $errores;
    }

    /**
     * Escribe una fila válida en la DB (dentro de una transacción activa).
     *
     * Lógica:
     *   - Alumno: upsert por CURP si se proporciona, sino crea nuevo
     *   - Padre:  upsert por email dentro de la misma escuela
     *   - Pivot alumno_padre: crea si no existe
     *   - Inscripcion: crea si el alumno no tiene inscripción activa en el ciclo
     *
     * @return 'created'|'updated'
     */
    private function writeRow(array $row, int $cicloEscolarId): string
    {
        $key   = mb_strtolower(trim($row['grado_nombre']) . '|' . trim($row['grupo_nombre']));
        $grupo = $this->gruposLookup[$key];
        $curp  = !empty($row['curp'] ?? '') ? strtoupper(trim($row['curp'])) : null;
        $accion = 'created';

        // ── Alumno ────────────────────────────────────────────────────────────
        $datosAlumno = [
            'escuela_id'       => $this->escuelaId,
            'nombre'           => trim($row['nombre']),
            'apellido_paterno' => trim($row['apellido_paterno']),
            'apellido_materno' => !empty($row['apellido_materno'] ?? '') ? trim($row['apellido_materno']) : null,
            'curp'             => $curp,
            'fecha_nacimiento' => !empty($row['fecha_nacimiento'] ?? '') ? trim($row['fecha_nacimiento']) : null,
        ];

        if ($curp) {
            $alumno = Alumno::withTrashed()
                ->where('escuela_id', $this->escuelaId)
                ->where('curp', $curp)
                ->first();

            if ($alumno) {
                if ($alumno->trashed()) {
                    $alumno->restore();
                }
                $alumno->update($datosAlumno);
                $accion = 'updated';
            } else {
                $alumno = Alumno::create($datosAlumno);
            }
        } else {
            $alumno = Alumno::create($datosAlumno);
        }

        // ── Padre ─────────────────────────────────────────────────────────────
        $emailPadre = strtolower(trim($row['padre_email']));

        $padre = Padre::withTrashed()
            ->where('escuela_id', $this->escuelaId)
            ->where('email', $emailPadre)
            ->first();

        if ($padre) {
            if ($padre->trashed()) {
                $padre->restore();
            }
            $padre->nombre_completo = trim($row['padre_nombre']);
            if (!empty($row['padre_telefono'] ?? '')) {
                $padre->telefono = trim($row['padre_telefono']);
            }
            $padre->save();
        } else {
            $padre = Padre::create([
                'escuela_id'     => $this->escuelaId,
                'nombre_completo' => trim($row['padre_nombre']),
                'email'          => $emailPadre,
                'telefono'       => !empty($row['padre_telefono'] ?? '') ? trim($row['padre_telefono']) : null,
            ]);
        }

        // ── Pivot alumno_padre ────────────────────────────────────────────────
        $pivotExiste = $alumno->padres()->where('padres.id', $padre->id)->exists();
        if (!$pivotExiste) {
            $alumno->padres()->attach($padre->id, [
                'parentesco'          => 'padre',
                'responsable_pagos'   => true,
                'contacto_emergencia' => false,
            ]);
        }

        // ── Inscripcion ───────────────────────────────────────────────────────
        $inscripcionExiste = Inscripcion::where('alumno_id', $alumno->id)
            ->where('ciclo_escolar_id', $cicloEscolarId)
            ->where('estado', 'activa')
            ->exists();

        if (!$inscripcionExiste) {
            Inscripcion::create([
                'alumno_id'        => $alumno->id,
                'grupo_id'         => $grupo->id,
                'ciclo_escolar_id' => $cicloEscolarId,
                'fecha_inscripcion' => now()->toDateString(),
                'estado'           => 'activa',
            ]);
        }

        return $accion;
    }
}
