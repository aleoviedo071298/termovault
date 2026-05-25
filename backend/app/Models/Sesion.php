<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sesion extends Model
{
    public const CREATED_AT = 'login_at';

    public const UPDATED_AT = null;

    protected $table = 'sesiones';

    protected $fillable = [
        'usuario_id',
        'ip',
        'user_agent',
        'logout_at',
    ];

    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'logout_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
