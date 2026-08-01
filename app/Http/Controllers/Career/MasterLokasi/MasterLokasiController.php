<?php

namespace App\Http\Controllers\Career\MasterLokasi;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER — MASTER LOKASI (kantor sendiri & vendor).
 *
 * Dipakai penjadwalan tatap muka: wawancara, tes offline, dan MCU. Rekruter
 * MEMILIH lokasi, bukan mengetiknya — sebelumnya satu tempat yang sama ditulis
 * berbeda-beda ("Kantor Pusat", "HO Palembang", "Kantor EVO Lt.3"), kandidat
 * tidak punya peta, dan tidak ada cara menghitung berapa MCU yang dikirim ke
 * klinik tertentu.
 *
 * KOORDINAT, BUKAN POTONGAN <iframe>
 * Yang disimpan Lintang & Bujur. URL embed Google Maps membawa parameter
 * zoom/ukuran/bahasa yang menyatu dengan satu tampilan, mudah kedaluwarsa, dan
 * menyisipkan HTML mentah dari database ke halaman membuka pintu XSS. Dari
 * koordinat, sistem menyusun sendiri URL peta MAUPUN tautan "buka di aplikasi
 * peta" untuk ponsel — lihat bentukLokasi().
 */
class MasterLokasiController extends Controller
{
    private const JENIS = ['KANTOR', 'VENDOR'];

    public function index()
    {
        return Inertia::render('Career/admin/master-lokasi/masterLokasi', CareerShell::props(
            '/master-lokasi',
            'Master Lokasi',
        ));
    }

    /**
     * Alamat satu baris: alamat + kota/provinsi yang BELUM tertulis di dalamnya.
     *
     * Kolom Alamat umumnya sudah memuat kota dan provinsinya. Menyambung
     * ketiganya apa adanya menghasilkan "Banyuasin, Sumatera Selatan,
     * Banyuasin, Sumatera Selatan" — pada undangan resmi itu terbaca seperti
     * data rusak, dan kandidat jadi ragu alamatnya benar atau tidak.
     */
    public static function alamatLengkap(?object $r): ?string
    {
        if (! $r) {
            return null;
        }

        $alamat = trim((string) ($r->Alamat ?? ''));
        $bawah = mb_strtolower($alamat);

        $tambahan = array_filter(
            [$r->Kota ?? null, $r->Provinsi ?? null, $r->Kode_Pos ?? null],
            fn ($v) => $v && ! str_contains($bawah, mb_strtolower((string) $v)),
        );

        return implode(', ', array_filter(array_merge([$alamat], $tambahan))) ?: null;
    }

    /**
     * Bentuk satu baris untuk dikirim ke layar.
     *
     * URL peta disusun DI SINI, bukan di Vue: aturannya sama untuk admin dan
     * portal kandidat, dan menaruhnya di satu tempat mencegah keduanya lambat
     * laun berbeda.
     */
    public static function bentukLokasi(?object $r): ?array
    {
        if (! $r) {
            return null;
        }

        $titik = ($r->Lintang !== null && $r->Bujur !== null)
            ? $r->Lintang . ',' . $r->Bujur
            : null;

        // Kueri peta: nama tempat lebih akurat daripada koordinat untuk gedung
        // yang sudah terdaftar di Google; koordinat jadi cadangannya.
        $kueri = $r->Maps_Query ?: ($titik ?: trim(($r->Nama ?? '') . ' ' . ($r->Alamat ?? '')));

        return [
            'id' => Hashids::encode($r->Id_Master_Lokasi),
            'kode' => $r->Kode,
            'nama' => $r->Nama,
            'jenis' => $r->Jenis,
            'kategori' => $r->Kategori,
            'alamat' => $r->Alamat,
            // Alamat SATU BARIS siap tulis (surel undangan, kartu lokasi):
            // kota/provinsi hanya ditambahkan bila belum tertulis di alamatnya.
            'alamatLengkap' => self::alamatLengkap($r),
            'kota' => $r->Kota,
            'provinsi' => $r->Provinsi,
            'kodePos' => $r->Kode_Pos,
            'lintang' => $r->Lintang !== null ? (float) $r->Lintang : null,
            'bujur' => $r->Bujur !== null ? (float) $r->Bujur : null,
            'kontakNama' => $r->Kontak_Nama,
            'kontakTelp' => $r->Kontak_Telp,
            'catatan' => $r->Catatan,
            'utama' => ($r->Flag_Default ?? 'T') === 'Y',
            'aktif' => ($r->Flag_Aktif ?? 'Y') === 'Y',
            // Peta sematan (iframe) & tautan buka di aplikasi peta.
            'mapsEmbed' => $kueri ? 'https://www.google.com/maps?q=' . urlencode($kueri) . '&output=embed' : null,
            'mapsUrl' => $kueri ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($kueri) : null,
        ];
    }

