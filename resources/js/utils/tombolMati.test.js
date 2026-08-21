// @vitest-environment jsdom
/**
 * TOMBOL MATI TIDAK BOLEH PUNYA PENANGAN KLIK.
 *
 * `:disabled` hanyalah atribut DOM. Siapa pun yang membuka DevTools bisa
 * mencopotnya dalam dua detik, dan bila penangan kliknya masih terpasang,
 * tombol yang seharusnya terkunci langsung bekerja seperti biasa — modal
 * keputusan terbuka, dan orang yang menekannya percaya ia berwenang.
 *
 * Server memang tetap menolak (izin APPROVE, gerbang kuota, gerbang
 * ketuntasan), jadi datanya tidak rusak. Tapi mengandalkan itu berarti
 * layarnya berbohong: ia menawarkan tindakan yang mustahil, lalu menyalahkan
 * penggunanya dengan galat 422. Yang mati harus benar-benar mati.
 *
 * Karena itu penangannya TIDAK dipasang selama tombolnya mati:
 *
 *     :disabled="mati" :onClick="mati ? null : () => lakukan()"
 *
 * `null` membuat Vue tidak memasang listener sama sekali. Mencopot atribut
 * `disabled` sesudah itu tidak menghidupkan apa pun — tidak ada yang bisa
 * dihidupkan.
 */
import { describe, it, expect, vi } from 'vitest';
import { createApp, defineComponent, ref } from 'vue';
import fs from 'node:fs';
import path from 'node:path';

function pasang(template, setup) {
    const wadah = document.createElement('div');
    document.body.appendChild(wadah);
    createApp(defineComponent({ template, setup })).mount(wadah);

    return wadah.querySelector('#uji');
}

const TPL = `<button id="uji" :disabled="mati" :onClick="mati ? null : () => lakukan()">Loloskan</button>`;

describe('tombol mati tidak bisa dihidupkan lewat DevTools', () => {
    it('mencopot atribut disabled TIDAK membuat kliknya bekerja', () => {
        const lakukan = vi.fn();
        const b = pasang(TPL, () => ({ mati: ref(true), lakukan }));

        // Persis yang dilakukan penyerang di panel Elements.
        b.removeAttribute('disabled');
        b.disabled = false;
        b.click();

        expect(lakukan).not.toHaveBeenCalled();
    });

    it('tombol yang hidup tetap bekerja seperti biasa', () => {
        const lakukan = vi.fn();
        const b = pasang(TPL, () => ({ mati: ref(false), lakukan }));
        b.click();

        expect(lakukan).toHaveBeenCalledTimes(1);
    });

    it('penangannya pasang-copot mengikuti keadaannya', async () => {
        const lakukan = vi.fn();
        const mati = ref(true);
        const b = pasang(TPL, () => ({ mati, lakukan }));

        b.click();
        expect(lakukan).not.toHaveBeenCalled();

        mati.value = false;
        await new Promise((r) => setTimeout(r));
        b.click();
        expect(lakukan).toHaveBeenCalledTimes(1);

        mati.value = true;
        await new Promise((r) => setTimeout(r));
        b.removeAttribute('disabled');
        b.click();
        expect(lakukan).toHaveBeenCalledTimes(1);
    });
});

/**
 * PENJAGA SELURUH BASIS KODE.
 *
 * Satu penyisiran sudah dikerjakan atas 151 tombol. Tanpa tes ini, tombol
 * ke-152 ditulis dengan pola lama minggu depan dan tak seorang pun tahu —
 * penyisiran yang tidak dijaga hanya menunda kejadian yang sama.
 */
describe('tidak ada tombol mati yang masih memasang @click', () => {
    const AKAR = path.resolve(__dirname, '..');
    const tagRe = /<(button|a|el-button)\b[^>]*?>/gs;
    const disRe = /(?::|v-bind:)disabled\s*=\s*"/;
    const klikRe = /(?<![\w-])(@click|v-on:click)/;

    function berkasVue(dir) {
        return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
            const p = path.join(dir, e.name);

            return e.isDirectory() ? berkasVue(p) : (e.name.endsWith('.vue') ? [p] : []);
        });
    }

    it('menyisir seluruh resources/js', () => {
        const pelanggar = [];
        for (const p of berkasVue(AKAR)) {
            const isi = fs.readFileSync(p, 'utf8');
            for (const m of isi.matchAll(tagRe)) {
                if (disRe.test(m[0]) && klikRe.test(m[0])) {
                    pelanggar.push(`${path.relative(AKAR, p).split(path.sep).join('/')}:${isi.slice(0, m.index).split('\n').length}`);
                }
            }
        }

        expect(pelanggar, 'pakai :onClick="<mati> ? null : ..." agar penangannya tidak terpasang saat mati').toEqual([]);
    });
});
