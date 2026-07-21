<!-- WEB CAREER — Admin: Worklist Pelamar. Rekomendasi mesin + ketuk palu admin. -->
<template>
    <Head><title>Worklist Pelamar - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Worklist Pelamar</h1>
                <p>Tahap yang menunggu keputusan Anda. Mesin sudah menilai syarat — <b>Anda yang ketuk palu</b>.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--ghost" @click="muat"><i class="bi bi-arrow-clockwise"></i> Segarkan</button>
            </div>
        </div>

        <div class="wca-legend">
            <span><i class="bi bi-hand-thumbs-up" style="color:#059669"></i> Rekomendasi LOLOS — semua syarat terpenuhi</span>
            <span><i class="bi bi-hand-thumbs-down" style="color:#dc2626"></i> Rekomendasi GUGUR — ada syarat tak terpenuhi</span>
        </div>

        <div v-loading="loading">
            <div v-if="!rows.length" class="wca-empty">
                <i class="bi bi-kanban"></i>
                <h4>Tidak ada tahap yang menunggu keputusan</h4>
                <p>Worklist terisi saat kandidat mengirim formulir pada tahap yang keputusannya di tangan admin.</p>
            </div>

            <div class="plm-list">
                <div v-for="r in rows" :key="r.id" class="plm-card" :class="r.rekomendasi === 'GUGUR' ? 'is-gugur' : 'is-lolos'">
                    <div class="plm-card__main">
                        <div class="plm-card__top">
                            <span class="plm-reko" :class="r.rekomendasi === 'GUGUR' ? 'is-gugur' : 'is-lolos'">
                                <i class="bi" :class="r.rekomendasi === 'GUGUR' ? 'bi-hand-thumbs-down-fill' : 'bi-hand-thumbs-up-fill'"></i>
                                Rekomendasi: {{ r.rekomendasi === 'GUGUR' ? 'Gugur' : 'Lolos' }}
                            </span>
                            <span class="wca-badge" :class="katBadge(r.kategori)">{{ katLabel(r.kategori) }}</span>
                        </div>

                        <div class="plm-card__nama">{{ r.pelamar || '—' }}</div>
                        <div class="plm-card__sub">
                            {{ r.posisi }} · {{ r.program }} · <code class="plm-kode">{{ r.lamaranKode }}</code>
                        </div>
                        <div class="plm-card__tahap"><i class="bi bi-signpost"></i> Tahap {{ r.urutan }}: <b>{{ r.tahap }}</b></div>

                        <div class="plm-alasan" :class="r.rekomendasi === 'GUGUR' ? 'is-gugur' : 'is-lolos'">
                            {{ r.alasan }}
                        </div>

                        <button v-if="r.jejak && r.jejak.length" class="plm-jejak-btn" @click="buka === r.id ? (buka = null) : (buka = r.id)">
                            <i class="bi" :class="buka === r.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                            {{ buka === r.id ? 'Sembunyikan' : 'Lihat' }} rincian penilaian ({{ jumlahKondisi(r) }})
                        </button>
                        <div v-if="buka === r.id" class="plm-jejak">
                            <div v-for="(s, i) in r.jejak" :key="i" class="plm-syarat" :class="{ 'is-uji': s.uji }">
                                <div class="plm-syarat__hd">
                                    <i class="bi" :class="s.lolos ? 'bi-check-circle text-success' : 'bi-x-circle text-danger'"></i>
                                    <strong>{{ s.syarat }}</strong>
                                    <span v-if="s.uji" class="wca-badge wca-b--amber">mode uji</span>
                                    <span class="wca-badge" :class="s.aksi === 'GUGUR' ? 'wca-b--red' : 'wca-b--slate'">{{ s.aksi === 'GUGUR' ? 'gugur langsung' : 'tandai' }}</span>
                                </div>
                                <ul class="plm-kondisi">
                                    <li v-for="(k, j) in (s.rincian || []).filter((x) => !x.diabaikan)" :key="j" :class="k.lolos ? 'ok' : 'no'">
                                        <i class="bi" :class="k.lolos ? 'bi-dot' : 'bi-x'"></i>
                                        {{ k.field }} = <b>{{ tampilNilai(k.nilai_kandidat) }}</b>
                                        <span class="plm-kondisi__req">(diminta {{ k.operator }} {{ k.diharapkan }})</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="plm-card__act">
                        <button class="wca-btn wca-btn--soft wca-btn--sm" @click="lihatJawaban(r)"><i class="bi bi-eye"></i> Jawaban</button>
                        <button class="plm-btn plm-btn--lulus" :disabled="sibuk" @click="askPutus(r, 'LULUS')"><i class="bi bi-check-lg"></i> Loloskan</button>
                        <button class="plm-btn plm-btn--gugur" :disabled="sibuk" @click="askPutus(r, 'GUGUR')"><i class="bi bi-x-lg"></i> Gugurkan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Konfirmasi ketuk palu -->
        <ConfirmModal
            :show="konfirmShow"
            :title="putusHasil === 'LULUS' ? 'Loloskan Kandidat' : 'Gugurkan Kandidat'"
            :busy="sibuk"
            :confirm-label="putusHasil === 'LULUS' ? 'Ya, Loloskan' : 'Ya, Gugurkan'"
            :note="putusHasil === 'LULUS' ? 'Kandidat lanjut ke tahap berikutnya.' : 'Kandidat digugurkan dari proses seleksi.'"
            @cancel="konfirmShow = false"
            @confirm="konfirmPutus"
        >
            <p>{{ putusHasil === 'LULUS' ? 'Loloskan' : 'Gugurkan' }} <strong>{{ putusTarget?.pelamar }}</strong> dari tahap <strong>{{ putusTarget?.tahap }}</strong>?</p>
            <label class="plm-note-lbl">Catatan (opsional)</label>
            <el-input v-model="putusCatatan" type="textarea" :rows="2" placeholder="mis. sesuai rekomendasi sistem / alasan khusus" />
        </ConfirmModal>

        <!-- Jawaban kandidat -->
        <AdminModal :show="jwbShow" title="Jawaban Kandidat" subtitle="Isian formulir yang dikirim kandidat." icon="bi-file-earmark-text" lg save-label="Tutup" @close="jwbShow = false" @save="jwbShow = false">
            <div v-if="jwbLoading" class="plm-jwb-load"><i class="bi bi-arrow-repeat spin"></i> Memuat…</div>
            <table v-else class="wca-table">
                <thead><tr><th style="width:40%">Field</th><th>Jawaban</th></tr></thead>
                <tbody>
                    <tr v-for="(v, k) in jwbData" :key="k"><td><code>{{ k }}</code></td><td>{{ tampilNilai(v) }}</td></tr>
                    <tr v-if="!Object.keys(jwbData).length"><td colspan="2" style="color:#94a3b8">Tidak ada data.</td></tr>
                </tbody>
            </table>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';

