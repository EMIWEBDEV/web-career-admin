// @vitest-environment jsdom
/**
 * Uji AmbilFoto — dua perilaku yang gagalnya tidak kelihatan di layar.
 *
 * 1. Aliran kamera yang datang terlambat harus dihentikan. Kalau lolos, lampu
 *    kamera menyala terus sampai tab ditutup dan wajar dibaca kandidat sebagai
 *    perekaman diam-diam.
 * 2. Foto yang sudah diambil harus tetap ditandai setelah komponen dipasang
 *    ulang. Layout bertahap melepas langkah yang tidak sedang dibuka, jadi ini
 *    terjadi setiap kali kandidat menekan Kembali lalu maju lagi.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AmbilFoto from './AmbilFoto.vue';

/** MediaStream tiruan yang mencatat apakah track-nya sempat dihentikan. */
function aliranPalsu() {
    const track = { stop: vi.fn() };

    return { track, stream: { getTracks: () => [track] } };
}

let tunda;

beforeEach(() => {
    tunda = [];
    Object.defineProperty(navigator, 'mediaDevices', {
        configurable: true,
        value: {
            getUserMedia: vi.fn(() => new Promise((resolve) => tunda.push(resolve))),
        },
    });
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('aliran kamera', () => {
    it('dihentikan bila komponen dilepas selagi izin masih ditunggu', async () => {
        const w = mount(AmbilFoto);
        const { track, stream } = aliranPalsu();

        w.vm.nyalakan();
        await w.vm.$nextTick();

        // Kandidat menekan "Kembali" sebelum menjawab dialog izin.
        w.unmount();

        // Izin baru diberikan sekarang, saat komponennya sudah tidak ada.
        tunda[0](stream);
        await new Promise((r) => setTimeout(r, 0));

        expect(track.stop).toHaveBeenCalled();
    });

    it('dihentikan bila pengguna menekan Batal selagi izin ditunggu', async () => {
        const w = mount(AmbilFoto);
        const { track, stream } = aliranPalsu();

        w.vm.nyalakan();
        await w.vm.$nextTick();
        w.vm.matikan();

        tunda[0](stream);
        await new Promise((r) => setTimeout(r, 0));

        expect(track.stop).toHaveBeenCalled();
        expect(w.vm.kameraNyala).toBe(false);
    });

    it('klik ganda tidak melahirkan aliran kedua yang telantar', async () => {
        const w = mount(AmbilFoto);

        w.vm.nyalakan();
        w.vm.nyalakan();
        w.vm.nyalakan();
        await w.vm.$nextTick();

        expect(navigator.mediaDevices.getUserMedia).toHaveBeenCalledTimes(1);
    });

    it('dihentikan saat komponen dilepas dalam keadaan menyala', async () => {
        const w = mount(AmbilFoto);
        const { track, stream } = aliranPalsu();

        w.vm.nyalakan();
        await w.vm.$nextTick();
        tunda[0](stream);
        await new Promise((r) => setTimeout(r, 0));
        expect(w.vm.kameraNyala).toBe(true);

        w.unmount();
        expect(track.stop).toHaveBeenCalled();
    });
});

describe('foto yang sudah diambil', () => {
    it('ditandai walau gambarnya tidak ikut terbawa saat dipasang ulang', () => {
        const w = mount(AmbilFoto, { props: { namaTersimpan: 'foto-verifikasi.jpg' } });

        expect(w.vm.adaFotoTersimpan).toBe(true);
        expect(w.text()).toContain('Foto sudah diambil');
        expect(w.text()).toContain('foto-verifikasi.jpg');
        expect(w.text()).not.toContain('Kamera belum aktif');
    });

    it('menawarkan Ambil Ulang, bukan Aktifkan Kamera', () => {
        const w = mount(AmbilFoto, { props: { namaTersimpan: 'foto-verifikasi.jpg' } });

        expect(w.text()).toContain('Ambil Ulang');
        expect(w.text()).not.toContain('Aktifkan Kamera');
    });

    it('tanpa foto apa pun tetap menampilkan keadaan awal', () => {
        const w = mount(AmbilFoto);

        expect(w.vm.adaFotoTersimpan).toBe(false);
        expect(w.text()).toContain('Kamera belum aktif');
        expect(w.text()).toContain('Aktifkan Kamera');
    });
});
