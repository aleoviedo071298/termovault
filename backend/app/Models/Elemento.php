<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Elemento extends Model
{
    use HasFactory;

    protected $fillable = [
        'yacimiento_id',
        'tipo_elemento_id',
        'funcion',
        'nivel_tension_id',
        'nombre',
        'codigo',
        'marca',
        'modelo',
        'n_serie',
        'criticidad_id',
        'estado_operativo',
        'observaciones_generales',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function scopeForEmpresa(Builder $query, int|string|null $empresaId): Builder
    {
        if ($empresaId === null || $empresaId === '') {
            return $query;
        }

        return $query->whereHas('yacimiento', fn (Builder $query) => $query->where('empresa_id', (int) $empresaId));
    }

    public function yacimiento(): BelongsTo
    {
        return $this->belongsTo(Yacimiento::class);
    }

    public function tipoElemento(): BelongsTo
    {
        return $this->belongsTo(TipoElemento::class);
    }

    public function nivelTension(): BelongsTo
    {
        return $this->belongsTo(NivelTension::class);
    }

    public function criticidad(): BelongsTo
    {
        return $this->belongsTo(Criticidad::class);
    }

    public function inspecciones(): HasMany
    {
        return $this->hasMany(Inspeccion::class);
    }
}
