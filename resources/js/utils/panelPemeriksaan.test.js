// @vitest-environment jsdom
/**
 * PANEL PEMERIKSAAN — aturan tampilan yang tidak boleh diam-diam berubah.
 *
 * Yang diuji di sini bukan tata letaknya, melainkan empat keputusan yang
 * masing-masing punya akibat di luar layar:
 *
 *   1. TEMUAN SENSITIF TERKUNCI. Payload daftar Worklist tidak membawa isi
 *      temuan catatan hukum; panel hanya boleh menampilkannya setelah dibuka
 *      lewat endpoint yang mencatat pembukaan itu.
 *
 *   2. PERSETUJUAN PUNYA TIGA KEADAAN, bukan dua. "Ada" dan "ada tapi
 *      pernyataannya umum" berbeda — dan bedanya harus terlihat oleh yang
 *      memutuskan, bukan disamarkan jadi satu centang hijau.
 *
 *   3. BENTUK PANGGILAN SERVER. GET & DELETE tidak berbadan; patch/post
 *      berbadan. Menyamakan ketiganya membuat header hilang tanpa gejala.
 *
 *   4. LAMPIRAN MENEMPEL KE KOMPONENNYA, bukan ke aktivitas.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import panel from '../components/career/PanelPemeriksaan.vue';

const { computed, methods } = panel;

/** Instance tiruan: panel ini Options API biasa. */
function bikin(data, berkas = []) {
    const inst = {
        data,
        berkas,
        subTesId: 'abc123',
        sibuk: false,
        galat: '',
        edit: null,
        f: {},
        setujuBuka: false,
        tolakBuka: false,
        fSetuju: '',
        // Ikut data() komponennya: lebar kolom Ringkasan di basis data.
        BATAS: 1000,
        fTanggapan: '',
        $emit: vi.fn(),
        ...methods,
    };

    for (const nama of Object.keys(computed)) {
        Object.defineProperty(inst, nama, { get: () => computed[nama].call(inst), configurable: true });
    }

    return inst;
}

const LATAR = (komponen = []) => ({
    jenis: 'LATAR',
    komponen,
    narasumber: [],
    tersedia: [{ kode: 'CATATAN_HUKUM', nama: 'Catatan Hukum / SKCK', sensitif: true }],
    pilihanAdjudikasi: ['BERSIH', 'PERTIMBANGAN', 'TIDAK_MEMENUHI'],
    persetujuan: null,
    tanggapan: { dimintaPada: null, isi: null, pada: null },
    ringkas: null,
});

