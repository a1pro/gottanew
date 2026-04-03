<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Allowed origins
     */
    protected array $allowedOrigins = [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:4040',
        'https://f177-122-173-28-222.ngrok-free.app',
        'https://gotta.a1professionals.net',
        'https://gottaweb.a1professionals.net',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $isAllowedOrigin = $origin && in_array($origin, $this->allowedOrigins, true);

        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);

            if ($isAllowedOrigin) {
                $this->applyCorsHeaders($response, $origin);
            }

            return $response;
        }

        /** @var Response $response */
        $response = $next($request);

        if ($isAllowedOrigin) {
            $this->applyCorsHeaders($response, $origin);
        }

        return $response;
    }

    protected function applyCorsHeaders(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN, Cache-Control, Last-Event-ID');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', 'Cache-Control, Content-Type');
        $response->headers->set('Vary', 'Origin');
    }
}