const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal },
    props: {
        worklist: { type: Array, default: () => [] },
    },
    data() {
        return {
            rows: [...this.worklist],
            loading: false,
            buka: null,
            konfirmShow: false,
            putusTarget: null,
            putusHasil: '',
            putusCatatan: '',
            sibuk: false,
            jwbShow: false,
            jwbLoading: false,
            jwbData: {},
            toast: '',
            toastErr: false,
            tm: null,
        };
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k; },
        katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--slate'; },
        jumlahKondisi(r) { return (r.jejak || []).reduce((n, s) => n + (s.rincian || []).filter((x) => !x.diabaikan).length, 0); },
        tampilNilai(v) {
            if (v === null || v === undefined || v === '') return '—';
            if (Array.isArray(v)) return v.join(', ');
            if (typeof v === 'boolean') return v ? 'Ya' : 'Tidak';
            return v;
        },
        async muat() {
            this.loading = true;
            try {
                const res = await axios.get('/api/v1/karir/lamaran/worklist', CFG);
                this.rows = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat worklist.', true);
            } finally {
                this.loading = false;
            }
        },
        askPutus(r, hasil) {
            this.putusTarget = r;
            this.putusHasil = hasil;
            this.putusCatatan = '';
            this.konfirmShow = true;
        },
        async konfirmPutus() {
            if (this.sibuk || !this.putusTarget) return;
            this.sibuk = true;
            try {
                const res = await axios.patch(`/api/v1/karir/lamaran/tahap/${this.putusTarget.id}/putus`, {
                    hasil: this.putusHasil,
                    catatan: this.putusCatatan || null,
                }, CFG);
                this.notice(res.data?.message || 'Keputusan tersimpan.');
                this.konfirmShow = false;
                // Baris hilang dari worklist begitu diputus.
                this.rows = this.rows.filter((x) => x.id !== this.putusTarget.id);
                this.putusTarget = null;
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal memproses keputusan.', true);
            } finally {
                this.sibuk = false;
            }
        },
        async lihatJawaban(r) {
            if (!r.pengisianId) { this.notice('Belum ada jawaban.', true); return; }
            this.jwbShow = true;
            this.jwbLoading = true;
            this.jwbData = {};
            try {
                const res = await axios.get(`/api/v1/karir/lamaran/pengisian/${r.pengisianId}`, CFG);
                this.jwbData = res.data?.result?.jawaban || {};
            } catch (e) {
                this.notice('Gagal memuat jawaban.', true);
            } finally {
                this.jwbLoading = false;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3500); },
    },
};
</script>

