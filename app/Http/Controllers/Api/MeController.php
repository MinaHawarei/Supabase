<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->attributes->get('supabase_user_id');
        $email = $request->attributes->get('supabase_user_email');

        return response()->json([
            'user_id' => $userId,
            'email' => $email,
        ]);
    }
}
