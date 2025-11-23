<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the origin from the request
        $origin = $request->headers->get('Origin');
        
        // Allowed origins - você pode configurar isso via .env se necessário
        $allowedOrigins = [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:5000',
            'http://127.0.0.1:5000',
            'http://172.18.0.3:3000',
            'http://172.18.0.3:5000',
            'http://172.18.0.3:8000',
            'http://172.18.0.4:3000',
            'http://172.18.0.4:5000',
            'http://172.18.0.4:8000',
            env('FRONTEND_URL', 'http://localhost:3000'),
        ];
        
        // In development, allow any origin from the Docker network or localhost
        $isDevelopment = env('APP_ENV', 'local') === 'local' || env('APP_DEBUG', false);
        
        // Use the request origin if it's allowed, or if in development and from Docker network/localhost
        if (in_array($origin, $allowedOrigins)) {
            $allowedOrigin = $origin;
        } elseif ($isDevelopment && $origin && (
            str_contains($origin, '172.18.0.') || 
            str_contains($origin, 'localhost') || 
            str_contains($origin, '127.0.0.1') ||
            str_contains($origin, '0.0.0.0')
        )) {
            $allowedOrigin = $origin;
        } else {
            $allowedOrigin = '*';
        }
        
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $headers = [
                'Access-Control-Allow-Origin' => $allowedOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Max-Age' => '3600',
            ];
            
            // Only add credentials header if not using wildcard
            if ($allowedOrigin !== '*') {
                $headers['Access-Control-Allow-Credentials'] = 'true';
            }
            
            return response('', 200)->withHeaders($headers);
        }

        $response = $next($request);

        // Add CORS headers to the response
        $headers = [
            'Access-Control-Allow-Origin' => $allowedOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
        ];
        
        // Only add credentials header if not using wildcard
        if ($allowedOrigin !== '*') {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }
        
        return $response->withHeaders($headers);
    }
}
