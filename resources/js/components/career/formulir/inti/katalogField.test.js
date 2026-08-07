/**
 * Uji KATALOG FIELD — sumber kebenaran tunggal aturan tipe->properti.
 *
 * Yang dikunci di sini adalah janji katalog kepada pemakainya: setiap tipe
 * punya entri, properti universal berlaku di mana-mana, dan membersihkan field
 * benar-benar MEMBUANG properti yang bukan miliknya. Yang terakhir itu inti
 * perbaikan ini — sebelumnya properti sisa menempel selamanya di JSON skema.
 */
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    KATALOG_FIELD,
    PROPERTI_UNIVERSAL,
    TIPE_VALID,
    bawaanTipe,
    bersihkanField,
    bolehPunya,
    daftarTipe,
    galatTipe,
    propertiTipe,
} from './katalogField';

describe('bentuk katalog', () => {
    it('setiap tipe punya label dan daftar properti', () => {
        Object.entries(KATALOG_FIELD).forEach(([tipe, def]) => {
            expect(def.label, `${tipe} tanpa label`).toBeTruthy();
            expect(Array.isArray(def.properti), `${tipe} tanpa properti`).toBe(true);
        });
    });

    it('TIPE_VALID persis sebanyak kunci katalog', () => {
        expect(TIPE_VALID.size).toBe(Object.keys(KATALOG_FIELD).length);
        Object.keys(KATALOG_FIELD).forEach((t) => expect(TIPE_VALID.has(t)).toBe(true));
    });

    it('tidak ada tipe yang mendaftarkan properti universal sebagai properti khusus', () => {
        Object.entries(KATALOG_FIELD).forEach(([tipe, def]) => {
            def.properti.forEach((p) => {
                expect(PROPERTI_UNIVERSAL.includes(p), `${tipe} mengulang universal "${p}"`).toBe(false);
            });
        });
    });

    it('daftarTipe memuat tipe yang selama ini tak muncul di dropdown admin', () => {
        const nilai = daftarTipe().map((t) => t.value);
        ['currency', 'bulan', 'tahun', 'prefill'].forEach((t) => expect(nilai).toContain(t));
    });
});

/**
 * Katalog hanya berguna kalau isinya benar-benar terhubung ke renderer. Dua uji
 * berikut membaca berkas rendernya sebagai teks dan memastikan janji katalog
 * tidak melebihi kenyataan — menawarkan tipe yang tak bisa digambar atau kotak
 * yang tak berefek sama-sama membuat admin menebak.
 */
describe('katalog sejalan dengan renderer', () => {
    const RENDERER = readFileSync(new URL('./FieldRenderer.vue', import.meta.url), 'utf8');

    /**
     * `email` sengaja tidak punya cabang sendiri: ia jatuh ke <el-input v-else>
     * di akhir FieldRenderer dan tampil persis seperti teks biasa. Yang
     * membedakannya cuma pemeriksaan format di aturan.js — dan itu memang tidak
     * butuh kotak render tersendiri.
     */
    const TANPA_CABANG = new Set(['email']);

    it('setiap tipe punya cabang render', () => {
        Object.keys(KATALOG_FIELD)
            .filter((tipe) => !TANPA_CABANG.has(tipe))
            .forEach((tipe) => {
                expect(RENDERER, `tipe "${tipe}" tanpa cabang render`).toContain(`field.tipe === '${tipe}'`);
            });
    });

    it('tipe tanpa cabang render tetap punya pemeriksaan formatnya sendiri', () => {
        const ATURAN = readFileSync(new URL('./aturan.js', import.meta.url), 'utf8');
        TANPA_CABANG.forEach((tipe) => {
            expect(ATURAN, `tipe "${tipe}" tidak dirender khusus DAN tidak diperiksa khusus`).toMatch(
                new RegExp(`tipeEfektif !== '${tipe}'`),
            );
        });
    });

    it('setiap properti khusus benar-benar dibaca renderer atau aturan', () => {
        // SatuHalaman.vue ikut dibaca karena `reset_anak` ditangani di layout,
        // bukan di renderer field.
        const dibaca =
            ['./aturan.js', './BagianRenderer.vue', '../template-1/layout/SatuHalaman.vue']
                .map((p) => readFileSync(new URL(p, import.meta.url), 'utf8'))
                .join('\n') + RENDERER;

        Object.entries(KATALOG_FIELD).forEach(([tipe, def]) => {
            def.properti.forEach((p) => {
                expect(dibaca, `properti "${p}" (${tipe}) tidak dibaca siapa pun`).toMatch(new RegExp(`\\.${p}\\b`));
            });
        });
    });
});

