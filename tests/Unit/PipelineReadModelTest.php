<?php

namespace Tests\Unit;

use App\Support\Career\PipelineReadModel;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PipelineReadModelTest extends TestCase
{
    private function lamaran(string $status): object
    {
        return (object) ['Status' => $status];
    }

    private function tahap(array $attrs): object
    {
        return (object) array_merge([
            'Id_Lamaran_Tahap' => 1,
            'Status' => 'BERJALAN',
            'Hold_Flag' => 'T',
            'Siap_Diputus' => 'N',
            'Flag_Tuntas' => 'T',
            'Urutan' => 1,
            'Keputusan_Mode' => 'MANUAL',
        ], $attrs);
    }

    public function test_hold_mendominasi_siap_diputus(): void
    {
        $l = $this->lamaran('BERJALAN');
        $t = $this->tahap(['Hold_Flag' => 'Y', 'Siap_Diputus' => 'Y']);
        $tahapList = new Collection([$t]);

        $hasil = PipelineReadModel::bucket($l, $tahapList, []);

        $this->assertSame('HOLD', $hasil['bucket']);
    }

    public function test_siap_diputus_ketika_tidak_hold(): void
    {
        $l = $this->lamaran('BERJALAN');
        $t = $this->tahap(['Hold_Flag' => 'T', 'Siap_Diputus' => 'Y']);
        $tahapList = new Collection([$t]);

        $hasil = PipelineReadModel::bucket($l, $tahapList, []);

        $this->assertSame('SIAP_DIPUTUS', $hasil['bucket']);
    }
}
