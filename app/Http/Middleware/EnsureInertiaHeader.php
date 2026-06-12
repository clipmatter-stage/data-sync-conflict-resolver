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

        // If this is an Inertia request and the response is missing the X-Inertia header,
        // but the content is clearly an Inertia JSON payload, forcefully restore the header.
        if ($request->header('X-Inertia') && !$response->headers->has('X-Inertia')) {
            $content = $response->getContent();
            
            if (is_string($content) && str_starts_with($content, '{"component":')) {
                $response->headers->set('X-Inertia', 'true');
                $response->headers->set('Content-Type', 'application/json');
            }
        }

        return $response;
    }
}
