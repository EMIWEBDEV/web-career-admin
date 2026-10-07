<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CareerRole;
use App\Jobs\Career\WcSyncEmailJob;
use App\Support\Audit\KonteksAudit;
use App\Support\Audit\RiwayatLogin;
use App\Support\Career\AksesService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * WEB CAREER — AUTH PANEL ADMIN (DB N_WEB_CAREERS_Users) memakai QUERY BUILDER.
 * Controller WEB, dipanggil via axios ke route web ber-prefix api/v1 → balas JSON.
 *
 * Hanya akun STAF (CareerRole::ADMIN) yang bisa masuk dan me-reset sandi di
 * sini. Kandidat mendaftar, memverifikasi surel, dan masuk di situs kandidat
 * (project web-careers-pengguna).
 */
class AuthController extends Controller
{
    private string $table = 'N_WEB_CAREERS_Users';

    private string $auditTable = 'N_WEB_CAREERS_Reset_Audit';

    /** Masa berlaku OTP reset kata sandi (menit) — sekali pakai. */
    private const RESET_OTP_BERLAKU_MENIT = 10;

    /** Jeda minimal antar-permintaan OTP reset (menit) — anti spam. */
    private const RESET_OTP_THROTTLE_MENIT = 2;

    /** Batas percobaan OTP salah — pada percobaan ke-N OTP dihanguskan. */
    private const RESET_OTP_MAX_ATTEMPT = 3;

    /** Batas jumlah permintaan OTP dalam satu window. */
    private const RESET_OTP_MAX_KIRIM = 5;

    /** Panjang window rate-limit permintaan OTP (menit). */
    private const RESET_OTP_WINDOW_MENIT = 60;

