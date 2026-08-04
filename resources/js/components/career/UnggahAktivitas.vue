<!-- ══════════════════════════════════════════════════════════
     WEB CAREER — Kotak unggah berkas KANDIDAT untuk satu aktivitas seleksi.

     Dipakai di halaman detail lamaran (portal kandidat), pada SETIAP bentuk
     tahap aktif: tahap berujian online, tahap berformulir, dan tahap yang
     ditangani tim (wawancara/MCU/FGD).

     KENAPA SATU KOMPONEN, BUKAN DISALIN PER KARTU

     Kotak ini dulu ditulis ulang di dua tempat dan lupa di satu tempat lagi.
     Akibatnya tiga hal yang seharusnya sama justru berbeda-beda:

       - tahap berujian     → punya kotak unggah DAN tombol kirim;
       - tahap ditangani tim→ punya kotak unggah, TANPA tombol kirim, sehingga
                              kandidat tidak pernah bisa menyatakan selesai;
       - tahap berformulir  → tidak punya kotak unggah SAMA SEKALI, jadi setelan
                              "kandidat harus mengunggah" di Master Alur diam-diam
                              tidak berlaku di sana.

     Satu komponen membuat ketiganya mustahil berbeda lagi.

     KOMPONEN INI TIDAK BICARA KE SERVER. Ia hanya menyodorkan berkas ke atas
     lewat emit; seluruh pemanggilan API, pesan galat, dan penyegaran halaman
     tetap milik induknya — supaya tidak ada dua tempat yang sama-sama merasa
     berhak menulis daftar berkas.
     ══════════════════════════════════════════════════════════ -->
