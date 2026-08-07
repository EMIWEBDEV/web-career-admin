/**
 * Uji NORMALISASI skema terhadap katalog.
 *
 * Titik beratnya: skema yang dimuat dari DB ikut dibersihkan, bukan hanya field
 * yang baru saja diubah tipenya di editor. Tanpa itu, sampah yang sudah terlanjur
 * tersimpan tidak akan pernah hilang.
 */
import { describe, expect, it } from 'vitest';
import { normalisasiSkema } from './schema';

function satuField(field) {
    return normalisasiSkema({
        langkah: [{ judul: 'L', bagian: [{ judul: 'B', field: [field] }] }],
    }).langkah[0].bagian[0].field[0];
}

describe('pembersihan saat memuat skema', () => {
    it('membuang properti sisa tipe lama', () => {
        const f = satuField({ key: 'kota', label: 'Kota', tipe: 'text', opsi: ['A', 'B'], maks_mb: 5 });
        expect(f.opsi).toBeUndefined();
        expect(f.maks_mb).toBeUndefined();
    });

    it('mempertahankan properti sah tipenya', () => {
        const f = satuField({ key: 'ipk', label: 'IPK', tipe: 'number', min: 0, maks: 4, desimal: 2 });
        expect(f.min).toBe(0);
        expect(f.maks).toBe(4);
        expect(f.desimal).toBe(2);
    });

    it('mempertahankan cascade referensi yang selama ini hanya bisa ditulis manual', () => {
        const f = satuField({
            key: 'nama_kampus',
            label: 'Kampus',
            tipe: 'referensi',
            sumber: 'kampus',
            bergantung: { jenjang: 'jenjang_pendidikan' },
            saring: { jenis: 'jenis_institusi' },
            ph_terkunci: 'Pilih jenjang dulu',
            reset_anak: ['jurusan'],
        });
        expect(f.sumber).toBe('kampus');
        expect(f.bergantung).toEqual({ jenjang: 'jenjang_pendidikan' });
        expect(f.saring).toEqual({ jenis: 'jenis_institusi' });
        expect(f.ph_terkunci).toBe('Pilih jenjang dulu');
        expect(f.reset_anak).toEqual(['jurusan']);
    });

    it('tipe tak dikenal jatuh ke text dan kehilangan properti asing', () => {
        const f = satuField({ key: 'a', label: 'A', tipe: 'warna', opsi: ['merah'] });
        expect(f.tipe).toBe('text');
        expect(f.opsi).toBeUndefined();
    });

    it('tetap menghasilkan key, field_id, dan lebar seperti sebelumnya', () => {
        const f = satuField({ label: 'Nama Lengkap', tipe: 'text' });
        expect(f.key).toBe('nama_lengkap');
        expect(f.field_id).toBeTruthy();
        expect(f.lebar_persen).toBe(33);
        expect(f.wajib).toBe(false);
    });

    it('field pilihan tetap punya array opsi walau skema lama tidak menyertakannya', () => {
        expect(satuField({ key: 'a', label: 'A', tipe: 'select' }).opsi).toEqual(['Opsi 1', 'Opsi 2']);
    });
});

describe('konteks pemakaian formulir', () => {
    it('skema lama tanpa konteks diperlakukan sebagai KEDUANYA', () => {
        expect(normalisasiSkema({ langkah: [] }).konteks).toBe('KEDUANYA');
    });

    it('nilai yang dikenal dipertahankan', () => {
        expect(normalisasiSkema({ konteks: 'PENDAFTARAN', langkah: [] }).konteks).toBe('PENDAFTARAN');
        expect(normalisasiSkema({ konteks: 'tahap', langkah: [] }).konteks).toBe('TAHAP');
    });

    it('nilai asing jatuh ke KEDUANYA, bukan diteruskan apa adanya', () => {
        expect(normalisasiSkema({ konteks: 'ENTAH', langkah: [] }).konteks).toBe('KEDUANYA');
    });
});
