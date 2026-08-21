// @vitest-environment jsdom
/**
 * REGRESI — kartu aktivitas yang MENYURUH MENUNGGU JADWAL untuk tes yang sudah
 * dikerjakan.
 *
 * Laporan aslinya (kandidat, tahap 3 "PSIKOTES ONLINE"): di kepala halaman
 * tertulis "PSIKOTES ONLINE sudah kamu kerjakan — hasilnya sedang ditinjau",
 * lencana blok aktivitasnya berbunyi "Selesai", tapi isi blok itu justru
 * berbunyi "Tim rekrutmen sedang menyiapkan jadwal ujianmu. Token, kode OTP,
 * dan waktu pengerjaan muncul di halaman ini begitu terbit". Satu layar, dua
 * kabar yang saling membatalkan — dan yang paling bawah, yang paling dekat
 * dengan nama tesnya, adalah yang salah.
 *
 * SEBABNYA: kalimat itu bukan ditulis di halaman ini, melainkan
 * Master_Tipe_Tahap.Pesan_Kandidat yang ditayangkan cadangan paling bawah
 * rangkaian v-if. Aktivitas yang sudah dikerjakan LALU ditutup tim melewati
 * semua cabang di atasnya dan mendarat di sana.
 *
 * Diuji lewat definisi Options API-nya langsung — sama seperti berkas uji lain
 * di folder ini — supaya tidak perlu merakit seluruh halaman.
 */
import { describe, expect, it } from 'vitest';
import Komponen from './LamaranDetail.vue';

const M = Komponen.methods;
const C = Komponen.computed;

/** `this` seadanya: hanya yang benar-benar disentuh fungsi yang diuji. */
function ctx(extra = {}) {
    return {
        now: Date.parse('2026-08-19T16:17:00'),
        tesTuntas: M.tesTuntas,
        sudahSelesai: M.sudahSelesai,
        belumMulai: M.belumMulai,
        sudahLewat: M.sudahLewat,
        blokAktivitas: M.blokAktivitas,
        judulSelesai: M.judulSelesai,
        ...extra,
    };
}

const blok = (a, extra) => M.blokAktivitas.call(ctx(extra), a);

/** Persis keadaan pada laporan: dikerjakan kandidat, lalu ditutup tim. */
const PSIKOTES_SELESAI = {
    id: 'akt-psi',
    label: 'PSIKOTES ONLINE',
    eksternal: true,
    selesai: true,
    // Kalimat dari master yang dulu bocor ke layar.
    pesan: 'Tim rekrutmen sedang menyiapkan jadwal ujianmu. Token, kode OTP, dan waktu pengerjaan muncul di halaman ini begitu terbit, dan kamu diberi tahu lewat email.',
    // Sesinya sudah dibereskan tim, jadi tak ada lagi yang menandainya terjadwal.
    ujian: { terjadwal: false, statusPengerjaan: 'selesai' },
};

describe('blokAktivitas — aktivitas yang sudah berakhir', () => {
    it('BUG ASLI: tes yang sudah dikerjakan TIDAK jatuh ke "menunggu dijadwalkan"', () => {
        expect(blok(PSIKOTES_SELESAI)).not.toBe('jadwal');
    });

    it('BUG ASLI: tidak jatuh pula ke cadangan yang menayangkan Pesan_Kandidat', () => {
        // Cadangan itu hanya hidup di cabang 'jalan'.
        expect(blok(PSIKOTES_SELESAI)).not.toBe('jalan');
    });

    it('dikerjakan kandidat → cabang "selesai"', () => {
        expect(blok(PSIKOTES_SELESAI)).toBe('selesai');
    });

    it('ditutup tim tanpa pernah dikerjakan → tetap cabang "selesai"', () => {
        const a = { ...PSIKOTES_SELESAI, ujian: { terjadwal: false, statusPengerjaan: null } };

        expect(blok(a)).toBe('selesai');
    });

    it('dikerjakan tapi tahapnya belum ditutup tim → tetap cabang "selesai"', () => {
        const a = { ...PSIKOTES_SELESAI, selesai: false, ujian: { terjadwal: true, statusPengerjaan: 'selesai' } };

        expect(blok(a)).toBe('selesai');
    });

    it('aktivitas tim (wawancara, MCU) yang sudah ditutup ikut memakainya', () => {
        expect(blok({ id: 'w1', label: 'Wawancara', eksternal: false, selesai: true, jadwal: { mulai: '2026-08-18 09:00' } })).toBe('selesai');
    });
});

describe('blokAktivitas — yang belum berakhir tidak ikut berubah', () => {
    it('ujian daring yang sesinya memang belum dibuat → "jadwal"', () => {
        expect(blok({ id: 'a', eksternal: true, selesai: false, ujian: { terjadwal: false } })).toBe('jadwal');
    });

    it('ujian daring yang sudah punya sesi → "jalan"', () => {
        expect(blok({ id: 'a', eksternal: true, selesai: false, ujian: { terjadwal: true } })).toBe('jalan');
    });

    it('aktivitas tim yang masih berjalan → "jalan"', () => {
        expect(blok({ id: 'a', eksternal: false, selesai: false, unggah: { terkirim: false } })).toBe('jalan');
    });
});

