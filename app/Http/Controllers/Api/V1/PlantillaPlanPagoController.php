<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlantillaPlanPago;
use App\Models\ConceptoPlantilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlantillaPlanPagoController extends Controller
{
    /**
     * Lista todas las plantillas disponibles para la escuela
     * Incluye plantillas del sistema y plantillas personalizadas de la escuela
     */
    public function index(Request $request): JsonResponse
    {
        $escuelaId = Auth::user()->escuela_id;

        $query = PlantillaPlanPago::with(['nivel', 'conceptos.precios'])
            ->forEscuela($escuelaId);

        // Filtrar por nivel
        if ($request->has('nivel_id')) {
            $query->where('nivel_id', $request->nivel_id);
        }

        // Filtrar por tipo
        if ($request->has('solo_sistema')) {
            $query->sistema();
        } elseif ($request->has('solo_personalizadas')) {
            $query->personalizadas($escuelaId);
        }

        // Filtrar por estado activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $plantillas = $query->get();

        return response()->json([
            'data' => $plantillas
        ]);
    }

    /**
     * Mostrar una plantilla específica
     */
    public function show(PlantillaPlanPago $plantillaPlanPago): JsonResponse
    {
        $plantillaPlanPago->load(['nivel', 'conceptos.precios']);

        return response()->json([
            'data' => $plantillaPlanPago
        ]);
    }

    /**
     * Crear nueva plantilla personalizada
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nivel_id' => ['required', 'exists:niveles,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'conceptos' => ['required', 'array', 'min:1'],
            'conceptos.*.concepto' => ['required', 'string'],
            'conceptos.*.tipo_concepto' => ['required', 'in:inscripcion,colegiatura,examen,otro'],
            'conceptos.*.orden' => ['required', 'integer'],
            'conceptos.*.mes_relativo' => ['nullable', 'integer', 'min:1', 'max:12'],
            'conceptos.*.monto_sugerido' => ['nullable', 'numeric', 'min:0'],
            'conceptos.*.dia_vencimiento' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        $escuelaId = Auth::user()->escuela_id;

        // Crear la plantilla
        $plantilla = PlantillaPlanPago::create([
            'escuela_id' => $escuelaId,
            'nivel_id' => $request->nivel_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'es_sistema' => false,
            'activo' => true,
        ]);

        // Crear los conceptos
        foreach ($request->conceptos as $conceptoData) {
            ConceptoPlantilla::create([
                'plantilla_plan_pago_id' => $plantilla->id,
                ...$conceptoData
            ]);
        }

        return response()->json([
            'message' => 'Plantilla creada exitosamente',
            'data' => $plantilla->load(['nivel', 'conceptos.precios'])
        ], 201);
    }

    /**
     * Actualizar plantilla personalizada
     */
    public function update(Request $request, PlantillaPlanPago $plantillaPlanPago): JsonResponse
    {
        $escuelaId = Auth::user()->escuela_id;

        // Verificar que no sea plantilla del sistema
        if ($plantillaPlanPago->es_sistema) {
            return response()->json([
                'message' => 'No se pueden editar las plantillas del sistema'
            ], 403);
        }

        // Verificar que pertenezca a la escuela
        if ($plantillaPlanPago->escuela_id !== $escuelaId) {
            return response()->json([
                'message' => 'No tienes permiso para editar esta plantilla'
            ], 403);
        }

        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $plantillaPlanPago->update($request->only(['nombre', 'descripcion', 'activo']));

        return response()->json([
            'message' => 'Plantilla actualizada exitosamente',
            'data' => $plantillaPlanPago
        ]);
    }

    /**
     * Eliminar plantilla personalizada
     */
    public function destroy(PlantillaPlanPago $plantillaPlanPago): JsonResponse
    {
        $escuelaId = Auth::user()->escuela_id;

        // Verificar que no sea plantilla del sistema
        if ($plantillaPlanPago->es_sistema) {
            return response()->json([
                'message' => 'No se pueden eliminar las plantillas del sistema'
            ], 403);
        }

        // Verificar que pertenezca a la escuela
        if ($plantillaPlanPago->escuela_id !== $escuelaId) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar esta plantilla'
            ], 403);
        }

        $plantillaPlanPago->delete();

        return response()->json([
            'message' => 'Plantilla eliminada exitosamente'
        ]);
    }
}
