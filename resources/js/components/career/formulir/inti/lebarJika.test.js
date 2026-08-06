/**
 * Uji perilaku LEBAR BERSYARAT (`lebar_jika`) dan normalisasinya.
 *
 * Yang dikunci di sini adalah aturan yang mudah rusak diam-diam saat skema
 * dirapikan: berapa span grid yang keluar dari sebuah persentase, dan kapan
 * lebar bersyarat boleh menang atas lebar tetap.
 */
import { describe, expect, it } from 'vitest';
import { normalisasiSkema } from './schema';
import { syaratTerpenuhi } from './aturan';

/** Salinan rumus lebar di FieldRenderer.vue — dijaga tetap sama oleh uji ini. */
function spanDari(field, jawaban) {
    const alt = field.lebar_jika;
    let persen;
    if (alt?.field && syaratTerpenuhi(alt, jawaban)) {
        persen = Number(alt.lebar_persen) || 100;
    } else if (field.penuh) {
        persen = 100;
    } else {
        persen = Number(field.lebar_persen || 33);
    }
    persen = Math.min(100, Math.max(33, persen));

    return Math.min(12, Math.max(4, Math.round((persen / 100) * 12)));
}

function skemaSatuField(field) {
    return normalisasiSkema({
        layout: 'BERTAHAP',
        langkah: [{ judul: 'L', bagian: [{ judul: 'B', field: [field] }] }],
    }).langkah[0].bagian[0].field[0];
}

describe('normalisasi lebar_jika', () => {
    it('membuang aturan tanpa field acuan', () => {
        expect(skemaSatuField({ key: 'a', label: 'A', lebar_jika: {} }).lebar_jika).toBeNull();
        expect(skemaSatuField({ key: 'a', label: 'A' }).lebar_jika).toBeNull();
    });

    it('melengkapi operator dan membatasi persen ke rentang 33-100', () => {
        const f = skemaSatuField({
            key: 'a',
            label: 'A',
            lebar_jika: { field: 'status', nilai: 'Mahasiswa', lebar_persen: 5 },
        });
        expect(f.lebar_jika.operator).toBe('=');
        expect(f.lebar_jika.lebar_persen).toBe(33);

        const g = skemaSatuField({
            key: 'a',
            label: 'A',
            lebar_jika: { field: 'status', nilai: 'x', lebar_persen: 999 },
        });
        expect(g.lebar_jika.lebar_persen).toBe(100);
    });
});

describe('span grid dari persentase', () => {
    it.each([
        [33, 4],
        [50, 6],
        [67, 8],
        [100, 12],
    ])('%i%% menjadi span %i dari 12', (persen, span) => {
        expect(spanDari({ lebar_persen: persen }, {})).toBe(span);
    });

    it('47% tetap setengah baris — nilai lama di skema tidak berubah artinya', () => {
        expect(spanDari({ lebar_persen: 47 }, {})).toBe(6);
    });
});

describe('kasus nyata: Status Kemahasiswaan', () => {
    const field = {
        key: 'status_kemahasiswaan',
        lebar_persen: 100,
        lebar_jika: {
            field: 'status_kemahasiswaan',
            operator: '=',
            nilai: 'Mahasiswa',
            lebar_persen: 50,
        },
    };

    it('penuh selagi belum dijawab', () => {
        expect(spanDari(field, {})).toBe(12);
        expect(spanDari(field, { status_kemahasiswaan: '' })).toBe(12);
    });

    it('penuh saat Sudah Lulus', () => {
        expect(spanDari(field, { status_kemahasiswaan: 'Sudah Lulus' })).toBe(12);
    });

    it('menyusut jadi setengah saat Mahasiswa, memberi ruang untuk Semester', () => {
        expect(spanDari(field, { status_kemahasiswaan: 'Mahasiswa' })).toBe(6);
    });

    it('mengabaikan beda huruf besar-kecil, sesuai mesin syarat', () => {
        expect(spanDari(field, { status_kemahasiswaan: 'mahasiswa' })).toBe(6);
    });
});

describe('lebar bersyarat menang atas penuh', () => {
    it('field ber-penuh tetap bisa menyusut ketika syaratnya terpenuhi', () => {
        const f = {
            penuh: true,
            lebar_persen: 100,
            lebar_jika: { field: 's', operator: '=', nilai: 'ya', lebar_persen: 50 },
        };
        expect(spanDari(f, { s: 'tidak' })).toBe(12);
        expect(spanDari(f, { s: 'ya' })).toBe(6);
    });
});
