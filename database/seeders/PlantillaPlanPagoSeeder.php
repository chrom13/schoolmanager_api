<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlantillaPlanPago;
use App\Models\ConceptoPlantilla;
use App\Models\ConceptoPrecio;
use App\Models\Nivel;

class PlantillaPlanPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener los niveles educativos
        $niveles = Nivel::all();

        foreach ($niveles as $nivel) {
            // Crear plantilla general para cada nivel
            $plantilla = PlantillaPlanPago::create([
                'escuela_id' => null, // null = plantilla del sistema
                'nivel_id' => $nivel->id,
                'nombre' => "Plan General " . ucfirst($nivel->nombre),
                'descripcion' => "Plantilla predefinida del sistema para {$nivel->nombre}. Incluye inscripción, 10 colegiaturas mensuales y 3 derechos a examen.",
                'es_sistema' => true,
                'activo' => true,
            ]);

            $orden = 1;

            // 1. Inscripción (Julio)
            $inscripcion = ConceptoPlantilla::create([
                'plantilla_plan_pago_id' => $plantilla->id,
                'concepto' => 'Inscripción',
                'descripcion' => 'Inscripción anual',
                'orden' => $orden++,
                'tipo_concepto' => 'inscripcion',
                'mes_relativo' => 7, // Julio
                'monto_sugerido' => 0,
                'dia_vencimiento' => 15,
                'activo' => true,
            ]);

            // Crear precios escalonados para inscripción
            // Descuento: 5% si pagas 5 días antes del vencimiento
            ConceptoPrecio::create([
                'concepto_type' => ConceptoPlantilla::class,
                'concepto_id' => $inscripcion->id,
                'tipo' => 'dias_vencimiento',
                'desde_dias' => -999, // Desde mucho antes
                'hasta_dias' => -6, // Hasta 6 días antes
                'monto' => 0, // El monto se calcula: base * (1 - 5%)
                'descripcion' => 'Descuento 5% por pago anticipado',
                'orden' => 1,
            ]);

            // Precio normal: del día 5 antes al vencimiento
            ConceptoPrecio::create([
                'concepto_type' => ConceptoPlantilla::class,
                'concepto_id' => $inscripcion->id,
                'tipo' => 'dias_vencimiento',
                'desde_dias' => -5,
                'hasta_dias' => 0,
                'monto' => 0, // Monto base
                'descripcion' => 'Precio regular',
                'orden' => 2,
            ]);

            // Recargo: 10% si pagas después de 10 días del vencimiento
            ConceptoPrecio::create([
                'concepto_type' => ConceptoPlantilla::class,
                'concepto_id' => $inscripcion->id,
                'tipo' => 'dias_vencimiento',
                'desde_dias' => 11,
                'hasta_dias' => 999,
                'monto' => 0, // El monto se calcula: base * (1 + 10%)
                'descripcion' => 'Recargo 10% por mora',
                'orden' => 3,
            ]);

            // 2-11. Colegiaturas mensuales (Agosto a Junio - 10 meses)
            $meses = [
                8 => 'Agosto',
                9 => 'Septiembre',
                10 => 'Octubre',
                11 => 'Noviembre',
                12 => 'Diciembre',
                1 => 'Enero',
                2 => 'Febrero',
                3 => 'Marzo',
                4 => 'Abril',
                5 => 'Mayo',
            ];

            foreach ($meses as $mes => $nombreMes) {
                $colegiatura = ConceptoPlantilla::create([
                    'plantilla_plan_pago_id' => $plantilla->id,
                    'concepto' => "Colegiatura {mes}", // {mes} se reemplazará dinámicamente
                    'descripcion' => "Colegiatura del mes de {mes}",
                    'orden' => $orden++,
                    'tipo_concepto' => 'colegiatura',
                    'mes_relativo' => $mes,
                    'monto_sugerido' => 0,
                    'dia_vencimiento' => 10, // Vence el día 10 de cada mes
                    'activo' => true,
                ]);

                // Crear precios escalonados para cada colegiatura
                // Descuento: 5% si pagas 5 días antes del vencimiento
                ConceptoPrecio::create([
                    'concepto_type' => ConceptoPlantilla::class,
                    'concepto_id' => $colegiatura->id,
                    'tipo' => 'dias_vencimiento',
                    'desde_dias' => -999,
                    'hasta_dias' => -6,
                    'monto' => 0,
                    'descripcion' => 'Descuento 5% por pago anticipado',
                    'orden' => 1,
                ]);

                // Precio normal
                ConceptoPrecio::create([
                    'concepto_type' => ConceptoPlantilla::class,
                    'concepto_id' => $colegiatura->id,
                    'tipo' => 'dias_vencimiento',
                    'desde_dias' => -5,
                    'hasta_dias' => 0,
                    'monto' => 0,
                    'descripcion' => 'Precio regular',
                    'orden' => 2,
                ]);

                // Recargo: 10% si pagas después de 10 días del vencimiento
                ConceptoPrecio::create([
                    'concepto_type' => ConceptoPlantilla::class,
                    'concepto_id' => $colegiatura->id,
                    'tipo' => 'dias_vencimiento',
                    'desde_dias' => 11,
                    'hasta_dias' => 999,
                    'monto' => 0,
                    'descripcion' => 'Recargo 10% por mora',
                    'orden' => 3,
                ]);
            }

            // 12-14. Derechos a Examen (según periodos escolares típicos)
            $examenes = [
                ['nombre' => 'Derecho a Examen 1er Periodo', 'mes' => 11], // Noviembre
                ['nombre' => 'Derecho a Examen 2do Periodo', 'mes' => 2],  // Febrero
                ['nombre' => 'Derecho a Examen 3er Periodo', 'mes' => 5],  // Mayo
            ];

            foreach ($examenes as $examen) {
                $conceptoExamen = ConceptoPlantilla::create([
                    'plantilla_plan_pago_id' => $plantilla->id,
                    'concepto' => $examen['nombre'],
                    'descripcion' => 'Derecho a presentar examen del periodo',
                    'orden' => $orden++,
                    'tipo_concepto' => 'examen',
                    'mes_relativo' => $examen['mes'],
                    'monto_sugerido' => 0,
                    'dia_vencimiento' => 5, // Vence el día 5 del mes
                    'activo' => true,
                ]);

                // Para exámenes solo creamos precio regular (sin descuentos ni recargos)
                ConceptoPrecio::create([
                    'concepto_type' => ConceptoPlantilla::class,
                    'concepto_id' => $conceptoExamen->id,
                    'tipo' => 'dias_vencimiento',
                    'desde_dias' => -999,
                    'hasta_dias' => 999,
                    'monto' => 0,
                    'descripcion' => 'Precio único',
                    'orden' => 1,
                ]);
            }
        }

        $this->command->info('✅ Plantillas de planes de pago del sistema creadas exitosamente');
    }
}
