<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumnoController extends Controller
{
    /**
     * Lista todos los alumnos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Alumno::with(['grupo.grado.nivel', 'padres']);

        // Filtrar por grupo si se proporciona
        if ($request->has('grupo_id')) {
            $query->where('grupo_id', $request->grupo_id);
        }

        // Filtrar por activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $alumnos = $query->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $alumnos
        ]);
    }

    /**
     * Crear nuevo alumno
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'apellido_materno' => ['nullable', 'string', 'max:255'],
            'curp' => ['nullable', 'string', 'size:18', 'unique:alumnos,curp',
                      'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'foto_url' => ['nullable', 'string', 'max:500'],
            'padres' => ['sometimes', 'array'],
            'padres.*.padre_id' => ['required', 'exists:padres,id'],
            'padres.*.parentesco' => ['required', 'in:padre,madre,tutor,abuelo,otro'],
            'padres.*.responsable_pagos' => ['sometimes', 'boolean'],
            'padres.*.contacto_emergencia' => ['sometimes', 'boolean'],
        ]);

        $alumno = Alumno::create($request->only([
            'nombre',
            'apellido_paterno',
            'apellido_materno',
            'curp',
            'fecha_nacimiento',
            'grupo_id',
            'foto_url'
        ]));

        // Asociar padres si se proporcionaron
        if ($request->has('padres')) {
            foreach ($request->padres as $padreData) {
                $alumno->padres()->attach($padreData['padre_id'], [
                    'parentesco' => $padreData['parentesco'],
                    'responsable_pagos' => $padreData['responsable_pagos'] ?? false,
                    'contacto_emergencia' => $padreData['contacto_emergencia'] ?? false,
                ]);
            }
        }

        return response()->json([
            'message' => 'Alumno creado exitosamente',
            'data' => $alumno->load(['grupo', 'padres'])
        ], 201);
    }

    /**
     * Mostrar un alumno específico
     */
    public function show(Alumno $alumno): JsonResponse
    {
        $alumno->load(['grupo.grado.nivel', 'padres']);

        return response()->json([
            'data' => $alumno
        ]);
    }

    /**
     * Actualizar alumno
     */
    public function update(Request $request, Alumno $alumno): JsonResponse
    {
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'apellido_paterno' => ['sometimes', 'string', 'max:255'],
            'apellido_materno' => ['nullable', 'string', 'max:255'],
            'curp' => ['nullable', 'string', 'size:18', 'unique:alumnos,curp,' . $alumno->id,
                      'regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'grupo_id' => ['nullable', 'exists:grupos,id'],
            'foto_url' => ['nullable', 'string', 'max:500'],
            'activo' => ['sometimes', 'boolean'],
            'padres' => ['sometimes', 'array'],
            'padres.*.padre_id' => ['required', 'exists:padres,id'],
            'padres.*.parentesco' => ['required', 'in:padre,madre,tutor,abuelo,otro'],
            'padres.*.responsable_pagos' => ['sometimes', 'boolean'],
            'padres.*.contacto_emergencia' => ['sometimes', 'boolean'],
        ]);

        $alumno->update($request->only([
            'nombre',
            'apellido_paterno',
            'apellido_materno',
            'curp',
            'fecha_nacimiento',
            'grupo_id',
            'foto_url',
            'activo'
        ]));

        // Actualizar padres si se proporcionaron
        if ($request->has('padres')) {
            $alumno->padres()->detach();
            foreach ($request->padres as $padreData) {
                $alumno->padres()->attach($padreData['padre_id'], [
                    'parentesco' => $padreData['parentesco'],
                    'responsable_pagos' => $padreData['responsable_pagos'] ?? false,
                    'contacto_emergencia' => $padreData['contacto_emergencia'] ?? false,
                ]);
            }
        }

        return response()->json([
            'message' => 'Alumno actualizado exitosamente',
            'data' => $alumno->load(['grupo', 'padres'])
        ]);
    }

    /**
     * Eliminar alumno
     */
    public function destroy(Alumno $alumno): JsonResponse
    {
        $alumno->delete();

        return response()->json([
            'message' => 'Alumno eliminado exitosamente'
        ]);
    }
}
