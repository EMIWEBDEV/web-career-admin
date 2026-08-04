<?php

namespace Tests\Unit;

use App\Support\Career\PipelineProgress;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class PipelineProgressTahapAktifTest extends TestCase
{
    private function tahap(int $urutan, string $status, string $flagTuntas = 'T'): object
    {
        return (object) ['Id_Lamaran_Tahap' => $urutan, 'Urutan' => $urutan, 'Status' => $status, 'Flag_Tuntas' => $flagTuntas];
    }

    public function test_status_berjalan_biasa_tetap_mengembalikan_tahap_berjalan(): void
    {
        $l = (object) ['Status' => 'BERJALAN'];
        $tahapList = new Collection([
            $this->tahap(1, 'SELESAI'),
            $this->tahap(2, 'BERJALAN'),
        ]);

        $hasil = PipelineProgress::tahapAktif($l, $tahapList);

        $this->assertSame(2, $hasil->Urutan);
    }

    public function test_status_gugur_tetap_mengembalikan_null(): void
    {
        $l = (object) ['Status' => 'GUGUR'];
        $tahapList = new Collection([$this->tahap(1, 'SELESAI')]);

        $this->assertNull(PipelineProgress::tahapAktif($l, $tahapList));
    }

    public function test_status_talent_pool_tetap_mengembalikan_null(): void
    {
        $l = (object) ['Status' => 'TALENT_POOL'];
        $tahapList = new Collection([$this->tahap(1, 'SELESAI')]);

        $this->assertNull(PipelineProgress::tahapAktif($l, $tahapList));
    }

    public function test_status_lulus_dengan_tahap_pascatuntas_berjalan_mengembalikan_tahap_itu(): void
    {
        $l = (object) ['Status' => 'LULUS'];
        $tahapList = new Collection([
            $this->tahap(1, 'SELESAI', 'Y'),   // titik tuntas, sudah selesai
            $this->tahap(2, 'BERJALAN', 'T'),  // tahap administratif (kontrak) — masih berjalan
        ]);

        $hasil = PipelineProgress::tahapAktif($l, $tahapList);

        $this->assertNotNull($hasil);
        $this->assertSame(2, $hasil->Urutan);
    }

    public function test_status_lulus_tanpa_tahap_berjalan_mengembalikan_null(): void
    {
        $l = (object) ['Status' => 'LULUS'];
        $tahapList = new Collection([
            $this->tahap(1, 'SELESAI', 'Y'),
            $this->tahap(2, 'SELESAI', 'T'),
        ]);

        $this->assertNull(PipelineProgress::tahapAktif($l, $tahapList));
    }
}
