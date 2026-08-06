// @vitest-environment jsdom
/**
 * Uji LANGKAH PRATINJAU pada layout Bertahap.
 *
 * Pratinjau adalah langkah SEMU di indeks `langkah.length` — di luar rentang
 * skema. Berkas ini mengunci aritmetika indeksnya (tempat off-by-one paling
 * mungkin muncul) dan aturan perangkuman jawabannya.
 */
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Bertahap from './Bertahap.vue';

const SKEMA = {
    layout: 'BERTAHAP',
    langkah: [
        {
            judul: 'Data Diri',
            bagian: [
                {
                    judul: 'Identitas',
                    field: [
                        { key: 'nama', label: 'Nama', tipe: 'text', wajib: true },
                        { key: 'hobi', label: 'Hobi', tipe: 'text' },
                    ],
                },
            ],
        },
        {
            judul: 'Pernyataan',
            bagian: [
                {
                    judul: 'Persetujuan',
                    field: [{ key: 'setuju', label: 'Saya setuju', tipe: 'consent', wajib: true }],
                },
            ],
        },
    ],
};

function pasang(jawaban = {}) {
    return mount(Bertahap, {
        props: { skema: SKEMA, modelValue: { nama: '', hobi: '', setuju: false, ...jawaban } },
        global: { stubs: { BagianRenderer: true, FieldRenderer: true } },
    });
}

describe('stepper', () => {
    it('menambahkan tepat satu langkah semu di ujung', () => {
        const w = pasang();
        expect(w.findAll('.t2__step')).toHaveLength(SKEMA.langkah.length + 1);
        expect(w.findAll('.t2__step').at(-1).text()).toContain('Periksa & Kirim');
    });

    it('mulai di langkah pertama, bukan di pratinjau', () => {
        expect(pasang().vm.aktif).toBe(0);
    });
});

describe('perpindahan langkah', () => {
    it('tidak bisa maju sebelum langkah sekarang lengkap', () => {
        const w = pasang();
        w.vm.maju();
        expect(w.vm.aktif).toBe(0);
        expect(w.vm.galat.length).toBeGreaterThan(0);
    });

    it('maju sampai pratinjau lalu berhenti — tidak melewati batas', () => {
        const w = pasang({ nama: 'Budi', setuju: true });
        w.vm.maju();
        expect(w.vm.aktif).toBe(1);
        w.vm.maju();
        expect(w.vm.aktif).toBe(2); // indeks pratinjau
        w.vm.maju();
        expect(w.vm.aktif).toBe(2); // tetap, tidak jadi 3
    });

    it('mundur dari pratinjau kembali ke langkah terakhir skema', () => {
        const w = pasang({ nama: 'Budi', setuju: true });
        w.vm.aktif = 2;
        w.vm.mundur();
        expect(w.vm.aktif).toBe(1);
    });

    it('indeks yang dikirim ke server dipotong ke rentang skema', async () => {
        const w = pasang({ nama: 'Budi', setuju: true });
        w.vm.aktif = 2;
        await w.vm.$nextTick();
        const kirim = w.emitted('pindah-langkah') || [];
        expect(kirim.at(-1)[0]).toBe(1); // bukan 2
    });
});

describe('rangkuman', () => {
    it('menampilkan label dan jawaban tiap field', () => {
        const w = pasang({ nama: 'Budi', hobi: 'Membaca', setuju: true });
        const L = w.vm.ringkasan;
        expect(L).toHaveLength(2);
        expect(L[0].judul).toBe('Data Diri');
        const item = L[0].bagian[0].item;
        expect(item.find((i) => i.key === 'nama').nilai).toBe('Budi');
        expect(item.find((i) => i.key === 'hobi').nilai).toBe('Membaca');
    });

    it('menandai yang belum diisi, bukan menyembunyikannya', () => {
        const w = pasang({ nama: 'Budi' });
        const hobi = w.vm.ringkasan[0].bagian[0].item.find((i) => i.key === 'hobi');
        expect(hobi.kosong).toBe(true);
        expect(hobi.nilai).toBe('—');
    });

    it('menerjemahkan persetujuan jadi teks, bukan true/false', () => {
        expect(pasang({ setuju: true }).vm.ringkasan[1].bagian[0].item[0].nilai).toBe('Disetujui');
        expect(pasang({ setuju: false }).vm.ringkasan[1].bagian[0].item[0].nilai).toBe('Belum disetujui');
    });

    it('menyimpan indeks langkah supaya tombol Ubah melompat ke tempat yang benar', () => {
        const w = pasang();
        expect(w.vm.ringkasan.map((L) => L.index)).toEqual([0, 1]);
    });
});

describe('persetujuan di halaman pratinjau', () => {
    it('dikumpulkan lintas langkah supaya bisa dicentang di dekat tombol kirim', () => {
        const w = pasang();
        expect(w.vm.persetujuan.map((f) => f.key)).toEqual(['setuju']);
    });

    it('tombol kirim terkunci sampai semua persetujuan dicentang', () => {
        expect(pasang({ nama: 'Budi' }).vm.bolehKirim).toBe(false);
        expect(pasang({ nama: 'Budi', setuju: true }).vm.bolehKirim).toBe(true);
    });
});

describe('kirim', () => {
    it('menolak kirim bila masih ada langkah yang belum lengkap, dan menyebut langkahnya', () => {
        const w = pasang({ setuju: true });
        w.vm.aktif = 2;
        w.vm.kirim();
        expect(w.emitted('kirim')).toBeUndefined();
        expect(w.vm.galat.some((g) => g.startsWith('Data Diri —'))).toBe(true);
    });

    it('mengirim jawaban saat semuanya lengkap', () => {
        const w = pasang({ nama: 'Budi', setuju: true });
        w.vm.aktif = 2;
        w.vm.kirim();
        expect(w.emitted('kirim')).toBeTruthy();
    });
});

describe('skema disunting saat dipratinjau (builder admin)', () => {
    it('menyunting isi field tidak melempar balik ke langkah 1', async () => {
        const w = pasang({ nama: 'Budi', setuju: true });
        w.vm.maju();
        expect(w.vm.aktif).toBe(1);

        // normalisasiSkema() membangun objek baru tiap ketukan huruf di builder.
        const disunting = JSON.parse(JSON.stringify(SKEMA));
        disunting.langkah[0].bagian[0].field[0].label = 'Nama Lengkap';
        await w.setProps({ skema: disunting });

        expect(w.vm.aktif).toBe(1);
    });

    it('menambah langkah baru memang mengembalikan ke awal', async () => {
        const w = pasang({ nama: 'Budi', setuju: true });
        w.vm.maju();
        expect(w.vm.aktif).toBe(1);

        const ditambah = JSON.parse(JSON.stringify(SKEMA));
        ditambah.langkah.push({ judul: 'Langkah Baru', bagian: [] });
        await w.setProps({ skema: ditambah });

        expect(w.vm.aktif).toBe(0);
    });
});

describe('skema tanpa langkah', () => {
    it('tidak melempar error', () => {
        const w = mount(Bertahap, {
            props: { skema: { langkah: [] }, modelValue: {} },
            global: { stubs: { BagianRenderer: true, FieldRenderer: true } },
        });
        expect(w.vm.ringkasan).toEqual([]);
    });
});
