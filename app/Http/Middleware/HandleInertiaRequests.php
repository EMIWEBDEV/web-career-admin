<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props minimal (project Web Career).
     * Prop `layout` shell TIDAK lagi dibangun global — halaman admin Career
     * meng-inject `layout` + `auth` sendiri lewat controller.
     */
    public function share(Request $request): array
    {
        $user = Auth::user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->Id_Users ?? null,
                        'name' => (string) ($user->Username ?? $user->name ?? 'User'),
                        'username' => (string) ($user->Username ?? $user->name ?? 'User'),
                    ]
                    : null,
            ],
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
            ],
            // Sesi login kandidat/admin Web Career (real, DB N_WEB_CAREERS_Users)
            'careerAuth' => fn () => $request->session()->get('career_auth'),

            // [feat/feedback] Feedback pending untuk banner di portal kandidat
            // Hanya di-resolve untuk role KANDIDAT yang punya feedback MENUNGGU.
            'feedbackPending' => function () use ($request) {
                $auth = $request->session()->get('career_auth');
                if (! $auth || ($auth['role'] ?? '') !== 'KANDIDAT') return null;

                $item = \Illuminate\Support\Facades\DB::table('N_WEB_CAREERS_Feedback_Jawaban as fj')
                    ->join('N_WEB_CAREERS_Lamaran as l', 'fj.Lamaran_Id', '=', 'l.Id_Lamaran')
                    ->where('l.Id_Users', $auth['id'])
                    ->where('fj.Status_Pengisian', 'MENUNGGU')
                    ->where('fj.Flag_Cancellation', 'T')
                    ->whereIn('l.Hasil_Akhir', ['DITERIMA', 'DITOLAK'])
                    ->select('fj.Id_Feedback_Jawaban', 'fj.Token_Hash', 'l.Id_Lamaran', 'l.Hasil_Akhir')
                    ->first();

                if (! $item) return null;

                // Parse token: format "hashids.signature"
                $parts = explode('.', $item->Token_Hash, 2);
                $item->feedback_url = rtrim(config('app.url'), '/')
                    . '/feedback/' . ($parts[0] ?? '') . '/' . ($parts[1] ?? '');

                return $item;
            },
        ]);
    }
}
