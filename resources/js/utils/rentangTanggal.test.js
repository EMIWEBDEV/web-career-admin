// @vitest-environment jsdom
/**
 * RENTANG TANGGAL — dipakai kartu MPP, panel detail, dan borang MPP.
 *
 * Aturannya sengaja ditulis sekali di satu berkas: sebelumnya tiap layar
 * memformat periodenya sendiri-sendiri, dan yang satu ("01 Okt 2026") tidak
 * sama artinya dengan yang lain ("20 Agu 2026 – 01 Okt 2026") padahal keduanya
 * menggambarkan MPP yang sama.
 */
import { describe, it, expect } from 'vitest';
import { punyaRentang, rentangPendek, rentangPanjang } from './rentangTanggal';

describe('rentangTanggal', () => {
    it('menulis rentang pendek untuk kartu', () => {
        expect(rentangPendek('2026-08-20', '2026-10-01')).toBe('20 Agu – 01 Okt 2026');
    });

    it('menulis rentang panjang untuk borang & panel', () => {
        expect(rentangPanjang('2026-08-20', '2026-10-01')).toBe('20 Agustus – 1 Oktober 2026');
    });

    it('tahun ditulis sekali bila keduanya setahun', () => {
        expect(rentangPendek('2026-08-20', '2026-10-01').match(/2026/g)).toHaveLength(1);
    });

    it('tahun ditulis dua kali bila menyeberang tahun', () => {
        expect(rentangPendek('2026-12-10', '2027-02-15')).toBe('10 Des 2026 – 15 Feb 2027');
    });

    it('tanpa tanggal mulai: tenggatnya saja, bukan rentang terbalik', () => {
        expect(punyaRentang(null, '2026-07-01')).toBe(false);
        expect(rentangPendek(null, '2026-07-01')).toBe('01 Jul 2026');
    });

    it('mulai yang melewati akhir juga bukan rentang', () => {
        // MPP lama: tenggat tersimpan di masa lalu, "mulai" hasil hitungan hari ini.
        expect(punyaRentang('2026-08-20', '2026-07-01')).toBe(false);
        expect(rentangPendek('2026-08-20', '2026-07-01')).toBe('01 Jul 2026');
    });

    it('hari yang sama tetap dihitung sebagai rentang', () => {
        expect(punyaRentang('2026-08-20', '2026-08-20')).toBe(true);
        expect(rentangPendek('2026-08-20', '2026-08-20')).toBe('20 Agu – 20 Agu 2026');
    });

    it('tanpa tenggat sama sekali tidak ada yang ditulis', () => {
        expect(rentangPendek('2026-08-20', null)).toBe('—');
        expect(rentangPanjang(null, null)).toBe('—');
    });

    it('tanggal ngawur tidak dianggap sah', () => {
        expect(punyaRentang('bukan-tanggal', '2026-10-01')).toBe(false);
        expect(rentangPendek('bukan-tanggal', '2026-10-01')).toBe('01 Okt 2026');
        expect(rentangPendek('2026-08-20', 'bukan-tanggal')).toBe('—');
    });
});
