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
        ]);
    }
}
