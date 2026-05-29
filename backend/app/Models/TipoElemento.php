<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoElemento extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'tipos_elemento';

    protected $fillable = [
        'codigo',
        'nombre',
        'prefijo_archivo',
        'requiere_tension',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'requiere_tension' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function elementos(): HasMany
    {
        return $this->hasMany(Elemento::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(fn() => \Illuminate\Support\Facades\Cache::forget('catalogs.static'));
        static::deleted(fn() => \Illuminate\Support\Facades\Cache::forget('catalogs.static'));
    }
}
