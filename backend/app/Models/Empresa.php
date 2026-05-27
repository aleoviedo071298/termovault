<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'plan',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function yacimientos(): HasMany
    {
        return $this->hasMany(Yacimiento::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }
}