describe('bolehPunya', () => {
    it('properti universal berlaku untuk semua tipe', () => {
        expect(bolehPunya('foto', 'wajib')).toBe(true);
        expect(bolehPunya('consent', 'tampil_jika')).toBe(true);
    });

    it('placeholder tidak berlaku untuk tipe yang renderernya tak membacanya', () => {
        expect(bolehPunya('radio', 'ph')).toBe(false);
        expect(bolehPunya('checkbox', 'ph')).toBe(false);
        expect(bolehPunya('foto', 'ph')).toBe(false);
        expect(bolehPunya('consent', 'ph')).toBe(false);
    });

    it('syarat auto-gugur tidak berlaku untuk jawaban yang tak bisa dibandingkan', () => {
        expect(bolehPunya('textarea', 'dapat_disaring')).toBe(false);
        expect(bolehPunya('file', 'dapat_disaring')).toBe(false);
        expect(bolehPunya('foto', 'dapat_disaring')).toBe(false);
        expect(bolehPunya('consent', 'dapat_disaring')).toBe(false);
        expect(bolehPunya('select', 'dapat_disaring')).toBe(true);
    });

    it('tipe tak dikenal tidak punya properti khusus apa pun', () => {
        expect(propertiTipe('tidak_ada')).toEqual([]);
        expect(bolehPunya('tidak_ada', 'ph')).toBe(false);
        expect(bolehPunya('tidak_ada', 'wajib')).toBe(true);
    });
});

describe('bersihkanField', () => {
    it('membuang opsi saat select berubah jadi text', () => {
        const f = bersihkanField({ key: 'a', label: 'A', tipe: 'text', opsi: ['x', 'y'], bebas_ketik: true });
        expect(f.opsi).toBeUndefined();
        expect(f.bebas_ketik).toBeUndefined();
        expect(f.key).toBe('a');
    });

    it('membuang aturan berkas saat file berubah jadi text', () => {
        const f = bersihkanField({ key: 'a', tipe: 'text', accept: '.pdf', maks_mb: 5 });
        expect(f.accept).toBeUndefined();
        expect(f.maks_mb).toBeUndefined();
    });

    it('mempertahankan properti universal apa pun tipenya', () => {
        const f = bersihkanField({
            key: 'a',
            label: 'A',
            tipe: 'foto',
            wajib: true,
            bantuan: 'Ambil dari kamera',
            tampil_jika: { field: 'b', operator: '=', nilai: 'ya' },
            lebar_jika: { field: 'b', operator: '=', nilai: 'ya', lebar_persen: 50 },
        });
        expect(f.wajib).toBe(true);
        expect(f.bantuan).toBe('Ambil dari kamera');
        expect(f.tampil_jika).toEqual({ field: 'b', operator: '=', nilai: 'ya' });
        expect(f.lebar_jika.lebar_persen).toBe(50);
    });

    it('mengisi bawaan tipe yang belum ada, tanpa menimpa yang sudah diisi', () => {
        expect(bersihkanField({ key: 'a', tipe: 'select' }).opsi).toEqual(['Opsi 1', 'Opsi 2']);
        expect(bersihkanField({ key: 'a', tipe: 'select', opsi: ['Ya'] }).opsi).toEqual(['Ya']);
        expect(bersihkanField({ key: 'a', tipe: 'file' }).accept).toBe('.pdf');
    });

    it('memaksa berkas dan foto memakan satu baris penuh', () => {
        expect(bersihkanField({ key: 'a', tipe: 'file' }).lebar_persen).toBe(100);
        expect(bersihkanField({ key: 'a', tipe: 'foto' }).penuh).toBe(true);
    });

    it('bawaanTipe mengembalikan salinan, bukan rujukan bersama', () => {
        const a = bawaanTipe('select');
        a.opsi.push('Opsi 3');
        expect(bawaanTipe('select').opsi).toEqual(['Opsi 1', 'Opsi 2']);
    });
});

describe('galatTipe', () => {
    it('menuntut minimal dua opsi untuk pilihan', () => {
        expect(galatTipe({ tipe: 'select', opsi: ['Ya'] })).toMatch(/2 opsi/);
        expect(galatTipe({ tipe: 'select', opsi: ['Ya', 'Tidak'] })).toBeNull();
    });

    it('select yang opsinya datang dari konteks tidak dituntut punya opsi statis', () => {
        expect(galatTipe({ tipe: 'select', sumber_opsi: 'daftarKampus', opsi: [] })).toBeNull();
    });

    it('menuntut sumber untuk referensi', () => {
        expect(galatTipe({ tipe: 'referensi' })).toMatch(/sumber/i);
        expect(galatTipe({ tipe: 'referensi', sumber: 'kampus' })).toBeNull();
    });

    it('menuntut format berkas untuk file', () => {
        expect(galatTipe({ tipe: 'file' })).toMatch(/format/i);
        expect(galatTipe({ tipe: 'file', accept: '.pdf' })).toBeNull();
    });

    it('menolak batas bawah yang lebih besar dari batas atas', () => {
        expect(galatTipe({ tipe: 'number', min: 4, maks: 0 })).toMatch(/minimum/i);
        expect(galatTipe({ tipe: 'number', min: 0, maks: 4 })).toBeNull();
        expect(galatTipe({ tipe: 'number' })).toBeNull();
    });

    it('menuntut kunci profil untuk tipe prefill', () => {
        expect(galatTipe({ tipe: 'prefill' })).toMatch(/isi otomatis/i);
        expect(galatTipe({ tipe: 'prefill', prefill: 'nama' })).toBeNull();
    });
});
