<!-- WEB CAREER — Portal Kandidat: Detail Lamaran + isi formulir tahap aktif. -->
<template>
    <Head><title>Detail Lamaran - EVO Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <Link href="/kandidat/portal" class="lde-back"><i class="bi bi-arrow-left"></i> Lamaran Saya</Link>
                <h1>{{ lamaran.posisi || lamaran.program }}</h1>
                <p>{{ lamaran.program }}<template v-if="lamaran.lokasi"> · {{ lamaran.lokasi }}</template> · {{ lamaran.kode }}</p>
            </div>
            <span class="wca-badge lde-status" :class="statusBadge(lamaran.status)"><i class="bi" :class="statusIkon(lamaran.status)"></i> {{ statusLabel(lamaran.status) }}</span>
        </div>

        <div class="lde-grid">
            <!-- Timeline tahap -->
            <div class="lde-timeline">
                <div class="wca-fsection__label" style="margin-bottom:.7rem"><i class="bi bi-signpost-split"></i> Tahapan Seleksi</div>
                <ol class="lde-steps">
                    <li v-for="t in tahap" :key="t.id" class="lde-step" :class="stepKelas(t)">
                        <span class="lde-step__dot"><i class="bi" :class="stepIkon(t)"></i></span>
                        <div class="lde-step__body">
                            <div class="lde-step__lbl">{{ t.urutan }}. {{ t.label }}</div>
                            <div class="lde-step__meta">
                                <span v-if="t.formulir"><i class="bi bi-input-cursor-text"></i> Formulir</span>
                                <span class="lde-step__st" :class="stepKelas(t)">{{ stepStatus(t) }}</span>
                            </div>
                        </div>
                    </li>
                </ol>
            </div>

            <!-- Panel utama -->
            <div class="lde-main">
                <!-- Gugur -->
                <div v-if="lamaran.status === 'GUGUR'" class="lde-final lde-final--gugur">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <h3>Belum berhasil kali ini</h3>
                        <p v-if="lamaran.gugurDi">Proses berhenti di tahap <b>{{ lamaran.gugurDi }}</b>.</p>
                        <p v-if="lamaran.alasanGugur" class="lde-final__note">{{ lamaran.alasanGugur }}</p>
                        <p>Jangan menyerah — masih banyak kesempatan lain di EVO Group.</p>
                    </div>
                </div>

                <!-- Diterima -->
                <div v-else-if="lamaran.status === 'LULUS'" class="lde-final lde-final--lulus">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <h3>Selamat! 🎉</h3>
                        <p>Anda menyelesaikan seluruh tahap seleksi. Tim rekrutmen akan menghubungi Anda untuk langkah berikutnya.</p>
                    </div>
                </div>

                <!-- Ada formulir yang harus diisi -->
                <div v-else-if="tugas && komponen" class="lde-form">
                    <div class="lde-form__head">
                        <span class="lde-form__ic"><i class="bi bi-pencil-square"></i></span>
                        <div>
                            <div class="lde-form__eyebrow">Tahap Aktif</div>
                            <h3>{{ tugas.label }}</h3>
                            <p>Lengkapi formulir berikut untuk melanjutkan proses.</p>
                        </div>
                    </div>

                    <div class="lde-form__body">
                        <component
                            :is="komponen"
                            v-model="jawaban"
                            label-kirim="Kirim & Lanjutkan"
                            @kirim="kirim"
                            @berkas="onBerkas"
                        />
                    </div>
                </div>

                <!-- Tahap berjalan tapi bukan formulir (tes/keputusan admin) -->
                <div v-else class="lde-wait">
                    <i class="bi bi-hourglass-split"></i>
                    <h3>Sedang dalam proses</h3>
                    <p>Tahap Anda saat ini sedang ditinjau tim rekrutmen. Kami akan mengabari perkembangannya.</p>
                </div>
            </div>
        </div>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast" :class="{ 'is-err': toastErr }"><i class="bi" :class="toastErr ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { komponenFormulir, skemaFormulir, jawabanAwal } from '@career/formulir';

const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, Link },
    props: {
        lamaran: { type: Object, default: () => ({}) },
        tahap: { type: Array, default: () => [] },
        tugas: { type: Object, default: null },
        profil: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            jawaban: {},
            berkas: {},
            mengirim: false,
            toast: '',
            toastErr: false,
            tm: null,
        };
    },
    computed: {
        // Komponen formulir dipilih dari kode komponen (Komponen_Kode) lewat registry.
        komponen() { return this.tugas ? komponenFormulir(this.tugas.komponen) : null; },
    },
    mounted() {
        if (this.tugas) {
            this.jawaban = jawabanAwal(skemaFormulir(this.tugas.komponen), this.profil);
        }
    },
    methods: {
        statusLabel(s) { return { BERJALAN: 'Berjalan', LULUS: 'Diterima', GUGUR: 'Tidak lolos' }[s] || s; },
        statusBadge(s) { return { BERJALAN: 'wca-b--indigo', LULUS: 'wca-b--green', GUGUR: 'wca-b--red' }[s] || 'wca-b--slate'; },
        statusIkon(s) { return { BERJALAN: 'bi-hourglass-split', LULUS: 'bi-check-circle', GUGUR: 'bi-x-circle' }[s] || 'bi-dot'; },
        stepKelas(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'is-gugur' : 'is-lulus';
            if (t.status === 'BERJALAN') return 'is-aktif';
            return 'is-nunggu';
        },
        stepIkon(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'bi-x' : 'bi-check-lg';
            if (t.status === 'BERJALAN') return 'bi-dot';
            return 'bi-lock';
        },
        stepStatus(t) {
            if (t.status === 'SELESAI') return t.hasil === 'GUGUR' ? 'Gugur' : 'Lulus';
            if (t.status === 'BERJALAN') return 'Berlangsung';
            return 'Menunggu';
        },
        onBerkas(e) {
            if (e.galat) { this.notice(e.galat, true); return; }
            if (e.file) this.berkas[e.field.key] = e.file;
        },
        async kirim(nilai) {
            if (this.mengirim || !this.tugas) return;
            this.mengirim = true;
            try {
                const res = await axios.post(`/api/v1/lamaran/tahap/${this.tugas.tahapId}/kirim`, { jawaban: nilai }, CFG);
                this.notice(res.data?.message || 'Formulir terkirim.');
                // Muat ulang detail: tahap maju / status berubah.
                setTimeout(() => router.reload(), 800);
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal mengirim formulir.', true);
            } finally {
                this.mengirim = false;
            }
        },
        notice(x, err = false) { this.toast = x; this.toastErr = err; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 4000); },
    },
};
</script>

