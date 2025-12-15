<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grupo extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'grupos';

    protected $fillable = [
        'escuela_id',
        'grado_id',
        'nombre',
        'capacidad_maxima',
        'maestro_id',
        'activo',
    ];

    protected $casts = [
        'capacidad_maxima' => 'integer',
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    public function maestro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'maestro_id');
    }
}
