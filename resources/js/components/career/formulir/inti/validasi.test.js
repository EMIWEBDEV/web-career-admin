/**
 * Uji ATURAN VALIDASI skema.
 *
 * Tiap kasus di sini pernah bisa lolos ke publish dan baru ketahuan salah saat
 * kandidat mengisi — field-nya diam-diam tidak pernah muncul, tanpa pesan galat
 * apa pun. Itu sebabnya masing-masing dapat uji sendiri.
 */
import { describe, expect, it } from 'vitest';
import { validasiSkema } from './schema';

function skema(field, extra = {}) {
    return {
        ...extra,
        langkah: [{ judul: 'L', bagian: [{ judul: 'B', field }] }],
    };
}

const teks = (key, lain = {}) => ({ key, label: key, tipe: 'text', ...lain });

function galat(hasil) {
    return hasil.errors.join(' | ');
}

describe('aturan yang sudah ada tetap berjalan', () => {
    it('menolak skema tanpa langkah', () => {
        expect(validasiSkema({ langkah: [] }).ok).toBe(false);
    });

    it('menolak key duplikat', () => {
        const h = validasiSkema(skema([teks('nama'), teks('nama')]));
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/dipakai lebih dari sekali/);
    });

    it('menerima skema paling sederhana', () => {
        expect(validasiSkema(skema([teks('nama')])).ok).toBe(true);
    });
});

describe('aturan dari katalog tipe', () => {
    it('menolak referensi tanpa sumber', () => {
        const h = validasiSkema(skema([{ key: 'kampus', label: 'Kampus', tipe: 'referensi' }]));
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/sumber/i);
    });

    it('menolak min lebih besar dari maks', () => {
        const h = validasiSkema(skema([{ key: 'ipk', label: 'IPK', tipe: 'number', min: 4, maks: 0 }]));
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/minimum/i);
    });

    it('menolak pilihan dengan satu opsi', () => {
        const h = validasiSkema(skema([{ key: 'setuju', label: 'Setuju', tipe: 'select', opsi: ['Ya'] }]));
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/2 opsi/);
    });

    /**
     * Berbeda dari kasus di atas, format berkas yang kosong TIDAK digagalkan
     * melainkan disembuhkan jadi `.pdf` oleh nilai bawaan katalog. Menolaknya
     * akan memblokir skema yang bisa diperbaiki sendiri tanpa menebak apa pun —
     * dan `.pdf` memang satu-satunya format yang masuk akal sebagai bawaan.
     * Jaring pengamannya tetap ada di galatTipe (lihat katalogField.test.js),
     * untuk pemakai yang memeriksa field mentah tanpa lewat normalisasi.
     */
    it('menyembuhkan file tanpa format berkas alih-alih menggagalkannya', () => {
        const h = validasiSkema(skema([{ key: 'cv', label: 'CV', tipe: 'file', accept: '' }]));
        expect(h.errors).toEqual([]);
        expect(h.skema.langkah[0].bagian[0].field[0].accept).toBe('.pdf');
    });
});

describe('rujukan antar-field', () => {
    it('menolak beda_dengan yang menunjuk key tak dikenal', () => {
        const h = validasiSkema(skema([teks('hp_darurat', { beda_dengan: 'hp_saya' })]));
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/hp_saya/);
    });

    it('menolak reset_anak yang menunjuk key tak dikenal', () => {
        const h = validasiSkema(
            skema([{ key: 'jenjang', label: 'Jenjang', tipe: 'referensi', sumber: 'jenjang', reset_anak: ['kampus'] }]),
        );
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/kampus/);
    });

    it('menolak bergantung dan saring yang menunjuk key tak dikenal', () => {
        const h = validasiSkema(
            skema([
                {
                    key: 'kampus',
                    label: 'Kampus',
                    tipe: 'referensi',
                    sumber: 'kampus',
                    bergantung: { jenjang: 'jenjang_pendidikan' },
                    saring: { jenis: 'jenis_institusi' },
                },
            ]),
        );
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/jenjang_pendidikan/);
        expect(galat(h)).toMatch(/jenis_institusi/);
    });

    it('menerima rujukan yang key-nya benar-benar ada', () => {
        const h = validasiSkema(
            skema([
                { key: 'jenjang', label: 'Jenjang', tipe: 'referensi', sumber: 'jenjang', reset_anak: ['kampus'] },
                {
                    key: 'kampus',
                    label: 'Kampus',
                    tipe: 'referensi',
                    sumber: 'kampus',
                    bergantung: { jenjang: 'jenjang' },
                },
            ]),
        );
        expect(h.errors).toEqual([]);
    });
});

describe('tampil_jika yang mengacu ke depan', () => {
    it('menolak acuan ke field yang letaknya sesudahnya', () => {
        const h = validasiSkema(skema([teks('a', { tampil_jika: { field: 'b', operator: '=', nilai: 'ya' } }), teks('b')]));
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/sesudah/i);
    });

    it('menerima acuan ke field sebelumnya', () => {
        const h = validasiSkema(
            skema([teks('a'), teks('b', { tampil_jika: { field: 'a', operator: '=', nilai: 'ya' } })]),
        );
        expect(h.errors).toEqual([]);
    });

    it('menolak acuan ke key yang tidak ada sama sekali', () => {
        const h = validasiSkema(skema([teks('a', { tampil_jika: { field: 'hantu', operator: '=', nilai: 'ya' } })]));
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/hantu/);
    });

    it('lebar_jika boleh mengacu dirinya sendiri', () => {
        const h = validasiSkema(
            skema([
                {
                    key: 'status',
                    label: 'Status',
                    tipe: 'select',
                    opsi: ['Mahasiswa', 'Lulus'],
                    lebar_jika: { field: 'status', operator: '=', nilai: 'Mahasiswa', lebar_persen: 50 },
                },
            ]),
        );
        expect(h.errors).toEqual([]);
    });
});

describe('kunci isi otomatis', () => {
    const s = skema([teks('nama', { prefill: 'nik' })]);

    it('dilewati bila daftar kunci tidak diberikan', () => {
        expect(validasiSkema(s).ok).toBe(true);
    });

    it('menolak kunci di luar daftar konteks formulir', () => {
        const h = validasiSkema(s, { kunciPrefill: ['nama', 'email', 'hp'] });
        expect(h.ok).toBe(false);
        expect(galat(h)).toMatch(/nik/);
    });

    it('menerima kunci yang tersedia', () => {
        expect(validasiSkema(s, { kunciPrefill: ['nama', 'nik'] }).errors).toEqual([]);
    });
});

describe('peringatan (tidak menggagalkan)', () => {
    it('memperingatkan foto yang key-nya bukan foto_verifikasi', () => {
        const h = validasiSkema(skema([{ key: 'selfie', label: 'Selfie', tipe: 'foto' }]));
        expect(h.ok).toBe(true);
        expect(h.peringatan.join(' ')).toMatch(/foto_verifikasi/);
    });

    it('tidak memperingatkan bila key-nya sudah benar', () => {
        const h = validasiSkema(skema([{ key: 'foto_verifikasi', label: 'Foto', tipe: 'foto' }]));
        expect(h.peringatan).toEqual([]);
    });
});
