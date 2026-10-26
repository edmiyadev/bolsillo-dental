<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = mb_strtolower($request->getHost());
        /** @var array<int, string> $centralDomains */
        $centralDomains = array_map('mb_strtolower', config('tenancy.central_domains', ['127.0.0.1', 'localhost']));

        if (! in_array($host, $centralDomains, true)) {
            abort(404);
        }

        return $next($request);
    }
}
