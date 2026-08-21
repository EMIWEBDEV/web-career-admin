// @vitest-environment jsdom
/**
 * INGAT MODAL — modal yang selamat dari refresh.
 *
 * Yang diuji di sini bukan tampilan, melainkan tiga janji yang mudah dilanggar
 * diam-diam saat mixin ini disentuh lagi nanti:
 *
 *   1. modal terbuka + isiannya kembali setelah halaman dimuat ulang;
 *   2. modal yang SUDAH DITUTUP tidak boleh hidup lagi karena refresh;
 *   3. dialog konfirmasi hapus tidak pernah bangkit sendiri — jarinya masih
 *      di posisi "Ya" saat halaman kembali.
 *
 * Mixin-nya diuji lewat objek tiruan, bukan komponen sungguhan: yang dipakai
 * mixin ini hanya `$data`, `$watch`, dan satu metode — merakit seluruh halaman
 * cuma menambah bagian yang bisa gagal tanpa menambah yang diperiksa.
 */
import { describe, it, expect, beforeEach } from 'vitest';
import { ingatModal } from './ingatModal';

const KUNCI = 'wca:modal:uji';

/** Instance tiruan: mixin cuma butuh $data + $watch. */
function bikin(data, opsi = {}) {
    const mixin = ingatModal('uji', opsi);
    const inst = { $data: data, $watch() {}, ...mixin.methods };
    mixin.created.call(inst);

    return inst;
}

/** ingatModalSimpan() menunda 250 ms; tunggu sampai tulisannya mendarat. */
async function tunggu() {
    await new Promise((r) => setTimeout(r, 320));
}

function isiSimpanan(data, umurMs = 0) {
    sessionStorage.setItem(KUNCI, JSON.stringify({ ts: Date.now() - umurMs, data }));
}

describe('ingatModal', () => {
    beforeEach(() => sessionStorage.clear());

    it('menyimpan keadaan selama ada modal terbuka', async () => {
        const inst = bikin({ show: true, form: { nama: 'Analis Data' }, loading: false });
        inst.ingatModalSimpan();
        await tunggu();

        const paket = JSON.parse(sessionStorage.getItem(KUNCI));
        expect(paket.data.show).toBe(true);
        expect(paket.data.form.nama).toBe('Analis Data');
    });

    it('membuang simpanan begitu modalnya ditutup', async () => {
        const inst = bikin({ show: true, form: { nama: 'Analis Data' } });
        inst.ingatModalSimpan();
        await tunggu();
        expect(sessionStorage.getItem(KUNCI)).not.toBeNull();

        // Ditutup dengan sengaja — refresh sesudahnya tidak boleh membangkitkannya.
        inst.$data.show = false;
        inst.ingatModalSimpan();
        await tunggu();

        expect(sessionStorage.getItem(KUNCI)).toBeNull();
    });

    it('memasang kembali modal & isiannya saat halaman dimuat ulang', () => {
        isiSimpanan({ show: true, editingId: 7, form: { nama: 'Analis Data', aktif: true } });

        const inst = bikin({ show: false, editingId: null, form: { nama: '', aktif: false } });

        expect(inst.$data.show).toBe(true);
        expect(inst.$data.editingId).toBe(7);
        expect(inst.$data.form.nama).toBe('Analis Data');
    });

    it('tidak memasang apa pun bila simpanannya tidak punya modal terbuka', () => {
        isiSimpanan({ show: false, form: { nama: 'lama' } });

        const inst = bikin({ show: false, form: { nama: '' } });

        expect(inst.$data.form.nama).toBe('');
        expect(sessionStorage.getItem(KUNCI)).toBeNull();
    });

    it('tidak pernah membangkitkan dialog hapus', () => {
        isiSimpanan({ show: true, delShow: true, katDelShow: true, delTarget: { id: 9 } });

        const inst = bikin({ show: false, delShow: false, katDelShow: false, delTarget: null });

        expect(inst.$data.show).toBe(true);
        expect(inst.$data.delShow).toBe(false);
        expect(inst.$data.katDelShow).toBe(false);
    });

    it('dialog hapus sendirian tidak dianggap "ada modal terbuka"', () => {
        isiSimpanan({ delShow: true, form: { nama: 'lama' } });

        const inst = bikin({ delShow: false, form: { nama: '' } });

        expect(inst.$data.form.nama).toBe('');
    });

    it('tidak menyimpan penanda proses & daftar yang dimuat ulang sendiri', async () => {
        const inst = bikin({
            show: true,
            form: { nama: 'x' },
            loading: true,
            saving: true,
            list: [{ id: 1 }],
            total: 1,
            karyawanLoading: true,
            cariTimer: 12,
        });
        inst.ingatModalSimpan();
        await tunggu();

        const { data } = JSON.parse(sessionStorage.getItem(KUNCI));
        expect(data.show).toBe(true);
        expect(data.loading).toBeUndefined();
        expect(data.saving).toBeUndefined();
        expect(data.list).toBeUndefined();
        expect(data.total).toBeUndefined();
        expect(data.karyawanLoading).toBeUndefined();
        expect(data.cariTimer).toBeUndefined();
    });

    it('simpanan yang sudah basi diabaikan', () => {
        isiSimpanan({ show: true, form: { nama: 'kemarin' } }, 13 * 60 * 60 * 1000);

        const inst = bikin({ show: false, form: { nama: '' } });

        expect(inst.$data.show).toBe(false);
        expect(inst.$data.form.nama).toBe('');
    });

    it('opsi buka: hanya penanda yang disebut yang membangkitkan pemulihan', async () => {
        // panelBuka cuma laci penyaring — bukan pekerjaan yang bisa hilang.
        isiSimpanan({ panelBuka: true, emailShow: false, form: { isi: 'lama' } });

        const inst = bikin(
            { panelBuka: false, emailShow: false, form: { isi: '' } },
            { buka: ['emailShow'] },
        );

        expect(inst.$data.form.isi).toBe('');

        inst.$data.panelBuka = true;
        inst.ingatModalSimpan();
        await tunggu();
        expect(sessionStorage.getItem(KUNCI)).toBeNull();

        inst.$data.emailShow = true;
        inst.ingatModalSimpan();
        await tunggu();
        expect(JSON.parse(sessionStorage.getItem(KUNCI)).data.emailShow).toBe(true);
    });

    it('opsi abaikan: bidang yang disebut tidak ikut tersimpan', async () => {
        const inst = bikin(
            { show: true, borong: { antre: 5 }, form: { nama: 'x' } },
            { abaikan: [/^borong$/] },
        );
        inst.ingatModalSimpan();
        await tunggu();

        const { data } = JSON.parse(sessionStorage.getItem(KUNCI));
        expect(data.borong).toBeUndefined();
        expect(data.form.nama).toBe('x');
    });

    it('berkas terpilih tidak disimpan sebagai objek hampa', async () => {
        const berkas = new File(['isi'], 'ktp.pdf', { type: 'application/pdf' });
        const inst = bikin({ show: true, form: { nama: 'x', lampiran: berkas } });
        inst.ingatModalSimpan();
        await tunggu();

        const { data } = JSON.parse(sessionStorage.getItem(KUNCI));
        expect(data.form.lampiran).toBeNull();
        expect(data.form.nama).toBe('x');
    });

    it('nama biasa yang kebetulan mengandung "del" tetap ikut dipasang', () => {
        // "model" pernah tertangkap pola /del/i yang polos.
        isiSimpanan({ show: true, model: 'ONLINE', form: {} });

        const inst = bikin({ show: false, model: '', form: {} });

        expect(inst.$data.model).toBe('ONLINE');
    });
});
