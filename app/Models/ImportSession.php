<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportSession extends Model
{
    use HasFactory;

    protected $table = 'import_sessions';

    // Estados del wizard
    public const STATUS_PENDING        = 'pending';
    public const STATUS_MAPPED         = 'mapped';
    public const STATUS_PREVIEWED      = 'previewed';
    public const STATUS_PLAN_ASSIGNED  = 'plan_assigned';
    public const STATUS_CONCILIATED    = 'conciliated';
    public const STATUS_CONFIRMED      = 'confirmed';
    public const STATUS_IMPORTED       = 'imported';
    public const STATUS_CANCELLED      = 'cancelled';

    // Estados terminales (no se puede retomar)
    public const TERMINAL_STATUSES = [
        self::STATUS_IMPORTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'escuela_id',
        'user_id',
        'ciclo_escolar_id',
        'archivo_path',
        'archivo_nombre_original',
        'hoja_seleccionada',
        'hojas_disponibles',
        'mapeo_columnas',
        'alumnos_parseados',
        'grupos_a_crear',
        'plan_general_id',
        'planes_por_alumno',
        'morosos',
        'status',
        'confirmado_at',
        'confirmado_by',
    ];

    protected $casts = [
        'hojas_disponibles' => 'array',
        'mapeo_columnas'    => 'array',
        'alumnos_parseados' => 'array',
        'grupos_a_crear'    => 'array',
        'planes_por_alumno' => 'array',
        'morosos'           => 'array',
        'confirmado_at'     => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cicloEscolar(): BelongsTo
    {
        return $this->belongsTo(CicloEscolar::class);
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    /**
     * Limpia todos los campos downstream y resetea status a pending.
     * Se llama cuando el usuario cambia la hoja o el ciclo escolar.
     */
    public function resetDownstream(): void
    {
        $this->update([
            'mapeo_columnas'    => null,
            'alumnos_parseados' => null,
            'grupos_a_crear'    => null,
            'plan_general_id'   => null,
            'planes_por_alumno' => null,
            'morosos'           => null,
            'status'            => self::STATUS_PENDING,
        ]);
    }

    /**
     * Devuelve el plan_pago_id asignado a un alumno (override o plan general).
     */
    public function planParaAlumno(string $tempId): ?int
    {
        if ($this->planes_por_alumno) {
            foreach ($this->planes_por_alumno as $override) {
                if ($override['temp_id'] === $tempId) {
                    return (int) $override['plan_pago_id'];
                }
            }
        }

        return $this->plan_general_id ? (int) $this->plan_general_id : null;
    }

    /**
     * Devuelve los concepto_plan_pago_ids marcados como pendientes para un alumno.
     * Si el alumno no está en morosos, retorna array vacío (todo al corriente).
     */
    public function conceptosPendientesDeAlumno(string $tempId): array
    {
        if (!$this->morosos) {
            return [];
        }

        $porAlumno = $this->morosos['por_alumno'] ?? [];
        foreach ($porAlumno as $entrada) {
            if ($entrada['temp_id'] === $tempId) {
                return $entrada['concepto_plan_pago_ids'] ?? [];
            }
        }

        return [];
    }
}
