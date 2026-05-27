<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Usuario extends Model
{
    use BelongsToEmpresa;
    use HasFactory;

    protected $table = 'usuarios';

    protected $hidden = [
        'password_hash',
    ];

    protected $fillable = [
        'empresa_id',
        'rol_id',
        'nombre',
        'apellido',
        'email',
        'password_hash',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    public function yacimientos(): BelongsToMany
    {
        return $this->belongsToMany(Yacimiento::class, 'usuario_yacimientos');
    }

    public function inspecciones(): HasMany
    {
        return $this->hasMany(Inspeccion::class, 'tecnico_id');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(Inspeccion::class, 'revisada_por');
    }

    public function cierres(): HasMany
    {
        return $this->hasMany(Inspeccion::class, 'cerrada_por');
    }
}
