<?php
declare(strict_types=1);
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class SecurityHeaders
{
 public function handle(Request $request,Closure $next):Response{$response=$next($request);$response->headers->set('X-Frame-Options','DENY');$response->headers->set('X-Content-Type-Options','nosniff');$response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');$response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=()');$response->headers->set('Content-Security-Policy',"default-src 'self'; script-src 'self' 'unsafe-inline' http://localhost:5173; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net data:; img-src 'self' data:; connect-src 'self' http://localhost:5173 ws://localhost:5173");return $response;}
}
