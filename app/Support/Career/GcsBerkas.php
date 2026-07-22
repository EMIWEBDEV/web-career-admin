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