    /** Verifikasi token Cloudflare Turnstile ke server siteverify. Return true bila valid. */
    private function verifyTurnstile(string $secret, string $token, ?string $ip): bool
    {
        try {
            $res = Http::asForm()
                ->timeout(6)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            return $res->ok() && $res->json('success') === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Turnstile (opsional): bila secret dikonfigurasi, token WAJIB & harus lolos verifikasi.
        $secret = config('services.cloudflare.turnstile_secret');
        if (!empty($secret)) {
            $token = (string) $request->input('turnstile_token', '');
            if ($token === '' || !$this->verifyTurnstile($secret, $token, $request->ip())) {
                RiwayatLogin::catat($request, RiwayatLogin::GAGAL, null, $data['email'], 'CAPTCHA');

                return ResponseHelper::error('Verifikasi keamanan gagal. Selesaikan captcha lalu coba lagi.', 400);
            }
        }

        $row = DB::table($this->table)->where('Email', $data['email'])->first();

        if (!$row || !Hash::check($data['password'], $row->Password)) {
            RiwayatLogin::catat($request, RiwayatLogin::GAGAL, $row ? (int) $row->Id_Users : null, $data['email'], $row ? 'SANDI_SALAH' : 'TIDAK_ADA');

            return ResponseHelper::error('Email atau kata sandi salah.', 401);
        }

        if ($row->Status !== 'AKTIF') {
            RiwayatLogin::catat($request, RiwayatLogin::GAGAL, (int) $row->Id_Users, $data['email'], 'NONAKTIF');

            return ResponseHelper::error('Akun Anda dinonaktifkan. Silakan hubungi tim rekrutmen EVO Group.', 403);
        }

        // Panel ini khusus STAF. Kandidat masuk di situs kandidat.
        if (! in_array($row->Role, CareerRole::ADMIN, true)) {
            RiwayatLogin::catat($request, RiwayatLogin::GAGAL, (int) $row->Id_Users, $data['email'], 'BUKAN_STAF');
            $situs = (string) config('sinkron.pengguna_url');

            return ResponseHelper::error(
                'Panel ini khusus tim rekrutmen. Kandidat silakan masuk lewat situs karier'
                    . ($situs !== '' ? ' (' . $situs . ').' : '.'),
                403,
            );
        }

        if (($row->Flag_Email_Verified ?? 'T') !== 'Y') {
            RiwayatLogin::catat($request, RiwayatLogin::GAGAL, (int) $row->Id_Users, $data['email'], 'BELUM_VERIFIKASI');

            return ResponseHelper::error('Email akun ini belum diverifikasi. Hubungi admin sistem.', 403);
        }

        if (
            $row->Valid_Until !== null &&
            Carbon::parse($row->Valid_Until)
                ->startOfDay()
                ->lessThan(Carbon::now()->startOfDay())
        ) {
            RiwayatLogin::catat($request, RiwayatLogin::GAGAL, (int) $row->Id_Users, $data['email'], 'KEDALUWARSA');

            return ResponseHelper::error(
                'Masa berlaku akun Anda telah berakhir pada ' . Carbon::parse($row->Valid_Until)->format('d M Y') . '.',
                403,
            );
        }

        // Perubahan berikutnya di permintaan ini sudah atas nama akun yang baru masuk.
        KonteksAudit::aktor((int) $row->Id_Users, (string) $row->Email);

        DB::table($this->table)
            ->where('Id_Users', $row->Id_Users)
            ->update(['Last_Login_At' => Carbon::now()]);

        $user = [
            'id' => $row->Id_Users,
            'nama' => $row->Nama,
            'email' => $row->Email,
            'role' => $row->Role,
            'klasifikasi' => $row->Klasifikasi,
            'valid_until' => $row->Valid_Until,
            'pwd_epoch' => $row->Pwd_Changed_At,
            // Jembatan ke MPP: penanggung jawabnya disimpan sebagai kode karyawan,
            // bukan sebagai akun. Dibawa di sesi supaya penyaring lingkup PIC
            // tidak perlu menanyakannya ke database pada setiap permintaan.
            //
            // Ikut kedaluwarsa bersama sesinya: kode yang diubah admin baru
            // berlaku setelah pemiliknya masuk lagi — sama seperti perannya.
            'kode_karyawan' => $row->Kode_Karyawan ?? null,
        ];
        // Id sesi baru setiap kali masuk — menutup session fixation.
        $request->session()->regenerate();
        $request->session()->put('career_auth', $user);

        // Paket hak akses (permissions / label menu / kategori) — pola cat-evo.
        $request
            ->session()
            ->put('career_akses', AksesService::paket((int) $row->Id_Users, (string) $row->Role, $row->Klasifikasi));

        RiwayatLogin::catat($request, RiwayatLogin::MASUK, (int) $row->Id_Users, (string) $row->Email);

        return ResponseHelper::success($user, 'Login berhasil.');
    }

    /** Akun STAF ber-email ini (kandidat tidak me-reset sandinya di panel ini). */
    private function akunStaf(string $email): ?object
    {
        return DB::table($this->table)
            ->where('Email', $email)
            ->whereIn('Role', CareerRole::ADMIN)
            ->first();
    }

    public function logout(Request $request)
    {
        $auth = $request->session()->get('career_auth');
        if (is_array($auth) && ! empty($auth['id'])) {
            RiwayatLogin::catat($request, RiwayatLogin::KELUAR, (int) $auth['id'], $auth['email'] ?? null);
        }
        $request->session()->forget(['career_auth', 'career_akses']);
        KonteksAudit::aktor(null, null);

        return ResponseHelper::success(null, 'Logout berhasil.');
    }

    /**
     * Catat satu peristiwa reset kata sandi ke tabel audit (queryable).
     * Sengaja dibungkus try/catch: kegagalan audit TIDAK boleh menggagalkan
     * alur reset. `Keterangan` TIDAK PERNAH memuat OTP atau kata sandi.
     */
    private function catatAuditReset(
        Request $request,
        string $event,
        ?int $userId,
        ?string $email,
        ?string $ket = null,
    ): void {
        try {
            DB::table($this->auditTable)->insert([
                'Id_Users' => $userId,
                'Email' => $email !== null ? mb_substr($email, 0, 150) : null,
                'Event' => $event,
                'Ip_Address' => $request->ip(),
                'User_Agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'Keterangan' => $ket !== null ? mb_substr($ket, 0, 255) : null,
                'Created_At' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("[RESET] gagal audit '{$event}': " . $e->getMessage());
        }
    }

    /**
     * Siapkan OTP reset kata sandi: simpan HASH OTP di DB (OTP asli tidak pernah
     * disimpan), setel masa berlaku, reset counter percobaan, dan hitung ulang
     * window rate-limit di PHP (hindari fungsi khusus SQL Server). Pengiriman
     * email dilakukan asinkron lewat WcSyncEmailJob; `Reset_Otp_Sent_At` diset di
     * job SETELAH email benar-benar terkirim (mulai hitung cooldown).
     */
    private function kirimOtpReset(int $userId): void
    {
        try {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); // 6 digit, CSPRNG
            $now = Carbon::now();

            $row = DB::table($this->table)->where('Id_Users', $userId)->first();

            // Window rate-limit dihitung di PHP (tanpa ISNULL/T-SQL) agar portabel.
            $windowMasihBerlaku =
                $row &&
                $row->Reset_Otp_Window_At &&
                Carbon::parse($row->Reset_Otp_Window_At)->diffInMinutes($now) < self::RESET_OTP_WINDOW_MENIT;
            $kirimCount = $windowMasihBerlaku ? (int) ($row->Reset_Otp_Kirim_Count ?? 0) + 1 : 1;
            $windowAt = $windowMasihBerlaku ? $row->Reset_Otp_Window_At : $now;

            DB::table($this->table)
                ->where('Id_Users', $userId)
                ->update([
                    'Reset_Otp_Hash' => hash('sha256', $otp),
                    'Reset_Otp_Expired_At' => $now->copy()->addMinutes(self::RESET_OTP_BERLAKU_MENIT),
                    'Reset_Otp_Attempt' => 0,
                    'Reset_Otp_Kirim_Count' => $kirimCount,
                    'Reset_Otp_Window_At' => $windowAt,
                    'Updated_At' => $now,
                ]);

            WcSyncEmailJob::kirim(WcSyncEmailJob::JENIS_RESET_OTP, $userId, [
                'otp' => $otp,
                'menit' => self::RESET_OTP_BERLAKU_MENIT,
            ]);
        } catch (\Throwable $e) {
            Log::error("[RESET] gagal antre OTP user #{$userId}: " . $e->getMessage());
            Log::channel('web_career')->error("[RESET] gagal antre OTP user #{$userId}: " . $e->getMessage());
        }
    }

    /**
     * Minta OTP reset kata sandi (POST api/v1/lupa-sandi, body: email).
     *
     * ANTI-ENUMERASI: respons SELALU identik (status, pesan, bentuk) terlepas
     * dari apakah email terdaftar, aktif, atau sedang dalam masa cooldown —
     * supaya tidak membocorkan keberadaan sebuah akun. OTP hanya dikirim untuk
     * akun STAF AKTIF + terverifikasi yang tidak melanggar cooldown/kuota window
     * (kandidat me-reset sandinya di situs kandidat).
     */
    public function mintaOtpReset(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $row = $this->akunStaf($data['email']);

        if ($row && $row->Status === 'AKTIF' && ($row->Flag_Email_Verified ?? 'T') === 'Y') {
            $now = Carbon::now();

            // Cooldown HANYA berlaku selama masih ada OTP aktif (mencegah spam
            // kirim-ulang saat kode lama masih hidup). Bila OTP sudah hangus
            // (terkunci karena salah berkali-kali / sudah terpakai), user berhak
            // langsung minta kode baru — cukup dibatasi kuota per jam.
            $kenaCooldown =
                $row->Reset_Otp_Hash &&
                $row->Reset_Otp_Sent_At &&
                Carbon::parse($row->Reset_Otp_Sent_At)->diffInMinutes($now) < self::RESET_OTP_THROTTLE_MENIT;

            $windowMasihBerlaku =
                $row->Reset_Otp_Window_At &&
                Carbon::parse($row->Reset_Otp_Window_At)->diffInMinutes($now) < self::RESET_OTP_WINDOW_MENIT;
            $kenaKuota = $windowMasihBerlaku && (int) ($row->Reset_Otp_Kirim_Count ?? 0) >= self::RESET_OTP_MAX_KIRIM;

            if ($kenaCooldown || $kenaKuota) {
                // Diam-diam tidak mengirim ulang (tetap balas generik) — jangan
                // munculkan 429 yang bisa dipakai membedakan email terdaftar.
                $this->catatAuditReset(
                    $request,
                    'REQUEST',
                    (int) $row->Id_Users,
                    $row->Email,
                    $kenaCooldown ? 'cooldown' : 'kuota window',
                );
            } else {
                $this->kirimOtpReset((int) $row->Id_Users);
                $this->catatAuditReset($request, 'REQUEST', (int) $row->Id_Users, $row->Email);
            }
        } else {
            // Email tidak terdaftar / non-aktif / belum verifikasi → tidak kirim.
            $this->catatAuditReset($request, 'REQUEST', null, $data['email'], 'tidak memenuhi syarat');
        }

        return ResponseHelper::success(
            ['email' => $data['email']],
            'Jika email terdaftar, kami telah mengirim kode OTP 6 digit (berlaku ' .
                self::RESET_OTP_BERLAKU_MENIT .
                ' menit). Silakan cek kotak masuk atau folder spam.',
        );
    }

    /**
     * Inti pemeriksaan OTP terhadap baris user — dipakai bersama oleh verifikasiOtp
     * (cek dulu) dan gantiSandi (submit final). Efek samping: menaikkan counter
     * salah & menghanguskan OTP saat kedaluwarsa/terkunci. TIDAK mengganti password
     * dan TIDAK mengonsumsi OTP saat cocok — supaya OTP yang sudah diverifikasi
     * masih valid untuk langkah simpan kata sandi.
     * Return: 'ok' | 'invalid' | 'expired' | 'locked'.
     */
    private function statusOtp(Request $request, ?object $row, string $otp, ?string $email = null): string
    {
        $emailAudit = $row?->Email ?? $email;

        // Tidak ada akun / tidak ada OTP aktif → generik "invalid" (anti-enumerasi).
        if (!$row || !$row->Reset_Otp_Hash) {
            $this->catatAuditReset(
                $request,
                'WRONG',
                $row ? (int) $row->Id_Users : null,
                $emailAudit,
                'tanpa OTP aktif',
            );

            return 'invalid';
        }

        // Kedaluwarsa → hanguskan.
        if ($row->Reset_Otp_Expired_At && Carbon::parse($row->Reset_Otp_Expired_At)->isPast()) {
            DB::table($this->table)
                ->where('Id_Users', $row->Id_Users)
                ->update([
                    'Reset_Otp_Hash' => null,
                    'Reset_Otp_Expired_At' => null,
                    'Updated_At' => Carbon::now(),
                ]);
            $this->catatAuditReset($request, 'EXPIRED', (int) $row->Id_Users, $row->Email);

            return 'expired';
        }

        // Sudah mencapai batas percobaan → terkunci.
        if ((int) ($row->Reset_Otp_Attempt ?? 0) >= self::RESET_OTP_MAX_ATTEMPT) {
            DB::table($this->table)
                ->where('Id_Users', $row->Id_Users)
                ->update([
                    'Reset_Otp_Hash' => null,
                    'Reset_Otp_Expired_At' => null,
                    'Updated_At' => Carbon::now(),
                ]);
            $this->catatAuditReset($request, 'LOCKED', (int) $row->Id_Users, $row->Email);

            return 'locked';
        }

        // Salah → tambah counter (di PHP); bila mencapai batas, hanguskan OTP.
        if (!hash_equals($row->Reset_Otp_Hash, hash('sha256', $otp))) {
            $percobaan = (int) ($row->Reset_Otp_Attempt ?? 0) + 1;
            $mencapaiBatas = $percobaan >= self::RESET_OTP_MAX_ATTEMPT;

            DB::table($this->table)
                ->where('Id_Users', $row->Id_Users)
                ->update([
                    'Reset_Otp_Attempt' => $percobaan,
                    'Reset_Otp_Hash' => $mencapaiBatas ? null : $row->Reset_Otp_Hash,
                    'Reset_Otp_Expired_At' => $mencapaiBatas ? null : $row->Reset_Otp_Expired_At,
                    'Updated_At' => Carbon::now(),
                ]);
            $this->catatAuditReset(
                $request,
                $mencapaiBatas ? 'LOCKED' : 'WRONG',
                (int) $row->Id_Users,
                $row->Email,
                "attempt {$percobaan}/" . self::RESET_OTP_MAX_ATTEMPT,
            );

            return $mencapaiBatas ? 'locked' : 'invalid';
        }

        return 'ok';
    }

    /** Ubah status OTP non-'ok' menjadi respons JSON ber-`code` (dibaca frontend). */
    private function responsOtpGagal(string $status)
    {
        return match ($status) {
            'expired' => response()->json(
                [
                    'success' => false,
                    'status' => 410,
                    'code' => 'OTP_EXPIRED',
                    'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.',
                ],
                410,
            ),
            'locked' => response()->json(
                [
                    'success' => false,
                    'status' => 429,
                    'code' => 'OTP_LOCKED',
                    'message' => 'Terlalu banyak percobaan. Silakan minta kode OTP baru.',
                ],
                429,
            ),
            default => response()->json(
                [
                    'success' => false,
                    'status' => 422,
                    'code' => 'OTP_INVALID',
                    'message' => 'Kode OTP salah atau sudah tidak berlaku.',
                ],
                422,
            ),
        };
    }

    /**
     * Verifikasi OTP SAJA (POST api/v1/verifikasi-otp, body: email+otp) — dipakai
     * frontend untuk mengecek kode SEBELUM menampilkan form kata sandi baru. OTP
     * TIDAK dikonsumsi di sini (masih dipakai saat submit gantiSandi), namun batas
     * percobaan & kedaluwarsa tetap diberlakukan (anti brute-force).
     */
    public function verifikasiOtp(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        $row = $this->akunStaf($data['email']);
        $status = $this->statusOtp($request, $row, $data['otp'], $data['email']);

        if ($status !== 'ok') {
            return $this->responsOtpGagal($status);
        }

        $this->catatAuditReset($request, 'VERIFIED', (int) $row->Id_Users, $row->Email);

        return ResponseHelper::success(['email' => $data['email']], 'Kode OTP benar. Silakan buat kata sandi baru.');
    }

    /**
     * Reset kata sandi dengan OTP (POST api/v1/ganti-sandi, body: email+otp+password).
     *
     * OTP diperiksa ulang via statusOtp (constant-time, batas percobaan, kedaluwarsa)
     * lalu SEKALI PAKAI (dihapus setelah sukses). Setelah sukses: semua sesi aktif
     * user diinvalidasi (Pwd_Changed_At di-bump, dibaca ulang di CareerAuth), email
     * pemberitahuan dikirim, dan peristiwa dicatat ke audit.
     */
    public function gantiSandi(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $row = $this->akunStaf($data['email']);
        $status = $this->statusOtp($request, $row, $data['otp'], $data['email']);

        if ($status !== 'ok') {
            return $this->responsOtpGagal($status);
        }

        // OTP benar → ganti kata sandi, OTP SEKALI PAKAI (dihapus), dan bump
        // Pwd_Changed_At sebagai "session epoch" → seluruh sesi aktif otomatis
        // keluar pada permintaan berikutnya (dibaca ulang di CareerAuth).
        $now = Carbon::now();
        DB::table($this->table)
            ->where('Id_Users', $row->Id_Users)
            ->update([
                'Password' => Hash::make($data['password']),
                'Reset_Otp_Hash' => null,
                'Reset_Otp_Expired_At' => null,
                'Reset_Otp_Attempt' => 0,
                'Pwd_Changed_At' => $now,
                'Updated_At' => $now,
                'Updated_By' => $row->Email,
            ]);

        WcSyncEmailJob::kirim(WcSyncEmailJob::JENIS_RESET_SELESAI, (int) $row->Id_Users);

        $this->catatAuditReset($request, 'SUCCESS', (int) $row->Id_Users, $row->Email);
        Log::channel('web_career')->info("[RESET] user #{$row->Id_Users} ({$row->Email}) berhasil reset kata sandi.");

        // Bersihkan sesi tab yang melakukan reset.
        $request->session()->forget(['career_auth', 'career_akses']);

        return ResponseHelper::success(null, 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.');
    }
}
