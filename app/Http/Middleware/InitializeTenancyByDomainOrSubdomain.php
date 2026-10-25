<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByDomainOrSubdomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            return $next($request);
        }

        $host = mb_strtolower($request->getHost());
        /** @var array<int, string> $centralDomains */
        $centralDomains = array_map('mb_strtolower', config('tenancy.central_domains', ['127.0.0.1', 'localhost']));

        if (in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        $candidates = [$host];

        foreach ($centralDomains as $centralDomain) {
            $suffix = '.'.$centralDomain;
            if (Str::endsWith($host, $suffix)) {
                $subdomain = Str::beforeLast($host, $suffix);
                if ($subdomain !== '' && ! str_contains($subdomain, '.')) {
                    $candidates[] = $subdomain;
                }
            }
        }

        /** @var Domain|null $domainRecord */
        $domainRecord = Domain::query()
            ->with('tenant')
            ->whereIn('domain', array_values(array_unique($candidates)))
            ->first();

        if (! $domainRecord || ! $domainRecord->tenant instanceof Tenant) {
            abort(404);
        }

        if (! $domainRecord->tenant->is_active) {
            abort(403, __('This client account is currently inactive.'));
        }

        tenancy()->initialize($domainRecord->tenant);

        return $next($request);
    }
}