    /** GET /api/v1/master-lokasi — daftar + pencarian + saringan jenis. */
    public function list(Request $request)
    {
        $q = DB::table('N_WEB_CAREERS_Master_Lokasi');

        if ($cari = trim((string) $request->query('cari', ''))) {
            $q->where(function ($w) use ($cari) {
                $w->where('Nama', 'like', "%{$cari}%")
                    ->orWhere('Kode', 'like', "%{$cari}%")
                    ->orWhere('Alamat', 'like', "%{$cari}%")
                    ->orWhere('Kota', 'like', "%{$cari}%");
            });
        }

        if ($jenis = $request->query('jenis')) {
            $q->where('Jenis', strtoupper($jenis));
        }

        // Hanya yang aktif — dipakai dropdown penjadwalan.
        if ($request->boolean('aktif')) {
            $q->where('Flag_Aktif', 'Y');
        }

        $rows = $q->orderByDesc('Flag_Default')->orderBy('Urutan')->orderBy('Nama')->get();

        return ResponseHelper::success(
            $rows->map(fn ($r) => self::bentukLokasi($r))->all(),
            'Daftar lokasi',
        );
    }

    private function aturan(?int $id = null): array
    {
        return [
            'nama' => 'required|string|max:200',
            'jenis' => 'required|in:' . implode(',', self::JENIS),
            'kategori' => 'nullable|string|max:60',
            'alamat' => 'nullable|string|max:500',
            'kota' => 'nullable|string|max:120',
            'provinsi' => 'nullable|string|max:120',
            'kodePos' => 'nullable|string|max:15',
            // Rentang dibatasi agar salah ketik (mis. tertukar lintang-bujur)
            // tertangkap di sini, bukan berupa pin yang jatuh di tengah laut.
            'lintang' => 'nullable|numeric|between:-90,90',
            'bujur' => 'nullable|numeric|between:-180,180',
            'kontakNama' => 'nullable|string|max:150',
            'kontakTelp' => 'nullable|string|max:40',
            'catatan' => 'nullable|string|max:1000',
            'utama' => 'nullable|boolean',
        ];
    }

    /** Susun payload DB dari input yang sudah tervalidasi. */
    private function isian(array $d): array
    {
        return [
            'Nama' => $d['nama'],
            'Jenis' => strtoupper($d['jenis']),
            'Kategori' => $d['kategori'] ?? null,
            'Alamat' => $d['alamat'] ?? null,
            'Kota' => $d['kota'] ?? null,
            'Provinsi' => $d['provinsi'] ?? null,
            'Kode_Pos' => $d['kodePos'] ?? null,
            'Lintang' => $d['lintang'] ?? null,
            'Bujur' => $d['bujur'] ?? null,
            'Kontak_Nama' => $d['kontakNama'] ?? null,
            'Kontak_Telp' => $d['kontakTelp'] ?? null,
            'Catatan' => $d['catatan'] ?? null,
        ];
    }

