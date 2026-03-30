<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\CicloEscolar;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Services\AlumnoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlumnoController extends Controller
{
    /**
     * Lista todos los alumnos con su grupo activo desde inscripciones.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Alumno::with(['inscripcionActiva.grupo.grado.nivel']);

        // Filtrar por grupo a través de la inscripción activa
        if ($request->has('grupo_id')) {
            $grupoId = $request->grupo_id;
            $query->whereHas('inscripciones', function ($q) use ($grupoId) {
                $q->where('grupo_id', $grupoId)->where('estado', 'activa');
            });
        }

        // Filtrar por estado activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $alumnos = $query->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombre')
            ->get()
            ->map(function ($alumno) {
                // Inyectar grupo y grupo_id en el nivel superior del alumno
                $alumno->setRelation('grupo', $alumno->inscripcionActiva?->grupo);
                $alumno->setAttribute('grupo_id', $alumno->inscripcionActiva?->grupo_id);
                $alumno->makeHidden(['inscripcion_activa']);
                return $alumno;
            });

        return response()->json(['data' => $alumnos]);
    }

    /**
     * Crear nuevo alumno. Si se proporciona grupo_id, crea la inscripción correspondiente.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre'           => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'apellido_materno' => ['nullable', 'string', 'max:255'],
            'curp'             => ['nullable', 'string', 'size:18', 'unique:alumnos,curp',
                                   'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'genero'           => ['nullable', 'in:masculino,femenino,otro'],
            'telefono'         => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:255'],
            'direccion'        => ['nullable', 'string', 'max:500'],
            'foto_url'         => ['nullable', 'string', 'max:500'],
            'grupo_id'         => ['nullable', 'exists:grupos,id'],
            'padres'           => ['sometimes', 'array'],
            'padres.*.padre_id'             => ['required', 'exists:padres,id'],
            'padres.*.parentesco'           => ['required', 'in:padre,madre,tutor,abuelo,otro'],
            'padres.*.responsable_pagos'    => ['sometimes', 'boolean'],
            'padres.*.contacto_emergencia'  => ['sometimes', 'boolean'],
        ]);

        $alumno = Alumno::create($request->only([
            'nombre',
            'apellido_paterno',
            'apellido_materno',
            'curp',
            'fecha_nacimiento',
            'genero',
            'telefono',
            'email',
            'direccion',
            'foto_url',
        ]));

        // Crear inscripción si se proporcionó grupo_id
        if ($request->grupo_id) {
            $grupo = Grupo::find($request->grupo_id);
            if ($grupo && $grupo->ciclo_escolar_id) {
                Inscripcion::create([
                    'alumno_id'         => $alumno->id,
                    'grupo_id'          => $grupo->id,
                    'ciclo_escolar_id'  => $grupo->ciclo_escolar_id,
                    'fecha_inscripcion' => now()->toDateString(),
                    'estado'            => 'activa',
                ]);
            }
        }

        // Asociar padres si se proporcionaron
        if ($request->has('padres')) {
            foreach ($request->padres as $padreData) {
                $alumno->padres()->attach($padreData['padre_id'], [
                    'parentesco'          => $padreData['parentesco'],
                    'responsable_pagos'   => $padreData['responsable_pagos'] ?? false,
                    'contacto_emergencia' => $padreData['contacto_emergencia'] ?? false,
                ]);
            }
        }

        $alumno->load(['inscripcionActiva.grupo.grado.nivel', 'padres']);
        $alumno->setRelation('grupo', $alumno->inscripcionActiva?->grupo);
        $alumno->setAttribute('grupo_id', $alumno->inscripcionActiva?->grupo_id);
        $alumno->makeHidden(['inscripcion_activa']);

        return response()->json([
            'message' => 'Alumno creado exitosamente',
            'data'    => $alumno,
        ], 201);
    }

    /**
     * Mostrar un alumno específico.
     */
    public function show(Alumno $alumno): JsonResponse
    {
        $alumno->load(['inscripcionActiva.grupo.grado.nivel', 'padres']);
        $alumno->setRelation('grupo', $alumno->inscripcionActiva?->grupo);
        $alumno->setAttribute('grupo_id', $alumno->inscripcionActiva?->grupo_id);
        $alumno->makeHidden(['inscripcion_activa']);

        return response()->json(['data' => $alumno]);
    }

    /**
     * Actualizar alumno. Si se proporciona grupo_id, actualiza o crea la inscripción activa.
     */
    public function update(Request $request, Alumno $alumno): JsonResponse
    {
        $request->validate([
            'nombre'           => ['sometimes', 'string', 'max:255'],
            'apellido_paterno' => ['sometimes', 'string', 'max:255'],
            'apellido_materno' => ['nullable', 'string', 'max:255'],
            'curp'             => ['nullable', 'string', 'size:18', 'unique:alumnos,curp,' . $alumno->id,
                                   'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'genero'           => ['nullable', 'in:masculino,femenino,otro'],
            'telefono'         => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:255'],
            'direccion'        => ['nullable', 'string', 'max:500'],
            'foto_url'         => ['nullable', 'string', 'max:500'],
            'activo'           => ['sometimes', 'boolean'],
            'grupo_id'         => ['nullable', 'exists:grupos,id'],
            'padres'           => ['sometimes', 'array'],
            'padres.*.padre_id'             => ['required', 'exists:padres,id'],
            'padres.*.parentesco'           => ['required', 'in:padre,madre,tutor,abuelo,otro'],
            'padres.*.responsable_pagos'    => ['sometimes', 'boolean'],
            'padres.*.contacto_emergencia'  => ['sometimes', 'boolean'],
        ]);

        $alumno->update($request->only([
            'nombre',
            'apellido_paterno',
            'apellido_materno',
            'curp',
            'fecha_nacimiento',
            'genero',
            'telefono',
            'email',
            'direccion',
            'foto_url',
            'activo',
        ]));

        // Actualizar inscripción si se proporcionó grupo_id
        if ($request->has('grupo_id') && $request->grupo_id) {
            $grupo = Grupo::find($request->grupo_id);
            if ($grupo && $grupo->ciclo_escolar_id) {
                $inscripcion = Inscripcion::where('alumno_id', $alumno->id)
                    ->where('ciclo_escolar_id', $grupo->ciclo_escolar_id)
                    ->where('estado', 'activa')
                    ->first();

                if ($inscripcion) {
                    $inscripcion->update(['grupo_id' => $grupo->id]);
                } else {
                    Inscripcion::create([
                        'alumno_id'         => $alumno->id,
                        'grupo_id'          => $grupo->id,
                        'ciclo_escolar_id'  => $grupo->ciclo_escolar_id,
                        'fecha_inscripcion' => now()->toDateString(),
                        'estado'            => 'activa',
                    ]);
                }
            }
        }

        // Actualizar padres si se proporcionaron
        if ($request->has('padres')) {
            $alumno->padres()->detach();
            foreach ($request->padres as $padreData) {
                $alumno->padres()->attach($padreData['padre_id'], [
                    'parentesco'          => $padreData['parentesco'],
                    'responsable_pagos'   => $padreData['responsable_pagos'] ?? false,
                    'contacto_emergencia' => $padreData['contacto_emergencia'] ?? false,
                ]);
            }
        }

        $alumno->load(['inscripcionActiva.grupo.grado.nivel', 'padres']);
        $alumno->setRelation('grupo', $alumno->inscripcionActiva?->grupo);
        $alumno->setAttribute('grupo_id', $alumno->inscripcionActiva?->grupo_id);
        $alumno->makeHidden(['inscripcion_activa']);

        return response()->json([
            'message' => 'Alumno actualizado exitosamente',
            'data'    => $alumno,
        ]);
    }

    /**
     * Eliminar alumno (soft delete).
     */
    public function destroy(Alumno $alumno): JsonResponse
    {
        $alumno->delete();

        return response()->json(['message' => 'Alumno eliminado exitosamente']);
    }

    /**
     * Devuelve la plantilla CSV para importación masiva.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $columnas = [
            'nombre', 'apellido_paterno', 'apellido_materno', 'curp',
            'fecha_nacimiento', 'grado_nombre', 'grupo_nombre',
            'padre_nombre', 'padre_email', 'padre_telefono',
        ];

        $ejemplos = [
            ['Juan', 'García', 'López', 'GALJ050312HMCRPN08', '2005-03-12', '1er Grado', 'A', 'Pedro García Martínez', 'pedro.garcia@email.com', '3311234567'],
            ['María', 'Hernández', 'Díaz', '', '2006-07-25', '2do Grado', 'B', 'Ana Díaz González', 'ana.diaz@email.com', ''],
        ];

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_importacion_alumnos.csv"',
        ];

        return response()->stream(function () use ($columnas, $ejemplos) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $columnas);
            foreach ($ejemplos as $ejemplo) {
                fputcsv($handle, $ejemplo);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Importa alumnos desde un CSV (flujo legado).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'archivo'          => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'ciclo_escolar_id' => ['required', 'integer'],
        ]);

        $ciclo = CicloEscolar::findOrFail($request->ciclo_escolar_id);

        $service   = new AlumnoImportService();
        $resultado = $service->import(
            $request->file('archivo'),
            $ciclo->id,
            auth()->user()->escuela_id,
        );

        $totalErrores = count($resultado['errores']);
        $message = "Importación completada: {$resultado['importados']} importados, "
            . "{$resultado['actualizados']} actualizados, {$totalErrores} errores.";

        return response()->json([
            'message' => $message,
            'data'    => $resultado,
        ]);
    }
}
