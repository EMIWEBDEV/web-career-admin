<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * WEB CAREER — MENENTUKAN PRODI/JURUSAN YANG BOLEH DIPILIH untuk sebuah kampus.
 *
 * Ada dua lapis, dan urutannya penting:
 *
 *  1. BINDING RESMI — N_WEB_CAREERS_Kampus_Prodi.
 *     Berisi prodi yang benar-benar dibuka kampus tersebut. Kalau kampusnya
 *     sudah punya baris di sini, hanya ini yang dipakai.
 *
 *  2. ATURAN CADANGAN — jenis institusi → jenjang → prodi.
 *     Dipakai selama binding resmi kampus itu belum ada. Tanpa lapis ini,
 *     328 ribu kampus akan tampil "tidak punya prodi" dan pelamar mentok.
 *
 * Lapis 2 sengaja TIDAK disimpan sebagai baris palsu di tabel binding: kalau
 * disimpan, kelak tidak bisa dibedakan mana data resmi dan mana tebakan.
 * Hasilnya selalu menyertakan penanda `sumber` supaya UI bisa jujur.
 */
class ProdiKampus
{
    private const T_KAMPUS = 'N_WEB_CAREERS_Master_Kampus';

    private const T_PRODI = 'N_WEB_CAREERS_Master_Prodi';

    private const T_PJ = 'N_WEB_CAREERS_Prodi_Jenjang';

    private const T_KP = 'N_WEB_CAREERS_Kampus_Prodi';

    private const T_BIDANG = 'N_WEB_CAREERS_Master_Bidang_Ilmu';

    /** Data kampus seperlunya (negara menentukan daftar prodi mana yang dipakai). */
    public static function kampus(string $kodeKampus): ?object
    {
        if ($kodeKampus === '') {
            return null;
        }

        return Cache::remember("wc_kampus_{$kodeKampus}", now()->addMinutes(30), fn () => DB::table(self::T_KAMPUS)
            ->where('Kode', $kodeKampus)
            ->first(['Kode', 'Nama', 'Negara', 'Jenis_Institusi_Kode', 'Jenjang_Kode']));
    }

    /** Apakah kampus ini sudah punya binding resmi pada jenjang tersebut? */
    public static function punyaBinding(string $kodeKampus, string $jenjang): bool
    {
        if ($kodeKampus === '') {
            return false;
        }

        return DB::table(self::T_KP)
            ->where('Kode_Kampus', $kodeKampus)
            ->when($jenjang !== '', fn ($q) => $q->where('Kode_Jenjang', $jenjang))
            ->where('Flag_Aktif', 'Y')
            ->exists();
    }

    /**
     * Cakupan daftar prodi yang dipakai: kampus dalam negeri memakai nama
     * Indonesia, kampus luar negeri memakai nama internasional (CIP).
     */
    private static function cakupan(?object $kampus): string
    {
        $negara = trim((string) ($kampus->Negara ?? 'Indonesia'));

        return ($negara === '' || $negara === 'Indonesia') ? 'ID' : 'GLOBAL';
    }

    /**
     * Daftar prodi untuk satu kampus + jenjang.
     *
     * @return array{sumber:string, data:array}
     */
    public static function prodi(string $kodeKampus, string $jenjang, string $bidang = '', string $cari = '', int $limit = 50): array
    {
        $kampus = self::kampus($kodeKampus);
        $limit = max(1, min($limit, 200));

        if ($kodeKampus !== '' && self::punyaBinding($kodeKampus, $jenjang)) {
            $q = DB::table(self::T_KP . ' as kp')
                ->join(self::T_PRODI . ' as p', 'p.Kode', '=', 'kp.Kode_Prodi')
                ->where('kp.Kode_Kampus', $kodeKampus)
                ->where('kp.Flag_Aktif', 'Y')
                ->where('p.Flag_Aktif', 'Y')
                ->when($jenjang !== '', fn ($w) => $w->where('kp.Kode_Jenjang', $jenjang));

            self::saring($q, $bidang, $cari);

            $rows = $q->orderBy('p.Nama')->limit($limit)
                ->get(['p.Kode', 'p.Nama', 'p.Nama_En', 'p.Bidang_Kode', 'p.Gelar', 'p.Kelompok', 'kp.Nama_Fakultas', 'kp.Akreditasi']);

            return ['sumber' => 'BINDING', 'data' => self::rapikan($rows)];
        }

        // Cadangan: prodi yang lazim untuk jenjang ini.
        $q = DB::table(self::T_PRODI . ' as p')
            ->join(self::T_PJ . ' as pj', 'pj.Kode_Prodi', '=', 'p.Kode')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Cakupan', self::cakupan($kampus))
            ->when($jenjang !== '', fn ($w) => $w->where('pj.Kode_Jenjang', $jenjang));

        self::saring($q, $bidang, $cari);

        $rows = $q->orderBy('p.Nama')->limit($limit)
            ->get(['p.Kode', 'p.Nama', 'p.Nama_En', 'p.Bidang_Kode', 'p.Gelar', 'p.Kelompok']);

        return ['sumber' => 'ATURAN', 'data' => self::rapikan($rows)];
    }

