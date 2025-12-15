<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nivel extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'niveles';

    protected $fillable = [
        'escuela_id',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function grados(): HasMany
    {
        return $this->hasMany(Grado::class);
    }
}
