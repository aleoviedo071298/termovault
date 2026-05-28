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

    public const ESTADO_ENVIADA = 'enviada';
    public const ESTADO_REVISADA = 'revisada';
    public const ESTADO_CERRADA = 'cerrada';

    public static function getEstados(): array
    {
        return [
            self::ESTADO_ENVIADA,
            self::ESTADO_REVISADA,
            self::ESTADO_CERRADA,
        ];
    }

    protected $table = 'inspecciones';

    protected $fillable = [
        'elemento_id',
        'tecnico_id',
        'fecha_inspeccion',
        'cuadrilla',
        'integrantes',
        'empresa_contratista',
        'condiciones_clima',
        'resumen',
        'estado',
        'created_by',
        'updated_by',
        'revisada_por',
        'cerrada_por',
        'fecha_revision',
        'fecha_cierre',
        'observaciones_revisor',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inspeccion' => 'datetime',
            'fecha_revision' => 'datetime',
            'fecha_cierre' => 'datetime',
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

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrada_por');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'updated_by');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class);
    }

    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class);
    }
}