describe('judulSelesai — jujur soal siapa yang mengakhirinya', () => {
    it('kandidat yang mengerjakannya: disebut apa adanya, sekaligus tidak bisa diulang', () => {
        const j = M.judulSelesai.call(ctx(), PSIKOTES_SELESAI);

        expect(j).toMatch(/sudah kamu kerjakan/i);
        expect(j).toMatch(/tidak dapat diulang/i);
    });

    it('ditutup tim tanpa ia kerjakan: TIDAK mengaku-akui ia yang mengerjakan', () => {
        const a = { ...PSIKOTES_SELESAI, ujian: { terjadwal: false, statusPengerjaan: null } };

        expect(M.judulSelesai.call(ctx(), a)).not.toMatch(/kamu kerjakan/i);
    });
});

describe('subTahap — subjudul kartu tahap', () => {
    function ctxTahap(aktivitas) {
        return {
            aktivitasTampil: aktivitas,
            adaGiliran: aktivitas.some((a) => a.terkunci),
            pesanTahap: { sub: 'Proses seleksi' },
            tesTuntas: M.tesTuntas,
            sudahSelesai: M.sudahSelesai,
        };
    }

    it('BUG ASLI: berhenti menyuruh "ikuti aktivitas di bawah" untuk yang sudah selesai', () => {
        const s = C.subTahap.call(ctxTahap([PSIKOTES_SELESAI]));

        expect(s).not.toMatch(/ikuti aktivitas/i);
        expect(s).toMatch(/sudah selesai/i);
    });

    it('seluruh rangkaian selesai → menyebut yang sedang ditunggu: hasilnya', () => {
        const dua = [PSIKOTES_SELESAI, { ...PSIKOTES_SELESAI, id: 'akt-2' }];

        expect(C.subTahap.call(ctxTahap(dua))).toMatch(/menunggu hasilnya/i);
    });

    it('masih ada yang bisa dikerjakan → kalimat lamanya utuh', () => {
        const satu = [{ id: 'a', eksternal: true, selesai: false, ujian: { terjadwal: true } }];

        expect(C.subTahap.call(ctxTahap(satu))).toMatch(/ikuti aktivitas di bawah/i);
    });

    it('lebih dari satu aktivitas, sebagian belum → jumlah dan urutannya disebut', () => {
        const campur = [PSIKOTES_SELESAI, { id: 'b', eksternal: false, selesai: false, terkunci: true }];

        expect(C.subTahap.call(ctxTahap(campur))).toBe('Tahap ini terdiri dari 2 aktivitas, dikerjakan berurutan.');
    });

    it('tanpa aktivitas yang terlihat → kalimat tahap dari master', () => {
        expect(C.subTahap.call(ctxTahap([]))).toBe('Proses seleksi');
    });
});

describe('aktivitasSorot — tahap berisi lebih dari satu psikotes', () => {
    /**
     * Susunan yang jadi kekhawatiran aslinya: dua-tiga tes daring dalam satu
     * tahap. Yang satu baru selesai dikerjakan, yang lain masih menunggu
     * giliran — dan spanduk paling atas halaman hanya bisa menunjuk satu.
     */
    function ctxSorot(daftar) {
        return {
            aktivitasTampil: daftar,
            tesTuntas: M.tesTuntas,
            sudahSelesai: M.sudahSelesai,
        };
    }

    const dikerjakan = {
        id: 'psi-1', label: 'PSIKOTES ONLINE 1', eksternal: true, selesai: false,
        ujian: { terjadwal: true, statusPengerjaan: 'selesai' },
        keadaan: { nada: 'proses', label: 'Sudah dikerjakan' },
    };
    const terbuka = {
        id: 'psi-2', label: 'PSIKOTES ONLINE 2', eksternal: true, selesai: false,
        ujian: { terjadwal: true, bisaAkses: true },
        keadaan: { nada: 'aksi', label: 'Bisa dikerjakan' },
    };
    const belumDijadwalkan = {
        id: 'psi-3', label: 'PSIKOTES ONLINE 3', eksternal: true, selesai: false,
        ujian: { terjadwal: false },
        keadaan: { nada: 'tunggu', label: 'Menunggu dijadwalkan' },
    };

    it('BUG ASLI: tes yang baru dikerjakan tidak merebut spanduk dari tes yang menunggu dikerjakan', () => {
        expect(C.aktivitasSorot.call(ctxSorot([dikerjakan, terbuka])).id).toBe('psi-2');
    });

    it('urutannya tetap: yang bisa dikerjakan mengalahkan yang belum dijadwalkan', () => {
        expect(C.aktivitasSorot.call(ctxSorot([dikerjakan, belumDijadwalkan, terbuka])).id).toBe('psi-2');
    });

    it('tak ada yang bisa dikerjakan → yang belum dijadwalkan yang disorot', () => {
        expect(C.aktivitasSorot.call(ctxSorot([dikerjakan, belumDijadwalkan])).id).toBe('psi-3');
    });

    it('SELURUHNYA tuntas → spanduk tetap menunjuk yang baru ia kerjakan, bukan kosong', () => {
        const s = C.aktivitasSorot.call(ctxSorot([dikerjakan, { ...terbuka, id: 'psi-2', selesai: true }]));

        expect(s).not.toBeNull();
        expect(s.id).toBe('psi-1');
    });

    it('yang terkunci tidak pernah disorot', () => {
        const kunci = { ...terbuka, id: 'psi-9', terkunci: true, keadaan: { nada: 'kunci', label: 'Belum gilirannya' } };

        expect(C.aktivitasSorot.call(ctxSorot([kunci, belumDijadwalkan])).id).toBe('psi-3');
    });
});
