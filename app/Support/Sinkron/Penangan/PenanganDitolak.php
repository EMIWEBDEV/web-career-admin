<?php

namespace App\Support\Sinkron\Penangan;

/**
 * Penangan yang perlu membereskan sisi publik saat peristiwanya DITOLAK
 * (mis. lamaran yang tidak bisa diproses: baris lamaran publiknya harus
 * ditutup supaya kandidat tidak tertahan aturan "satu lamaran aktif").
 * Dijalankan di transaksi yang sama dengan penandaan DITOLAK.
 */
interface PenanganDitolak
{
    public function ditolak(array $muatan, object $baris, string $alasan): void;
}
