<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    /**
     * Obtener estado del onboarding
     */
    public function status(Request $request): JsonResponse
    {
        $escuela = $request->user()->escuela;

        return response()->json([
            'data' => [
                'completado' => $escuela->onboarding_completado,
                'paso_actual' => $escuela->onboarding_data['paso_actual'] ?? 'bienvenida',
                'fecha_registro' => $escuela->created_at,
                'es_registro_express' => $escuela->es_registro_express ?? false,
            ]
        ]);
    }

    /**
     * Completar datos de la escuela (Paso 1)
     */
    public function completeSchoolData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cct' => [
                'required',
                'string',
                'size:10',
                'unique:escuelas,cct,' . $request->user()->escuela_id,
                'regex:/^[0-9]{2}[A-Z]{3}[0-9]{4}[A-Z]$/'
            ],
            'rfc' => [
                'nullable',
                'string',
                'size:13',
                'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/'
            ],
            'email_escuela' => ['required', 'email'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
        ], [
            'cct.required' => 'El CCT es requerido',
            'cct.size' => 'El CCT debe tener exactamente 10 caracteres',
            'cct.unique' => 'Este CCT ya está registrado',
            'cct.regex' => 'El CCT debe tener el formato: 2 dígitos, 3 letras, 4 dígitos y 1 letra',
            'rfc.size' => 'El RFC debe tener exactamente 13 caracteres',
            'rfc.regex' => 'El RFC no tiene el formato correcto',
            'email_escuela.required' => 'El email de la escuela es requerido',
            'email_escuela.email' => 'El email debe ser válido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $escuela = $request->user()->escuela;

        // Obtener onboarding_data y asegurar que sea un array
        $onboardingData = $escuela->onboarding_data;
        if (is_string($onboardingData)) {
            $onboardingData = json_decode($onboardingData, true) ?? [];
        } elseif (is_null($onboardingData)) {
            $onboardingData = [];
        }

        // Actualizar datos de la escuela
        $escuela->update([
            'cct' => $request->cct,
            'rfc' => $request->rfc,
            'email' => $request->email_escuela,
            'telefono' => $request->telefono,
            'codigo_postal' => $request->codigo_postal,
            'onboarding_data' => array_merge(
                $onboardingData,
                ['paso_actual' => 'estructura']
            ),
        ]);

        return response()->json([
            'message' => 'Datos actualizados correctamente',
            'data' => $escuela->fresh(),
        ]);
    }

    /**
     * Completar estructura académica básica (Paso 2)
     */
    public function completeStructure(Request $request): JsonResponse
    {
        $escuela = $request->user()->escuela;

        // Obtener onboarding_data y asegurar que sea un array
        $onboardingData = $escuela->onboarding_data;
        if (is_string($onboardingData)) {
            $onboardingData = json_decode($onboardingData, true) ?? [];
        } elseif (is_null($onboardingData)) {
            $onboardingData = [];
        }

        // Marcar paso de estructura como completado
        $escuela->update([
            'onboarding_data' => array_merge(
                $onboardingData,
                ['paso_actual' => 'completado']
            ),
        ]);

        return response()->json([
            'message' => 'Estructura configurada',
            'data' => $escuela->fresh(),
        ]);
    }

    /**
     * Marcar onboarding como completado
     */
    public function complete(Request $request): JsonResponse
    {
        $escuela = $request->user()->escuela;

        // Obtener onboarding_data y asegurar que sea un array
        $onboardingData = $escuela->onboarding_data;
        if (is_string($onboardingData)) {
            $onboardingData = json_decode($onboardingData, true) ?? [];
        } elseif (is_null($onboardingData)) {
            $onboardingData = [];
        }

        $escuela->update([
            'onboarding_completado' => true,
            'onboarding_completado_at' => now(),
            'onboarding_data' => array_merge(
                $onboardingData,
                ['paso_actual' => 'dashboard']
            ),
        ]);

        return response()->json([
            'message' => '¡Onboarding completado! Bienvenido a School Manager',
            'data' => $escuela->fresh(),
        ]);
    }

    /**
     * Saltar onboarding (permite completarlo después)
     */
    public function skip(Request $request): JsonResponse
    {
        $escuela = $request->user()->escuela;

        // Obtener onboarding_data y asegurar que sea un array
        $onboardingData = $escuela->onboarding_data;
        if (is_string($onboardingData)) {
            $onboardingData = json_decode($onboardingData, true) ?? [];
        } elseif (is_null($onboardingData)) {
            $onboardingData = [];
        }

        $escuela->update([
            'onboarding_data' => array_merge(
                $onboardingData,
                [
                    'skipped' => true,
                    'skipped_at' => now()->toIso8601String(),
                ]
            ),
        ]);

        return response()->json([
            'message' => 'Onboarding saltado. Puedes completarlo desde Configuración',
        ]);
    }
}
