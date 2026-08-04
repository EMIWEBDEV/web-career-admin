<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WEB CAREER — pengunggah berkas apply-form ke Google Cloud Storage.
 *
 * Struktur folder WAJIB:
 *   apply-form/{tahun}/{bulan}/{tanggal}/{nama-kandidat}/{nama-berkas}/{namafile}.{ext}
 *   contoh: apply-form/2026/07/01/mustofa-bin-musa/ktp/ktp.png
 *   foto  : apply-form/2026/07/01/mustofa-bin-musa/foto-verifikasi/verifikasi-<rand>.jpg
 *
 * Ekstensi diizinkan: pdf, jpg (jpeg dinormalkan ke jpg). Maks 2 MB per berkas.
 * Atomicity dikendalikan pemanggil (Job): unggah dulu, DB menyusul; bila DB gagal,
 * panggil hapus() untuk membersihkan file (tidak boleh ada berkas yatim).
 */
class GcsBerkas
{
    public const DISK = 'gcs';

    public const MAKS_BYTE = 2 * 1024 * 1024; // 2 MB

    public const EKSTENSI_DIIZINKAN = ['pdf', 'jpg', 'jpeg'];

    /** Path folder dasar kandidat pada tanggal tertentu. */
    public function folderKandidat(string $tahun, string $bulan, string $tanggal, string $namaKandidat): string
    {
        return 'apply-form/' . $tahun . '/' . $bulan . '/' . $tanggal . '/' . $this->slug($namaKandidat);
    }

    /**
     * KONSEP PENYIMPANAN BERKAS — satu pola untuk semua sumber.
     *
     *   {akar}/{tahun}/{bulan}/{tanggal}/{slug-nama-kandidat}/[{ruang}/]{slug-berkas}/{slug-berkas}.{ext}
     *
     * Akar membedakan ASAL berkasnya, bukan formatnya:
     *   apply-form/     berkas saat kandidat MELAMAR (CV, foto verifikasi)
     *   formulir-tahap/ berkas yang KANDIDAT unggah di formulir sebuah tahap
     *   hasil-tahap/    berkas yang TIM unggah sebagai hasil tahap
     *                   (wawancara, MCU, psikotes offline, dst.)
     *
     * `ruang` hanya dipakai hasil-tahap, diisi kode tipe tahap (INTERVIEW, MCU,
     * …) supaya berkas satu kandidat tidak menumpuk jadi satu tumpukan tanpa
     * penanda. Tanggal ada di path agar penelusuran dan kebijakan lifecycle
     * bucket bisa bekerja per periode tanpa membaca database.
     *
     * SEBELUMNYA hasil tahap menumpang folderKandidat() dengan menempelkan
     * "-hasil-tahap" pada NAMA kandidat, sehingga berkas tim ikut masuk ke
     * apply-form/ dan nama foldernya tidak lagi cocok dengan kandidat mana pun.
     */
    public function folderHasilTahap(string $tahun, string $bulan, string $tanggal, string $namaKandidat, ?string $kodeTahap = null): string
    {
        $ruang = $kodeTahap ? '/' . $this->slug($kodeTahap) : '';

        return 'hasil-tahap/' . $tahun . '/' . $bulan . '/' . $tanggal . '/' . $this->slug($namaKandidat) . $ruang;
    }

    /**
     * Path folder berkas FORMULIR TAHAP (mis. Kelengkapan Data Diri).
     *
     * Susunannya sengaja SAMA PERSIS dengan folderKandidat() — tahun/bulan/
     * tanggal/nama-kandidat — supaya menelusuri berkas seorang kandidat di
     * bucket tidak menuntut hafal dua pola berbeda. Yang berbeda hanya akarnya,
     * agar berkas lamaran awal dan berkas tahap seleksi tidak tercampur.
     */
    public function folderTahap(string $tahun, string $bulan, string $tanggal, string $namaKandidat): string
    {
        return 'formulir-tahap/' . $tahun . '/' . $bulan . '/' . $tanggal . '/' . $this->slug($namaKandidat);
    }

    /**
     * Unggah satu berkas. Nama file = slug-berkas.ext (deterministik → tanggal/nama
     * yang sama masuk folder yang sama, tidak bentrok).
     *
     * @return string path GCS bila sukses
     *
     * @throws \RuntimeException bila gagal unggah
     */
    public function unggah(string $folderKandidat, string $namaBerkas, string $ext, string $konten): string
    {
        $slug = $this->slug($namaBerkas);
        $ext = $this->normalkanExt($ext);
        $path = "{$folderKandidat}/{$slug}/{$slug}.{$ext}";

        if (! Storage::disk(self::DISK)->put($path, $konten)) {
            throw new \RuntimeException("Gagal mengunggah berkas {$namaBerkas} ke GCS.");
        }

        return $path;
    }