    /**
     * Fakultas / rumpun ilmu yang tersedia untuk kampus + jenjang tersebut,
     * berikut jumlah prodi di dalamnya (untuk cascade dua langkah).
     */
    public static function fakultas(string $kodeKampus, string $jenjang): array
    {
        $kampus = self::kampus($kodeKampus);

        if ($kodeKampus !== '' && self::punyaBinding($kodeKampus, $jenjang)) {
            $q = DB::table(self::T_KP . ' as kp')
                ->join(self::T_PRODI . ' as p', 'p.Kode', '=', 'kp.Kode_Prodi')
                ->where('kp.Kode_Kampus', $kodeKampus)
                ->where('kp.Flag_Aktif', 'Y')
                ->when($jenjang !== '', fn ($w) => $w->where('kp.Kode_Jenjang', $jenjang));
        } else {
            $q = DB::table(self::T_PRODI . ' as p')
                ->join(self::T_PJ . ' as pj', 'pj.Kode_Prodi', '=', 'p.Kode')
                ->where('p.Cakupan', self::cakupan($kampus))
                ->when($jenjang !== '', fn ($w) => $w->where('pj.Kode_Jenjang', $jenjang));
        }

        // Bidang luas = 2 digit pertama kode ISCED → itulah padanan "fakultas".
        $rows = $q->where('p.Flag_Aktif', 'Y')
            ->whereNotNull('p.Bidang_Kode')
            ->select(DB::raw('LEFT(p.Bidang_Kode, 2) as Broad'), DB::raw('COUNT(*) as Jumlah'))
            ->groupBy(DB::raw('LEFT(p.Bidang_Kode, 2)'))
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $bidang = DB::table(self::T_BIDANG)
            ->whereIn('Kode', $rows->pluck('Broad')->all())
            ->get(['Kode', 'Nama', 'Nama_En', 'Nama_Fakultas', 'Urutan'])
            ->keyBy('Kode');

        return $rows->map(fn ($r) => [
            'kode' => $r->Broad,
            'nama' => $bidang[$r->Broad]->Nama_Fakultas ?: ($bidang[$r->Broad]->Nama ?? $r->Broad),
            'rumpun' => $bidang[$r->Broad]->Nama ?? '',
            'jumlah' => (int) $r->Jumlah,
            'urutan' => (int) ($bidang[$r->Broad]->Urutan ?? 0),
        ])->filter(fn ($x) => isset($bidang[$x['kode']]))
            ->sortBy('urutan')->values()->all();
    }

    /** Filter bidang (broad/narrow/detail) + pencarian nama. */
    private static function saring($q, string $bidang, string $cari): void
    {
        if ($bidang !== '') {
            // Cocokkan berdasarkan awalan kode ISCED: '07' mencakup 071x, 0711…
            $aman = addcslashes($bidang, '%_[\\');
            $q->whereRaw('p.Bidang_Kode LIKE ? ESCAPE ' . "'\\'", [$aman . '%']);
        }
        if ($cari !== '') {
            $aman = addcslashes($cari, '%_[\\');
            $q->where(function ($w) use ($aman) {
                $w->whereRaw('p.Nama LIKE ? ESCAPE ' . "'\\'", ['%' . $aman . '%'])
                    ->orWhereRaw('p.Nama_En LIKE ? ESCAPE ' . "'\\'", ['%' . $aman . '%']);
            });
        }
    }

    private static function rapikan($rows): array
    {
        return $rows->map(fn ($r) => [
            'value' => $r->Kode,
            'label' => $r->Nama,
            'namaEn' => $r->Nama_En,
            'bidang' => $r->Bidang_Kode,
            'gelar' => $r->Gelar,
            'kelompok' => $r->Kelompok,
            'fakultas' => $r->Nama_Fakultas ?? null,
            'akreditasi' => $r->Akreditasi ?? null,
        ])->values()->all();
    }
}
