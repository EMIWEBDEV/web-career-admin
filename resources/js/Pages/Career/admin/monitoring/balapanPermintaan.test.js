// @vitest-environment jsdom
/**
 * Uji BALAPAN PERMINTAAN pada drawer monitoring.
 *
 * Drawer detail dimuat ulang lewat `watch` tiap kali admin memilih pelamar
 * lain. Permintaan lama tidak dibatalkan, dan jaringan tidak menjamin urutan
 * kedatangan: bila balasan pelamar PERTAMA tiba setelah balasan pelamar
 * KEDUA, isi lama menimpa isi baru. Yang terlihat bukan galat — nama di
 * kepala drawer sudah orang baru, sementara perjalanan di bawahnya milik
 * orang sebelumnya. Berkas dan keputusan pun ikut salah orang.
 *
 * Berkas ini mengunci penjaganya: balasan yang bukan milik permintaan
 * terakhir harus dibuang.
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import DetailPerjalanan from './DetailPerjalanan.vue';

vi.mock('axios');

/** Bentuk balasan seminimal yang dibutuhkan template. */
function balasan(nama) {
    return {
        data: {
            result: {
                lamaran: {
                    nama, kode: `KODE-${nama}`, posisi: 'Staf', programNama: 'Program',
                    email: null, status: 'BERJALAN', waktuLamar: null, waktuSelesai: null,
                    mulaiDariUrutan: null,
                },
                tahap: [],
                jejak: [],
                talentPool: null,
            },
        },
    };
}

/** Janji yang penyelesaiannya dikendalikan uji, bukan waktu. */
function tertunda() {
    let selesaikan;
    const janji = new Promise((res) => { selesaikan = res; });
    return { janji, selesaikan };
}

const GLOBAL = { stubs: { KeadaanPanel: true } };

describe('drawer perjalanan — balasan yang tiba terbalik', () => {
    beforeEach(() => vi.clearAllMocks());

    it('membuang balasan pelamar lama yang tiba setelah pelamar baru', async () => {
        const lama = tertunda();
        const baru = tertunda();

        axios.get
            .mockReturnValueOnce(lama.janji)   // permintaan pelamar A
            .mockReturnValueOnce(baru.janji);  // permintaan pelamar B

        const w = mount(DetailPerjalanan, { props: { lamaranId: 'A' }, global: GLOBAL });
        await w.setProps({ lamaranId: 'B' });

        // B tiba lebih dulu, A menyusul terlambat — persis urutan yang merusak.
        baru.selesaikan(balasan('Budi Baru'));
        await flushPromises();
        lama.selesaikan(balasan('Ana Lama'));
        await flushPromises();

        expect(w.text()).toContain('Budi Baru');
        expect(w.text()).not.toContain('Ana Lama');
    });

    it('tetap menampilkan balasan terakhir saat permintaan lama justru gagal', async () => {
        const lama = tertunda();
        const baru = tertunda();
        let tolakLama;
        const janjiLama = new Promise((_, rej) => { tolakLama = rej; });

        axios.get
            .mockReturnValueOnce(janjiLama)
            .mockReturnValueOnce(baru.janji);

        const w = mount(DetailPerjalanan, { props: { lamaranId: 'A' }, global: GLOBAL });
        await w.setProps({ lamaranId: 'B' });

        baru.selesaikan(balasan('Budi Baru'));
        await flushPromises();

        // Kegagalan permintaan LAMA tidak boleh menghapus isi yang sudah benar.
        tolakLama(new Error('jaringan putus'));
        await flushPromises();

        expect(w.text()).toContain('Budi Baru');

        // Bukan sekadar tidak kosong: panel galat tidak boleh muncul.
        expect(w.findAll('keadaanpanel-stub').some((s) => s.attributes('keadaan') === 'galat')).toBe(false);

        void lama;
    });
});
