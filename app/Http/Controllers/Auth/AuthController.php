<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\Career\WcSyncEmailJob;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * WEB CAREER — AUTH (REAL, DB N_WEB_CAREERS_Users) memakai QUERY BUILDER.
 * Controller WEB, dipanggil via axios ke route web ber-prefix api/v1 → balas JSON.
 */
class AuthController extends Controller
{
    private string $table = 'N_WEB_CAREERS_Users';

    private string $klasTable = 'N_WEB_CAREERS_Klasifikasi_Akun';

    /** Masa berlaku tautan verifikasi email (menit) — sesuai desain: 30 menit, sekali pakai. */
    private const VERIF_BERLAKU_MENIT = 30;

    /** Jeda minimal kirim-ulang email verifikasi (menit) — anti spam. */
    private const VERIF_THROTTLE_MENIT = 2;

    /**
     * Siapkan verifikasi email: simpan HASH token di DB (token asli tidak
     * pernah disimpan), lalu antrekan pengiriman via queue 'wc-syncemailjob'.
     * Gagal kirim email TIDAK boleh menggagalkan registrasi → dibungkus try.
     */
    private function kirimEmailVerifikasi(int $userId): void
    {
        try {
            $token = Str::random(64); // dikirim utuh di magic link
            $now = Carbon::now();

            DB::table($this->table)->where('Id_Users', $userId)->update([
                'Flag_Email_Verified' => 'T',
                'Email_Verified_At' => null,
                'Email_Verif_Token' => hash('sha256', $token),
                'Email_Verif_Expired_At' => $now->copy()->addMinutes(self::VERIF_BERLAKU_MENIT),
                'Email_Verif_Attempt' => DB::raw('ISNULL(Email_Verif_Attempt, 0) + 1'),
                'Updated_At' => $now,
            ]);

            WcSyncEmailJob::dispatch(WcSyncEmailJob::JENIS_VERIFIKASI, $userId, ['token' => $token]);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("[EMAIL] gagal antre verifikasi user #{$userId}: " . $e->getMessage());
        }
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
        ]);

        $now = Carbon::now();

        // Masa berlaku registrasi diambil dari tabel config (TIDAK hardcode).
        $klas = DB::table($this->klasTable)->where('Is_Default_Register', 'Y')->where('Flag_Aktif', 'Y')->first();
        $kode = $klas->Kode ?? 'PERMANEN';
        $validUntil = ($klas && $klas->Durasi_Hari !== null)
            ? $now->copy()->addDays((int) $klas->Durasi_Hari)->toDateString()
            : null;

        $existing = DB::table($this->table)->where('Email', $data['email'])->first();

        if ($existing) {
            $terverifikasi = ($existing->Flag_Email_Verified ?? 'T') === 'Y';
            // Masa berlaku pendaftaran mengikuti KLASIFIKASI akun (mis. trial
            // 6 bulan → Valid_Until = tanggal daftar + Durasi_Hari klasifikasi).
            $masihBerlaku = $existing->Status === 'AKTIF'
                && ($existing->Valid_Until === null || Carbon::parse($existing->Valid_Until)->startOfDay()->greaterThanOrEqualTo($now->copy()->startOfDay()));

            // Ditolak HANYA bila akun terverifikasi DAN masih dalam masa berlaku.
            if ($terverifikasi && $masihBerlaku) {
                $sampai = $existing->Valid_Until ? ' hingga ' . Carbon::parse($existing->Valid_Until)->format('d M Y') : '';

                return ResponseHelper::error("Email sudah terdaftar dan masih aktif{$sampai}. Silakan langsung masuk.", 422);
            }

            // Dua kasus yang BOLEH daftar ulang (akun di-klaim ulang):
            //  1. Belum pernah verifikasi email — kepemilikan email belum
            //     terbukti, jadi pendaftar sekarang berhak mengambil alih.
            //     Tanpa ini, orang yang telat verifikasi akan selamanya
            //     mentok "email sudah terdaftar" tanpa pernah dapat email.
            //  2. Sudah terverifikasi tapi masa berlaku klasifikasinya habis
            //     (mis. trial 6 bulan lewat) / akun nonaktif.
            DB::table($this->table)->where('Id_Users', $existing->Id_Users)->update([
                'Nama' => $data['nama'],
                'No_Hp' => $data['phone'] ?? $existing->No_Hp,
                'Password' => Hash::make($data['password']),
                'Klasifikasi' => $kode,
                'Status' => 'AKTIF',
                'Mulai_Berlaku' => $now->toDateString(),
                'Valid_Until' => $validUntil,
                'Updated_At' => $now,
                'Updated_By' => $data['email'],
            ]);

            $this->kirimEmailVerifikasi((int) $existing->Id_Users);

            $pesan = $terverifikasi
                ? 'Pendaftaran ulang berhasil (akun sebelumnya telah kedaluwarsa). Cek email kamu untuk verifikasi.'
                : 'Email ini pernah didaftarkan namun belum diverifikasi. Data kamu diperbarui — cek email untuk tautan verifikasi yang baru.';

            // TIDAK auto-login: sesi baru dibuat setelah email terverifikasi + login.
            return ResponseHelper::success(
                ['email' => $data['email'], 'perlu_verifikasi' => true],
                $pesan
            );
        }

        $id = DB::table($this->table)->insertGetId([
            'Nama' => $data['nama'],
            'Email' => $data['email'],
            'No_Hp' => $data['phone'] ?? null,
            'Password' => Hash::make($data['password']),
            'Role' => 'KANDIDAT',
            'Klasifikasi' => $kode,
            'Status' => 'AKTIF',
            'Mulai_Berlaku' => $now->toDateString(),
            'Valid_Until' => $validUntil,
            'Created_At' => $now,
            'Created_By' => $data['email'],
            'Updated_At' => $now,
            'Updated_By' => $data['email'],
        ], 'Id_Users');

        $this->kirimEmailVerifikasi((int) $id);

        // TIDAK auto-login: kandidat wajib verifikasi email dulu baru bisa masuk.
        return ResponseHelper::success(
            ['email' => $data['email'], 'perlu_verifikasi' => true],
            'Registrasi berhasil! Kami telah mengirim tautan verifikasi ke email kamu (berlaku ' . self::VERIF_BERLAKU_MENIT . ' menit). Setelah verifikasi, silakan masuk.',
            201
        );
    }

    /**
     * Magic link dari email: GET /verifikasi-email?email=...&token=...
     * Merender halaman hasil (Career/VerifikasiEmail) dengan status:
     *  - sukses      : baru saja terverifikasi (countdown ke /login)
     *  - sudah       : sudah pernah terverifikasi (idempoten — link diklik 2x)
     *  - kadaluarsa  : token benar tapi lewat masa berlaku → tombol kirim ulang
     *  - invalid     : token salah/kosong → form tempel tautan/token manual
     * Token dibandingkan sebagai HASH SHA-256 (constant-time) dan SEKALI PAKAI
     * (dihapus setelah sukses). Bila email tak dikirim (kandidat hanya menempel
     * token), pencarian jatuh ke hash token.
     */
    public function verifikasiEmail(Request $request)
    {
        $hasil = $this->terapkanVerifikasiToken(
            trim((string) $request->query('email', '')),
            trim((string) $request->query('token', '')),
        );

        return Inertia::render('Career/VerifikasiEmail', [
            'status' => $hasil['status'],
            'email' => $hasil['email'],
        ]);
    }

    /**
     * Verifikasi via tempel token (POST api/v1/verifikasi-token). Dipakai halaman
     * "menunggu verifikasi" agar kandidat bisa menempel magic link jika tautan di
     * email bermasalah — hasilnya JSON, verifikasi tetap di TAB yang sama.
     */
    public function verifikasiToken(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'nullable|email',
        ]);

        $hasil = $this->terapkanVerifikasiToken(trim((string) ($data['email'] ?? '')), trim($data['token']));

        return match ($hasil['status']) {
            'sukses' => ResponseHelper::success(['status' => 'sukses', 'email' => $hasil['email']], 'Email berhasil diverifikasi.'),
            'sudah' => ResponseHelper::success(['status' => 'sudah', 'email' => $hasil['email']], 'Email kamu memang sudah terverifikasi.'),
            'kadaluarsa' => response()->json(['success' => false, 'status' => 410, 'code' => 'KADALUARSA', 'message' => 'Tautan sudah kedaluwarsa. Silakan kirim ulang email verifikasi.', 'result' => ['email' => $hasil['email']]], 410),
            default => ResponseHelper::error('Tautan / token tidak dikenali. Salin utuh tautan dari email terbaru kamu.', 422),
        };
    }

    /** Cek status verifikasi (GET api/v1/status-verifikasi?email=) untuk polling halaman tunggu. */
    public function statusVerifikasi(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();
        if (! $row) {
            return ResponseHelper::error('Email tidak terdaftar.', 404);
        }

        return ResponseHelper::success(['verified' => ($row->Flag_Email_Verified ?? 'T') === 'Y']);
    }

    /**
     * Inti verifikasi token, dipakai bersama magic-link (GET) & tempel-token (POST).
     * Mengembalikan ['status' => sukses|sudah|kadaluarsa|invalid, 'email' => ?string].
     * Token dibandingkan sebagai HASH SHA-256 (constant-time) dan SEKALI PAKAI
     * (dihapus setelah sukses). Bila email tak diketahui (hanya token), pencarian
     * jatuh ke hash token karena kolomnya unik per akun.
     */
    private function terapkanVerifikasiToken(string $email, string $token): array
    {
        if ($token === '') {
            return ['status' => 'invalid', 'email' => null];
        }

        $row = null;
        if ($email !== '') {
            $row = DB::table($this->table)->where('Email', $email)->first();
        }
        if (! $row) {
            $row = DB::table($this->table)->where('Email_Verif_Token', hash('sha256', $token))->first();
        }

        if (! $row) {
            return ['status' => 'invalid', 'email' => null];
        }

        // Sudah terverifikasi (mis. tautan diklik dua kali) → idempoten.
        if (($row->Flag_Email_Verified ?? 'T') === 'Y') {
            return ['status' => 'sudah', 'email' => $row->Email];
        }

        if (! $row->Email_Verif_Token || ! hash_equals($row->Email_Verif_Token, hash('sha256', $token))) {
            return ['status' => 'invalid', 'email' => null];
        }

        if ($row->Email_Verif_Expired_At && Carbon::parse($row->Email_Verif_Expired_At)->isPast()) {
            return ['status' => 'kadaluarsa', 'email' => $row->Email];
        }

        // Sukses → tandai verified + hapus token (sekali pakai).
        DB::table($this->table)->where('Id_Users', $row->Id_Users)->update([
            'Flag_Email_Verified' => 'Y',
            'Email_Verified_At' => Carbon::now(),
            'Email_Verif_Token' => null,
            'Email_Verif_Expired_At' => null,
            'Updated_At' => Carbon::now(),
        ]);

        Log::channel('web_career')->info("[EMAIL] user #{$row->Id_Users} ({$row->Email}) terverifikasi.");

        return ['status' => 'sukses', 'email' => $row->Email];
    }

    /** Kirim ulang email verifikasi (POST api/v1/kirim-verifikasi, body: email). */
    public function kirimUlangVerifikasi(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();
        if (! $row) {
            return ResponseHelper::error('Email tidak terdaftar.', 404);
        }

        if (($row->Flag_Email_Verified ?? 'T') === 'Y') {
            return ResponseHelper::error('Email sudah terverifikasi. Silakan langsung masuk.', 422);
        }

        if ($row->Email_Verif_Sent_At && Carbon::parse($row->Email_Verif_Sent_At)->diffInMinutes(Carbon::now()) < self::VERIF_THROTTLE_MENIT) {
            return ResponseHelper::error('Email verifikasi baru saja dikirim. Mohon tunggu beberapa menit lalu cek kotak masuk/spam.', 429);
        }

        $this->kirimEmailVerifikasi((int) $row->Id_Users);

        return ResponseHelper::success(null, 'Email verifikasi telah dikirim ulang. Silakan cek kotak masuk atau folder spam.');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $row = DB::table($this->table)->where('Email', $data['email'])->first();

        if (! $row || ! Hash::check($data['password'], $row->Password)) {
            return ResponseHelper::error('Email atau kata sandi salah.', 401);
        }

        if ($row->Status !== 'AKTIF') {
            return ResponseHelper::error('Akun Anda dinonaktifkan. Silakan hubungi tim rekrutmen EVO Group.', 403);
        }

        // Wajib verifikasi email dulu sebelum bisa masuk. Kirim `code` khusus
        // supaya frontend bisa menampilkan tombol "kirim ulang verifikasi".
        if (($row->Flag_Email_Verified ?? 'T') !== 'Y') {
            return response()->json([
                'success' => false,
                'status' => 403,
                'code' => 'BELUM_VERIFIKASI',
                'message' => 'Email kamu belum diverifikasi. Silakan cek kotak masuk/spam, atau kirim ulang tautan verifikasi.',
            ], 403);
        }

        if ($row->Valid_Until !== null && Carbon::parse($row->Valid_Until)->startOfDay()->lessThan(Carbon::now()->startOfDay())) {
            return ResponseHelper::error('Masa berlaku akun Anda telah berakhir pada ' . Carbon::parse($row->Valid_Until)->format('d M Y') . '.', 403);
        }

        DB::table($this->table)->where('Id_Users', $row->Id_Users)->update(['Last_Login_At' => Carbon::now()]);

        $user = ['id' => $row->Id_Users, 'nama' => $row->Nama, 'email' => $row->Email, 'role' => $row->Role, 'klasifikasi' => $row->Klasifikasi, 'valid_until' => $row->Valid_Until];
        $request->session()->put('career_auth', $user);

        return ResponseHelper::success($user, 'Login berhasil.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('career_auth');

        return ResponseHelper::success(null, 'Logout berhasil.');
    }

    /** Ganti / reset kata sandi berdasarkan email (sementara tanpa verifikasi token). */
    public function gantiSandi(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $exists = DB::table($this->table)->where('Email', $data['email'])->exists();
        if (! $exists) {
            return ResponseHelper::error('Email tidak terdaftar.', 404);
        }

        DB::table($this->table)->where('Email', $data['email'])->update([
            'Password' => Hash::make($data['password']),
            'Updated_At' => Carbon::now(),
            'Updated_By' => $data['email'],
        ]);

        return ResponseHelper::success(null, 'Kata sandi berhasil diperbarui. Silakan masuk kembali.');
    }
}
