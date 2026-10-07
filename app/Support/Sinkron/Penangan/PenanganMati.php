<?php

namespace App\Support\Sinkron\Penangan;

/**
 * Penangan yang perlu meninggalkan jejak terlihat saat peristiwanya MATI
 * (habis percobaan) — mis. lamaran yang gagal masuk harus tampil di panel
 * "Lamaran gagal masuk" dashboard, bukan hanya di log.
 */
interface PenanganMati
{
    public function mati(array $muatan, object $baris, string $galat): void;
}