<style scoped>
.plm-list { display: flex; flex-direction: column; gap: .8rem; }
.plm-card { display: flex; gap: 1rem; border: 1px solid rgba(11, 16, 51, .09); border-left: 4px solid #cbd5e1; border-radius: 14px; padding: 1rem; background: #fff; }
.plm-card.is-lolos { border-left-color: #10b981; }
.plm-card.is-gugur { border-left-color: #ef4444; }
.plm-card__main { flex: 1; min-width: 0; }
.plm-card__top { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .5rem; }
.plm-reko { display: inline-flex; align-items: center; gap: .35rem; font-size: 12px; font-weight: 700; border-radius: 999px; padding: .2rem .6rem; }
.plm-reko.is-lolos { color: #047857; background: rgba(16,185,129,.12); }
.plm-reko.is-gugur { color: #b91c1c; background: rgba(239,68,68,.12); }
.plm-card__nama { font-size: 1.05rem; font-weight: 800; color: #0f172a; }
.plm-card__sub { font-size: 12.5px; color: #64748b; margin-top: .1rem; }
.plm-kode { font-size: 11px; color: #4338ca; background: rgba(79,70,229,.08); border-radius: 5px; padding: 0 .3rem; }
.plm-card__tahap { font-size: 12.5px; color: #475569; margin-top: .5rem; }
.plm-alasan { margin-top: .5rem; font-size: 12.5px; padding: .5rem .65rem; border-radius: 10px; }
.plm-alasan.is-lolos { color: #047857; background: rgba(16,185,129,.08); }
.plm-alasan.is-gugur { color: #b91c1c; background: rgba(239,68,68,.08); }

.plm-jejak-btn { margin-top: .55rem; border: 0; background: transparent; color: #4338ca; font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .3rem; padding: 0; }
.plm-jejak { margin-top: .6rem; display: flex; flex-direction: column; gap: .5rem; }
.plm-syarat { border: 1px solid rgba(11,16,51,.08); border-radius: 10px; padding: .55rem .65rem; background: #f8fafc; }
.plm-syarat.is-uji { opacity: .7; border-style: dashed; }
.plm-syarat__hd { display: flex; align-items: center; gap: .4rem; font-size: 12.5px; color: #334155; }
.text-success { color: #059669; }
.text-danger { color: #dc2626; }
.plm-kondisi { list-style: none; margin: .35rem 0 0; padding: 0; font-size: 11.5px; }
.plm-kondisi li { color: #475569; padding: .1rem 0; }
.plm-kondisi li.no { color: #b91c1c; }
.plm-kondisi__req { color: #94a3b8; }

.plm-card__act { display: flex; flex-direction: column; gap: .4rem; flex-shrink: 0; align-self: flex-start; }
.plm-btn { border: 0; border-radius: 9px; padding: .5rem .9rem; font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: .35rem; justify-content: center; white-space: nowrap; }
.plm-btn:disabled { opacity: .5; cursor: not-allowed; }
.plm-btn--lulus { background: #059669; color: #fff; }
.plm-btn--lulus:hover:not(:disabled) { background: #047857; }
.plm-btn--gugur { background: #fee2e2; color: #b91c1c; }
.plm-btn--gugur:hover:not(:disabled) { background: #fecaca; }

.plm-note-lbl { display: block; font-size: 12px; font-weight: 600; color: #475569; margin: .7rem 0 .3rem; }
.plm-jwb-load { padding: 1.2rem; text-align: center; color: #64748b; }
.spin { animation: plmspin 1s linear infinite; display: inline-block; }
@keyframes plmspin { to { transform: rotate(360deg); } }
.wca-toast.is-err { background: #b91c1c; }

@media (max-width: 700px) {
    .plm-card { flex-direction: column; }
    .plm-card__act { flex-direction: row; flex-wrap: wrap; }
}
</style>
