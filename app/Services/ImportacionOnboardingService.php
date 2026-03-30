<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\CuentaPorCobrar;
use App\Models\Grupo;
use App\Models\ImportSession;
use App\Models\Inscripcion;
use App\Models\PlanPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ejecuta la importación final del onboarding en una sola transacción.
 *
 * Orden de operaciones:
 *  1. Crear Grupos faltantes
 *  2. Crear Alumnos (con escuela_id explícito desde la sesión)
 *  3. Crear Inscripciones
 *  4. Asignar plan de pago a cada alumno → genera CuentaPorCobrar
 *  5. Para alumnos "al corriente": marcar todas sus cuentas como pagadas
 *  6. Para morosos: marcar como pagadas las cuentas que NO están en su lista pendiente
 *  7. Actualizar sesión a status=imported, limpiar archivo temporal
 */
class ImportacionOnboardingService
{
    public function __construct(
        private readonly ServicioGeneradorPagos $servicioGeneradorPagos,
    ) {}

    /**
     * Ejecuta la importación completa dentro de una transacción.
     *
     * @throws \Exception si la transacción falla
     */
    public function ejecutar(ImportSession $session, int $userId): array
    {
        return DB::transaction(function () use ($session, $userId) {
            $escuelaId   = $session->escuela_id;
            $cicloId     = $session->ciclo_escolar_id;
            $alumnos     = $session->alumnos_parseados ?? [];
            $morososData = $session->morosos['por_alumno'] ?? [];

            // Índice de morosos por temp_id para acceso O(1)
            $morososIndex = $this->buildMorososIndex($morososData);

            // ── 1. Crear grupos faltantes ────────────────────────────────────
            $gruposCreados = 0;
            $gruposTempLookup = []; // "gradoId_letra" => grupo_id

            if (!empty($session->grupos_a_crear)) {
                foreach ($session->grupos_a_crear as $info) {
                    $grupo = Grupo::firstOrCreate(
                        [
                            'escuela_id'       => $escuelaId,
                            'grado_id'         => $info['grado_id'],
                            'ciclo_escolar_id' => $cicloId,
                            'nombre'           => $info['grupo_letra'],
                        ],
                        [
                            'capacidad_maxima' => 40,
                            'capacidad_ideal'  => 30,
                        ]
                    );

                    $clave = "{$info['grado_id']}_{$info['grupo_letra']}";
                    $gruposTempLookup[$clave] = $grupo->id;

                    if ($grupo->wasRecentlyCreated) {
                        $gruposCreados++;
                    }
                }
            }

            // ── 2-6. Procesar alumnos válidos ────────────────────────────────
            $alumnosCreados    = 0;
            $inscripciones     = 0;
            $cuentasGeneradas  = 0;
            $pagosRegistrados  = 0;

            $alumnosValidos = array_filter($alumnos, fn($a) => $a['es_valido']);

            foreach ($alumnosValidos as $alumnoData) {
                $tempId    = $alumnoData['temp_id'];
                $gradoId   = $alumnoData['grado_id'];
                $grupoLetra = strtoupper($alumnoData['grupo_letra'] ?? '');

                // Resolver grupo_id (puede venir del parse o de grupos recién creados)
                $grupoId = $alumnoData['grupo_id']
                    ?? $gruposTempLookup["{$gradoId}_{$grupoLetra}"]
                    ?? null;

                if ($grupoId === null) {
                    throw new \Exception(
                        "No se pudo resolver grupo_id para alumno {$tempId} (grado={$gradoId}, grupo={$grupoLetra})"
                    );
                }

                // ── 2. Crear o actualizar Alumno ─────────────────────────────
                // escuela_id se asigna EXPLÍCITAMENTE desde la sesión (no del auth context)
                $alumnoAtributos = [
                    'escuela_id'       => $escuelaId,
                    'nombre'           => $alumnoData['nombre'],
                    'apellido_paterno' => $alumnoData['apellido_paterno'],
                    'apellido_materno' => $alumnoData['apellido_materno'] ?: null,
                    'fecha_nacimiento' => $alumnoData['fecha_nacimiento'] ?: null,
                ];

                if (!empty($alumnoData['curp'])) {
                    // Upsert por CURP dentro de la misma escuela
                    $alumno = Alumno::withTrashed()
                        ->where('escuela_id', $escuelaId)
                        ->where('curp', $alumnoData['curp'])
                        ->first();

                    if ($alumno) {
                        if ($alumno->trashed()) {
                            $alumno->restore();
                        }
                        $alumno->update($alumnoAtributos);
                    } else {
                        $alumno = Alumno::create(array_merge($alumnoAtributos, [
                            'curp' => $alumnoData['curp'],
                        ]));
                        $alumnosCreados++;
                    }
                } else {
                    $alumno = Alumno::create($alumnoAtributos);
                    $alumnosCreados++;
                }

                // ── 3. Crear Inscripción ─────────────────────────────────────
                $inscripcionExiste = Inscripcion::where('alumno_id', $alumno->id)
                    ->where('ciclo_escolar_id', $cicloId)
                    ->where('estado', 'activa')
                    ->exists();

                if (!$inscripcionExiste) {
                    Inscripcion::create([
                        'alumno_id'         => $alumno->id,
                        'grupo_id'          => $grupoId,
                        'ciclo_escolar_id'  => $cicloId,
                        'fecha_inscripcion' => now()->toDateString(),
                        'estado'            => 'activa',
                    ]);
                    $inscripciones++;
                }

                // ── 4. Asignar plan de pago ──────────────────────────────────
                $planId = $session->planParaAlumno($tempId);
                if ($planId === null) {
                    continue; // sin plan asignado → omitir finanzas
                }

                $plan = PlanPago::find($planId);
                if (!$plan) {
                    continue;
                }

                $cuentas = $this->servicioGeneradorPagos->asignarPlanAAlumno($alumno, $plan);
                $cuentasGeneradas += $cuentas->count();

                // ── 5 y 6. Registrar pagos ───────────────────────────────────
                $conceptosPendientes = $session->conceptosPendientesDeAlumno($tempId);
                // conceptosPendientes: array de concepto_plan_pago_ids que QUEDAN como deuda

                foreach ($cuentas as $cuenta) {
                    $esPendiente = in_array($cuenta->concepto_plan_id, $conceptosPendientes);

                    if (!$esPendiente) {
                        // Marcar como pagado en la fecha de vencimiento del concepto
                        $fechaPago = $cuenta->fecha_vencimiento
                            ? $cuenta->fecha_vencimiento->format('Y-m-d')
                            : now()->toDateString();

                        $this->servicioGeneradorPagos->registrarPago(
                            $cuenta,
                            $cuenta->monto_base,
                            $fechaPago
                        );

                        // Anotar como saldo inicial de onboarding
                        $cuenta->update(['notas' => 'Saldo inicial — onboarding']);
                        $pagosRegistrados++;
                    }
                }
            }

            // ── 7. Finalizar sesión ──────────────────────────────────────────
            $session->update([
                'status'        => ImportSession::STATUS_IMPORTED,
                'confirmado_at' => now(),
                'confirmado_by' => $userId,
            ]);

            // Eliminar archivo temporal del storage
            if ($session->archivo_path && Storage::disk('local')->exists($session->archivo_path)) {
                Storage::disk('local')->delete($session->archivo_path);
            }

            return [
                'grupos_creados'   => $gruposCreados,
                'alumnos_creados'  => $alumnosCreados,
                'inscripciones'    => $inscripciones,
                'cuentas_generadas' => $cuentasGeneradas,
                'pagos_registrados' => $pagosRegistrados,
            ];
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Construye un índice {temp_id => [concepto_plan_pago_id, ...]} para acceso O(1).
     */
    private function buildMorososIndex(array $morososData): array
    {
        $index = [];
        foreach ($morososData as $entrada) {
            $index[$entrada['temp_id']] = $entrada['concepto_plan_pago_ids'] ?? [];
        }
        return $index;
    }
}