    /**
     * Folder gambar yang DITANAM DI DALAM CATATAN penilaian.
     *
     * Akarnya sendiri (`catatan-gambar/`), bukan menumpang hasil-tahap/: berkas
     * di sana adalah dokumen resmi yang dilihat sebagai lampiran, sedangkan ini
     * potongan di tengah paragraf. Mencampurnya membuat siapa pun yang menyisir
     * bucket per kandidat menemukan belasan potret tanpa tahu itu apa.
     */
    public function folderCatatan(string $tahun, string $bulan, string $tanggal, string $namaKandidat): string
    {
        return 'catatan-gambar/' . $tahun . '/' . $bulan . '/' . $tanggal . '/' . $this->slug($namaKandidat);
    }

    /**
     * Unggah satu gambar catatan. Namanya ACAK, bukan turunan nama berkas asal:
     * penilai kerap menempelkan beberapa potret berturut-turut yang semuanya
     * bernama "image.jpg" dari kamera, dan nama deterministik akan membuat yang
     * kedua menimpa yang pertama tanpa jejak.
     */
    public function unggahGambarCatatan(string $folder, string $ext, string $konten): string
    {
        $path = "{$folder}/ctt-" . Str::lower(Str::random(14)) . '.' . $this->normalkanExt($ext);

        if (! Storage::disk(self::DISK)->put($path, $konten)) {
            throw new \RuntimeException('Gagal mengunggah gambar catatan ke GCS.');
        }

        return $path;
    }

    /**
     * Validasi khusus GAMBAR CATATAN — sengaja berbeda dari validasi() umum.
     *
     * PDF tidak masuk: yang ditanam di dalam kalimat harus bisa dirender sebagai
     * gambar. PNG masuk (tangkapan layar hasil tes daring hampir selalu PNG),
     * begitu pula WEBP yang jadi keluaran bawaan banyak ponsel baru — menolaknya
     * memaksa penilai mengonversi dulu, dan yang terjadi justru berkasnya
     * dikirim lewat WhatsApp.
     */
    public function validasiGambar(string $namaBerkas, string $ext, int $ukuran): void
    {
        if (! in_array($this->normalkanExt($ext), ['jpg', 'png', 'webp'], true)) {
            throw new \RuntimeException("Gambar {$namaBerkas}: hanya JPG, PNG & WEBP yang bisa ditanam di catatan.");
        }
        if ($ukuran > self::MAKS_BYTE) {
            throw new \RuntimeException("Gambar {$namaBerkas}: melebihi 2 MB.");
        }
    }

    /** Unggah foto verifikasi (nama acak, boleh lebih dari satu). */
    public function unggahFoto(string $folderKandidat, string $konten): string
    {
        $path = "{$folderKandidat}/foto-verifikasi/verifikasi-" . Str::lower(Str::random(10)) . '.jpg';

        if (! Storage::disk(self::DISK)->put($path, $konten)) {
            throw new \RuntimeException('Gagal mengunggah foto verifikasi ke GCS.');
        }

        return $path;
    }

    /** Hapus daftar path (kompensasi bila transaksi DB gagal). */
    public function hapus(array $paths): void
    {
        foreach (array_filter($paths) as $p) {
            try {
                Storage::disk(self::DISK)->delete($p);
            } catch (\Throwable $e) {
                // Best-effort; jangan menggagalkan alur pembersihan.
            }
        }
    }

    /** Validasi ekstensi & ukuran; lempar bila melanggar. */
    public function validasi(string $namaBerkas, string $ext, int $ukuran): void
    {
        if (! in_array($this->normalkanExt($ext), ['pdf', 'jpg'], true)) {
            throw new \RuntimeException("Berkas {$namaBerkas}: hanya PDF & JPG yang diperbolehkan.");
        }
        if ($ukuran > self::MAKS_BYTE) {
            throw new \RuntimeException("Berkas {$namaBerkas}: melebihi 2 MB.");
        }
    }

    public function normalkanExt(string $ext): string
    {
        $ext = strtolower(ltrim($ext, '.'));

        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    /** Slug ramah folder: huruf kecil, spasi→strip, buang karakter aneh. */
    public function slug(string $teks): string
    {
        $teks = strtolower(trim($teks));
        // buang prefiks umum field berkas (dok_, file_, upload_)
        $teks = preg_replace('/^(dok|file|upload|berkas)[_\-\s]+/', '', $teks);
        $teks = preg_replace('/[^a-z0-9]+/', '-', $teks);

        return trim($teks, '-') ?: 'berkas';
    }
}
