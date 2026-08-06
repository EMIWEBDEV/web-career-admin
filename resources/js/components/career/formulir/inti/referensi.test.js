/**
 * Uji INGATAN BENDERA pada pengambil opsi referensi.
 *
 * Yang tersimpan di jawaban hanya nama kampus, tanpa negara. Layout bertahap
 * melepas komponen langkah yang tidak sedang dibuka, jadi tanpa ingatan ini
 * bendera lenyap dari kolom terisi setiap kali kandidat menekan Kembali lalu
 * maju lagi — persis yang terlihat saat pengujian di peramban.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import { ambilOpsi, benderaDiingat } from './referensi';

vi.mock('axios');

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('ingatan bendera', () => {
    it('mengingat bendera dari hasil yang termuat', async () => {
        axios.get.mockResolvedValue({
            data: {
                result: [
                    { nilai: 'Universitas Sriwijaya', label: 'Universitas Sriwijaya', bendera: 'id' },
                    { nilai: 'Harvard University', label: 'Harvard University', bendera: 'us' },
                ],
            },
        });

        await ambilOpsi('kampus', { cari: 'uji-ingat-1' }, 'k1');

        expect(benderaDiingat('Universitas Sriwijaya')).toBe('id');
        expect(benderaDiingat('Harvard University')).toBe('us');
    });

    it('tidak mengingat baris tanpa bendera — kampus yang negaranya tak tercatat', async () => {
        axios.get.mockResolvedValue({
            data: {
                result: [{ nilai: 'Abaden Institute', label: 'Abaden Institute', bendera: null }],
            },
        });

        await ambilOpsi('kampus', { cari: 'uji-ingat-2' }, 'k2');

        expect(benderaDiingat('Abaden Institute')).toBeNull();
    });

    it('nilai yang belum pernah terlihat mengembalikan null, bukan undefined', () => {
        expect(benderaDiingat('Kampus Entah Berantah')).toBeNull();
        expect(benderaDiingat('')).toBeNull();
        expect(benderaDiingat(null)).toBeNull();
    });

    it('sumber non-kampus tidak mencemari ingatan', async () => {
        axios.get.mockResolvedValue({
            data: { result: [{ nilai: 'Teknik Informatika', label: 'Teknik Informatika', ket: 'S.Kom' }] },
        });

        await ambilOpsi('prodi', { cari: 'uji-ingat-3' }, 'p1');

        expect(benderaDiingat('Teknik Informatika')).toBeNull();
    });
});
