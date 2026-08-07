/**
 * Uji SKEMA BAWAAN terhadap aturan validasi yang ditegakkan ke admin.
 *
 * Registry fallback (blok.js + form-1..4) adalah kode, bukan data, jadi ia tidak
 * pernah melewati validasi editor. Kalau aturan yang kita tuntut dari admin
 * tidak dipenuhi skema bawaan sendiri, aturannya yang salah — atau skemanya.
 * Uji ini memaksa keduanya tetap sejalan.
 */
import { describe, expect, it } from 'vitest';
import { validasiSkema } from './schema';
import { blokDataDiri, blokPendidikan } from './blok';
// Skemanya diimpor LANGSUNG, bukan lewat ../index: berkas itu ikut menarik
// FormN.vue -> TeleponNegara.vue, yang butuh `vue/server-renderer` dan tidak
// bisa dimuat di lingkungan uji. Yang diuji di sini memang skemanya, bukan
// pemetaan kode komponen ke berkasnya.
import { SKEMA as FORMULIR_1 } from '../template-1/form-1/skema';
import { SKEMA as FORMULIR_2 } from '../template-1/form-2/skema';
import { SKEMA as FORMULIR_3 } from '../template-1/form-3/skema';
import { SKEMA as FORMULIR_4 } from '../template-1/form-4/skema';

describe.each(Object.entries({ FORMULIR_1, FORMULIR_2, FORMULIR_3, FORMULIR_4 }))('%s', (_nama, skema) => {
    it('lolos validasi skema', () => {
        expect(skema).toBeTruthy();
        expect(validasiSkema(skema).errors).toEqual([]);
    });
});

function bungkus(field) {
    return { langkah: [{ judul: 'L', bagian: [{ judul: 'B', field }] }] };
}

describe('blok siap pakai', () => {
    it('blok pendidikan lolos validasi', () => {
        expect(validasiSkema(bungkus(blokPendidikan())).errors).toEqual([]);
    });

    it('blok pendidikan tanpa pertanyaan kemahasiswaan tetap lolos', () => {
        const field = blokPendidikan({ tanyaKemahasiswaan: false, tanyaTahunLulus: false });
        expect(validasiSkema(bungkus(field)).errors).toEqual([]);
    });

    it('blok data diri lolos validasi', () => {
        expect(validasiSkema(bungkus(blokDataDiri({ tanyaDomisili: true }))).errors).toEqual([]);
    });
});

describe('cascade pendidikan mengosongkan anaknya', () => {
    const cari = (field, key) => field.find((f) => f.key === key);

    it('mengganti jenjang membatalkan institusi, kampus, dan jurusan', () => {
        expect(cari(blokPendidikan(), 'jenjang_pendidikan').reset_anak).toEqual([
            'jenis_institusi',
            'nama_kampus',
            'jurusan',
        ]);
    });

    it('mengganti jenis institusi membatalkan kampus', () => {
        expect(cari(blokPendidikan(), 'jenis_institusi').reset_anak).toEqual(['nama_kampus']);
    });

    it('seluruh key yang dikosongkan benar-benar ada di bloknya', () => {
        const f = blokPendidikan();
        const ada = new Set(f.map((x) => x.key));
        f.forEach((x) => (x.reset_anak || []).forEach((k) => expect(ada.has(k), `${x.key} -> ${k}`).toBe(true)));
    });
});