    /**
     * Hanya SATU lokasi utama.
     *
     * Dua lokasi bertanda utama membuat bawaan dropdown tidak bisa ditebak —
     * yang terpilih tergantung urutan baris, dan itu berubah sendiri seiring
     * data bertambah.
     */
    private function jadikanUtamaTunggal(int $kecuali): void
    {
        DB::table('N_WEB_CAREERS_Master_Lokasi')
            ->where('Id_Master_Lokasi', '!=', $kecuali)
            ->where('Flag_Default', 'Y')
            ->update(['Flag_Default' => 'T', 'Updated_At' => now()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->aturan());
        $nama = session('career_auth.nama');

        $kode = Str::upper(Str::slug($d['nama'], '-'));
        $kode = Str::limit($kode, 34, '');
        if (DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Kode', $kode)->exists()) {
            $kode .= '-' . Str::upper(Str::random(4));
        }

        $id = DB::table('N_WEB_CAREERS_Master_Lokasi')->insertGetId(
            $this->isian($d) + [
                'Kode' => $kode,
                'Flag_Default' => ! empty($d['utama']) ? 'Y' : 'T',
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => $nama,
                'Created_By_Id' => session('career_auth.id'),
            ],
            'Id_Master_Lokasi',
        );

        if (! empty($d['utama'])) {
            $this->jadikanUtamaTunggal((int) $id);
        }

        return ResponseHelper::success(['id' => Hashids::encode($id)], 'Lokasi ditambahkan.');
    }

    public function update(Request $request, string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lokasi tidak valid.', 422);
        }

        $d = $request->validate($this->aturan((int) $realId));

        DB::table('N_WEB_CAREERS_Master_Lokasi')
            ->where('Id_Master_Lokasi', $realId)
            ->update($this->isian($d) + [
                'Flag_Default' => ! empty($d['utama']) ? 'Y' : 'T',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

        if (! empty($d['utama'])) {
            $this->jadikanUtamaTunggal((int) $realId);
        }

        return ResponseHelper::success(null, 'Lokasi diperbarui.');
    }

    /** PATCH toggle aktif/nonaktif. */
    public function toggle(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        $row = $realId ? DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $realId)->first() : null;

        if (! $row) {
            return ResponseHelper::error('Lokasi tidak ditemukan.', 404);
        }

        $baru = $row->Flag_Aktif === 'Y' ? 'T' : 'Y';

        DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $realId)->update([
            'Flag_Aktif' => $baru,
            // Lokasi nonaktif tidak boleh tetap jadi bawaan dropdown.
            'Flag_Default' => $baru === 'T' ? 'T' : $row->Flag_Default,
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama'),
        ]);

        return ResponseHelper::success(null, $baru === 'Y' ? 'Lokasi diaktifkan.' : 'Lokasi dinonaktifkan.');
    }

    /**
     * DELETE — hanya bila belum pernah dipakai menjadwalkan.
     *
     * Menghapus lokasi yang sudah terpakai akan membuat jadwal lama kehilangan
     * tempatnya; kandidat yang membuka riwayat melihat undangan tanpa lokasi.
     * Yang terpakai cukup dinonaktifkan — ia hilang dari dropdown tapi jadwal
     * lama tetap utuh.
     */
    public function destroy(string $id)
    {
        $realId = Hashids::decode($id)[0] ?? null;
        if (! $realId) {
            return ResponseHelper::error('Lokasi tidak valid.', 422);
        }

        $terpakai = DB::table('N_WEB_CAREERS_Lamaran_Tahap_Tes')
            ->where('Jadwal_Lokasi_Id', $realId)
            ->count();

        if ($terpakai > 0) {
            return ResponseHelper::error(
                "Lokasi ini sudah dipakai pada {$terpakai} jadwal. Nonaktifkan saja agar jadwal lama tetap utuh.",
                409,
            );
        }

        DB::table('N_WEB_CAREERS_Master_Lokasi')->where('Id_Master_Lokasi', $realId)->delete();

        return ResponseHelper::success(null, 'Lokasi dihapus.');
    }
}
