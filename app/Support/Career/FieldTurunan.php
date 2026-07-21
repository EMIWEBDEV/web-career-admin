<?php

namespace App\Support\Career;

use Carbon\Carbon;

/**
 * WEB CAREER — Field turunan.
 *
 * Field yang TIDAK ditanyakan ke kandidat, tapi dihitung sistem dari jawaban
 * lain. Ada dua alasan kenapa ini perlu:
 *
 *   1. Data yang basi kalau diketik.
 *      Menyuruh kandidat mengetik "usia" berarti angkanya salah tahun depan.
 *      Yang benar disimpan tanggal lahir, usianya dihitung saat dievaluasi.
 *
 *   2. Field yang di formulir sengaja bercabang.
 *      Formulir 1 punya jenjang_politeknik DAN jenjang_universitas — dua
 *      field terpisah karena pertanyaannya memang berbeda per jalur. Syarat
 *      "jenjang = S1" tidak akan kena keduanya. Field turunan menyatukannya.
 *
 * Bagi admin, hasil turunan terlihat seperti field biasa di daftar pilihan
 * syarat — bedanya ditandai "otomatis" supaya jelas ini bukan ketikan kandidat.
 */
class FieldTurunan
{
    /**
     * Definisi field turunan.
     *
     * sumber : field yang harus ada di jawaban agar turunan ini dihitung
     * label  : nama yang dibaca admin saat menyusun syarat
     * tipe   : ANGKA | TEKS | TANGGAL  (menentukan cara membandingkan)
     */
    public const DEFINISI = [
        'usia' => [
            'sumber' => ['tanggal_lahir'],
            'label' => 'Usia (otomatis dari tanggal lahir)',
            'tipe' => 'ANGKA',
            'satuan' => 'tahun',
        ],
        'jenjang' => [
            'sumber' => ['jenjang_politeknik', 'jenjang_universitas', 'jenjang_pendidikan'],
            'label' => 'Jenjang Pendidikan (gabungan semua jalur)',
            'tipe' => 'TEKS',
            'satuan' => null,
        ],
        'jurusan_gabungan' => [
            'sumber' => ['jurusan', 'fakultas'],
            'label' => 'Jurusan / Fakultas (gabungan)',
            'tipe' => 'TEKS',
            'satuan' => null,
        ],
    ];

    /**
     * Hitung seluruh field turunan dari satu set jawaban.
     *
     * @param  array  $jawaban  isi Jawaban_Json
     * @return array  [key => nilai]  hanya yang berhasil dihitung
     */
    public static function hitung(array $jawaban): array
    {
        $out = [];

        foreach (self::DEFINISI as $key => $def) {
            $nilai = self::hitungSatu($key, $jawaban);
            if ($nilai !== null && $nilai !== '') {
                $out[$key] = $nilai;
            }
        }

        return $out;
    }

    /** @return string|int|float|null */
    private static function hitungSatu(string $key, array $jawaban)
    {
        switch ($key) {
            case 'usia':
                return self::usia($jawaban['tanggal_lahir'] ?? null);

            case 'jenjang':
            case 'jurusan_gabungan':
                // Ambil isian pertama yang tidak kosong dari daftar sumbernya.
                // Field bercabang memang cuma satu yang terisi.
                foreach (self::DEFINISI[$key]['sumber'] as $s) {
                    $v = $jawaban[$s] ?? null;
                    if ($v !== null && trim((string) $v) !== '') {
                        return trim((string) $v);
                    }
                }

                return null;

            default:
                return null;
        }
    }

    /** Usia penuh dalam tahun pada saat dihitung. */
    private static function usia($tanggalLahir): ?int
    {
        if (! $tanggalLahir) {
            return null;
        }

        try {
            $lahir = Carbon::parse($tanggalLahir);
        } catch (\Throwable $e) {
            return null;
        }

        // Tanggal di masa depan jelas salah input — jangan mengarang angka.
        if ($lahir->isFuture()) {
            return null;
        }

        return $lahir->age;
    }

    /** Daftar untuk dropdown penyusun syarat. */
    public static function daftar(): array
    {
        $out = [];
        foreach (self::DEFINISI as $key => $def) {
            $out[] = [
                'key' => $key,
                'label' => $def['label'],
                'tipe' => $def['tipe'],
                'satuan' => $def['satuan'],
                'turunan' => true,
            ];
        }

        return $out;
    }
}
