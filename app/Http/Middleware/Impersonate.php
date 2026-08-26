<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Impersonate
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->impersonated_by) {
            $request->merge([
                'is_impersonating' => true,
                'impersonated_by_admin_id' => $user->impersonated_by
            ]);

            $response = $next($request);
            $response->headers->set('X-Impersonating', 'true');
            
            return $response;
        }

        return $next($request);
    }
}