<template>
    <div v-for="u in daftar" :key="'u' + u.key" class="ld-upl">
        <div class="ld-upl__head">
            <span class="ld-upl__ico"><i class="bi bi-cloud-arrow-up-fill"></i></span>
            <div style="min-width: 0; flex: 1">
                <div class="ld-eyebrow">
                    BERKAS YANG PERLU KAMU UNGGAH
                    <b v-if="u.wajib" class="ld-upl__wajib">WAJIB</b>
                </div>
                <div class="ld-upl__judul">{{ u.label }}</div>
                <p v-if="u.petunjuk" class="ld-upl__petunjuk">{{ u.petunjuk }}</p>
                <!-- Aturan yang tertulis DI SINI datang dari aktivitasnya
                     sendiri — bukan aturan umum — sehingga persis sama dengan
                     yang ditegakkan server saat berkasnya diperiksa. -->
                <p class="ld-upl__aturan">
                    Format {{ (u.format || []).join(', ').toUpperCase() }} &middot; maksimal {{ u.maksMb }} MB per berkas
                </p>
            </div>
        </div>

        <!-- Seret-lepas ATAU klik. Keduanya, karena di ponsel seret-lepas
             praktis tidak terpakai. -->
        <label
            class="ld-upl__drop"
            :class="{ 'is-over': seretDi === u.key, 'is-busy': unggahDi === u.key }"
            @dragover.prevent="seretDi = u.key"
            @dragleave.prevent="seretDi = null"
            @drop.prevent="lepas($event, u)"
        >
            <input
                type="file" hidden
                :accept="(u.format || []).map((x) => '.' + x).join(',')"
                :disabled="unggahDi === u.key"
                @change="pilih($event, u)"
            >
            <i class="bi" :class="unggahDi === u.key ? 'bi-arrow-repeat ld-upl__spin' : 'bi-upload'"></i>
            <span>
                <b>{{ unggahDi === u.key ? 'Mengunggah…' : 'Seret berkas ke sini' }}</b>
                <small v-if="unggahDi !== u.key">atau klik untuk memilih dari perangkat</small>
            </span>
        </label>

        <div v-if="(berkas[u.key] || []).length" class="ld-upl__list">
            <div v-for="b in berkas[u.key]" :key="b.id" class="ld-upl__item">
                <i class="bi" :class="b.isImage ? 'bi-file-earmark-image-fill' : 'bi-file-earmark-pdf-fill'"></i>
                <button type="button" class="ld-upl__nama" @click="$emit('buka', b)">{{ b.nama }}</button>
                <span class="ld-upl__size">{{ ukuran(b.ukuran) }}</span>
                <button type="button" class="ld-upl__del" title="Hapus berkas" @click="$emit('hapus', b, u)">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <p v-else-if="u.wajib" class="ld-upl__kosong">
            <i class="bi bi-exclamation-circle-fill"></i>
            Belum ada berkas — aktivitas ini belum bisa dianggap selesai.
        </p>

        <!-- KIRIM — pernyataan "berkas saya lengkap".
             Mengunggah dan selesai mengunggah bukan hal yang sama. Tanpa langkah
             ini kandidat tidak pernah tahu kapan ia boleh berhenti, dan tim
             tidak tahu apakah masih ada susulan sebelum menilai. -->
        <div class="ld-upl__kirim">
            <template v-if="u.terkirim">
                <span class="ld-upl__ok">
                    <i class="bi bi-patch-check-fill"></i>
                    <span>
                        <b>Berkas sudah kamu kirim</b>
                        <small>{{ fmtWaktu(u.terkirim) }} — menunggu penilaian tim. Masih bisa menambah berkas bila ada yang kurang.</small>
                    </span>
                </span>
            </template>
            <template v-else>
                <button
                    type="button"
                    class="ld-upl__btn"
                    :disabled="!(berkas[u.key] || []).length || kirimDi === u.key"
                    @click="$emit('kirim', u)"
                >
                    <i class="bi" :class="kirimDi === u.key ? 'bi-arrow-repeat ld-upl__spin' : 'bi-send-fill'"></i>
                    {{ kirimDi === u.key ? 'Mengirim…' : 'Kirim Berkas' }}
                </button>
                <small class="ld-upl__hint">
                    {{ (berkas[u.key] || []).length
                        ? 'Tekan bila seluruh berkas sudah lengkap — tim akan mulai menilainya.'
                        : 'Unggah berkas dulu, tombol kirim akan aktif.' }}
                </small>
            </template>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        /** Aktivitas tahap aktif yang meminta unggahan (lihat `unggahAktivitas`). */
        daftar: { type: Array, default: () => [] },
        /** Peta key aktivitas → berkas yang sudah masuk. */
        berkas: { type: Object, default: () => ({}) },
        /** Key aktivitas yang sedang mengunggah — null bila tak ada. */
        unggahDi: { type: [Number, String], default: null },
        /** Key aktivitas yang sedang dikirim — null bila tak ada. */
        kirimDi: { type: [Number, String], default: null },
    },
    emits: ['pilih', 'hapus', 'buka', 'kirim'],
    data() {
        // Sorotan seret-lepas murni urusan tampilan; induk tidak perlu tahu.
        return { seretDi: null };
    },
    methods: {
        lepas(ev, u) {
            this.seretDi = null;
            const file = ev.dataTransfer?.files?.[0];
            if (file) {
                this.$emit('pilih', file, u);
            }
        },
        pilih(ev, u) {
            const file = ev.target.files?.[0];
            // Input dikosongkan supaya memilih berkas yang SAMA dua kali tetap
            // memicu `change` — kandidat yang unggahannya gagal lalu mencoba
            // ulang berkas yang sama tidak akan menghadapi layar yang diam.
            ev.target.value = '';
            if (file) {
                this.$emit('pilih', file, u);
            }
        },
        ukuran(b) {
            if (!b) return '';

            return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
        },
        fmtWaktu(iso) {
            if (!iso) return '—';

            return new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>

<style scoped>
/* Gaya ikut PINDAH bersama markupnya.
   Sebelumnya seluruh aturan .ld-upl* tinggal di <style scoped> halaman induk.
   Begitu markupnya jadi komponen sendiri, gaya bercakupan induk TIDAK LAGI
   mengenainya — kotak unggahnya akan tampil telanjang tanpa satu pun pesan
   galat. Karena itu aturannya dibawa ke sini, bukan ditinggal di sana. */
.ld-eyebrow { font-size: 11px; font-weight: 800; letter-spacing: 0.14em; color: #8b5cf6; }

.ld-upl { margin: 16px 20px 0; padding: 15px 17px; border-radius: 16px; border: 1px solid rgba(99, 102, 241, .26); background: linear-gradient(135deg, rgba(99, 102, 241, .06), rgba(139, 92, 246, .03)); }
.ld-upl__head { display: flex; align-items: flex-start; gap: 12px; }
.ld-upl__ico { flex: none; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 17px; background: linear-gradient(140deg, #818cf8, #6366f1); }
.ld-upl__wajib { margin-left: 6px; padding: 1px 7px; border-radius: 999px; font-size: 9.5px; color: #fff; background: #dc2626; letter-spacing: .06em; }
.ld-upl__judul { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 2px; }
.ld-upl__petunjuk { margin: 6px 0 0; font-size: 12.5px; line-height: 1.55; color: #475569; }
.ld-upl__aturan { margin: 4px 0 0; font-size: 11.5px; color: #94a3b8; }
.ld-upl__drop { display: flex; align-items: center; gap: 11px; margin-top: 13px; padding: 15px 16px; border: 1.5px dashed rgba(99, 102, 241, .38); border-radius: 14px; background: #fff; cursor: pointer; transition: border-color .18s, background .18s, transform .18s; }
.ld-upl__drop:hover, .ld-upl__drop.is-over { border-color: #6366f1; background: rgba(99, 102, 241, .07); transform: translateY(-1px); }
.ld-upl__drop.is-busy { pointer-events: none; opacity: .7; }
.ld-upl__drop > .bi { flex: none; font-size: 19px; color: #6366f1; }
.ld-upl__spin { display: inline-block; animation: ldPutar .9s linear infinite; }
.ld-upl__drop b { display: block; font-size: 13.5px; font-weight: 800; color: #1e293b; }
.ld-upl__drop small { display: block; font-size: 11.5px; color: #94a3b8; margin-top: 1px; }
.ld-upl__list { display: flex; flex-direction: column; gap: 7px; margin-top: 11px; }
.ld-upl__item { display: flex; align-items: center; gap: 9px; padding: 9px 12px; border: 1px solid #e6e8f2; border-radius: 11px; background: #fff; }
.ld-upl__item > .bi { flex: none; color: #dc2626; }
.ld-upl__item .bi-file-earmark-image-fill { color: #6366f1; }
.ld-upl__nama { flex: 1; min-width: 0; border: 0; background: none; padding: 0; font: inherit; font-size: 13px; font-weight: 700; color: #4f46e5; text-align: left; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ld-upl__size { flex: none; font-size: 11px; color: #94a3b8; }
.ld-upl__del { flex: none; border: 0; background: none; padding: 2px 4px; color: #94a3b8; font-size: 11px; cursor: pointer; }
.ld-upl__del:hover { color: #dc2626; }
.ld-upl__kosong { display: flex; align-items: center; gap: 7px; margin: 11px 0 0; font-size: 12px; font-weight: 700; color: #b45309; }

/* ── Kirim berkas: menutup langkah unggah ────────────────────────────────── */
.ld-upl__kirim { margin-top: 13px; padding-top: 13px; border-top: 1px dashed #dbe2ea; display: flex; flex-direction: column; align-items: flex-start; gap: 7px; }
.ld-upl__btn {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border: 0; border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff; font: inherit; font-size: 13.5px; font-weight: 800;
    cursor: pointer; transition: filter .16s, transform .16s, box-shadow .16s; box-shadow: 0 6px 18px rgba(79, 70, 229, .26);
}
.ld-upl__btn:hover:not(:disabled) { filter: brightness(1.06); transform: translateY(-1px); box-shadow: 0 10px 24px rgba(79, 70, 229, .32); }
/* Latar PADAT saat mati, bukan diredupkan: tombol pucat di atas gradien
   membuat tulisannya nyaris tak terbaca — persis saat kandidat perlu tahu
   kenapa ia belum bisa menekan. */
.ld-upl__btn:disabled { background: #e2e8f0; color: #94a3b8; box-shadow: none; cursor: not-allowed; transform: none; }
.ld-upl__hint { font-size: 11.5px; line-height: 1.5; color: #64748b; }
.ld-upl__ok { display: flex; align-items: flex-start; gap: 9px; padding: 11px 14px; border-radius: 13px; background: rgba(16, 185, 129, .09); border: 1px solid rgba(16, 185, 129, .28); width: 100%; }
.ld-upl__ok i { flex: 0 0 auto; margin-top: 1px; font-size: 16px; color: #059669; }
.ld-upl__ok b { display: block; font-size: 13px; font-weight: 800; color: #065f46; }
.ld-upl__ok small { display: block; margin-top: 3px; font-size: 11.5px; line-height: 1.55; color: #475569; }

@media (max-width: 520px) {
    .ld-upl__btn { width: 100%; justify-content: center; }
}

@keyframes ldPutar { to { transform: rotate(360deg); } }
</style>
