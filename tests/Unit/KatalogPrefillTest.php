<?php

namespace Tests\Unit;

use App\Support\Career\KatalogPrefill;
use PHPUnit\Framework\TestCase;

/**
 * Uji KATALOG PREFILL — kosakata kunci isi-otomatis.
 *
 * Sebelum ini daftar kunci ditulis lepas di dua controller yang berbeda, dan
 * tak ada yang tahu bahwa `nik` tidak pernah terisi di formulir tahap. Uji ini
 * mengunci konsekuensi itu: konteks menentukan kunci, bukan kebiasaan.
 */
class KatalogPrefillTest extends TestCase
{
    public function test_kunci_pendaftaran_memuat_nik_dan_posisi(): void
    {
        $kunci = KatalogPrefill::kunciUntuk('PENDAFTARAN');

        $this->assertContains('nik', $kunci);
        $this->assertContains('posisi', $kunci);
        $this->assertContains('nama', $kunci);
    }

    public function test_kunci_tahap_memuat_kampus_tapi_bukan_nik(): void
    {
        $kunci = KatalogPrefill::kunciUntuk('TAHAP');

        $this->assertContains('kampus', $kunci);
        $this->assertContains('tahunLulus', $kunci);
        $this->assertNotContains('nik', $kunci);
        $this->assertNotContains('posisi', $kunci);
    }

    /**
     * KEDUANYA hanya boleh menawarkan irisan. Formulir yang dipakai di dua
     * tempat tidak boleh memakai kunci yang di salah satunya selalu kosong.
     */
    public function test_konteks_keduanya_hanya_irisan(): void
    {
        $kunci = KatalogPrefill::kunciUntuk('KEDUANYA');

        $this->assertContains('nama', $kunci);
        $this->assertContains('email', $kunci);
        $this->assertContains('hp', $kunci);
        $this->assertNotContains('nik', $kunci);
        $this->assertNotContains('kampus', $kunci);
    }

    public function test_konteks_tak_dikenal_diperlakukan_sebagai_keduanya(): void
    {
        $this->assertSame(
            KatalogPrefill::kunciUntuk('KEDUANYA'),
            KatalogPrefill::kunciUntuk('ENTAH'),
        );
    }

    public function test_saring_membuang_kunci_di_luar_konteks(): void
    {
        $hasil = KatalogPrefill::saring('TAHAP', [
            'nama' => 'Budi',
            'nik' => '1671xxxx',
            'kampus' => 'Universitas Sriwijaya',
        ]);

        $this->assertSame('Budi', $hasil['nama']);
        $this->assertSame('Universitas Sriwijaya', $hasil['kampus']);
        $this->assertArrayNotHasKey('nik', $hasil);
    }

    public function test_saring_mengisi_null_untuk_kunci_yang_belum_ada(): void
    {
        $hasil = KatalogPrefill::saring('PENDAFTARAN', ['nama' => 'Budi']);

        $this->assertArrayHasKey('hp', $hasil);
        $this->assertNull($hasil['hp']);
    }

    public function test_untuk_editor_memberi_label_dan_konteks_tiap_kunci(): void
    {
        $daftar = KatalogPrefill::untukEditor();

        $this->assertNotEmpty($daftar);
        foreach ($daftar as $baris) {
            $this->assertArrayHasKey('kunci', $baris);
            $this->assertNotEmpty($baris['label']);
            $this->assertNotEmpty($baris['konteks']);
        }
    }
}
