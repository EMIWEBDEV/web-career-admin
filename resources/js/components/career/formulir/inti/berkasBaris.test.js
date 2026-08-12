// @vitest-environment jsdom
/**
 * Identitas berkas per baris, dari sisi browser.
 *
 * BagianRenderer sudah lama mengirim { bagian, baris } pada kejadian `berkas`
 * — nilainya hanya tidak pernah dipakai siapa pun. Uji ini mengunci dua hal
 * yang harus benar sebelum halaman bisa memakainya: kunci kompositnya, dan
 * kejadian hapus-baris yang membuat berkasnya ikut dibuang.
 */
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BagianRenderer from './BagianRenderer.vue';
import { kunciBerkas, labelBerkas } from './berkasBaris';

const BAGIAN = {
    key: 'riwayat_sertifikasi',
    judul: 'Riwayat Sertifikasi',
    berulang: true,
    maks_baris: 5,
    field: [
        { key: 'sert_nama', label: 'Nama Sertifikat', tipe: 'text' },
        { key: 'sert_file', label: 'Sertifikat', tipe: 'file', accept: '.pdf' },
    ],
};

const BARIS = [
    { sert_nama: 'A', sert_file: 'a.pdf' },
    { sert_nama: 'B', sert_file: 'b.pdf' },
    { sert_nama: 'C', sert_file: 'c.pdf' },
];

function pasang(baris = BARIS) {
    return mount(BagianRenderer, {
        props: { bagian: BAGIAN, jawaban: { riwayat_sertifikasi: baris } },
        global: { stubs: { FieldRenderer: true } },
    });
}

describe('kunciBerkas', () => {
    it('berkas biasa dikenali dari fieldnya saja', () => {
        expect(kunciBerkas(null, null, 'dok_cv')).toBe('dok_cv');
        expect(kunciBerkas(undefined, undefined, 'dok_cv')).toBe('dok_cv');
    });

    it('berkas berulang membawa bagian dan barisnya', () => {
        expect(kunciBerkas('riwayat_sertifikasi', 2, 'sert_file')).toBe('riwayat_sertifikasi[2].sert_file');
    });

    it('baris 0 tetap identitas yang sah — bukan dianggap kosong', () => {
        expect(kunciBerkas('riwayat_sertifikasi', 0, 'sert_file')).toBe('riwayat_sertifikasi[0].sert_file');
    });
});

describe('labelBerkas', () => {
    it('menomori mulai dari satu', () => {
        expect(labelBerkas('Sertifikat', 0)).toBe('Sertifikat #1');
        expect(labelBerkas('Sertifikat', 2)).toBe('Sertifikat #3');
    });

    it('berkas biasa tidak bernomor', () => {
        expect(labelBerkas('Curriculum Vitae', null)).toBe('Curriculum Vitae');
    });
});

describe('BagianRenderer — berkas per baris', () => {
    it('meneruskan bagian dan baris ke tiap FieldRenderer', () => {
        const w = pasang();
        const fr = w.findAllComponents({ name: 'FieldRenderer' });

        expect(fr[0].props('bagian')).toBe('riwayat_sertifikasi');
        expect(fr[0].props('baris')).toBe(0);
    });

    it('memancarkan hapus-baris berisi kunci bagian dan indeksnya', async () => {
        const w = pasang();

        await w.findAll('.bg__hapus')[1].trigger('click');

        expect(w.emitted('hapus-baris')).toBeTruthy();
        expect(w.emitted('hapus-baris')[0]).toEqual(['riwayat_sertifikasi', 1]);
    });

    it('tetap memancarkan ubah agar barisnya benar-benar terbuang dari jawaban', async () => {
        const w = pasang();

        await w.findAll('.bg__hapus')[1].trigger('click');

        expect(w.emitted('ubah')[0]).toEqual(['riwayat_sertifikasi', [BARIS[0], BARIS[2]]]);
    });
});
