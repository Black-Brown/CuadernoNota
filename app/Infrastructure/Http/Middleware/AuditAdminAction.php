<?php

namespace App\Infrastructure\Http\Middleware;

use App\Infrastructure\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminAction
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // ResetSystemData writes its audit record atomically with the deletion.
        if ($request->routeIs('admin.system.reset-data')) {
            return $response;
        }
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $response->getStatusCode() < 400) {
            $segments = $request->segments();
            $routeParameters = collect($request->route()?->parameters() ?? [])->filter(fn ($value) => is_numeric($value) || is_object($value));
            $record = $routeParameters->first();
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => strtolower($request->method()),
                'affected_table' => substr($segments[2] ?? 'admin', 0, 40),
                'record_id' => is_object($record) ? ($record->id ?? 0) : ((int) $record),
                'detail' => ['path' => $request->path(), 'input' => $request->except(['password', 'password_confirmation'])],
                'ip' => $request->ip(),
            ]);
        }
        return $response;
    }
}
