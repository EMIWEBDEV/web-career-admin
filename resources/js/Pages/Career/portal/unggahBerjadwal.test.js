// @vitest-environment jsdom
/**
 * REGRESI — kotak unggah berkas muncul untuk tes yang BELUM dijadwalkan.
 *
 * Laporan aslinya (tahap "FGD DAN WAWANCARA", 4 aktivitas): aktivitas 2
 * "CRITICAL THINGKING — Tes Offline (Manual)" memamerkan "Seret berkas ke sini",
 * tombol "Kirim Berkas", lencana "Menunggu berkasmu", dan peringatan merah
 * "Belum ada berkas — aktivitas ini belum bisa dianggap selesai" — padahal
 * waktu dan tempat tesnya belum ditentukan. Kandidat diminta mengunggah JAWABAN
 * atas tes yang belum ia kerjakan, lalu ditegur karena belum mengunggahnya.
 *
 * Di kartu yang sama, FGD dan WAWANCARA — sama-sama belum dijadwalkan — dengan
 * tenang berbunyi "Waktu dan tempat pelaksanaan tes akan dikabarkan tim
 * rekrutmen". Tiga aktivitas sederajat, tiga cerita berbeda.
 *
 * Aturannya sekarang: aktivitas yang tipenya memang dijadwalkan tim
 * (Master_Tipe_Tahap.Flag_Jadwal = 'Y' → `perluJadwal`) menunggu jadwalnya
 * terbit dulu; yang tidak dijadwalkan (formulir, dokumen) meminta berkasnya
 * sejak awal seperti biasa.
 */
import { describe, expect, it } from 'vitest';
import Komponen from './LamaranDetail.vue';

const M = Komponen.methods;

function ctx(berkasTes = {}) {
    return {
        berkasTes,
        now: Date.parse('2026-08-19T16:29:00'),
        tesDitunggu: null,
        unggahSiap: M.unggahSiap,
        tesTuntas: M.tesTuntas,
        sudahSelesai: M.sudahSelesai,
        belumMulai: M.belumMulai,
        sudahLewat: M.sudahLewat,
        keadaanAktivitas: M.keadaanAktivitas,
    };
}

const siap = (a, berkasTes) => M.unggahSiap.call(ctx(berkasTes), a);
const lencana = (a, berkasTes) => M.keadaanAktivitas.call(ctx(berkasTes), a);

/** Persis aktivitas 2 pada laporan: tes offline, minta berkas, belum dijadwalkan. */
const TES_OFFLINE = {
    id: 'akt-ct',
    urutan: 2,
    label: 'CRITICAL THINGKING',
    tipe: 'TES_OFFLINE_MANUAL',
    eksternal: false,
    selesai: false,
    perluJadwal: true,
    jadwal: null,
    unggah: { wajib: true, format: ['pdf'], maksMb: 3, terkirim: null },
};

const DIJADWALKAN = { ...TES_OFFLINE, jadwal: { mulai: '2026-08-20 09:22', mode: 'LURING' } };

describe('unggahSiap — menunggu jadwal dulu', () => {
    it('BUG ASLI: tes offline yang belum dijadwalkan TIDAK meminta berkas', () => {
        expect(siap(TES_OFFLINE)).toBe(false);
    });

    it('begitu dijadwalkan, kotak unggahnya muncul', () => {
        expect(siap(DIJADWALKAN)).toBe(true);
    });

    it('aktivitas yang memang tak pernah dijadwalkan (formulir/dokumen) tetap meminta berkas sejak awal', () => {
        expect(siap({ ...TES_OFFLINE, tipe: 'DOCUMENT', perluJadwal: false })).toBe(true);
    });

    it('aktivitas tanpa permintaan berkas sama sekali → tidak ada kotaknya', () => {
        expect(siap({ ...DIJADWALKAN, unggah: null })).toBe(false);
    });
});

describe('unggahSiap — bukti kiriman kandidat tidak boleh lenyap', () => {
    it('sudah dinyatakan lengkap → tetap tampil walau jadwalnya dicabut', () => {
        const a = { ...TES_OFFLINE, unggah: { ...TES_OFFLINE.unggah, terkirim: '2026-08-19 12:00' } };

        expect(siap(a)).toBe(true);
    });

    it('sudah ada berkas terunggah (belum dikirim) → tetap tampil', () => {
        expect(siap(TES_OFFLINE, { 2: [{ id: 'b1', nama: 'jawaban.pdf' }] })).toBe(true);
    });
});

describe('lencana blok — harus sepakat dengan isinya', () => {
    it('BUG ASLI: bukan "Menunggu berkasmu" untuk aktivitas yang belum dijadwalkan', () => {
        const k = lencana(TES_OFFLINE);

        expect(k.label).not.toMatch(/berkasmu/i);
        expect(k.label).toMatch(/menunggu jadwal/i);
    });

    it('sudah dijadwalkan dan berkasnya belum masuk → "Menunggu berkasmu"', () => {
        expect(lencana(DIJADWALKAN).label).toMatch(/menunggu berkasmu/i);
    });

    it('berkas sudah dikirim → "Berkas terkirim", bukan lagi menuntut kandidat', () => {
        const a = { ...DIJADWALKAN, unggah: { ...DIJADWALKAN.unggah, terkirim: '2026-08-19 12:00' } };
        const k = lencana(a);

        expect(k.label).toMatch(/berkas terkirim/i);
        expect(k.nada).toBe('proses');
    });

    it('dijadwalkan tanpa permintaan berkas → "Sudah dijadwalkan"', () => {
        expect(lencana({ ...DIJADWALKAN, unggah: null }).label).toMatch(/sudah dijadwalkan/i);
    });

    it('aktivitas yang sudah ditutup tim tetap "Selesai" lebih dulu dari apa pun', () => {
        expect(lencana({ ...TES_OFFLINE, selesai: true }).label).toBe('Selesai');
    });
});
