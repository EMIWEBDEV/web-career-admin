// @vitest-environment jsdom
/**
 * Portal kandidat — dua perbaikan tampilan detail lamaran.
 *
 * 1. TALENT POOL punya kabarnya sendiri. Sebelumnya keadaan ini tidak punya
 *    cabang di banner(), jadi ia jatuh ke cadangan "Lamaranmu sedang diproses"
 *    — untuk lamaran yang justru sudah DITUTUP — dan catatan yang ditulis tim
 *    tidak pernah sampai ke kandidat.
 *
 * 2. WATERFALL tidak lagi memakai tanggal. Yang dulu tampil sebagian besar
 *    perkiraan buatan kode sendiri (tiga hari per tahap sejak tanggal melamar),
 *    dan tanggal di layar perusahaan terbaca sebagai janji.
 */
import { describe, expect, it } from 'vitest';
import Komponen from './LamaranDetail.vue';

const C = Komponen.computed;

function konteks(lamaran, tahap = []) {
    return {
        lamaran,
        tahap,
        totalTahap: tahap.length,
        tahapAktif: tahap.find((t) => t.status === 'BERJALAN') || null,
        tugas: null,
        tahapTes: null,
        stKey: C.stKey.call({ lamaran }),
        tlState: (t) => (t.status === 'SELESAI' ? (t.hasil === 'GUGUR' ? 'fail' : 'done') : t.status === 'BERJALAN' ? 'current' : 'todo'),
        stepStatus: (t) => (t.status === 'SELESAI' ? 'Selesai' : t.status === 'BERJALAN' ? 'Berlangsung' : 'Menunggu'),
    };
}

const CATATAN = 'Nilai wawancaramu bagus, hanya pengalamannya belum cukup untuk posisi ini.';

describe('banner — talent pool', () => {
    const dasar = { status: 'TALENT_POOL', statusNada: 'menunggu', statusLabel: 'Talent Pool' };

    it('menyampaikan TIDAK LOLOS sekaligus DISIMPAN', () => {
        const b = C.banner.call(konteks({ ...dasar, alasanGugur: null }));

        expect(b.title).toMatch(/belum lolos/i);
        expect(b.text).toMatch(/talent pool/i);
    });

    it('menampilkan CATATAN dari tim bila ada', () => {
        const b = C.banner.call(konteks({ ...dasar, alasanGugur: CATATAN }));

        expect(b.text).toBe(CATATAN);
    });

    it('BUG ASLI: tidak lagi berkata "sedang diproses" untuk lamaran yang sudah ditutup', () => {
        const b = C.banner.call(konteks({ ...dasar, alasanGugur: null }));

        expect(b.title).not.toMatch(/sedang diproses/i);
        expect(b.text).not.toMatch(/pantau halaman ini/i);
    });
});

describe('banner — ditutup oleh kandidat sendiri', () => {
    const dasar = { status: 'MENGUNDURKAN_DIRI', statusNada: 'netral', statusLabel: 'Mengundurkan Diri' };

    it('memakai label dari master, bukan kalimat penolakan', () => {
        const b = C.banner.call(konteks({ ...dasar, alasanGugur: null }));

        expect(b.title).toBe('Mengundurkan Diri');
        expect(b.title).not.toMatch(/belum lolos|gagal|ditolak/i);
    });

    it('menampilkan catatan bila ada', () => {
        expect(C.banner.call(konteks({ ...dasar, alasanGugur: CATATAN })).text).toBe(CATATAN);
    });
});

describe('banner — yang lama tidak ikut berubah', () => {
    it('gugur tetap menampilkan alasannya', () => {
        const b = C.banner.call(konteks({ status: 'GUGUR', statusNada: 'gugur', alasanGugur: CATATAN }));

        expect(b.title).toMatch(/belum lolos/i);
        expect(b.text).toBe(CATATAN);
    });

    it('lolos tetap mengucapkan selamat', () => {
        expect(C.banner.call(konteks({ status: 'LULUS', statusNada: 'lolos' })).title).toMatch(/diterima/i);
    });
});

