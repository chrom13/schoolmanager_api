<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escuela extends Model
{
    use HasFactory;

    protected $table = 'escuelas';

    protected $fillable = [
        'nombre',
        'slug',
        'cct',
        'rfc',
        'razon_social',
        'email',
        'telefono',
        'codigo_postal',
        'regimen_fiscal',
        'stripe_account_id',
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
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }
}
