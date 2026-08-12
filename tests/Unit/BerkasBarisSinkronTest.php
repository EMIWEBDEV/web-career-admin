<?php

namespace Tests\Unit;

use App\Support\Career\BerkasBaris;
use PHPUnit\Framework\TestCase;

/**
 * Uji SINKRONISASI format kunci PHP dengan cerminan JS-nya.
 *
 * Polanya sama dengan KatalogFieldSinkronTest: membaca berkas JS langsung dan
 * membandingkan konstantanya. Kalau format kuncinya diubah di satu sisi saja,
 * browser dan server akan membentuk kunci berbeda untuk berkas yang sama — dan
 * gejalanya (berkas "hilang" tanpa galat) jauh lebih mahal dilacak daripada
 * uji ini.
 */
class BerkasBarisSinkronTest extends TestCase
{
    private const JS = __DIR__ . '/../../resources/js/components/career/formulir/inti/berkasBaris.js';

    public function test_format_kunci_sama_dengan_js(): void
    {
        $this->assertSame(BerkasBaris::FORMAT_KUNCI, $this->konstantaJs('FORMAT_KUNCI'));
    }

    public function test_format_label_sama_dengan_js(): void
    {
        $this->assertSame(BerkasBaris::FORMAT_LABEL, $this->konstantaJs('FORMAT_LABEL'));
    }

    private function konstantaJs(string $nama): string
    {
        $isi = file_get_contents(self::JS);
        preg_match('/export const ' . $nama . " = '(.*?)';/", $isi, $m);
        $this->assertNotEmpty($m, "{$nama} tidak ditemukan di berkasBaris.js");

        return $m[1];
    }
}
