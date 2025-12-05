<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApplySupabaseRls
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->attributes->get('supabase_user_id');

        if ($userId) {
            // Set the config variable that RLS policies will use (auth.uid() maps to this in Supabase/Postgres w/ extensions)
            // Or explicitly matches what we configured in our custom policies or user request: 'request.jwt.claim.sub'
            DB::statement("SELECT set_config('request.jwt.claim.sub', ?, true)", [$userId]);
        }

        return $next($request);
    }
}
