/**
 * Uji AUTOFILL identitas dari akun (`prefill` pada field biasa).
 *
 * Yang dijaga di sini adalah keputusan penting di baliknya: field TIDAK dikunci.
 * 22 dari 35 akun belum punya NIK, dan `periksaLangkah` melewatkan validasi
 * untuk prefill terkunci — kalau dikunci, kolom wajib yang kosong akan lolos
 * diam-diam. Uji terakhir mengunci perilaku itu supaya tidak berubah tanpa
 * sengaja di kemudian hari.
 */
import { describe, expect, it } from 'vitest';
import { jawabanAwal, periksaLangkah } from './aturan';

const SKEMA = {
    langkah: [
        {
            judul: 'Data Diri',
            bagian: [
                {
                    judul: 'Identitas',
                    field: [
                        { key: 'nama_lengkap', label: 'Nama Lengkap', tipe: 'text', wajib: true, prefill: 'nama' },
                        { key: 'email', label: 'Email', tipe: 'email', wajib: true, prefill: 'email' },
                        { key: 'no_hp', label: 'No. HP', tipe: 'phone', wajib: true, prefill: 'hp' },
                        { key: 'nik', label: 'NIK', tipe: 'text', wajib: true, prefill: 'nik' },
                        { key: 'kota', label: 'Kota', tipe: 'text', wajib: false },
                    ],
                },
            ],
        },
    ],
};

const AKUN_LENGKAP = {
    nama: 'Budi Santoso',
    email: 'budi@contoh.id',
    hp: '628123456789',
    nik: '3273010101990001',
};

describe('semai dari profil akun', () => {
    it('mengisi keempat field identitas', () => {
        const jawaban = jawabanAwal(SKEMA, AKUN_LENGKAP);
        expect(jawaban.nama_lengkap).toBe('Budi Santoso');
        expect(jawaban.email).toBe('budi@contoh.id');
        expect(jawaban.no_hp).toBe('628123456789');
        expect(jawaban.nik).toBe('3273010101990001');
    });

    it('membiarkan field tanpa prefill tetap kosong', () => {
        expect(jawabanAwal(SKEMA, AKUN_LENGKAP).kota).toBe('');
    });

    it('tidak melempar error saat profil kosong', () => {
        const jawaban = jawabanAwal(SKEMA, {});
        expect(jawaban.nama_lengkap).toBe('');
        expect(jawaban.nik).toBe('');
    });
});

describe('akun tanpa NIK — 63% akun nyata', () => {
    const tanpaNik = { ...AKUN_LENGKAP, nik: null };

    it('NIK dibiarkan kosong, bukan diisi "null"', () => {
        expect(jawabanAwal(SKEMA, tanpaNik).nik).toBe('');
    });

    it('NIK kosong TETAP ditolak validasi — inilah alasan field tidak dikunci', () => {
        const galat = periksaLangkah(SKEMA.langkah[0], jawabanAwal(SKEMA, tanpaNik));
        expect(galat.some((g) => g.includes('NIK'))).toBe(true);
    });

    it('field lain yang terisi dari akun tidak ikut mengeluh', () => {
        const galat = periksaLangkah(SKEMA.langkah[0], jawabanAwal(SKEMA, tanpaNik));
        expect(galat.some((g) => g.includes('Nama Lengkap'))).toBe(false);
        expect(galat.some((g) => g.includes('Email'))).toBe(false);
    });
});

/**
 * Pembanding: kalau NIK dijadikan `tipe: prefill` tanpa `buka_jika`.
 *
 * FieldRenderer mengunci field seperti itu (`prefillTerkunci`), TAPI
 * `periksaLangkah` tetap memvalidasinya — `syaratTerpenuhi(undefined)`
 * mengembalikan true, sehingga cabang lewat-validasi tidak pernah kena.
 *
 * Hasilnya buntu total bagi 63% akun yang belum punya NIK: kolomnya terkunci,
 * kosong, dan formulir menolak maju dengan galat yang tidak bisa mereka
 * selesaikan. Inilah alasan NIK dibiarkan sebagai field biasa ber-`prefill`.
 */
describe('pembanding: NIK sebagai prefill terkunci', () => {
    const skemaTerkunci = {
        langkah: [
            {
                judul: 'Data Diri',
                bagian: [
                    {
                        judul: 'Identitas',
                        field: [{ key: 'nik', label: 'NIK', tipe: 'prefill', wajib: true, prefill: 'nik' }],
                    },
                ],
            },
        ],
    };

    it('akun tanpa NIK jadi buntu: kolom terkunci tapi tetap dituntut wajib', () => {
        const jawaban = jawabanAwal(skemaTerkunci, { nik: null });
        expect(jawaban.nik).toBe('');

        const galat = periksaLangkah(skemaTerkunci.langkah[0], jawaban);
        expect(galat).toContain('"NIK" wajib diisi.');
    });

    it('prefill dengan buka_jika yang belum terpenuhi memang dilewati validasinya', () => {
        const skema = {
            langkah: [
                {
                    judul: 'Data Diri',
                    bagian: [
                        {
                            judul: 'Identitas',
                            field: [
                                {
                                    key: 'v_nama',
                                    label: 'Nama',
                                    tipe: 'prefill',
                                    wajib: true,
                                    prefill: 'nama',
                                    buka_jika: { field: 'data_sesuai', operator: '=', nilai: 'Perlu diperbarui' },
                                },
                                { key: 'data_sesuai', label: 'Sesuai?', tipe: 'select', opsi: ['Sesuai', 'Perlu diperbarui'] },
                            ],
                        },
                    ],
                },
            ],
        };

        // Terkunci (data dianggap sesuai) -> tidak dituntut.
        expect(periksaLangkah(skema.langkah[0], { v_nama: '', data_sesuai: 'Sesuai' })).toHaveLength(0);

        // Dibuka untuk disunting -> jadi isian biasa, wajib berlaku lagi.
        const galat = periksaLangkah(skema.langkah[0], { v_nama: '', data_sesuai: 'Perlu diperbarui' });
        expect(galat).toContain('"Nama" wajib diisi.');
    });
});
