<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grado extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'grados';

    protected $fillable = [
        'escuela_id',
        'nivel_id',
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }
}
