// @vitest-environment jsdom
/**
 * PERIODE TARGET MPP — satu tanggal yang tidak bisa dibaca.
 *
 * Laporan aslinya: di bawah label "Tanggal Periode Target" tertulis
 * "Kamis, 01 Oktober 2026" — dan tidak ada cara membacanya. Itu tanggal mulai?
 * tanggal selesai? Yang dimaksud sebenarnya rentang kerjanya: dari hari MPP
 * dibuat sampai tenggat SLA-nya, 20 Agustus – 1 Oktober 2026.
 *
 * Dua jebakan yang diuji di sini, keduanya muncul justru setelah rentangnya
 * dipakai:
 *
 *   1. MPP LAMA. Tenggatnya tersimpan, tanggal mulainya tidak (dibuat sebelum
 *      kolom snapshot SLA ada). Menambal awalnya dengan "hari ini" mencetak
 *      rentang TERBALIK — "20 Agustus – 1 Juli 2026".
 *   2. TAHUN. Rentang setahun tidak perlu menulis tahunnya dua kali; rentang
 *      yang menyeberang tahun justru wajib.
 *
 * Diuji lewat definisi Options API-nya langsung, sama seperti berkas uji lain
 * di proyek ini — merakit seluruh halaman cuma menambah bagian yang bisa gagal
 * tanpa menambah yang diperiksa.
 */
import { describe, it, expect } from 'vitest';
import halaman from '../Pages/Career/admin/master-mpp/masterMpp.vue';

const { computed, methods } = halaman;

/** Instance tiruan: computed periode cuma membaca form, sla, slaBeku, editingNo. */
function bikin({ mulai = null, akhir = '', hari = null, beku = null, editingNo = null } = {}) {
    const inst = {
        editingNo,
        slaBeku: beku,
        sla: { hari, mulai, batas: null },
        form: { tanggalPeriode: akhir },
        tglPanjang: methods.tglPanjang,
        rentangTanggal: methods.rentangTanggal,
    };

    for (const nama of ['periodeMulai', 'periodeAkhir', 'periodeHari', 'punyaRentang', 'periodeTeks']) {
        Object.defineProperty(inst, nama, { get: () => computed[nama].call(inst), configurable: true });
    }

    return inst;
}

describe('periode target MPP', () => {
    it('menulis periode sebagai RENTANG, bukan satu tanggal', () => {
        const inst = bikin({ mulai: '2026-08-20', akhir: '2026-10-01', hari: 30 });

        expect(inst.punyaRentang).toBe(true);
        expect(inst.periodeTeks).toBe('20 Agustus – 1 Oktober 2026');
    });

    it('tahun ditulis sekali bila keduanya setahun', () => {
        const inst = bikin({ mulai: '2026-08-20', akhir: '2026-10-01' });

        expect(inst.periodeTeks.match(/2026/g)).toHaveLength(1);
    });

    it('tahun ditulis dua kali bila menyeberang tahun', () => {
        const inst = bikin({ mulai: '2026-12-10', akhir: '2027-02-15' });

        expect(inst.periodeTeks).toBe('10 Desember 2026 – 15 Februari 2027');
    });

    it('MPP lama tanpa tanggal mulai: tenggat saja, tidak dibuat rentang terbalik', () => {
        // sla.mulai = hari ini (hitungan baru), tenggat tersimpan jauh di masa lalu.
        const inst = bikin({ mulai: '2026-08-20', akhir: '2026-07-01', editingNo: 'MPP-2026-112' });

        expect(inst.punyaRentang).toBe(false);
        expect(inst.periodeTeks).toBe('Rabu, 01 Juli 2026');
    });

    it('MPP lama yang PUNYA snapshot memakai tanggal mulainya sendiri', () => {
        const inst = bikin({
            mulai: '2026-08-20', // hitungan hari ini — harus kalah
            akhir: '2026-07-01',
            hari: 30,
            beku: { mulai: '2026-05-20', hari: 30 },
            editingNo: 'MPP-2026-112',
        });

        expect(inst.periodeMulai).toBe('2026-05-20');
        expect(inst.periodeHari).toBe(30);
        expect(inst.periodeTeks).toBe('20 Mei – 1 Juli 2026');
    });

    it('tenggat dibaca dari borang, bukan dari hitungan hari ini', () => {
        const inst = bikin({ mulai: '2026-08-20', akhir: '2026-07-01', editingNo: 'MPP-2026-112' });

        expect(inst.periodeAkhir).toBe('2026-07-01');
    });

    it('belum ada tenggat sama sekali → tidak ada yang ditulis', () => {
        const inst = bikin({});

        expect(inst.periodeAkhir).toBeNull();
        expect(inst.punyaRentang).toBe(false);
        expect(inst.periodeTeks).toBe('—');
    });
});
