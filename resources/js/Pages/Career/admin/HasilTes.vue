<!-- WEB CAREER — Admin: Hasil Tes (RINGKASAN dari HCLearn; detail skor dibuka di HCLearn) -->
<template>
    <Head><title>Hasil Tes - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Hasil Tes</h1>
                <p>Ringkasan status penyelesaian tes dari <b>HCLearn</b>. Skor &amp; detail lengkap tetap di HCLearn — buka lewat tombol di tiap baris.</p>
            </div>
            <div class="wca-phead__actions">
                <a :href="hclearnBase" target="_blank" rel="noopener" class="wca-btn wca-btn--ghost"><i class="bi bi-box-arrow-up-right"></i> Buka HCLearn</a>
                <button class="wca-btn wca-btn--primary" @click="notice('Sinkronisasi status dari HCLearn… (demo dummy).')"><i class="bi bi-arrow-repeat"></i> Sinkron</button>
            </div>
        </div>

        <!-- Ringkasan angka (klik = filter status) -->
        <div class="hasil-stats">
            <button v-for="c in cards" :key="c.key" class="hasil-stat" :class="[{ on: fStatus === c.key }, c.cls]" @click="fStatus = c.key">
                <span class="hasil-stat__ic"><i class="bi" :class="c.icon"></i></span>
                <span class="hasil-stat__txt"><b>{{ c.count }}</b><small>{{ c.label }}</small></span>
            </button>
        </div>

        <div class="hasil-panel">
            <!-- Toolbar -->
            <div class="hasil-toolbar">
                <div class="hasil-seg">
                    <button v-for="j in ['ALL', 'REKRUTMEN', 'MT']" :key="j" class="hasil-seg__it" :class="{ on: fJenis === j }" @click="fJenis = j">
                        {{ { ALL: 'Semua', REKRUTMEN: 'Rekrutmen', MT: 'MT' }[j] }}
                    </button>
                </div>
                <div class="hasil-search">
                    <i class="bi bi-search"></i>
                    <input v-model="q" type="text" placeholder="Cari kandidat, tes, atau paket…" />
                    <button v-if="q" class="hasil-search__clr" @click="q = ''"><i class="bi bi-x-lg"></i></button>
                </div>
                <span class="hasil-count">{{ visible.length }} hasil</span>
            </div>

            <!-- List -->
            <div class="hasil-list">
                <div class="hasil-head">
                    <span>Kandidat</span><span>Tes / Paket</span><span>Status Hasil</span><span>Selesai</span><span></span>
                </div>
                <div v-for="r in visible" :key="r.id" class="hasil-row" :class="'is-' + r.hasil.toLowerCase()">
                    <div class="hasil-row__who">
                        <span class="hasil-av">{{ initials(r.nama) }}</span>
                        <div class="hasil-row__id">
                            <strong>{{ r.nama }}</strong>
                            <small>{{ r.posisi }} <span class="hasil-jchip" :class="r.jenis === 'MT' ? 'is-mt' : 'is-rek'">{{ r.jenis }}</span></small>
                        </div>
                    </div>
                    <div class="hasil-row__test">
                        <span class="hasil-lbl">Tes</span>
                        <strong>{{ r.ujian }}</strong><small>{{ r.paket }}</small>
                    </div>
                    <div class="hasil-row__status">
                        <span class="hasil-lbl">Status</span>
                        <span class="hasil-pill" :class="'is-' + r.hasil.toLowerCase()"><i class="bi" :class="hasilIcon(r.hasil)"></i> {{ hasilText(r.hasil) }}</span>
                        <span v-if="r.pelanggaran" class="hasil-viol" title="Pelanggaran proctoring"><i class="bi bi-exclamation-triangle-fill"></i> {{ r.pelanggaran }}</span>
                    </div>
                    <div class="hasil-row__time">
                        <span class="hasil-lbl">Selesai</span>
                        <span>{{ r.selesaiAt }}</span><small v-if="r.durasi">{{ r.durasi }}</small>
                    </div>
                    <a :href="hclearnUrl(r)" target="_blank" rel="noopener" class="hasil-hcl"><i class="bi bi-box-arrow-up-right"></i> <span>HCLearn</span></a>
                </div>

                <div v-if="!visible.length" class="wca-empty" style="padding:3rem 1rem"><i class="bi bi-clipboard-x"></i><h4>Tidak ada hasil</h4><p style="margin:.3rem 0 0">Ubah filter atau kata kunci pencarian.</p></div>
            </div>
        </div>

        <div class="wca-note wca-note--info" style="margin-top:1rem"><i class="bi bi-info-circle"></i><span>Skor rinci, jawaban, rekaman proctoring &amp; berita acara ada di <b>HCLearn</b>. Keputusan lolos/gagal dibuat di <b>Worklist Pelamar</b> atau <b>Pengumuman</b>.</span></div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { initials } from './careerAdmin';

const props = defineProps({
    hasil: { type: Array, default: () => [] },
    hclearnBase: { type: String, default: '' },
});

const fStatus = ref('ALL');
const fJenis = ref('ALL');
const q = ref('');

const base = computed(() => props.hasil);
const cards = computed(() => [
    { key: 'ALL', label: 'Total', icon: 'bi-collection', cls: 'is-slate', count: base.value.length },
    { key: 'SELESAI', label: 'Selesai', icon: 'bi-check-circle', cls: 'is-green', count: base.value.filter((r) => r.hasil === 'SELESAI').length },
    { key: 'BERLANGSUNG', label: 'Berlangsung', icon: 'bi-hourglass-split', cls: 'is-sky', count: base.value.filter((r) => r.hasil === 'BERLANGSUNG').length },
    { key: 'TIDAK_SUBMIT', label: 'Tidak Submit', icon: 'bi-slash-circle', cls: 'is-amber', count: base.value.filter((r) => r.hasil === 'TIDAK_SUBMIT').length },
    { key: 'GAGAL', label: 'Diskualifikasi', icon: 'bi-exclamation-triangle', cls: 'is-red', count: base.value.filter((r) => r.hasil === 'GAGAL').length },
]);

const visible = computed(() => {
    const s = q.value.trim().toLowerCase();
    return base.value.filter((r) => {
        if (fStatus.value !== 'ALL' && r.hasil !== fStatus.value) return false;
        if (fJenis.value !== 'ALL' && r.jenis !== fJenis.value) return false;
        if (!s) return true;
        return [r.nama, r.posisi, r.ujian, r.paket, r.email].join(' ').toLowerCase().includes(s);
    });
});

function hasilIcon(h) { return { SELESAI: 'bi-check-circle-fill', BERLANGSUNG: 'bi-hourglass-split', TIDAK_SUBMIT: 'bi-slash-circle-fill', GAGAL: 'bi-exclamation-triangle-fill' }[h] || 'bi-dash-circle'; }
function hasilText(h) { return { SELESAI: 'Selesai', BERLANGSUNG: 'Sedang Berlangsung', TIDAK_SUBMIT: 'Tidak Submit', GAGAL: 'Diskualifikasi' }[h] || h; }
function hclearnUrl(r) { return `${props.hclearnBase}/hasil/${r.ref}`; }

const toast = ref('');
let t = null;
function notice(m) { toast.value = m; if (t) clearTimeout(t); t = setTimeout(() => (toast.value = ''), 3000); }
</script>
