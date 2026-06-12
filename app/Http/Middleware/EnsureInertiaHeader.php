<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInertiaHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Forcefully ensure X-Inertia header is present for Inertia payloads
        if ($request->header('X-Inertia')) {
            $content = $response->getContent();
            
            if (is_string($content) && str_starts_with($content, '{"component":')) {
                $response->headers->set('X-Inertia', 'true');
                $response->headers->set('Content-Type', 'application/json');
                $response->headers->set('Access-Control-Expose-Headers', 'X-Inertia, X-Inertia-Location');
            }
        }

        return $response;
    }
}
