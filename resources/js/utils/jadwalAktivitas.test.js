// @vitest-environment jsdom
/**
 * JADWAL AKTIVITAS — dua aturan yang akibatnya menimpa kandidat, bukan layar.
 *
 *   1. WAKTU SELESAI TIDAK BOLEH MENDAHULUI WAKTU MULAI. Server memang
 *      menolaknya (`after:mulai`), tapi penolakan itu baru datang setelah
 *      tombol "Simpan & Undang Kandidat" ditekan — dan yang membacanya
 *      mengira sistemnya rusak, bukan bahwa ia salah pilih.
 *
 *   2. TANGGAL SELESAI TANPA JAM BERARTI "SAMPAI HABIS HARI ITU". Bawaan
 *      Element Plus 00:00 berarti sebaliknya: kegiatannya berakhir tepat
 *      saat hari itu dimulai, yaitu sebelum jam mulainya.
 *
 * Diuji terhadap komponennya sendiri (Options API biasa), bukan terhadap
 * salinan aturannya — supaya tes ini ikut jatuh kalau logikanya dipindah
 * atau dilonggarkan.
 */
import { describe, it, expect } from 'vitest';
import halaman from '../Pages/Career/admin/Pelamar.vue';

const { computed, methods, data } = halaman;
// data() membaca props halaman; yang dibutuhkan tes ini cuma satu tetapan
// di dalamnya, jadi propsnya cukup dipenuhi seadanya.
const bawaan = data.call({
    programAwal: { data: [], page: 1, totalPage: 1 },
    filterAwal: {},
    statAwal: {},
    aksesAwal: {},
});

/** Instance tiruan seadanya — hanya bidang yang disentuh aturan jadwal. */
function bikin(mulai, selesai) {
    const inst = {
        jadwalMulai: mulai,
        jadwalSelesai: selesai,
        JAM_TUTUP: bawaan.JAM_TUTUP,
    };
    inst.jadwalSelesaiSalah = computed.jadwalSelesaiSalah.call(inst);

    return inst;
}

describe('waktu selesai tidak boleh mendahului waktu mulai', () => {
    it('menolak jam yang lebih awal di hari yang sama', () => {
        expect(bikin('2026-08-21 14:00:00', '2026-08-21 09:00:00').jadwalSelesaiSalah).toBe(true);
    });

    it('menolak waktu yang persis sama — kegiatan berdurasi nol bukan jadwal', () => {
        expect(bikin('2026-08-21 09:03:00', '2026-08-21 09:03:00').jadwalSelesaiSalah).toBe(true);
    });

    it('menolak tanggal yang lebih awal', () => {
        expect(bikin('2026-08-21 09:00:00', '2026-08-20 23:59:00').jadwalSelesaiSalah).toBe(true);
    });

    it('menerima yang benar, termasuk lintas hari', () => {
        expect(bikin('2026-08-21 09:03:00', '2026-08-21 16:00:00').jadwalSelesaiSalah).toBe(false);
        expect(bikin('2026-08-21 09:03:00', '2026-08-22 23:59:00').jadwalSelesaiSalah).toBe(false);
    });

    it('diam saja bila salah satunya kosong — kolom selesai memang opsional', () => {
        expect(bikin('2026-08-21 09:00:00', '').jadwalSelesaiSalah).toBe(false);
        expect(bikin('', '2026-08-21 09:00:00').jadwalSelesaiSalah).toBe(false);
    });

    it('memadamkan tombol simpan, bukan sekadar menampilkan peringatan', () => {
        // Peringatan yang bisa dilewati tidak menahan apa pun: undangannya
        // tetap terkirim, dan undangan yang sudah terkirim tak bisa ditarik.
        const inst = {
            ...bikin('2026-08-21 14:00:00', '2026-08-21 09:00:00'),
            modeJadwalDef: { butuhTautan: false, butuhLokasi: false, butuhKontak: false },
        };
        expect(computed.bolehSimpanJadwal.call(inst)).toBe(false);
    });
});

describe('kalender waktu selesai', () => {
    const salah = (mulai, d) => methods.sebelumHariMulai.call({ jadwalMulai: mulai }, d);

    it('mematikan hari-hari sebelum tanggal mulai', () => {
        expect(salah('2026-08-21 09:03:00', new Date(2026, 7, 20))).toBe(true);
    });

    it('MEMBIARKAN hari mulai sendiri — 14:00 boleh selesai 16:00 hari itu juga', () => {
        expect(salah('2026-08-21 14:00:00', new Date(2026, 7, 21))).toBe(false);
    });

    it('membiarkan hari-hari sesudahnya', () => {
        expect(salah('2026-08-21 09:03:00', new Date(2026, 7, 22))).toBe(false);
    });

    it('tidak mematikan apa pun selama waktu mulai belum dipilih', () => {
        expect(salah('', new Date(2020, 0, 1))).toBe(false);
    });
});

describe('jam bawaan saat hanya tanggal yang dipilih', () => {
    it('menutup di 23:59, bukan membuka di 00:00', () => {
        expect(bawaan.JAM_TUTUP.getHours()).toBe(23);
        expect(bawaan.JAM_TUTUP.getMinutes()).toBe(59);
    });
});
