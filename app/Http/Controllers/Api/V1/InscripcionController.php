<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\CicloEscolar;
use App\Models\Grupo;
use App\Models\Inscripcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InscripcionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Scope a través de alumno — BelongsToTenant filtra por escuela_id del usuario
        $query = Inscripcion::whereHas('alumno')
            ->with(['alumno', 'grupo.grado.nivel', 'cicloEscolar']);

        if ($request->has('alumno_id')) {
            $query->where('alumno_id', $request->alumno_id);
        }
        if ($request->has('grupo_id')) {
            $query->where('grupo_id', $request->grupo_id);
        }
        if ($request->has('ciclo_escolar_id')) {
            $query->where('ciclo_escolar_id', $request->ciclo_escolar_id);
        }
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        $inscripciones = $query->get();

        return response()->json(['data' => $inscripciones]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'alumno_id'         => ['required', 'integer'],
            'grupo_id'          => ['required', 'integer'],
            'ciclo_escolar_id'  => ['required', 'integer'],
            'fecha_inscripcion' => ['required', 'date'],
            'estado'            => ['sometimes', 'in:activa,baja,transferido'],
            'observaciones'     => ['nullable', 'string'],
        ]);

        // findOrFail con BelongsToTenant devuelve 404 si el recurso pertenece a otro tenant
        $alumno = Alumno::findOrFail($request->alumno_id);
        $grupo  = Grupo::findOrFail($request->grupo_id);
        $ciclo  = CicloEscolar::findOrFail($request->ciclo_escolar_id);

        $existente = Inscripcion::where('alumno_id', $alumno->id)
            ->where('ciclo_escolar_id', $ciclo->id)
            ->where('estado', 'activa')
            ->exists();

        if ($existente) {
            return response()->json([
                'message' => 'El alumno ya tiene una inscripción activa en este ciclo escolar'
            ], 422);
        }

        $inscripcion = Inscripcion::create([
            'alumno_id'         => $alumno->id,
            'grupo_id'          => $grupo->id,
            'ciclo_escolar_id'  => $ciclo->id,
            'fecha_inscripcion' => $request->fecha_inscripcion,
            'estado'            => $request->estado ?? 'activa',
            'observaciones'     => $request->observaciones,
        ]);

        return response()->json([
            'message' => 'Inscripción creada exitosamente',
            'data'    => $inscripcion->load(['alumno', 'grupo', 'cicloEscolar'])
        ], 201);
    }

    public function show(Inscripcion $inscripcion): JsonResponse
    {
        $this->authorizeTenant($inscripcion);
        $inscripcion->load(['alumno', 'grupo.grado.nivel', 'cicloEscolar']);

        return response()->json(['data' => $inscripcion]);
    }

    public function update(Request $request, Inscripcion $inscripcion): JsonResponse
    {
        $this->authorizeTenant($inscripcion);

        $request->validate([
            'grupo_id'      => ['sometimes', 'integer'],
            'estado'        => ['sometimes', 'in:activa,baja,transferido'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if ($request->has('grupo_id')) {
            Grupo::findOrFail($request->grupo_id); // valida que el grupo sea del tenant
        }

        $inscripcion->update($request->only(['grupo_id', 'estado', 'observaciones']));

        return response()->json([
            'message' => 'Inscripción actualizada exitosamente',
            'data'    => $inscripcion
        ]);
    }

    public function destroy(Inscripcion $inscripcion): JsonResponse
    {
        $this->authorizeTenant($inscripcion);
        $inscripcion->delete();

        return response()->json(['message' => 'Inscripción eliminada exitosamente']);
    }

    private function authorizeTenant(Inscripcion $inscripcion): void
    {
        // Alumno tiene BelongsToTenant; si escuela_id no coincide, es acceso no autorizado
        $alumno = $inscripcion->alumno;
        if (!$alumno || $alumno->escuela_id !== auth()->user()->escuela_id) {
            abort(403, 'No autorizado');
        }
    }
}
