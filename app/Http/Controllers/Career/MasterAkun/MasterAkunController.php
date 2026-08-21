<?php

namespace App\Http\Controllers\Career\MasterAkun;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER AKUN (Users: pengguna/pelamar + admin/superadmin).
 * SPA + WEB. CRUD Query Builder + ResponseHelper + Log channel. Password di-Hash, id di-Hashids.
 */
class MasterAkunController extends Controller
{
    /** Umur tautan verifikasi. Disamakan dengan AuthController::VERIF_BERLAKU_MENIT. */
    private const VERIF_BERLAKU_MENIT = 30;

    public function index()
    {
        return Inertia::render('Career/admin/master-akun/masterAkun', CareerShell::props('/master-akun', 'Master Akun'));
    }

    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Users as u')
                ->leftJoin('N_WEB_CAREERS_Users as c', 'c.Id_Users', '=', 'u.Created_By_Id')
                ->orderByDesc('u.Id_Users')
                ->select('u.*', 'c.Nama as Pembuat')
                ->get()
                ->map(fn ($r) => [
                    'id' => Hashids::encode($r->Id_Users),
                    'nama' => $r->Nama,
                    'email' => $r->Email,
                    'phone' => $r->No_Hp,
                    'role' => $r->Role,
                    'klasifikasi' => $r->Klasifikasi,
                    'status' => $r->Status,
                    'mulai_berlaku' => $r->Mulai_Berlaku,
                    'valid_until' => $r->Valid_Until,
                    'last_login_at' => $r->Last_Login_At ?? null,
                    // ── KEADAAN VERIFIKASI EMAIL ────────────────────────────
                    //
                    // `verifSentAt` bukan sekadar hiasan: kolom itu HANYA ditulis
                    // setelah server surat menjawab TERKIRIM (WcSyncEmailJob).
                    // Jadi `verifAttempt > 0` dengan `verifSentAt` kosong berarti
                    // "sudah dicoba sekian kali, tidak satu pun pernah keluar" —
                    // keadaan yang selama ini hanya terbaca di log job, dan tidak
                    // pernah sampai ke orang yang bisa menindaklanjutinya.
                    'emailVerified' => ($r->Flag_Email_Verified ?? 'T') === 'Y',
                    'emailVerifiedAt' => $r->Email_Verified_At ?? null,
                    'verifSentAt' => $r->Email_Verif_Sent_At ?? null,
                    'verifExpiredAt' => $r->Email_Verif_Expired_At ?? null,
                    'verifAttempt' => (int) ($r->Email_Verif_Attempt ?? 0),
                    'createdBy' => $r->Pembuat ?: $r->Created_By,
                    'createdAt' => $r->Created_At,
                ])
                ->values();