describe('keadaanTahap — tahap yang seluruhnya dikerjakan tim', () => {
    // Negosiasi penawaran: dijadwalkan tim untuk dirinya sendiri, jadwalnya
    // tidak diumumkan, jadi kandidat tidak melihat satu pun aktivitas di sini.
    const tahapNego = (pesan) => [
        { urutan: 1, label: 'Wawancara', status: 'SELESAI', hasil: 'LULUS', tes: [] },
        { urutan: 2, label: 'Negosiasi Penawaran', status: 'BERJALAN', pesan, tes: [] },
    ];

    function ctxTahap(pesan) {
        const tahap = tahapNego(pesan);
        const c = konteks({ status: 'BERJALAN', statusNada: 'berjalan' }, tahap);
        c.aktivitas = [];
        c.tesDitunggu = null;
        return c;
    }

    it('TIDAK berkata "sudah kamu selesaikan" untuk tahap yang tak ia kerjakan', () => {
        const k = C.keadaanTahap.call(ctxTahap(null));

        expect(k.judul).not.toMatch(/sudah kamu selesaikan/i);
    });

    it('memberi kabar menunggu yang menenangkan', () => {
        const k = C.keadaanTahap.call(ctxTahap(null));

        expect(k.judul).toMatch(/ditangani tim rekrutmen/i);
        expect(k.pesan).toMatch(/tidak ada yang perlu kamu kerjakan/i);
    });

    it('memakai Pesan_Kandidat dari master bila diisi', () => {
        const dariMaster = 'Tim rekrutmen akan menghubungimu untuk membahas penawaran.';

        expect(C.keadaanTahap.call(ctxTahap(dariMaster)).pesan).toBe(dariMaster);
    });

    it('tidak membocorkan apa yang sedang dikerjakan tim', () => {
        const k = C.keadaanTahap.call(ctxTahap(null));

        expect(`${k.judul} ${k.pesan}`).not.toMatch(/negosiasi|gaji|rapat|jadwal/i);
    });
});

describe('waterfall tanpa tanggal', () => {
    const tahap = [
        { urutan: 1, label: 'Seleksi Berkas', status: 'SELESAI', hasil: 'LULUS' },
        { urutan: 2, label: 'Tes Online', status: 'BERJALAN' },
        { urutan: 3, label: 'Wawancara', status: 'MENUNGGU' },
        { urutan: 4, label: 'Penawaran', status: 'MENUNGGU' },
    ];
    // Dipanggil di dalam tiap kasus, bukan saat koleksi: implementasi lama
    // meledak di sini (memanggil helper tanggal), dan kalau itu terjadi saat
    // koleksi maka SELURUH berkas ini gagal dimuat — termasuk kasus banner yang
    // tidak ada hubungannya, sehingga merahnya tidak lagi menunjuk apa pun.
    const jalankan = () => C.gantt.call(konteks({ status: 'BERJALAN', statusNada: 'berjalan' }, tahap));

    it('tidak lagi menghasilkan sumbu tanggal maupun teks tanggal', () => {
        const g = jalankan();
        expect(g.ticks).toBeUndefined();
        g.rows.forEach((r) => {
            expect(r.dateText).toBeUndefined();
            expect(r.barLabel).toBeUndefined();
            expect(r.est).toBeUndefined();
        });
    });

    it('batangnya bertingkat menurut URUTAN tahap', () => {
        const g = jalankan();
        expect(g.rows.map((r) => r.left)).toEqual([0, 25, 50, 75]);
    });

    it('tiap batang bertindih dengan tetangganya, dan tak ada yang melewati tepi', () => {
        const g = jalankan();
        g.rows.forEach((r) => expect(r.left + r.width).toBeLessThanOrEqual(100));
        // Baris terakhir dipotong tepi; sisanya selebar dua kolom.
        expect(g.rows[0].width).toBe(50);
        expect(g.rows[3].width).toBe(25);
    });

    it('tetap membedakan selesai / berlangsung / menunggu', () => {
        const g = jalankan();
        expect(g.rows.map((r) => r.state)).toEqual(['done', 'current', 'todo', 'todo']);
        expect(g.rows[1].glow).toBe(true);
    });

    it('alur kosong tidak menjatuhkan halaman', () => {
        expect(C.gantt.call(konteks({ status: 'BERJALAN', statusNada: 'berjalan' }, []))).toEqual({ rows: [], kolom: 0 });
    });
});
