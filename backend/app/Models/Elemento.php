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
        'created_by',
        'updated_by',
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

    /**
     * Scope to filter elementos by yacimiento IDs.
     *
     * Validates all IDs are integers to prevent SQL injection from dynamic whereIn.
     * This scope ensures parameterized queries are used even if called with untrusted input.
     *
     * @param Builder $query
     * @param array $yacimientoIds Array of yacimiento IDs to filter by
     * @return Builder
     * @throws \InvalidArgumentException if any ID is not an integer
     */
    public function scopeByYacimientoIds(Builder $query, array $yacimientoIds): Builder
    {
        if (empty($yacimientoIds)) {
            return $query;
        }

        // Validate all IDs are integers to prevent SQL injection
        foreach ($yacimientoIds as $id) {
            if (! is_int($id) || $id <= 0) {
                throw new \InvalidArgumentException('All yacimiento IDs must be positive integers.');
            }
        }

        return $query->whereIn('yacimiento_id', $yacimientoIds);
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

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'updated_by');
    }
}
