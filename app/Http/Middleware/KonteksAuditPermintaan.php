<?php

namespace App\Http\Middleware;

use App\Support\Audit\KonteksAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Konteks audit per permintaan: siapa (akun staf di sesi), dari mana (IP),
 * rute mana, dan id permintaan — dibawa ke jejak perubahan data
 * (N_WEB_CAREERS_Audit_Perubahan) oleh KonteksAudit.
 *
 * Sumber ditentukan grup middleware-nya, BUKAN path: rute panel juga
 * ber-awalan api/v1 (grup web). web → PANEL; api (pemicu Cloud Scheduler,
 * dorong Pub/Sub, panggilan balik mesin) → TUGAS.
 *
 * Id permintaan memakai X-Cloud-Trace-Context bila ada, jadi satu jejak
 * audit bisa dicari langsung di Cloud Logging.
 */
class KonteksAuditPermintaan
{
    public function handle(Request $request, Closure $next, string $sumber = 'PANEL'): Response
    {
        $auth = $request->hasSession() ? $request->session()->get('career_auth') : null;
        $rute = $request->route();
        $nama = $rute && method_exists($rute, 'getName') ? $rute->getName() : null;

        KonteksAudit::atur([
            'sumber' => $sumber,
            'aktor_id' => is_array($auth) && ! empty($auth['id']) ? (int) $auth['id'] : null,
            'aktor' => is_array($auth) ? ($auth['email'] ?? null) : null,
            'konteks' => $request->method().' '.($nama ?: '/'.ltrim($request->path(), '/')),
            'ip' => $request->ip(),
            'permintaan' => self::idPermintaan($request),
        ]);

        return $next($request);
    }

    private static function idPermintaan(Request $request): string
    {
        $jejak = (string) $request->header('X-Cloud-Trace-Context', '');
        if (preg_match('/^([0-9a-f]{16,32})/i', $jejak, $m)) {
            return strtolower($m[1]);
        }

        return (string) Str::uuid();
    }
}
