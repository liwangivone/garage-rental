<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePenyewa
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPenyewa(), 403, 'Halaman ini khusus penyewa.');

        return $next($request);
    }
}
