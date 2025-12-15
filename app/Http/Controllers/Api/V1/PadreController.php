<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Padre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PadreController extends Controller
{
    /**
     * Lista todos los padres
     */
    public function index(Request $request): JsonResponse
    {
        $query = Padre::with(['alumnos.grupo']);

        // Filtrar por activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        // Buscar por nombre o email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre_completo', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $padres = $query->orderBy('nombre_completo')->get();

        return response()->json([
            'data' => $padres
        ]);
    }

    /**
     * Crear nuevo padre
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_completo' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'rfc' => ['nullable', 'string', 'size:13',
                     'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/'],
            'regimen_fiscal' => ['nullable', 'string', 'max:100'],
            'uso_cfdi' => ['nullable', 'string', 'max:10'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
        ]);

        // Validar email único por escuela
        $exists = Padre::where('email', $request->email)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'El email ya está registrado en esta escuela',
                'errors' => ['email' => ['El email ya está en uso']]
            ], 422);
        }

        $padre = Padre::create($request->only([
            'nombre_completo',
            'email',
            'telefono',
            'rfc',
            'regimen_fiscal',
            'uso_cfdi',
            'codigo_postal'
        ]));

        return response()->json([
            'message' => 'Padre/tutor creado exitosamente',
            'data' => $padre
        ], 201);
    }

    /**
     * Mostrar un padre específico
     */
    public function show(Padre $padre): JsonResponse
    {
        $padre->load(['alumnos.grupo.grado.nivel']);

        return response()->json([
            'data' => $padre
        ]);
    }

    /**
     * Actualizar padre
     */
    public function update(Request $request, Padre $padre): JsonResponse
    {
        $request->validate([
            'nombre_completo' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'rfc' => ['nullable', 'string', 'size:13',
                     'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/'],
            'regimen_fiscal' => ['nullable', 'string', 'max:100'],
            'uso_cfdi' => ['nullable', 'string', 'max:10'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        // Validar email único por escuela (excluyendo el registro actual)
        if ($request->has('email') && $request->email !== $padre->email) {
            $exists = Padre::where('email', $request->email)->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'El email ya está registrado en esta escuela',
                    'errors' => ['email' => ['El email ya está en uso']]
                ], 422);
            }
        }

        $padre->update($request->only([
            'nombre_completo',
            'email',
            'telefono',
            'rfc',
            'regimen_fiscal',
            'uso_cfdi',
            'codigo_postal',
            'activo'
        ]));

        return response()->json([
            'message' => 'Padre/tutor actualizado exitosamente',
            'data' => $padre
        ]);
    }

    /**
     * Eliminar padre
     */
    public function destroy(Padre $padre): JsonResponse
    {
        $padre->delete();

        return response()->json([
            'message' => 'Padre/tutor eliminado exitosamente'
        ]);
    }
}
