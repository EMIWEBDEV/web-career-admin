<!--
  ZONA C — FUNNEL & KONVERSI.

  Dikelompokkan PER ALUR, bukan per kategori. Program dalam satu kategori boleh
  memakai alur berbeda, jadi menjumlahkan "tahap 3" dari dua alur menghasilkan
  kolom bernama satu hal yang isinya campuran dua hal. Pemilih alur di atas
  memilih salah satu; bawaannya alur dengan pelamar terbanyak.

  Yang membuat panel ini bukan sekadar batang berjajar: PENYEMPITAN TERBESAR
  dinamai secara eksplisit. Tanpa itu admin harus membandingkan sendiri tujuh
  angka untuk menemukan tahap mana yang paling banyak memakan kandidat.

  Warna batang SATU hue untuk semua tahap — panjang batang sudah memikul
  magnitudo, jadi mewarnai "makin besar makin gelap" cuma menggandakan
  informasi yang sama dan membuang satu-satunya kanal warna yang tersisa.
  Kanal itu dipakai untuk hal lain: menyorot tahap penyempitan terbesar.
-->
<template>
    <div v-if="!funnel.length">
        <KeadaanPanel keadaan="kosong" ikon="bi-funnel"
            teks="Belum ada funnel untuk kategori ini"
            ket="Funnel terbentuk dari tahap alur seleksi program. Pastikan programnya sudah punya alur." />
    </div>

    <div v-else>
        <!-- Pemilih alur — hanya muncul kalau memang ada lebih dari satu. -->
        <div v-if="funnel.length > 1" class="fn-pilih">
            <span class="fn-pilih__lbl">Alur seleksi</span>
            <div class="wcd-seg">
                <button v-for="(f, i) in funnel" :key="f.alurId" type="button"
                    :class="{ 'is-on': idx === i }" :title="f.programNama.join(', ')"
                    @click="idx = i">
                    {{ f.alur }} <em>{{ angka(f.pelamar) }}</em>
                </button>
            </div>
        </div>

        <p class="fn-cakup">
            <i class="bi bi-diagram-2"></i>
            {{ aktif.program }} program · {{ angka(aktif.pelamar) }} pelamar masuk funnel ini
            <span v-if="funnel.length > 1">· {{ aktif.programNama.slice(0, 3).join(', ') }}<template v-if="aktif.programNama.length > 3"> +{{ aktif.programNama.length - 3 }}</template></span>
        </p>

        <!-- Penyempitan terbesar: status, jadi ikon + teks, bukan warna sendirian. -->
        <div v-if="aktif.penyempitan" class="fn-sempit">
            <i class="bi bi-arrow-down-right-circle-fill"></i>
            <div>
                <strong>Penyempitan terbesar: {{ aktif.penyempitan.dari }} → {{ aktif.penyempitan.ke }}</strong>
                Hanya <b>{{ aktif.penyempitan.persen }}%</b> yang lanjut —
                {{ angka(aktif.penyempitan.hilang) }} kandidat berhenti di titik itu.
            </div>
        </div>

        <!-- Batang funnel. Lebar = capai/capai-tahap-pertama, jadi tahap pertama
             selalu 100% dan penyusutannya terbaca langsung. -->
        <ul class="fn-list">
            <li v-for="t in aktif.tahap" :key="t.urutan" :class="{ 'is-sempit': sorot(t) }">
                <div class="fn-hd">
                    <span class="fn-ur">{{ t.urutan }}</span>
                    <span class="fn-lbl">
                        {{ t.label }}
                        <i v-if="t.provider === 'THIRD_PARTY'" class="bi bi-box-arrow-up-right"
                            title="Tahap oleh penyedia tes pihak ketiga"></i>
                    </span>
                    <span v-if="t.konversi !== null" class="wcd-lb" :style="nadaKonversi(t)">
                        <i class="bi" :class="sorot(t) ? 'bi-arrow-down-right' : 'bi-arrow-right-short'"></i>
                        {{ t.konversi }}%
                    </span>
                    <span class="fn-capai">{{ angka(t.capai) }}</span>
                </div>

                <div class="fn-rel">
                    <span class="fn-bar" :style="{ width: lebar(t) + '%' }"></span>
                </div>

                <div class="fn-rinci">
                    <span v-if="t.aktif"><i class="bi bi-person-walking"></i> {{ angka(t.aktif) }} di sini</span>
                    <span v-if="t.lulus"><i class="bi bi-patch-check"></i> {{ angka(t.lulus) }} diterima</span>
                    <span v-if="t.gugur"><i class="bi bi-x-circle"></i> {{ angka(t.gugur) }} gugur</span>
                    <span v-if="t.talent"><i class="bi bi-bookmark-star"></i> {{ angka(t.talent) }} talent pool</span>
                    <span v-if="!t.aktif && !t.lulus && !t.gugur && !t.talent" class="is-nol">belum ada yang berhenti di tahap ini</span>
                </div>
            </li>
        </ul>

        <!-- Padanan tabel: tiap angka tetap bisa dibaca tanpa mengandalkan
             panjang batang atau warna. -->
        <details class="fn-tabel">
            <summary>Lihat sebagai tabel</summary>
            <div class="wcd-tw">
                <table class="wcd-tbl">
                    <thead>
                        <tr>
                            <th>#</th><th>Tahap</th><th class="wcd-num">Capai</th><th class="wcd-num">Konversi</th>
                            <th class="wcd-num">Di sini</th><th class="wcd-num">Diterima</th>
                            <th class="wcd-num">Gugur</th><th class="wcd-num">Talent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in aktif.tahap" :key="t.urutan">
                            <td>{{ t.urutan }}</td>
                            <td class="wcd-tbl__utama">{{ t.label }}</td>
                            <td class="wcd-num">{{ angka(t.capai) }}</td>
                            <td class="wcd-num">{{ t.konversi === null ? '—' : t.konversi + '%' }}</td>
                            <td class="wcd-num">{{ angka(t.aktif) }}</td>
                            <td class="wcd-num">{{ angka(t.lulus) }}</td>
                            <td class="wcd-num">{{ angka(t.gugur) }}</td>
                            <td class="wcd-num">{{ angka(t.talent) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { angka, STATUS, INK } from '../dashboardHelpers';
import KeadaanPanel from '../../monitoring/KeadaanPanel.vue';

const props = defineProps({ funnel: { type: Array, default: () => [] } });

const idx = ref(0);
const aktif = computed(() => props.funnel[idx.value] || props.funnel[0] || { tahap: [], programNama: [] });

const dasar = computed(() => aktif.value.tahap?.[0]?.capai || 0);

function lebar(t) {
    if (!dasar.value) return 0;
    // Minimum 1.5% supaya tahap bernilai kecil masih punya bentuk yang terlihat,
    // tapi tidak dibulatkan ke atas sampai membohongi perbandingannya.
    return Math.max(t.capai > 0 ? 1.5 : 0, (t.capai / dasar.value) * 100);
}

function sorot(t) {
    const p = aktif.value.penyempitan;
    return !!p && t.label === p.ke && t.konversi === p.persen;
}

function nadaKonversi(t) {
    if (sorot(t)) return { background: `color-mix(in srgb, ${STATUS.critical.warna} 12%, #fff)`, color: STATUS.critical.warna };
    if (t.konversi >= 80) return { background: `color-mix(in srgb, ${STATUS.good.warna} 12%, #fff)`, color: STATUS.good.warna };
    return { background: '#eef2f7', color: INK.kedua };
}
</script>

<style scoped>
.fn-pilih { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.fn-pilih__lbl { font-size: 0.74rem; font-weight: 800; color: #475569; }
.fn-pilih .wcd-seg { flex-wrap: wrap; }
.fn-pilih em { font-style: normal; opacity: 0.65; }

.fn-cakup { margin: 0 0 12px; font-size: 0.74rem; color: #64748b; }
.fn-cakup .bi { color: #94a3b8; }

.fn-sempit {
    display: flex;
    gap: 11px;
    align-items: flex-start;
    padding: 11px 13px;
    margin-bottom: 16px;
    border-radius: 12px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    font-size: 0.77rem;
    line-height: 1.6;
    color: #7c2d12;
}
.fn-sempit > .bi { font-size: 17px; color: #ec835a; flex: none; margin-top: 1px; }
.fn-sempit strong { display: block; color: #9a3412; }
.fn-sempit b { font-weight: 900; }

.fn-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; }
.fn-list > li { padding: 11px 13px; border-radius: 13px; border: 1px solid #eef2f7; background: #fff; }
.fn-list > li.is-sempit { border-color: #fed7aa; background: #fffbf5; }

.fn-hd { display: flex; align-items: center; gap: 9px; margin-bottom: 8px; }
.fn-ur {
    display: grid;
    place-items: center;
    width: 21px;
    height: 21px;
    flex: none;
    border-radius: 7px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.68rem;
    font-weight: 900;
}
.fn-lbl { flex: 1; font-size: 0.8rem; font-weight: 800; color: #0f172a; }
.fn-lbl .bi { font-size: 11px; color: #94a3b8; margin-left: 4px; }
.fn-capai { font-size: 0.86rem; font-weight: 900; color: #0f172a; font-variant-numeric: tabular-nums; }

/* Batang tipis, ujung membulat 4px, menempel ke garis dasar kiri. Grid & rel
   berupa garis rambut solid — tidak putus-putus. */
.fn-rel { height: 10px; border-radius: 999px; background: #f1f5f9; overflow: hidden; }
.fn-bar {
    display: block;
    height: 100%;
    border-radius: 0 4px 4px 0;
    background: #6366f1;
    transition: width 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.is-sempit .fn-bar { background: #ec835a; }

.fn-rinci { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; font-size: 0.72rem; color: #64748b; }
.fn-rinci .bi { font-size: 11px; color: #94a3b8; }
.fn-rinci .is-nol { color: #cbd5e1; font-style: italic; }

.fn-tabel { margin-top: 16px; }
.fn-tabel summary {
    cursor: pointer;
    font-size: 0.74rem;
    font-weight: 800;
    color: #4f46e5;
    padding: 6px 0;
}
.fn-tabel .wcd-tw { margin-top: 8px; }

@media (prefers-reduced-motion: reduce) { .fn-bar { transition: none; } }
</style>
