<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomCors
{
    public function handle(Request $request, Closure $next)
    {
        // Add CORS headers
        $response = $next($request);

        // Allow all origins or specify origins
        $response->headers->set('Access-Control-Allow-Origin', '*');  // Use specific origins for better security
        
        // Allow specific HTTP methods (GET, POST, PUT, DELETE, etc.)
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');

        // Allow specific headers (if needed)
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization, Origin, Accept');

        // Allow credentials if required
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        // Cache for pre-flight requests (OPTIONS)
        if ($request->getMethod() == "OPTIONS") {
            $response->setStatusCode(200);
        }

        return $response;
    }
}
