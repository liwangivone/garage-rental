<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Hanya penyewa yang pendaftarannya sudah diterima admin yang boleh menyewa. */
class EnsureApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->isApproved()) {
            return redirect()->route('penyewa.dashboard')
                ->with('error', 'Akun Anda belum diverifikasi admin, jadi belum bisa menyewa garasi.');
        }

        return $next($request);
    }
}