<style scoped>
.lde-back { display: inline-flex; align-items: center; gap: .35rem; font-size: 12.5px; color: #64748b; text-decoration: none; margin-bottom: .3rem; }
.lde-back:hover { color: #4338ca; }
.lde-status { align-self: flex-start; }

.lde-grid { display: grid; grid-template-columns: 17rem 1fr; gap: 1.2rem; align-items: start; }

/* Timeline */
.lde-timeline { border: 1px solid rgba(11, 16, 51, .09); border-radius: 14px; padding: 1rem; background: #fff; position: sticky; top: 1rem; }
.lde-steps { list-style: none; margin: 0; padding: 0; }
.lde-step { display: flex; gap: .6rem; padding-bottom: .9rem; position: relative; }
.lde-step:not(:last-child)::before { content: ''; position: absolute; left: 12px; top: 26px; bottom: 0; width: 2px; background: #eef2f7; }
.lde-step__dot { flex: none; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-size: .75rem; background: #eef2f7; color: #94a3b8; z-index: 1; }
.lde-step.is-lulus .lde-step__dot { background: rgba(16,185,129,.15); color: #059669; }
.lde-step.is-gugur .lde-step__dot { background: rgba(239,68,68,.15); color: #dc2626; }
.lde-step.is-aktif .lde-step__dot { background: linear-gradient(140deg,#4f46e5,#7c3aed); color: #fff; animation: ldepulse 1.6s ease-in-out infinite; }
.lde-step__lbl { font-size: 13px; font-weight: 600; color: #334155; }
.lde-step__meta { display: flex; align-items: center; gap: .5rem; margin-top: .15rem; font-size: 11px; color: #94a3b8; }
.lde-step__st { font-weight: 600; }
.lde-step.is-aktif .lde-step__st { color: #4338ca; }
.lde-step.is-lulus .lde-step__st { color: #059669; }
.lde-step.is-gugur .lde-step__st { color: #dc2626; }
@keyframes ldepulse { 0%,100% { box-shadow: 0 0 0 0 rgba(124,58,237,.4); } 50% { box-shadow: 0 0 0 5px rgba(124,58,237,0); } }

/* Panel */
.lde-main { min-width: 0; }
.lde-form { border: 1px solid rgba(11, 16, 51, .09); border-radius: 16px; background: #fff; overflow: hidden; }
.lde-form__head { display: flex; gap: .8rem; padding: 1.1rem 1.2rem; border-bottom: 1px solid rgba(11,16,51,.07); background: linear-gradient(180deg, rgba(79,70,229,.05), transparent); }
.lde-form__ic { flex: none; width: 2.4rem; height: 2.4rem; display: grid; place-items: center; border-radius: .8rem; background: linear-gradient(140deg,#4f46e5,#7c3aed); color: #fff; font-size: 1.05rem; }
.lde-form__eyebrow { font-size: 10.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #7c3aed; }
.lde-form__head h3 { margin: .1rem 0 0; font-size: 1.05rem; font-weight: 800; color: #0f172a; }
.lde-form__head p { margin: .1rem 0 0; font-size: 12.5px; color: #64748b; }
.lde-form__body { padding: 1.3rem 1.2rem; }

.lde-wait, .lde-final { display: flex; gap: 1rem; align-items: flex-start; border-radius: 16px; padding: 1.4rem; }
.lde-wait { border: 1px dashed rgba(11,16,51,.16); background: rgba(248,250,252,.7); }
.lde-wait { flex-direction: column; align-items: center; text-align: center; color: #64748b; }
.lde-wait .bi { font-size: 1.8rem; color: #7c3aed; }
.lde-wait h3 { margin: .3rem 0 .1rem; color: #0f172a; }
.lde-final .bi { font-size: 1.8rem; }
.lde-final h3 { margin: 0 0 .2rem; }
.lde-final p { margin: .15rem 0; font-size: 13px; }
.lde-final--gugur { border: 1px solid rgba(239,68,68,.25); background: rgba(239,68,68,.06); }
.lde-final--gugur .bi { color: #dc2626; }
.lde-final--lulus { border: 1px solid rgba(16,185,129,.3); background: rgba(16,185,129,.07); }
.lde-final--lulus .bi { color: #059669; }
.lde-final__note { font-style: italic; color: #64748b; }

.wca-toast.is-err { background: #b91c1c; }

@media (max-width: 820px) {
    .lde-grid { grid-template-columns: 1fr; }
    .lde-timeline { position: static; }
}
</style>