            return ResponseHelper::success($rows, 'Data akun dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat akun: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data akun', 500);
        }
    }

    /** Hitung Valid_Until dari klasifikasi (Durasi_Hari NULL = permanen). */
    private function hitungValidUntil(string $kode, Carbon $mulai): ?string
    {
        $klas = DB::table('N_WEB_CAREERS_Klasifikasi_Akun')->where('Kode', $kode)->first();
        if ($klas && $klas->Durasi_Hari !== null) {
            return $mulai->copy()->addDays((int) $klas->Durasi_Hari)->toDateString();
        }

        return null;
    }

    private function rules(bool $create = true): array
    {
        return [
            'nama' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'password' => ($create ? 'required' : 'nullable') . '|string|min:6',
            'role' => 'required|in:KANDIDAT,ADMIN,SUPERADMIN',
            'klasifikasi' => 'required|string|max:40',
            'status' => 'required|in:AKTIF,NONAKTIF',
        ];
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules(true));
            if (DB::table('N_WEB_CAREERS_Users')->where('Email', $data['email'])->exists()) {
                return ResponseHelper::error('Email sudah digunakan akun lain.', 422);
            }
            $now = now();
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            DB::table('N_WEB_CAREERS_Users')->insert([
                'Nama' => $data['nama'],
                'Email' => $data['email'],
                'No_Hp' => $data['phone'] ?? null,
                'Password' => Hash::make($data['password']),
                'Role' => $data['role'],
                'Klasifikasi' => $data['klasifikasi'],
                'Status' => $data['status'],
                'Mulai_Berlaku' => $now->toDateString(),
                'Valid_Until' => $this->hitungValidUntil($data['klasifikasi'], $now),
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ]);

            Log::channel('web_career')->info("Akun dibuat ({$data['role']}) {$data['email']} oleh {$userName}");

            return ResponseHelper::success(null, 'Akun berhasil dibuat', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal buat akun: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan akun', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            $data = $request->validate($this->rules(false));
            if (DB::table('N_WEB_CAREERS_Users')->where('Email', $data['email'])->where('Id_Users', '!=', $realId)->exists()) {
                return ResponseHelper::error('Email sudah digunakan akun lain.', 422);
            }
            $now = now();
            $mulai = $row->Mulai_Berlaku ? Carbon::parse($row->Mulai_Berlaku) : $now;
            $update = [
                'Nama' => $data['nama'],
                'Email' => $data['email'],
                'No_Hp' => $data['phone'] ?? null,
                'Role' => $data['role'],
                'Klasifikasi' => $data['klasifikasi'],
                'Status' => $data['status'],
                'Valid_Until' => $this->hitungValidUntil($data['klasifikasi'], $mulai),
                'Updated_At' => $now, 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ];
            if (! empty($data['password'])) {
                $update['Password'] = Hash::make($data['password']);
            }
            DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->update($update);
            Log::channel('web_career')->info("Akun diperbarui #{$realId} ({$data['email']})");

            return ResponseHelper::success(null, 'Akun diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui akun', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');
            $terpengaruh = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->update([
                'Status' => $aktif ? 'AKTIF' : 'NONAKTIF',
                'Updated_At' => now(), 'Updated_By' => session('career_auth.nama', 'ADMIN'), 'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            Log::channel('web_career')->info("Akun #{$realId} status " . ($aktif ? 'AKTIF' : 'NONAKTIF'));

            return ResponseHelper::success(null, 'Status akun diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    /**
     * PATCH master-akun/{id}/kirim-verifikasi — kirim ULANG tautan verifikasi.
     *
     * ══ KENAPA DIKIRIM LANGSUNG, BUKAN LEWAT ANTREAN ══
     *
     * Registrasi memakai antrean, dan itu benar: kandidat tidak boleh menunggu
     * SMTP yang lambat, dan kegagalannya tidak boleh menggagalkan pendaftaran.
     * Tapi konsekuensinya, alasan gagalnya hanya mendarat di log job — dan
     * akun seperti yang memicu pintu ini justru mencatat `Email_Verif_Attempt`
     * sampai enam kali dengan `Email_Verif_Sent_At` yang tak pernah terisi:
     * enam kali dicoba, tidak sekali pun keluar, tanpa sepatah kata pun sampai
     * ke orang yang bisa membetulkannya.
     *
     * Di sini keadaannya terbalik. Yang menekan tombol adalah admin yang SEDANG
     * menyelidiki kegagalan itu; ia sanggup menunggu dua detik, dan yang paling
     * ia butuhkan justru kalimat galat aslinya — "Connection could not be
     * established", "535 Authentication failed" — bukan "sedang diproses".
     * Karena itu `dispatchSync`: job yang sama, logika yang sama, tapi
     * lemparannya sampai ke layar alih-alih tenggelam di log.
     *
     * TANPA THROTTLE, berbeda dari kirim-ulang publik yang menahan 2 menit.
     * Penahanan itu mencegah penyalahgunaan oleh orang asing; di sini ia justru
     * menghalangi satu-satunya orang yang sedang berusaha memperbaiki keadaan.
     */
    public function kirimVerifikasi($id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->first();

        if (! $row) {
            return ResponseHelper::error('Akun tidak ditemukan.', 404);
        }

        if (($row->Flag_Email_Verified ?? 'T') === 'Y') {
            return ResponseHelper::error('Email akun ini sudah terverifikasi.', 422);
        }

        $adminNama = session('career_auth.nama', 'ADMIN');
        $adminId = session('career_auth.id');

        // Token BARU tiap kali dikirim. Yang lama ikut hangus begitu barisnya
        // ditimpa — tautan basi di kotak masuk tidak boleh tetap berlaku.
        $token = Str::random(64);
        $now = Carbon::now();

        try {
            DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->update([
                'Email_Verif_Token' => hash('sha256', $token),
                'Email_Verif_Expired_At' => $now->copy()->addMinutes(self::VERIF_BERLAKU_MENIT),
                'Email_Verif_Attempt' => DB::raw('ISNULL(Email_Verif_Attempt, 0) + 1'),
                'Updated_At' => $now, 'Updated_By' => $adminNama, 'Updated_By_Id' => $adminId,
            ]);

            // Job yang sama dengan jalur registrasi — termasuk penulisan
            // Email_Verif_Sent_At setelah SMTP benar-benar menerima.
            \App\Jobs\Career\WcSyncEmailJob::dispatchSync(
                \App\Jobs\Career\WcSyncEmailJob::JENIS_VERIFIKASI,
                (int) $realId,
                ['token' => $token],
            );
        } catch (\Throwable $e) {
            Log::channel('web_career')->error(
                "[VERIF-ULANG] gagal ke {$row->Email} (akun #{$realId}, oleh {$adminNama}): " . $e->getMessage()
            );

            // Kalimat galat ASLI diteruskan apa adanya. Menggantinya dengan
            // "terjadi kesalahan" menghapus satu-satunya petunjuk yang dipunyai
            // admin, dan ia tidak punya akses ke log server.
            return ResponseHelper::error('Email gagal dikirim: ' . $e->getMessage(), 502);
        }

        // Dibaca ULANG, bukan diasumsikan: job sengaja berhenti diam-diam pada
        // beberapa keadaan (akun keburu terverifikasi, token kosong). Bila
        // kolomnya tidak bergerak, emailnya memang tidak keluar — dan itu harus
        // dikatakan, bukan dirayakan sebagai keberhasilan.
        $sesudah = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->value('Email_Verif_Sent_At');

        if (! $sesudah || Carbon::parse($sesudah)->lt($now)) {
            Log::channel('web_career')->warning("[VERIF-ULANG] akun #{$realId} ({$row->Email}) — job selesai tanpa mengirim.");

            return ResponseHelper::error(
                'Pengiriman tidak jadi dijalankan. Periksa log server: kemungkinan akun sudah terverifikasi di sela permintaan, atau konfigurasi email belum lengkap.',
                502
            );
        }

        Log::channel('web_career')->info("[VERIF-ULANG] terkirim ke {$row->Email} (akun #{$realId}, oleh {$adminNama}).");

        return ResponseHelper::success(
            ['verifSentAt' => $sesudah, 'berlakuMenit' => self::VERIF_BERLAKU_MENIT],
            "Tautan verifikasi terkirim ke {$row->Email}. Berlaku " . self::VERIF_BERLAKU_MENIT . ' menit.'
        );
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Akun tidak ditemukan.', 404);
            }
            if ($row->Role === 'SUPERADMIN' && DB::table('N_WEB_CAREERS_Users')->where('Role', 'SUPERADMIN')->count() <= 1) {
                return ResponseHelper::error('Tidak dapat menghapus Superadmin terakhir.', 422);
            }
            DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $realId)->delete();
            Log::channel('web_career')->info("Akun dihapus #{$realId} ({$row->Email})");

            return ResponseHelper::success(null, 'Akun dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus akun #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus akun', 500);
        }
    }
}