describe('panel pemeriksaan', () => {
    beforeEach(() => vi.restoreAllMocks());

    it('temuan sensitif yang terkunci tidak membawa isinya', () => {
        const inst = bikin(LATAR([
            { id: 1, kode: 'CATATAN_HUKUM', nama: 'Catatan Hukum / SKCK', status: 'TEMUAN', sensitif: true, terkunci: true, ringkasan: null },
        ]));

        const k = inst.cari('CATATAN_HUKUM');
        expect(k.terkunci).toBe(true);
        expect(k.ringkasan).toBeNull();
    });

    it('persetujuan: belum dijawab, setuju, menolak', () => {
        // BELUM DIJAWAB. Persetujuan umum di formulir lamaran TIDAK menghitung —
        // pertanyaan "bersediakah diperiksa" baru diajukan di tahap ini.
        const kosong = bikin({
            ...LATAR(),
            persetujuan: { ada: false, ditolak: false, keputusan: null, formulir: { ada: true, pada: '2026-08-19' } },
        });
        expect(kosong.setujuAda).toBe(false);
        expect(kosong.keputusanAda).toBe(false);
        expect(kosong.kelasSetuju).toBe('is-kurang');
        expect(kosong.judulSetuju).toMatch(/belum ditanyakan/i);

        const setuju = bikin({
            ...LATAR(),
            persetujuan: { ada: true, ditolak: false, keputusan: 'SETUJU', sumber: 'TELEPON', eksplisit: true },
        });
        expect(setuju.keputusanAda).toBe(true);
        expect(setuju.kelasSetuju).toBe('is-ok');
        expect(setuju.judulSetuju).toMatch(/setuju diperiksa/i);

        const tolak = bikin({
            ...LATAR(),
            persetujuan: { ada: false, ditolak: true, keputusan: 'TOLAK', sumber: 'TOLAK', eksplisit: true },
        });
        expect(tolak.keputusanAda).toBe(true);
        expect(tolak.kelasSetuju).toBe('is-tolak');
        expect(tolak.judulSetuju).toMatch(/menolak/i);
    });

    it('lampiran hanya milik komponennya sendiri', () => {
        const inst = bikin(
            LATAR([
                { id: 7, kode: 'CATATAN_HUKUM', nama: 'Catatan Hukum', status: 'BERSIH' },
            ]),
            [
                { id: 'a', nama: 'skck.pdf', verifikasiId: 7 },
                { id: 'b', nama: 'lain.pdf', verifikasiId: 99 },
                { id: 'c', nama: 'umum.pdf', verifikasiId: null },
            ],
        );

        const milik = inst.berkasKomponen('CATATAN_HUKUM');
        expect(milik).toHaveLength(1);
        expect(milik[0].nama).toBe('skck.pdf');
    });

    it('komponen yang belum tersimpan tidak punya lampiran', () => {
        const inst = bikin(LATAR(), [{ id: 'a', nama: 'x.pdf', verifikasiId: 7 }]);

        expect(inst.berkasKomponen('CATATAN_HUKUM')).toHaveLength(0);
    });

    it('membuka kunci memakai GET tanpa badan', async () => {
        const axios = (await import('axios')).default;
        const get = vi.spyOn(axios, 'get').mockResolvedValue({ data: { result: LATAR() } });

        const inst = bikin(LATAR());
        await inst.bukaKunci();

        expect(get).toHaveBeenCalledTimes(1);
        const [url, cfg] = get.mock.calls[0];
        expect(url).toMatch(/\/abc123\/pemeriksaan$/);
        // Argumen kedua HARUS config, bukan badan permintaan.
        expect(cfg).toHaveProperty('headers');
        expect(inst.$emit).toHaveBeenCalledWith('perbarui', expect.any(Object));
    });

    it('menyimpan komponen memakai PATCH berbadan', async () => {
        const axios = (await import('axios')).default;
        const patch = vi.spyOn(axios, 'patch').mockResolvedValue({ data: { result: LATAR() } });

        const inst = bikin(LATAR());
        inst.f = { status: 'TEMUAN', tingkatTemuan: 'TINGGI', sumber: 'Polres', ringkasan: 'x', tanggalSelesai: '' };
        await inst.simpanKomponen({ kode: 'CATATAN_HUKUM' });

        const [, badan, cfg] = patch.mock.calls[0];
        expect(badan.jenisKode).toBe('CATATAN_HUKUM');
        expect(badan.tingkatTemuan).toBe('TINGGI');
        expect(cfg).toHaveProperty('headers');
    });

    it('tingkat temuan tidak ikut terkirim bila statusnya bukan TEMUAN', async () => {
        const axios = (await import('axios')).default;
        const patch = vi.spyOn(axios, 'patch').mockResolvedValue({ data: { result: LATAR() } });

        const inst = bikin(LATAR());
        inst.f = { status: 'BERSIH', tingkatTemuan: 'TINGGI', ringkasan: '' };
        await inst.simpanKomponen({ kode: 'PENDIDIKAN' });

        expect(patch.mock.calls[0][1].tingkatTemuan).toBeNull();
    });

    it('rekomendasi narasumber hanya dikirim bila memang terhubung', async () => {
        const axios = (await import('axios')).default;
        const post = vi.spyOn(axios, 'post').mockResolvedValue({ data: { result: {} } });

        const inst = bikin({ ...LATAR(), jenis: 'REFERENSI' });
        inst.edit = 'BARU';
        inst.f = { nama: 'Budi', statusKontak: 'TIDAK_TERHUBUNG', rekomendasi: 'YA', percobaan: 3 };
        await inst.simpanRef();

        const badan = post.mock.calls[0][1];
        expect(badan.rekomendasi).toBeNull();
        expect(badan.percobaan).toBe(3);
    });

    it('persetujuan lewat telepon: satu tekan, tanpa isian', async () => {
        const axios = (await import('axios')).default;
        const patch = vi.spyOn(axios, 'patch').mockResolvedValue({ data: { result: LATAR() } });

        const inst = bikin(LATAR());
        await inst.putusSetuju();

        const [url, badan] = patch.mock.calls[0];
        expect(url).toMatch(/\/persetujuan$/);
        expect(badan).toEqual({ sumber: 'TELEPON' });
    });

    it('penolakan tanpa alasan tidak dikirim sama sekali', async () => {
        const axios = (await import('axios')).default;
        const patch = vi.spyOn(axios, 'patch').mockResolvedValue({ data: { result: LATAR() } });

        const inst = bikin(LATAR());
        inst.fSetuju = '   ';
        const ok = await inst.putusTolak();

        expect(ok).toBe(false);
        expect(patch).not.toHaveBeenCalled();
    });

    it('penolakan beralasan terkirim sebagai TOLAK', async () => {
        const axios = (await import('axios')).default;
        const patch = vi.spyOn(axios, 'patch').mockResolvedValue({ data: { result: LATAR() } });

        const inst = bikin(LATAR());
        inst.fSetuju = 'Keberatan catatan hukumnya diperiksa.';
        await inst.putusTolak();

        expect(patch.mock.calls[0][1]).toEqual({
            sumber: 'TOLAK',
            keterangan: 'Keberatan catatan hukumnya diperiksa.',
        });
    });

    it('penolakan yang tercatat menutup pekerjaannya', () => {
        const inst = bikin({
            ...LATAR(),
            persetujuan: { ada: false, ditolak: true, pernyataan: 'Keberatan.', sumber: 'TOLAK' },
        });

        expect(inst.ditolak).toBe(true);
        expect(inst.keputusanAda).toBe(true);
        expect(inst.kelasSetuju).toBe('is-tolak');
        expect(inst.judulSetuju).toMatch(/menolak/i);
    });

    it('ringkasan yang melebihi lebar kolom ditahan sebelum dikirim', async () => {
        const axios = (await import('axios')).default;
        const patch = vi.spyOn(axios, 'patch').mockResolvedValue({ data: { result: LATAR() } });

        const inst = bikin(LATAR());
        // Editor menyimpan HTML-nya, jadi tag ikut dihitung terhadap VARCHAR(1000).
        inst.f = { status: 'BERSIH', ringkasan: '<p>' + 'x'.repeat(1000) + '</p>' };
        const ok = await inst.simpanKomponen({ kode: 'PENDIDIKAN' });

        expect(ok).toBe(false);
        expect(patch).not.toHaveBeenCalled();
        expect(inst.galat).toMatch(/melebihi 1000/i);
    });

    it('ringkasan yang masih muat tetap dikirim', async () => {
        const axios = (await import('axios')).default;
        const patch = vi.spyOn(axios, 'patch').mockResolvedValue({ data: { result: LATAR() } });

        const inst = bikin(LATAR());
        inst.f = { status: 'BERSIH', ringkasan: '<p>Ijazah terverifikasi.</p>' };
        await inst.simpanKomponen({ kode: 'PENDIDIKAN' });

        expect(patch).toHaveBeenCalledTimes(1);
        expect(inst.lebihPanjang(inst.f.ringkasan)).toBe(false);
    });

    it('galat dari server ditampilkan, bukan ditelan', async () => {
        const axios = (await import('axios')).default;
        vi.spyOn(axios, 'patch').mockRejectedValue({ response: { data: { message: 'Isi ringkasan temuannya' } } });

        const inst = bikin(LATAR());
        inst.f = { status: 'TEMUAN', ringkasan: '' };
        const ok = await inst.simpanKomponen({ kode: 'CATATAN_HUKUM' });

        expect(ok).toBe(false);
        expect(inst.galat).toBe('Isi ringkasan temuannya');
    });
});
