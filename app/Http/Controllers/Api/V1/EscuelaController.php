<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Escuela;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EscuelaController extends Controller
{
    /**
     * Obtener configuración de la escuela actual
     */
    public function getConfiguracion(Request $request): JsonResponse
    {
        $escuela = $request->user()->escuela;

        return response()->json([
            'data' => [
                'id' => $escuela->id,
                'nombre' => $escuela->nombre,
                'costo_operativo_mensual' => $escuela->costo_operativo_mensual,
                'colegiatura_mensual' => $escuela->colegiatura_mensual,
                'porcentaje_tolerancia' => $escuela->porcentaje_tolerancia,
                'alumnos_necesarios' => $escuela->alumnos_necesarios,
            ]
        ]);
    }

    /**
     * Actualizar configuración de Meta de Nómina
     */
    public function updateConfiguracion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'costo_operativo_mensual' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'colegiatura_mensual' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'porcentaje_tolerancia' => ['required', 'integer', 'min:0', 'max:30'],
            'completar_onboarding' => ['sometimes', 'boolean'], // Opcional: marcar onboarding como completado
        ]);

        // Calcular alumnos necesarios
        $alumnosNecesarios = 0;
        if ($validated['colegiatura_mensual'] > 0) {
            $alumnosNecesarios = (int) ceil(
                $validated['costo_operativo_mensual'] / $validated['colegiatura_mensual']
            );
        }

        $escuela = $request->user()->escuela;

        $dataToUpdate = [
            'costo_operativo_mensual' => $validated['costo_operativo_mensual'],
            'colegiatura_mensual' => $validated['colegiatura_mensual'],
            'porcentaje_tolerancia' => $validated['porcentaje_tolerancia'],
            'alumnos_necesarios' => $alumnosNecesarios,
        ];

        // Si se solicita completar onboarding, marcarlo como completado
        if (isset($validated['completar_onboarding']) && $validated['completar_onboarding']) {
            $dataToUpdate['onboarding_completado'] = true;
            $dataToUpdate['onboarding_completado_at'] = now();
        }

        $escuela->update($dataToUpdate);

        return response()->json([
            'message' => 'Configuración de Meta de Nómina actualizada exitosamente',
            'data' => [
                'id' => $escuela->id,
                'nombre' => $escuela->nombre,
                'costo_operativo_mensual' => $escuela->costo_operativo_mensual,
                'colegiatura_mensual' => $escuela->colegiatura_mensual,
                'porcentaje_tolerancia' => $escuela->porcentaje_tolerancia,
                'alumnos_necesarios' => $escuela->alumnos_necesarios,
                'onboarding_completado' => $escuela->onboarding_completado,
            ]
        ], 200);
    }

    /**
     * Obtener datos completos de la escuela
     */
    public function show(Request $request): JsonResponse
    {
        $escuela = $request->user()->escuela;

        return response()->json([
            'data' => $escuela
        ]);
    }

    /**
     * Actualizar datos generales de la escuela
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'cct' => ['sometimes', 'string', 'max:50'],
            'rfc' => ['sometimes', 'nullable', 'string', 'max:13'],
            'razon_social' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20'],
            'codigo_postal' => ['sometimes', 'nullable', 'string', 'max:10'],
            'regimen_fiscal' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $escuela = $request->user()->escuela;
        $escuela->update($validated);

        return response()->json([
            'message' => 'Datos de la escuela actualizados exitosamente',
            'data' => $escuela
        ], 200);
    }
}
