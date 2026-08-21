// @vitest-environment jsdom
/**
 * REGRESI — sambutan "Jawaban tesmu sudah terkirim" bocor ke tahap berikutnya.
 *
 * Laporan aslinya: alur dengan tes daring di tahap 3 DAN tahap 5. Begitu tahap 3
 * selesai dan kandidat sampai di tahap 5, tes tahap 5 yang belum tersentuh
 * disambut sebagai sudah dikerjakan — kartu tesnya tertutup keterangan biru, dan
 * kandidat mengira tak ada lagi yang perlu ia kerjakan.
 *
 * Dua jalan yang membocorkannya, dua-duanya diuji di sini:
 *   1. penanda `?dari=tes` yang menempel di alamat dan ikut terbawa
 *      router.reload() saat halaman memantau hasil;
 *   2. jejak sessionStorage tahap sebelumnya yang belum hangus.
 *
 * Diuji lewat definisi Options API-nya langsung (methods/computed dipanggil
 * dengan `this` buatan) supaya tidak perlu merakit seluruh halaman beserta
 * seluruh dependensinya.
 */
import { describe, expect, it, beforeEach } from 'vitest';
import Komponen from './LamaranDetail.vue';

const M = Komponen.methods;
const C = Komponen.computed;

/** `this` seadanya — hanya yang benar-benar disentuh fungsi yang diuji. */
function konteks({ tahapId, aktivitas, pulangTes = null }) {
    return {
        lamaran: { id: 'LMR1' },
        tahapAktif: { id: tahapId },
        aktivitas,
        pulangTes,
        sudahSelesai: (t) => !!t.selesai,
        lupakanPergiTes: M.lupakanPergiTes,
        hentikanPantauHasil() { /* tak ada timer di uji ini */ },
        kunciPergiTes: M.kunciPergiTes,
    };
}

const TES_TAHAP_3 = [{ id: 'akt3', ujian: { bisaAkses: true }, selesai: false }];
const TES_TAHAP_5 = [{ id: 'akt5', ujian: { bisaAkses: true }, selesai: false }];

beforeEach(() => {
    sessionStorage.clear();
    window.history.replaceState({}, '', '/kandidat/lamaran/LMR1');
});

describe('penanda ?dari=tes', () => {
    it('menyambut kepulangan pada pemuatan pertama', () => {
        window.history.replaceState({}, '', '/kandidat/lamaran/LMR1?dari=tes');
        const ctx = konteks({ tahapId: 'th3', aktivitas: TES_TAHAP_3 });

        expect(M.tebakPulangDariAlamat.call(ctx)).toMatchObject({ id: 'akt3', tahap: 'th3' });
    });

    it('HABIS SEKALI PAKAI — dicabut dari alamat begitu dibaca', () => {
        window.history.replaceState({}, '', '/kandidat/lamaran/LMR1?dari=tes');
        const ctx = konteks({ tahapId: 'th3', aktivitas: TES_TAHAP_3 });

        M.tebakPulangDariAlamat.call(ctx);

        expect(window.location.search).toBe('');
        // Pembacaan kedua (router.reload / tombol Kembali) tidak menyambut lagi.
        expect(M.tebakPulangDariAlamat.call(ctx)).toBeNull();
    });

    it('BUG ASLI: tidak lagi menyambut tes tahap 5 setelah pulang dari tahap 3', () => {
        window.history.replaceState({}, '', '/kandidat/lamaran/LMR1?dari=tes');

        // Tahap 3: kandidat memang baru pulang ujian.
        M.tebakPulangDariAlamat.call(konteks({ tahapId: 'th3', aktivitas: TES_TAHAP_3 }));

        // Hasil masuk, tahap bergerak ke 5 yang juga punya tepat satu tes daring.
        // Dulu penanda yang sama terbaca lagi di sini dan memungut 'akt5'.
        const ditahap5 = M.tebakPulangDariAlamat.call(konteks({ tahapId: 'th5', aktivitas: TES_TAHAP_5 }));

        expect(ditahap5).toBeNull();
    });

    it('tetap diam bila ada lebih dari satu tes daring belum selesai', () => {
        window.history.replaceState({}, '', '/kandidat/lamaran/LMR1?dari=tes');
        const ctx = konteks({
            tahapId: 'th3',
            aktivitas: [
                { id: 'a', ujian: {}, selesai: false },
                { id: 'b', ujian: {}, selesai: false },
            ],
        });

        expect(M.tebakPulangDariAlamat.call(ctx)).toBeNull();
    });
});

