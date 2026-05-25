<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $claims = $request->attributes->get('auth.claims', []);
        $empresaId = $request->attributes->get('auth.empresa_id');

        return response()->json([
            'sub' => $claims['sub'] ?? null,
            'email' => $claims['email'] ?? null,
            'token_use' => $claims['token_use'] ?? null,
            'empresa_id' => $empresaId,
            'claims' => $claims,
        ]);
    }
}
