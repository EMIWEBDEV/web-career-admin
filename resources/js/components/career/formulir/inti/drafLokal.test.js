// @vitest-environment jsdom
/**
 * Uji DRAF LOKAL pengisian formulir lamaran.
 *
 * Yang dijaga di sini bukan sekadar "tersimpan lalu terbaca", melainkan tiga
 * hal yang kalau salah justru MERUGIKAN kandidat dibanding tidak ada draf sama
 * sekali:
 *
 *   1. Nama berkas yang dipulihkan tanpa isinya → formulir tampak lengkap,
 *      lalu yang sampai ke server kosong.
 *   2. Draf milik akun lain di komputer bersama → NIK orang lain ikut terkirim.
 *   3. Draf dari susunan skema lama dituang ke formulir yang sudah berubah.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bacaDraf, hapusDraf, kunciDraf, petaSkema, sapuDrafKedaluwarsa, tulisDraf } from './drafLokal';

const HARI = 24 * 60 * 60 * 1000;

const SKEMA = {
    layout: 'BERTAHAP',
    langkah: [
        {
            kode: 'DIRI',
            bagian: [
                {
                    judul: 'Data Diri',
                    field: [
                        { key: 'nama_lengkap', tipe: 'text' },
                        { key: 'nik', tipe: 'prefill' },
                        { key: 'semester', tipe: 'number' },
                    ],
                },
            ],
        },
        {
            kode: 'BERKAS',
            bagian: [
                {
                    judul: 'Dokumen',
                    field: [
                        { key: 'cv', tipe: 'file' },
                        { key: 'foto_verifikasi', tipe: 'foto' },
                    ],
                },
                {
                    key: 'pengalaman',
                    judul: 'Pengalaman',
                    berulang: true,
                    field: [
                        { key: 'perusahaan', tipe: 'text' },
                        { key: 'surat_referensi', tipe: 'file' },
                    ],
                },
            ],
        },
    ],
};

const peta = petaSkema(SKEMA);
const KUNCI = kunciDraf({ identitas: 'kandidat@evo.test', lowonganId: 42 });

beforeEach(() => {
    localStorage.clear();
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-08-07T09:00:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
});

describe('kunciDraf', () => {
    it('memisahkan draf per akun dan per lowongan', () => {
        const a = kunciDraf({ identitas: 'a@evo.test', lowonganId: 42 });
        const b = kunciDraf({ identitas: 'b@evo.test', lowonganId: 42 });
        const c = kunciDraf({ identitas: 'a@evo.test', lowonganId: 43 });

        expect(a).not.toBe(b);
        expect(a).not.toBe(c);
    });

    it('tidak menaruh surel apa adanya di kunci localStorage', () => {
        expect(kunciDraf({ identitas: 'kandidat@evo.test', lowonganId: 42 })).not.toContain('kandidat@evo.test');
    });

    it('menolak menyimpan tanpa lowongan yang jelas', () => {
        expect(kunciDraf({ identitas: 'a@evo.test' })).toBeNull();
        expect(kunciDraf({})).toBeNull();
        expect(kunciDraf()).toBeNull();
    });
});

describe('petaSkema', () => {
    it('menandai field berkas & foto di akar, dan yang di dalam bagian berulang secara terpisah', () => {
        expect([...peta.takDisimpan].sort()).toEqual(['cv', 'foto_verifikasi', 'nik']);
        expect([...(peta.takDisimpanBaris.get('pengalaman') || [])]).toEqual(['surat_referensi']);
    });

    it('membedakan berkas dari field terkunci — hanya berkas yang perlu dilampirkan ulang', () => {
        expect(peta.punyaBerkas).toBe(true);

        const tanpaBerkas = petaSkema({
            layout: 'BERTAHAP',
            langkah: [{ kode: 'A', bagian: [{ field: [{ key: 'nik', tipe: 'prefill' }] }] }],
        });
        expect(tanpaBerkas.punyaBerkas).toBe(false);
        expect(tanpaBerkas.takDisimpan.has('nik')).toBe(true);
    });

    it('sidik jari berubah saat susunan field berubah, tetap sama saat tidak', () => {
        expect(petaSkema(SKEMA).tanda).toBe(peta.tanda);

        const diubah = JSON.parse(JSON.stringify(SKEMA));
        diubah.langkah[0].bagian[0].field.push({ key: 'domisili', tipe: 'text' });
        expect(petaSkema(diubah).tanda).not.toBe(peta.tanda);
    });
});

describe('tulis & baca', () => {
    it('mengembalikan jawaban dan langkah yang tersimpan', () => {
        tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'Rendi', semester: 5 }, langkah: 2, peta });

        const draf = bacaDraf(KUNCI, peta.tanda);
        expect(draf.jawaban).toEqual({ nama_lengkap: 'Rendi', semester: 5 });
        expect(draf.langkah).toBe(2);
    });

    it('TIDAK menyimpan nama berkas, foto, maupun field terkunci', () => {
        tulisDraf(KUNCI, {
            jawaban: {
                nama_lengkap: 'Rendi',
                nik: '1671xxxxxxxxxxxx',
                cv: 'cv-rendi.pdf',
                foto_verifikasi: 'foto_verifikasi-verifikasi.jpg',
            },
            langkah: 1,
            peta,
        });

        const { jawaban } = bacaDraf(KUNCI, peta.tanda);
        expect(jawaban).toEqual({ nama_lengkap: 'Rendi' });
    });

    it('membersihkan berkas di dalam baris bagian berulang tanpa membuang barisnya', () => {
        tulisDraf(KUNCI, {
            jawaban: {
                pengalaman: [
                    { perusahaan: 'EVO', surat_referensi: 'ref.pdf' },
                    { perusahaan: 'Nusa', surat_referensi: '' },
                ],
            },
            langkah: 1,
            peta,
        });

        expect(bacaDraf(KUNCI, peta.tanda).jawaban.pengalaman).toEqual([
            { perusahaan: 'EVO' },
            { perusahaan: 'Nusa' },
        ]);
    });

    it('langkah negatif atau bukan angka diperlakukan sebagai langkah pertama', () => {
        tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'A' }, langkah: -3, peta });
        expect(bacaDraf(KUNCI, peta.tanda).langkah).toBe(0);

        tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'A' }, langkah: 'dua', peta });
        expect(bacaDraf(KUNCI, peta.tanda).langkah).toBe(0);
    });

    it('tidak menulis apa pun bila kuncinya null', () => {
        expect(tulisDraf(null, { jawaban: { a: 1 }, langkah: 0, peta })).toBe(false);
        expect(localStorage.length).toBe(0);
    });

    it('gagal menyimpan tidak melempar galat — pengisian tidak boleh berhenti', () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('QuotaExceededError');
        });

        expect(() => tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'A' }, langkah: 0, peta })).not.toThrow();
        expect(tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'A' }, langkah: 0, peta })).toBe(false);
    });
});

describe('draf yang tidak layak dipakai', () => {
    it('skema sudah berganti susunan → draf diabaikan DAN dibuang', () => {
        tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'Rendi' }, langkah: 2, peta });

        expect(bacaDraf(KUNCI, 'sidik-lain')).toBeNull();
        expect(localStorage.getItem(KUNCI)).toBeNull();
    });

    it('lewat 7 hari → draf diabaikan DAN dibuang', () => {
        tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'Rendi' }, langkah: 2, peta });

        vi.setSystemTime(new Date(Date.now() + 7 * HARI + 1000));
        expect(bacaDraf(KUNCI, peta.tanda)).toBeNull();
        expect(localStorage.getItem(KUNCI)).toBeNull();
    });

    it('masih di dalam 7 hari → tetap dipakai', () => {
        tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'Rendi' }, langkah: 2, peta });

        vi.setSystemTime(new Date(Date.now() + 6 * HARI));
        expect(bacaDraf(KUNCI, peta.tanda).jawaban.nama_lengkap).toBe('Rendi');
    });

    it('isi simpanan rusak / bukan objek jawaban → null, tidak melempar', () => {
        localStorage.setItem(KUNCI, '{bukan json');
        expect(bacaDraf(KUNCI, peta.tanda)).toBeNull();

        localStorage.setItem(KUNCI, JSON.stringify({ v: 1, tanda: peta.tanda, pada: Date.now(), jawaban: [1, 2] }));
        expect(bacaDraf(KUNCI, peta.tanda)).toBeNull();
    });

    it('tidak ada draf sama sekali → null', () => {
        expect(bacaDraf(KUNCI, peta.tanda)).toBeNull();
        expect(bacaDraf(null, peta.tanda)).toBeNull();
    });
});

describe('sapuDrafKedaluwarsa', () => {
    it('membuang draf lowongan lain yang sudah kedaluwarsa, menyisakan yang masih segar', () => {
        const lama = kunciDraf({ identitas: 'kandidat@evo.test', lowonganId: 7 });
        tulisDraf(lama, { jawaban: { nama_lengkap: 'Lama' }, langkah: 1, peta });

        vi.setSystemTime(new Date(Date.now() + 8 * HARI));
        tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'Baru' }, langkah: 1, peta });

        expect(sapuDrafKedaluwarsa()).toBe(1);
        expect(localStorage.getItem(lama)).toBeNull();
        expect(localStorage.getItem(KUNCI)).not.toBeNull();
    });

    it('tidak menyentuh kunci milik fitur lain', () => {
        localStorage.setItem('evo_career_apps', '[]');
        localStorage.setItem('token', 'abc');

        expect(sapuDrafKedaluwarsa()).toBe(0);
        expect(localStorage.getItem('evo_career_apps')).toBe('[]');
        expect(localStorage.getItem('token')).toBe('abc');
    });
});

describe('hapusDraf', () => {
    it('membuang draf yang disebut saja', () => {
        const lain = kunciDraf({ identitas: 'kandidat@evo.test', lowonganId: 9 });
        tulisDraf(KUNCI, { jawaban: { nama_lengkap: 'A' }, langkah: 0, peta });
        tulisDraf(lain, { jawaban: { nama_lengkap: 'B' }, langkah: 0, peta });

        hapusDraf(KUNCI);

        expect(localStorage.getItem(KUNCI)).toBeNull();
        expect(localStorage.getItem(lain)).not.toBeNull();
    });

    it('kunci null diabaikan diam-diam', () => {
        expect(() => hapusDraf(null)).not.toThrow();
    });
});