describe('sambutPulangTes menghabiskan penanda alamat', () => {
    /*
     * LAPORAN: tahap 5 punya satu tes daring (belum tersentuh) dan satu tes
     * luring yang sudah selesai. Kandidat membuka halaman, dan tes daringnya
     * langsung berkata "Jawabanmu sudah terkirim".
     *
     * Sebabnya bukan tebakan alamatnya, melainkan URUTAN pemanggilannya. Saat
     * kandidat pulang dari ujian tahap sebelumnya, jejak sessionStorage yang
     * menang — sehingga `tebakPulangDariAlamat()` tidak pernah jalan, dan
     * `?dari=tes` tertinggal di alamat. Penanda itu baru hidup lagi setelah
     * tahapnya bergerak, tepat ketika jejaknya sudah hangus, lalu memungut tes
     * tahap baru yang belum pernah dibuka.
     */
    function konteksSambut(o) {
        const ctx = Object.assign(konteks(o), {
            bacaPergiTes: M.bacaPergiTes,
            tebakPulangDariAlamat: M.tebakPulangDariAlamat,
            tandaiPergiTes: M.tandaiPergiTes,
            hentikanPantauHasil() {},
            pulangCek: 0,
            pulangTimer: null,
        });

        // defineProperty, BUKAN Object.assign: assign menyalin NILAI getter
        // sekali di tempat itu juga — `tesDitunggu` akan membeku pada hasil
        // saat `pulangTes` masih null, dan sambutannya selalu terbaca kosong.
        Object.defineProperty(ctx, 'tesDitunggu', {
            get() {
                return C.tesDitunggu.call(this);
            },
        });

        return ctx;
    }

    it('BUG ASLI: penanda tidak boleh selamat ketika jejak sessionStorage yang menang', () => {
        // Tahap 3 — kandidat benar-benar pulang dari ujian: ada jejak DAN penanda.
        window.history.replaceState({}, '', '/kandidat/lamaran/LMR1?dari=tes');
        const ditahap3 = konteksSambut({ tahapId: 'th3', aktivitas: TES_TAHAP_3 });
        M.tandaiPergiTes.call(ditahap3, TES_TAHAP_3[0]);
        M.sambutPulangTes.call(ditahap3);

        // Jejaknya yang dipakai — tapi penandanya tetap harus habis.
        expect(ditahap3.pulangTes).toMatchObject({ id: 'akt3' });
        expect(window.location.search).toBe('');
    });

    it('BUG ASLI: tes tahap berikutnya tidak disambut sebagai sudah terkirim', () => {
        window.history.replaceState({}, '', '/kandidat/lamaran/LMR1?dari=tes');
        const ditahap3 = konteksSambut({ tahapId: 'th3', aktivitas: TES_TAHAP_3 });
        M.tandaiPergiTes.call(ditahap3, TES_TAHAP_3[0]);
        M.sambutPulangTes.call(ditahap3);

        // Hasil masuk, tahap bergerak. Kandidat membuka halaman lagi — dulu
        // penanda yang tertinggal memungut 'akt5' di sini.
        const ditahap5 = konteksSambut({ tahapId: 'th5', aktivitas: TES_TAHAP_5 });
        M.sambutPulangTes.call(ditahap5);

        expect(ditahap5.pulangTes).toBeNull();
    });

    it('cadangan alamat tetap jalan untuk kandidat tanpa sessionStorage', () => {
        window.history.replaceState({}, '', '/kandidat/lamaran/LMR1?dari=tes');
        const ctx = konteksSambut({ tahapId: 'th3', aktivitas: TES_TAHAP_3 });
        M.sambutPulangTes.call(ctx);

        expect(ctx.pulangTes).toMatchObject({ id: 'akt3', tahap: 'th3' });
        ctx.hentikanPantauHasil();
        clearInterval(ctx.pulangTimer);
    });
});

describe('jejak sessionStorage', () => {
    it('sah selama masih di tahap yang sama', () => {
        const ctx = konteks({ tahapId: 'th3', aktivitas: TES_TAHAP_3 });
        M.tandaiPergiTes.call(ctx, TES_TAHAP_3[0]);

        expect(M.bacaPergiTes.call(ctx)).toMatchObject({ id: 'akt3', tahap: 'th3' });
    });

    it('HANGUS begitu tahapnya bergerak — dan dibuang, bukan cuma diabaikan', () => {
        M.tandaiPergiTes.call(konteks({ tahapId: 'th3', aktivitas: TES_TAHAP_3 }), TES_TAHAP_3[0]);

        const ditahap5 = konteks({ tahapId: 'th5', aktivitas: TES_TAHAP_5 });
        expect(M.bacaPergiTes.call(ditahap5)).toBeNull();
        expect(sessionStorage.getItem('wc_tes_pergi_LMR1')).toBeNull();
    });

    it('jejak lebih tua dari 12 jam dibuang', () => {
        sessionStorage.setItem(
            'wc_tes_pergi_LMR1',
            JSON.stringify({ id: 'akt3', tahap: 'th3', at: Date.now() - 13 * 60 * 60 * 1000 }),
        );

        expect(M.bacaPergiTes.call(konteks({ tahapId: 'th3', aktivitas: TES_TAHAP_3 }))).toBeNull();
    });
});

describe('tesDitunggu', () => {
    it('menunggu hasil selama tesnya belum tercatat selesai', () => {
        const ctx = konteks({
            tahapId: 'th3',
            aktivitas: TES_TAHAP_3,
            pulangTes: { id: 'akt3', tahap: 'th3', at: Date.now() },
        });

        expect(C.tesDitunggu.call(ctx)).toMatchObject({ id: 'akt3' });
    });

    it('berhenti menunggu begitu hasilnya masuk', () => {
        const ctx = konteks({
            tahapId: 'th3',
            aktivitas: [{ id: 'akt3', ujian: {}, selesai: true }],
            pulangTes: { id: 'akt3', tahap: 'th3', at: Date.now() },
        });

        expect(C.tesDitunggu.call(ctx)).toBeNull();
    });

    it('BUG ASLI: jejak tahap 3 tidak boleh menyambut tes tahap 5', () => {
        const ctx = konteks({
            tahapId: 'th5',
            aktivitas: TES_TAHAP_5,
            pulangTes: { id: 'akt3', tahap: 'th3', at: Date.now() },
        });

        expect(C.tesDitunggu.call(ctx)).toBeNull();
    });
});
