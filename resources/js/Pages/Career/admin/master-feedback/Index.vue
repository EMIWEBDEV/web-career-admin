<!-- WEB CAREER — Master Feedback Form (induk-detail: Form + Pertanyaan). DATA dari DB via /api/v1/karir/master-feedback. -->
<template>
    <Head><title>Master Feedback Form - Web Career</title></Head>
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-chat-dots"></i></span>
                    <h1>Master Feedback Form</h1>
                </div>
                <p>Buat template formulir feedback — atur pertanyaan dengan berbagai tipe (rating, NPS, Likert, teks, pilihan ganda).</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Form Baru</button>
        </div>

        <div v-loading="loading" class="pkg-list">
            <div v-for="f in list" :key="f.Id_Master_Feedback_Form" class="pkg-card" :class="{ open: open === f.Id_Master_Feedback_Form }">
                <!-- Header row -->
                <div class="pkg-row">
                    <button type="button" class="pkg-chev" :class="{ open: open === f.Id_Master_Feedback_Form }" title="Buka detail" @click="open = (open === f.Id_Master_Feedback_Form ? null : f.Id_Master_Feedback_Form)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="open = (open === f.Id_Master_Feedback_Form ? null : f.Id_Master_Feedback_Form)">
                            <span class="pkg-row__title">{{ f.Nama }}</span>
                        </button>
                        <div class="pkg-row__meta">
                            <span class="pkg-code">{{ f.Mode_Tampilan === 'WIZARD' ? 'Step-by-Step' : 'Single Page' }}</span>
                            <template v-if="f.Deskripsi"><span class="pkg-sep"></span><span class="pkg-mi">{{ f.Deskripsi }}</span></template>
                        </div>
                        <div class="pkg-pills">
                            <span class="pkg-pill pkg-pill--violet"><i class="bi bi-question-circle"></i> {{ f.Jumlah_Pertanyaan ?? '?' }} pertanyaan</span>
                            <span v-if="f.Durasi_Hari" class="pkg-pill pkg-pill--struct"><i class="bi bi-hourglass-split"></i> {{ f.Durasi_Hari }} hari</span>
                            <span v-else class="pkg-pill pkg-pill--struct"><i class="bi bi-infinity"></i> Unlimited</span>
                            <span class="pkg-pill" :class="f.Flag_Aktif === 'Y' ? 'pkg-pill--green' : 'pkg-pill--slate'">
                                <span class="pkg-pill__dot"></span> {{ f.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                    </div>
                    <div class="pkg-row__act" @click.stop>
                        <el-switch :model-value="f.Flag_Aktif === 'Y'" @change="(v) => setStatus(f, v)" />
                        <button class="pkg-ibtn" title="Ubah" @click="openEdit(f)"><i class="bi bi-pencil"></i></button>
                        <button class="pkg-ibtn pkg-ibtn--danger" title="Hapus" @click="askRemove(f)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <!-- Creator strip -->
                <div class="pkg-creator">
                    <span class="pkg-creator__av" style="background:#6366f1">{{ initials(f.Created_By) }}</span>
                    <span class="pkg-creator__name">{{ f.Created_By || 'Sistem' }}</span>
                    <span class="pkg-creator__at"><i class="bi bi-clock"></i> {{ f.Created_At || '—' }}</span>
                </div>

                <!-- Expanded detail: questions -->
                <div v-if="open === f.Id_Master_Feedback_Form" class="pkg-detail">
                    <div class="pkg-dhead pkg-dhead--indigo">
                        <span><i class="bi bi-list-ol"></i> DAFTAR PERTANYAAN ({{ (f._pertanyaan || []).length }})</span>
                        <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addQuestion(f)"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                    <div v-if="!f._pertanyaan || !f._pertanyaan.length" class="wca-hint"><i class="bi bi-info-circle"></i> Belum ada pertanyaan. Klik <b>Tambah</b> untuk mulai.</div>
                    <div v-for="(p, i) in (f._pertanyaan || [])" :key="i" class="wca-stagecard">
                        <div class="wca-stagecard__num">{{ i + 1 }}</div>
                        <div class="wca-stagecard__body">
                            <div class="wca-frow">
                                <div style="flex:1"><label class="wca-field-lbl">Tipe</label>
                                    <el-select v-model="p.Tipe" size="small" style="width:100%">
                                        <el-option v-for="t in ['RATING','NPS','LIKERT','TEXTAREA','RADIO','CHECKBOX','DROPDOWN']" :key="t" :label="t" :value="t" />
                                    </el-select>
                                </div>
                                <div style="flex:3"><label class="wca-field-lbl">Label Pertanyaan</label>
                                    <el-input v-model="p.Label" size="small" placeholder="Tulis pertanyaan..." />
                                </div>
                            </div>
                            <div v-if="['RATING','NPS','LIKERT'].includes(p.Tipe)" class="wca-frow" style="margin-top:10px">
                                <div><label class="wca-field-lbl">Skala Min</label><el-input-number v-model="p.Skala_Min" size="small" :min="0" :max="10" /></div>
                                <div><label class="wca-field-lbl">Skala Max</label><el-input-number v-model="p.Skala_Max" size="small" :min="1" :max="10" /></div>
                            </div>
                            <div v-if="['RADIO','CHECKBOX','DROPDOWN'].includes(p.Tipe)" style="margin-top:10px">
                                <label class="wca-field-lbl">Opsi (pisahkan dengan koma)</label>
                                <el-input v-model="p._opsiText" size="small" placeholder="Opsi A, Opsi B, Opsi C" />
                            </div>
                            <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center">
                                <el-button size="small" type="danger" text @click="removeQuestion(f, i)"><i class="bi bi-trash"></i> Hapus</el-button>
                                <span style="font-size:.72rem;color:var(--muted)">Urutan: {{ i + 1 }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="(f._pertanyaan || []).length" style="margin-top:12px;text-align:right">
                        <el-button type="primary" size="small" @click="saveQuestions(f)" :loading="savingQ[f.Id_Master_Feedback_Form]">
                            <i class="bi bi-check-lg"></i> Simpan Pertanyaan
                        </el-button>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="pkg-empty">
                <i class="bi bi-chat-dots"></i> Belum ada form feedback. Klik <b>Form Baru</b> untuk membuat.
            </div>
        </div>

        <!-- Modal create/edit -->
        <AdminModal
            :show="show" :title="editingId ? 'Ubah Form Feedback' : 'Buat Form Feedback'"
            subtitle="Atur nama, mode tampilan, durasi, dan status form." icon="bi-chat-dots"
            :save-label="editingId ? 'Perbarui' : 'Simpan Form'"
            @close="show = false" @save="saveForm"
        >
            <!-- Identitas Form -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-pencil-square"></i> Identitas Form</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div style="flex:2">
                            <label class="wca-field-lbl"><i class="bi bi-tag"></i> Nama Form <span style="color:var(--danger)">*</span></label>
                            <el-input v-model="form.Nama" placeholder="mis. Feedback Rekrutmen Reguler 2026" size="large" />
                            <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:600;font-size:.72rem">Nama form yang mudah dikenali admin saat assign ke program.</small>
                        </div>
                        <div style="flex:1">
                            <label class="wca-field-lbl"><i class="bi bi-text-paragraph"></i> Deskripsi</label>
                            <el-input v-model="form.Deskripsi" type="textarea" :rows="3" placeholder="Jelaskan tujuan form ini (opsional)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pengaturan Tampilan & Durasi -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Pengaturan Tampilan & Durasi</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div style="flex:3">
                            <label class="wca-field-lbl"><i class="bi bi-layout-text-window-reverse"></i> Mode Tampilan <span style="color:var(--danger)">*</span></label>
                            <div class="wca-radio-cards">
                                <label :class="['wca-radio-card', { 'is-active': form.Mode_Tampilan === 'SCROLL' }]">
                                    <input type="radio" value="SCROLL" v-model="form.Mode_Tampilan" />
                                    <span class="wca-radio-card__icon"><i class="bi bi-file-earmark-text"></i></span>
                                    <span class="wca-radio-card__label">Single Page</span>
                                    <span class="wca-radio-card__desc">Semua pertanyaan dalam satu halaman scroll</span>
                                </label>
                                <label :class="['wca-radio-card', { 'is-active': form.Mode_Tampilan === 'WIZARD' }]">
                                    <input type="radio" value="WIZARD" v-model="form.Mode_Tampilan" />
                                    <span class="wca-radio-card__icon"><i class="bi bi-chevron-double-right"></i></span>
                                    <span class="wca-radio-card__label">Step-by-Step</span>
                                    <span class="wca-radio-card__desc">Satu pertanyaan per langkah dengan progress bar</span>
                                </label>
                            </div>
                        </div>
                        <div style="flex:1">
                            <label class="wca-field-lbl"><i class="bi bi-hourglass-split"></i> Durasi Pengisian</label>
                            <el-input-number v-model="form.Durasi_Hari" :min="1" :max="30" placeholder="Unlimited" style="width:100%" />
                            <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:600;font-size:.72rem">
                                {{ form.Durasi_Hari ? form.Durasi_Hari + ' hari sejak email dikirim' : 'Tidak ada batas waktu (unlimited)' }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-toggle-on"></i> Status</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <div :class="['wca-status-card', form.Flag_Aktif === 'Y' ? 'wca-status-card--on' : 'wca-status-card--off']">
                                <span class="wca-status-card__icon">
                                    <i class="bi" :class="form.Flag_Aktif === 'Y' ? 'bi-check-circle-fill' : 'bi-pause-circle'"></i>
                                </span>
                                <div class="wca-status-card__body">
                                    <strong>{{ form.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}</strong>
                                    <small>{{ form.Flag_Aktif === 'Y' ? 'Form dapat digunakan oleh kandidat' : 'Form tidak akan muncul untuk kandidat' }}</small>
                                </div>
                                <el-switch v-model="form.Flag_Aktif" active-value="Y" inactive-value="T" size="large" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminModal>

        <!-- Confirm remove -->
        <ConfirmModal :show="!!removeTarget" title="Hapus Form Feedback" :message="removeMessage" icon="bi-trash" @confirm="doRemove" @close="removeTarget = null" />
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';

export default {
    components: { Head, AdminModal, ConfirmModal },
    data() {
        return {
            list: [],
            loading: false,
            open: null,
            show: false,
            editingId: null,
            removeTarget: null,
            savingQ: {},
            form: { Nama: '', Deskripsi: '', Mode_Tampilan: 'SCROLL', Durasi_Hari: null, Flag_Aktif: 'Y' },
        };
    },
    computed: {
        removeMessage() {
            const name = this.removeTarget?.Nama ?? '';
            return `Yakin hapus "${name}"? Pertanyaan di dalamnya juga akan dihapus.`;
        },
    },
    mounted() { this.load(); },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/v1/karir/master-feedback');
                this.list = (data.result || []).map(f => ({ ...f, _pertanyaan: [], _questionsLoaded: false }));
            } catch (e) { /* silent */ }
            this.loading = false;
        },
        async loadQuestions(f) {
            if (f._questionsLoaded) return;
            try {
                const { data } = await axios.get(`/api/v1/karir/master-feedback/${f.Id_Master_Feedback_Form}`);
                f._pertanyaan = (data.result?.pertanyaan || []).map(p => ({
                    ...p,
                    _opsiText: (p.Opsi || []).join(', '),
                }));
                f.Jumlah_Pertanyaan = f._pertanyaan.length;
                f._questionsLoaded = true;
            } catch (e) {
                f._pertanyaan = [];
                f._questionsLoaded = true;
            }
        },
        openCreate() { this.editingId = null; this.form = { Nama: '', Deskripsi: '', Mode_Tampilan: 'SCROLL', Durasi_Hari: null, Flag_Aktif: 'Y' }; this.show = true; },
        async openEdit(f) {
            await this.loadQuestions(f);
            this.editingId = f.Id_Master_Feedback_Form;
            this.form = {
                Nama: f.Nama, Deskripsi: f.Deskripsi || '', Mode_Tampilan: f.Mode_Tampilan || 'SCROLL',
                Durasi_Hari: f.Durasi_Hari, Flag_Aktif: f.Flag_Aktif || 'T',
            };
            this.show = true;
        },
        async saveForm() {
            const payload = { nama: this.form.Nama, deskripsi: this.form.Deskripsi, mode_tampilan: this.form.Mode_Tampilan, durasi_hari: this.form.Durasi_Hari, flag_aktif: this.form.Flag_Aktif };
            if (this.editingId) {
                await axios.put(`/api/v1/karir/master-feedback/${this.editingId}`, payload);
            } else {
                await axios.post('/api/v1/karir/master-feedback', payload);
            }
            this.show = false; this.load();
        },
        askRemove(f) { this.removeTarget = f; },
        async doRemove() {
            if (!this.removeTarget) return;
            await axios.delete(`/api/v1/karir/master-feedback/${this.removeTarget.Id_Master_Feedback_Form}`);
            if (this.open === this.removeTarget.Id_Master_Feedback_Form) this.open = null;
            this.removeTarget = null; this.load();
        },
        async setStatus(f, active) {
            await axios.put(`/api/v1/karir/master-feedback/${f.Id_Master_Feedback_Form}`, { nama: f.Nama, deskripsi: f.Deskripsi, mode_tampilan: f.Mode_Tampilan, durasi_hari: f.Durasi_Hari, flag_aktif: active ? 'Y' : 'T' });
            this.load();
        },
        addQuestion(f) {
            if (!f._pertanyaan) f._pertanyaan = [];
            f._pertanyaan.push({ Tipe: 'RATING', Label: '', Skala_Min: 1, Skala_Max: 5, _opsiText: '' });
        },
        removeQuestion(f, i) { f._pertanyaan.splice(i, 1); },
        async saveQuestions(f) {
            this.savingQ = { ...this.savingQ, [f.Id_Master_Feedback_Form]: true };
            const pertanyaan = (f._pertanyaan || []).map((p, i) => ({
                urutan: i + 1, tipe: p.Tipe, label: p.Label,
                skala_min: p.Skala_Min ?? null, skala_max: p.Skala_Max ?? null,
                opsi: p._opsiText ? p._opsiText.split(',').map(o => o.trim()).filter(Boolean) : null,
            }));
            await axios.post(`/api/v1/karir/master-feedback/${f.Id_Master_Feedback_Form}/pertanyaan`, { pertanyaan });
            this.savingQ = { ...this.savingQ, [f.Id_Master_Feedback_Form]: false };
            f._questionsLoaded = false;
            await this.loadQuestions(f);
        },
        initials(name) {
            if (!name) return '?';
            return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        },
    },
};
</script>

<style scoped>
/* ── Radio Cards (Mode Tampilan) ── */
.wca-radio-cards {
    display: flex;
    gap: 12px;
}

.wca-radio-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 16px 12px;
    border: 2px solid var(--border-light, #e2e8f0);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    background: #fff;
}

.wca-radio-card:hover {
    border-color: #a5b4fc;
    background: rgba(99, 102, 241, 0.03);
}

.wca-radio-card.is-active {
    border-color: #6366f1;
    background: rgba(99, 102, 241, 0.06);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.wca-radio-card input[type="radio"] {
    display: none;
}

.wca-radio-card__icon {
    font-size: 1.5rem;
    color: var(--muted, #94a3b8);
    line-height: 1;
}

.wca-radio-card.is-active .wca-radio-card__icon {
    color: #6366f1;
}

.wca-radio-card__label {
    font-size: 0.82rem;
    font-weight: 700;
    color: #334155;
}

.wca-radio-card.is-active .wca-radio-card__label {
    color: #6366f1;
}

.wca-radio-card__desc {
    font-size: 0.7rem;
    color: var(--muted, #94a3b8);
    line-height: 1.35;
}

/* ── Status Card ── */
.wca-status-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border: 1.5px solid var(--border-light, #e2e8f0);
    border-radius: 12px;
    background: #fff;
    width: 100%;
}

.wca-status-card--on {
    border-color: rgba(16, 185, 129, 0.3);
    background: rgba(16, 185, 129, 0.04);
}

.wca-status-card--off {
    border-color: rgba(148, 163, 184, 0.25);
    background: rgba(148, 163, 184, 0.03);
}

.wca-status-card__icon {
    font-size: 1.6rem;
    line-height: 1;
}

.wca-status-card--on .wca-status-card__icon {
    color: #10b981;
}

.wca-status-card--off .wca-status-card__icon {
    color: #94a3b8;
}

.wca-status-card__body {
    flex: 1;
}

.wca-status-card__body strong {
    display: block;
    font-size: 0.85rem;
    color: #1e293b;
}

.wca-status-card__body small {
    display: block;
    font-size: 0.73rem;
    color: var(--muted, #94a3b8);
    margin-top: 2px;
}
</style>
