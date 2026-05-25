<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspeccion extends Model
{
    use HasFactory;

    protected $fillable = [
        'elemento_id',
        'tecnico_id',
        'fecha_inspeccion',
        'cuadrilla',
        'integrantes',
        'empresa_contratista',
        'temperatura_ambiente',
        'humedad_relativa',
        'carga_pct',
        'condiciones_clima',
        'lat_gps',
        'lng_gps',
        'resumen',
        'estado',
        'revisada_por',
        'fecha_revision',
        'observaciones_revisor',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inspeccion' => 'datetime',
            'fecha_revision' => 'datetime',
            'temperatura_ambiente' => 'decimal:1',
            'humedad_relativa' => 'decimal:1',
            'carga_pct' => 'decimal:1',
            'lat_gps' => 'decimal:7',
            'lng_gps' => 'decimal:7',
        ];
    }

    public function scopeForEmpresa(Builder $query, int|string|null $empresaId): Builder
    {
        return $query->whereHas('elemento', fn (Builder $query) => $query->forEmpresa($empresaId));
    }

    public function elemento(): BelongsTo
    {
        return $this->belongsTo(Elemento::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'revisada_por');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class);
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class);
    }
}
