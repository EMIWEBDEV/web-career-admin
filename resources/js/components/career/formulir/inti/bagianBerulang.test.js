// @vitest-environment jsdom
/**
 * Uji TOMBOL HAPUS BARIS pada bagian berulang (Pengalaman Kerja, Organisasi, …).
 *
 * Kandidat yang salah menambah baris harus bisa membuangnya. Tombolnya pernah
 * ada di markup tapi TIDAK PERNAH TERPASANG: alias `v-for` diberi nama sama
 * dengan computed penampungnya (`baris`), sehingga `baris.length` di dalam loop
 * membaca panjang OBJEK satu baris — selalu `undefined` — dan syaratnya tak
 * pernah benar. Berkas ini mengunci dua hal yang tidak boleh diam-diam rusak
 * lagi: tombolnya benar-benar muncul, dan yang terbuang persis baris yang
 * diklik.
 */
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BagianRenderer from './BagianRenderer.vue';

const BAGIAN = {
    judul: 'Pengalaman Kerja',
    berulang: true,
    maks_baris: 5,
    field: [
        { key: 'perusahaan', label: 'Perusahaan', tipe: 'text' },
        { key: 'jabatan', label: 'Jabatan', tipe: 'text' },
    ],
};

/** FieldRenderer distub: uji ini soal kerangka baris, bukan isi kolomnya. */
const GLOBAL = { stubs: { FieldRenderer: true } };

function pasang(baris, extra = {}) {
    return mount(BagianRenderer, {
        props: { bagian: BAGIAN, jawaban: { pengalaman_kerja: baris }, ...extra },
        global: GLOBAL,
    });
}

const BARIS = [
    { perusahaan: 'PT Satu', jabatan: 'Staf' },
    { perusahaan: 'PT Dua', jabatan: 'Supervisor' },
    { perusahaan: 'PT Tiga', jabatan: 'Manajer' },
];

describe('bagian berulang — tombol hapus baris', () => {
    it('memasang satu tombol hapus di tiap baris saat barisnya lebih dari satu', () => {
        const w = pasang(BARIS);

        expect(w.findAll('.bg__hapus')).toHaveLength(3);
    });

    it('menyembunyikan tombol saat tinggal satu baris — daftar tak boleh jadi kosong', () => {
        const w = pasang([BARIS[0]]);

        expect(w.findAll('.bg__hapus')).toHaveLength(0);
    });

    it('tidak memasang tombol apa pun saat formulir dikunci', () => {
        const w = pasang(BARIS, { disabled: true });

        expect(w.findAll('.bg__hapus')).toHaveLength(0);
    });

    /**
     * Inti persoalannya: yang hilang harus baris yang DIKLIK. Baris tengah
     * dipilih karena kesalahan indeks paling kentara di sana — salah satu
     * arah akan membuang tetangganya.
     */
    it('membuang persis baris yang diklik, bukan tetangganya', async () => {
        const w = pasang(BARIS);

        await w.findAll('.bg__hapus')[1].trigger('click');

        expect(w.emitted('ubah')).toBeTruthy();
        expect(w.emitted('ubah')[0]).toEqual([
            'pengalaman_kerja',
            [BARIS[0], BARIS[2]],
        ]);
    });

    it('membuang baris terakhir tanpa menyentuh baris sebelumnya', async () => {
        const w = pasang(BARIS);

        await w.findAll('.bg__hapus')[2].trigger('click');

        expect(w.emitted('ubah')[0][1]).toEqual([BARIS[0], BARIS[1]]);
    });
});
