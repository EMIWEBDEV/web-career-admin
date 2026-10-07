<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel admin TIDAK untuk mesin pencari: setiap jawaban membawa
 * `X-Robots-Tag: noindex, nofollow`, sehingga halaman masuk dan apa pun yang
 * terlanjur ditautkan dari luar tidak muncul di hasil pencarian.
 *
 * Header, bukan robots.txt: robots.txt yang menutup perayapan justru membuat
 * perayap tidak pernah melihat larangan indeksnya.
 */
class TanpaIndeks
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
