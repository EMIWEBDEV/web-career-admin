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
                    <button type="button" class="pkg-chev" :class="{ open: open === f.Id_Master_Feedback_Form }" title="Buka detail" @click="toggleExpand(f)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="toggleExpand(f)">
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
                        <span><i class="bi bi-list-ol"></i> PERTANYAAN FORM — {{ (f._pertanyaan || []).length }} butir</span>
                        <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="showTypePicker(f)"><i class="bi bi-plus-circle"></i> Tambah Pertanyaan</button>
                    </div>

                    <div v-if="!f._pertanyaan || !f._pertanyaan.length" class="fb-empty-questions">
                        <div class="fb-empty-questions__icon"><i class="bi bi-lightbulb"></i></div>
                        <h4>Belum ada pertanyaan</h4>
                        <p>Klik <b>Tambah Pertanyaan</b> lalu pilih tipe pertanyaan — Rating, NPS, Likert, Teks, atau Pilihan Ganda.</p>
                    </div>

                    <!-- DRAG-DROP QUESTION LIST -->
                    <draggable
                        v-else
                        v-model="f._pertanyaan"
                        item-key="_key"
                        handle=".fb-q-drag"
                        ghost-class="fb-q-ghost"
                        @end="onReorder(f)"
                    >
                        <template #item="{ element: p, index: i }">
                            <div :class="['fb-q-card', { 'fb-q-card--expanded': f._editIdx === i }]">
                                <!-- COLLAPSED STATE -->
                                <div v-if="f._editIdx !== i" class="fb-q-collapsed" @click="f._editIdx = i">
                                    <span class="fb-q-drag" @click.stop><i class="bi bi-grip-vertical"></i></span>
                                    <span class="fb-q-collapsed__num">{{ i + 1 }}</span>
                                    <span class="fb-q-collapsed__icon" v-html="typeIcon(p.Tipe)"></span>
                                    <span class="fb-q-collapsed__text">
                                        <strong>{{ p.Label || 'Pertanyaan tanpa judul' }}</strong>
                                        <small>{{ typeLabel(p.Tipe) }}</small>
                                    </span>
                                    <span :class="['wca-badge', typeBadgeClass(p.Tipe)]" style="margin-right:8px">{{ p.Tipe }}</span>
                                    <button class="fb-q-collapsed__edit" @click.stop="f._editIdx = i"><i class="bi bi-pencil"></i></button>
                                    <button class="fb-q-collapsed__del" @click.stop="removeQuestion(f, i)"><i class="bi bi-trash"></i></button>
                                </div>

                                <!-- EXPANDED STATE -->
                                <div v-else class="fb-q-expanded">
                                    <div class="fb-q-expanded__head">
                                        <span class="fb-q-drag"><i class="bi bi-grip-vertical"></i></span>
                                        <span class="fb-q-expanded__num">{{ i + 1 }}</span>
                                        <span class="fb-q-expanded__title">Edit Pertanyaan</span>
                                        <button class="fb-q-expanded__close" @click="f._editIdx = null"><i class="bi bi-check-lg"></i> Selesai</button>
                                    </div>

                                    <div class="fb-q-expanded__body">
                                        <!-- Left: editor -->
                                        <div class="fb-q-editor">
                                            <label class="wca-field-lbl">Tipe Pertanyaan</label>
                                            <div class="fb-type-pills">
                                                <button v-for="t in questionTypes" :key="t.value"
                                                    :class="['fb-type-pill', { 'fb-type-pill--active': p.Tipe === t.value }]"
                                                    @click="onTypeChange(p, t.value)">
                                                    <span class="fb-type-pill__icon" v-html="t.icon"></span>
                                                    <span class="fb-type-pill__label">{{ t.label }}</span>
                                                </button>
                                            </div>

                                            <label class="wca-field-lbl" style="margin-top:20px">Pertanyaan</label>
                                            <el-input v-model="p.Label" placeholder="Tulis pertanyaan yang akan ditampilkan ke kandidat..." size="large" />

                                            <!-- Scale range for RATING & NPS only (Likert is fixed 1-5) -->
                                            <div v-if="['RATING','NPS'].includes(p.Tipe)" class="fb-scale-row">
                                                <label class="wca-field-lbl">Rentang Skala</label>
                                                <div class="fb-scale-inputs">
                                                    <span class="fb-scale-label">Dari</span>
                                                    <el-input-number v-model="p.Skala_Min" :min="0" :max="10" size="large" style="width:100px" />
                                                    <span class="fb-scale-label">sampai</span>
                                                    <el-input-number v-model="p.Skala_Max" :min="1" :max="10" size="large" style="width:100px" />
                                                    <el-button size="small" text @click="p.Skala_Min = defaultMin(p.Tipe); p.Skala_Max = defaultMax(p.Tipe)">Reset default</el-button>
                                                </div>
                                            </div>

                                            <!-- Options with individual chips (not comma!) -->
                                            <div v-if="['RADIO','CHECKBOX','DROPDOWN'].includes(p.Tipe)" class="fb-options-row">
                                                <label class="wca-field-lbl">Opsi Pilihan</label>
                                                <div class="fb-option-chips">
                                                    <div v-for="(opt, oi) in (p._opsiList || [])" :key="oi" class="fb-option-chip">
                                                        <span class="fb-option-chip__num">{{ oi + 1 }}</span>
                                                        <el-input v-model="p._opsiList[oi]" placeholder="Opsi {{ oi + 1 }}" size="large" />
                                                        <button class="fb-option-chip__del" @click="removeOption(p, oi)" title="Hapus opsi"><i class="bi bi-x-lg"></i></button>
                                                    </div>
                                                </div>
                                                <button class="fb-option-add" @click="addOption(p)">
                                                    <i class="bi bi-plus-circle"></i> Tambah Opsi
                                                </button>
                                                <small v-if="!p._opsiList || !p._opsiList.length" style="display:block;margin-top:6px;color:var(--muted)">Tambahkan minimal 2 opsi pilihan untuk kandidat.</small>
                                            </div>
                                        </div>

                                        <!-- Right: proper preview -->
                                        <div class="fb-preview">
                                            <div class="fb-preview__label">Pratinjau — Tampilan Kandidat</div>
                                            <div class="fb-preview__card">
                                                <div class="fb-preview__q">{{ p.Label || 'Pertanyaan Anda...' }}</div>

                                                <!-- Rating: realistic stars preview -->
                                                <div v-if="p.Tipe === 'RATING'" class="fb-preview-rate-wrap">
                                                    <div class="fb-preview-rate__stars">
                                                        <span v-for="s in (p.Skala_Max || 5)" :key="s"
                                                              class="fb-preview-rate__star"
                                                              :class="{ 'fb-preview-rate__star--on': s <= rateDemo(p) }">
                                                            {{ s <= rateDemo(p) ? '★' : '☆' }}
                                                        </span>
                                                    </div>
                                                    <div class="fb-preview-rate__range">
                                                        Skala {{ p.Skala_Min || 1 }} – {{ p.Skala_Max || 5 }}
                                                    </div>
                                                </div>

                                                <!-- NPS: realistic scale-aware buttons with labels -->
                                                <div v-if="p.Tipe === 'NPS'" class="fb-preview-nps-wrap">
                                                    <div class="fb-preview-nps__btns">
                                                        <span v-for="n in npsRange(p)" :key="n"
                                                              class="fb-preview-nps__btn"
                                                              :class="{ 'fb-preview-nps__btn--demo': n === npsDemo(p) }"
                                                              :style="{ background: npsPreviewColor(n) }">
                                                            {{ n }}
                                                        </span>
                                                    </div>
                                                    <div class="fb-preview-nps__labels">
                                                        <span>Tidak mungkin</span>
                                                        <span>Sangat mungkin</span>
                                                    </div>
                                                </div>

                                                <!-- Likert: matches LikertInput.vue exactly -->
                                                <div v-if="p.Tipe === 'LIKERT'" class="fb-preview-likert-wrap">
                                                    <div class="fb-preview-likert__opts">
                                                        <span v-for="(lik, li) in likertLabels" :key="li"
                                                              :class="['fb-preview-likert__opt', { 'fb-preview-likert__opt--on': li === 2 }]">
                                                            {{ lik }}
                                                        </span>
                                                    </div>
                                                    <div class="fb-preview-likert__labels">
                                                        <span>Sangat Tidak Setuju</span>
                                                        <span>Sangat Setuju</span>
                                                    </div>
                                                </div>

                                                <!-- Textarea -->
                                                <div v-if="p.Tipe === 'TEXTAREA'" class="fb-preview-ta">
                                                    <div class="fb-preview-ta__box">Tulis jawaban kamu di sini...</div>
                                                    <div class="fb-preview-ta__counter">0/500</div>
                                                </div>

                                                <!-- Radio: realistic card pills — one selected with radio dot -->
                                                <div v-if="p.Tipe === 'RADIO'" class="fb-pv-radio">
                                                    <div v-for="(opt, oi) in (p._opsiList || [])" :key="oi"
                                                          class="fb-pv-radio__card"
                                                          :class="{ 'fb-pv-radio__card--sel': oi === 0 }">
                                                        <span class="fb-pv-radio__dot">
                                                            <span v-if="oi === 0" class="fb-pv-radio__dot--fill"></span>
                                                        </span>
                                                        <span class="fb-pv-radio__text">{{ opt }}</span>
                                                    </div>
                                                    <span v-if="!p._opsiList || !p._opsiList.length" class="fb-preview__empty">Tambahkan opsi di panel kiri</span>
                                                </div>

                                                <!-- Checkbox: realistic card pills — 2 selected with checkmarks -->
                                                <div v-if="p.Tipe === 'CHECKBOX'" class="fb-pv-check">
                                                    <div v-for="(opt, oi) in (p._opsiList || [])" :key="oi"
                                                          class="fb-pv-check__card"
                                                          :class="{ 'fb-pv-check__card--sel': oi < 2 }">
                                                        <span class="fb-pv-check__box">
                                                            <i v-if="oi < 2" class="bi bi-check-lg"></i>
                                                        </span>
                                                        <span class="fb-pv-check__text">{{ opt }}</span>
                                                    </div>
                                                    <span v-if="!p._opsiList || !p._opsiList.length" class="fb-preview__empty">Tambahkan opsi di panel kiri</span>
                                                </div>

                                                <!-- Dropdown: realistic select box + option menu -->
                                                <div v-if="p.Tipe === 'DROPDOWN'" class="fb-preview-dd">
                                                    <div class="fb-preview-dd__box">
                                                        <span>Pilih salah satu</span>
                                                        <i class="bi bi-chevron-down"></i>
                                                    </div>
                                                    <div class="fb-preview-dd__menu">
                                                        <div v-for="(opt, oi) in (p._opsiList || [])" :key="oi"
                                                             class="fb-preview-dd__item"
                                                             :class="{ 'fb-preview-dd__item--sel': oi === 0 }">
                                                            {{ opt }}
                                                            <i v-if="oi === 0" class="bi bi-check-lg"></i>
                                                        </div>
                                                    </div>
                                                    <div v-if="!p._opsiList || !p._opsiList.length" class="fb-preview__empty">
                                                        Tambahkan opsi di panel kiri
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </draggable>

                    <!-- Bottom actions -->
                    <div v-if="(f._pertanyaan || []).length" class="fb-q-footer">
                        <span class="fb-q-footer__info"><i class="bi bi-info-circle"></i> Geser <span class="fb-q-drag-inline"><i class="bi bi-grip-vertical"></i></span> untuk mengurutkan. Klik kartu untuk mengedit. </span>
                        <div class="fb-q-footer__btns">
                            <button class="wca-btn wca-btn--soft" type="button" @click="showTypePicker(f)"><i class="bi bi-plus-circle"></i> Tambah</button>
                            <button class="wca-btn wca-btn--primary" type="button" @click="saveQuestions(f)" :disabled="savingQ[f.Id_Master_Feedback_Form]">
                                <i class="bi" :class="savingQ[f.Id_Master_Feedback_Form] ? 'bi-hourglass-split' : 'bi-check-lg'"></i>
                                {{ savingQ[f.Id_Master_Feedback_Form] ? 'Menyimpan...' : 'Simpan Semua' }}
                            </button>
                        </div>
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
            lg
            @close="show = false" @save="saveForm"
        >
            <!-- Identitas Form -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-pencil-square"></i> Identitas Form</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl"><i class="bi bi-tag"></i> Nama Form <span style="color:var(--danger)">*</span></label>
                        <el-input v-model="form.Nama" placeholder="mis. Feedback Rekrutmen Reguler 2026" size="large" />
                        <small style="display:block;margin-top:.35rem;color:var(--muted);font-weight:600;font-size:.72rem">Nama form yang mudah dikenali admin saat assign ke program.</small>
                    </div>
                    <div style="margin-top:16px">
                        <label class="wca-field-lbl"><i class="bi bi-text-paragraph"></i> Deskripsi <span style="color:var(--muted);font-weight:400">(opsional)</span></label>
                        <el-input v-model="form.Deskripsi" type="textarea" :rows="3" placeholder="Jelaskan tujuan dan konteks form feedback ini..." />
                    </div>
                </div>
            </div>

            <!-- Pengaturan Tampilan & Durasi -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders"></i> Pengaturan Tampilan & Durasi</div>
                <div class="wca-form">
                    <div>
                        <label class="wca-field-lbl"><i class="bi bi-layout-text-window-reverse"></i> Mode Tampilan <span style="color:var(--danger)">*</span></label>
                        <div class="wca-radio-cards">
                            <label :class="['wca-radio-card', { 'is-active': form.Mode_Tampilan === 'SCROLL' }]">
                                <input type="radio" value="SCROLL" v-model="form.Mode_Tampilan" />
                                <span class="wca-radio-card__icon"><i class="bi bi-file-earmark-text"></i></span>
                                <span class="wca-radio-card__label">Single Page Scroll</span>
                                <span class="wca-radio-card__desc">Semua pertanyaan ditampilkan dalam satu halaman yang bisa di-scroll</span>
                            </label>
                            <label :class="['wca-radio-card', { 'is-active': form.Mode_Tampilan === 'WIZARD' }]">
                                <input type="radio" value="WIZARD" v-model="form.Mode_Tampilan" />
                                <span class="wca-radio-card__icon"><i class="bi bi-chevron-double-right"></i></span>
                                <span class="wca-radio-card__label">Step-by-Step Wizard</span>
                                <span class="wca-radio-card__desc">Satu pertanyaan per langkah dengan progress bar dan navigasi</span>
                            </label>
                        </div>
                    </div>
                    <div style="margin-top:20px">
                        <label class="wca-field-lbl"><i class="bi bi-hourglass-split"></i> Durasi Pengisian</label>
                        <el-input-number v-model="form.Durasi_Hari" :min="1" :max="30" placeholder="Unlimited" size="large" style="width:100%" />
                        <small style="display:block;margin-top:.35rem;color:var(--muted);font-weight:600;font-size:.72rem">
                            {{ form.Durasi_Hari ? 'Kandidat punya waktu ' + form.Durasi_Hari + ' hari sejak email dikirim untuk mengisi feedback' : 'Tidak ada batas waktu — kandidat bisa mengisi kapan saja (maks. 30 hari)' }}
                        </small>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-toggle-on"></i> Status</div>
                <div class="wca-form">
                    <div>
                        <div :class="['wca-status-card', form.Flag_Aktif === 'Y' ? 'wca-status-card--on' : 'wca-status-card--off']">
                            <span class="wca-status-card__icon">
                                <i class="bi" :class="form.Flag_Aktif === 'Y' ? 'bi-check-circle-fill' : 'bi-pause-circle'"></i>
                            </span>
                            <div class="wca-status-card__body">
                                <strong>{{ form.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}</strong>
                                <small>{{ form.Flag_Aktif === 'Y' ? 'Form dapat digunakan — kandidat akan menerima link feedback' : 'Form tidak akan muncul — kandidat tidak akan diminta mengisi feedback' }}</small>
                            </div>
                            <el-switch v-model="form.Flag_Aktif" active-value="Y" inactive-value="T" size="large" />
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
import draggable from 'vuedraggable';

export default {
    components: { Head, AdminModal, ConfirmModal, draggable },
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
            likertLabels: ['STS', 'TS', 'N', 'S', 'SS'],
            questionTypes: [
                { value: 'RATING', label: 'Rating', icon: '<i class="bi bi-star-fill"></i>' },
                { value: 'NPS', label: 'NPS', icon: '<i class="bi bi-0-circle"></i>' },
                { value: 'LIKERT', label: 'Likert', icon: '<i class="bi bi-ui-radios"></i>' },
                { value: 'TEXTAREA', label: 'Teks', icon: '<i class="bi bi-text-paragraph"></i>' },
                { value: 'RADIO', label: 'Pilih Satu', icon: '<i class="bi bi-record-circle"></i>' },
                { value: 'CHECKBOX', label: 'Multi-Pilih', icon: '<i class="bi bi-check2-square"></i>' },
                { value: 'DROPDOWN', label: 'Dropdown', icon: '<i class="bi bi-chevron-down"></i>' },
            ],
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
        async toggleExpand(f) {
            if (this.open === f.Id_Master_Feedback_Form) {
                this.open = null;
            } else {
                await this.loadQuestions(f);
                this.open = f.Id_Master_Feedback_Form;
            }
        },
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
                    _key: 'q_' + (p.Id_Master_Feedback_Pertanyaan || Math.random().toString(36).slice(2, 10)),
                    _opsiList: (p.Opsi && p.Opsi.length) ? [...p.Opsi] : [],
                }));
                f._editIdx = null;
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
        showTypePicker(f) {
            if (!f._pertanyaan) f._pertanyaan = [];
            const key = 'q_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);
            f._pertanyaan.push({ _key: key, Tipe: 'RATING', Label: '', Skala_Min: 1, Skala_Max: 5, _opsiList: [] });
            f._editIdx = f._pertanyaan.length - 1;
            this.$nextTick(() => {
                const el = this.$el.querySelector('.fb-q-card--expanded');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },
        addQuestion(f) { this.showTypePicker(f); },
        onReorder(f) {
            if (!f._pertanyaan) return;
            f._pertanyaan.forEach((p, i) => { p.Urutan = i + 1; });
        },
        onTypeChange(p, newType) {
            p.Tipe = newType;
            this.ensureDefaults(p);
            if (['RADIO','CHECKBOX','DROPDOWN'].includes(newType)) {
                if (!p._opsiList || !p._opsiList.length) p._opsiList = ['', ''];
            }
        },
        ensureDefaults(p) {
            if (p.Tipe === 'LIKERT') {
                p.Skala_Min = 1; p.Skala_Max = 5; // Likert selalu 1-5
            } else if (['RATING','NPS'].includes(p.Tipe)) {
                if (p.Skala_Min == null) p.Skala_Min = this.defaultMin(p.Tipe);
                if (p.Skala_Max == null) p.Skala_Max = this.defaultMax(p.Tipe);
            }
            if (['RADIO','CHECKBOX','DROPDOWN'].includes(p.Tipe)) {
                if (!p._opsiList) p._opsiList = [];
            }
        },
        addOption(p) {
            if (!p._opsiList) p._opsiList = [];
            p._opsiList.push('');
        },
        removeOption(p, idx) {
            if (!p._opsiList) return;
            p._opsiList.splice(idx, 1);
        },
        removeQuestion(f, i) { f._pertanyaan.splice(i, 1); },
        async saveQuestions(f) {
            this.savingQ = { ...this.savingQ, [f.Id_Master_Feedback_Form]: true };
            const pertanyaan = (f._pertanyaan || []).map((p, i) => ({
                urutan: i + 1, tipe: p.Tipe, label: p.Label,
                skala_min: p.Skala_Min ?? null, skala_max: p.Skala_Max ?? null,
                opsi: (p._opsiList && p._opsiList.length) ? p._opsiList.filter(o => o.trim()) : null,
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
        typeBadgeClass(tipe) {
            const map = { RATING: 'wca-b--amber', NPS: 'wca-b--indigo', LIKERT: 'wca-b--indigo', TEXTAREA: 'wca-b--slate', RADIO: 'wca-b--green', CHECKBOX: 'wca-b--green', DROPDOWN: 'wca-b--slate' };
            return map[tipe] || 'wca-b--slate';
        },
        typeLabel(tipe) {
            const map = { RATING: 'Bintang 1-5', NPS: 'NPS 0-10', LIKERT: 'Likert', TEXTAREA: 'Teks Bebas', RADIO: 'Pilih Satu', CHECKBOX: 'Multi-Pilih', DROPDOWN: 'Dropdown' };
            return map[tipe] || tipe;
        },
        defaultMin(tipe) {
            const map = { RATING: 1, NPS: 0, LIKERT: 1 };
            return map[tipe] ?? 1;
        },
        defaultMax(tipe) {
            const map = { RATING: 5, NPS: 10, LIKERT: 5 };
            return map[tipe] ?? 5;
        },
        typeIcon(tipe) {
            const map = {
                RATING: '<i class="bi bi-star-fill"></i>',
                NPS: '<i class="bi bi-0-circle"></i>',
                LIKERT: '<i class="bi bi-ui-radios"></i>',
                TEXTAREA: '<i class="bi bi-text-paragraph"></i>',
                RADIO: '<i class="bi bi-record-circle"></i>',
                CHECKBOX: '<i class="bi bi-check2-square"></i>',
                DROPDOWN: '<i class="bi bi-chevron-down"></i>',
            };
            return map[tipe] || '<i class="bi bi-question-circle"></i>';
        },
        npsPreviewColor(n) {
            if (n <= 2) return 'rgba(239,68,68,0.85)';
            if (n <= 4) return 'rgba(239,68,68,0.55)';
            if (n <= 6) return 'rgba(245,158,11,0.55)';
            if (n <= 8) return 'rgba(34,197,94,0.55)';
            return 'rgba(34,197,94,0.85)';
        },
        npsRange(p) {
            const min = p.Skala_Min ?? 0;
            const max = p.Skala_Max ?? 10;
            const result = [];
            for (let i = min; i <= max; i++) result.push(i);
            return result;
        },
        npsDemo(p) {
            const max = p.Skala_Max ?? 10;
            return Math.floor(max * 0.7); // realistic demo: 70th percentile
        },
        rateDemo(p) {
            const max = p.Skala_Max || 5;
            return Math.ceil(max * 0.6); // realistic demo: 60% filled
        },
    },
};
</script>

<style scoped>
/* ── Radio Cards (Mode Tampilan) ── */
.wca-radio-cards { display: flex; gap: 12px; }
.wca-radio-card {
    flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 16px 12px; border: 2px solid var(--border-light, #e2e8f0); border-radius: 12px;
    cursor: pointer; transition: all 0.2s; text-align: center; background: #fff;
}
.wca-radio-card:hover { border-color: #a5b4fc; background: rgba(99,102,241,.03); }
.wca-radio-card.is-active { border-color: #6366f1; background: rgba(99,102,241,.06); box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
.wca-radio-card input[type="radio"] { display: none; }
.wca-radio-card__icon { font-size: 1.5rem; color: var(--muted,#94a3b8); line-height: 1; }
.wca-radio-card.is-active .wca-radio-card__icon { color: #6366f1; }
.wca-radio-card__label { font-size: .82rem; font-weight: 700; color: #334155; }
.wca-radio-card.is-active .wca-radio-card__label { color: #6366f1; }
.wca-radio-card__desc { font-size: .7rem; color: var(--muted,#94a3b8); line-height: 1.35; }

/* ── Status Card ── */
.wca-status-card {
    display: flex; align-items: center; gap: 14px; padding: 16px 18px;
    border: 1.5px solid var(--border-light,#e2e8f0); border-radius: 12px; background: #fff; width: 100%;
}
.wca-status-card--on { border-color: rgba(16,185,129,.3); background: rgba(16,185,129,.04); }
.wca-status-card--off { border-color: rgba(148,163,184,.25); background: rgba(148,163,184,.03); }
.wca-status-card__icon { font-size: 1.6rem; line-height: 1; }
.wca-status-card--on .wca-status-card__icon { color: #10b981; }
.wca-status-card--off .wca-status-card__icon { color: #94a3b8; }
.wca-status-card__body { flex: 1; }
.wca-status-card__body strong { display: block; font-size: .85rem; color: #1e293b; }
.wca-status-card__body small { display: block; font-size: .73rem; color: var(--muted,#94a3b8); margin-top: 2px; }

/* ── Empty Questions State ── */
.fb-empty-questions {
    text-align: center; padding: 40px 20px;
    background: linear-gradient(135deg, #f8f7ff 0%, #f0f0ff 100%);
    border: 2px dashed #c4b5fd; border-radius: 14px; margin-bottom: 8px;
}
.fb-empty-questions__icon { font-size: 2.5rem; color: #a78bfa; margin-bottom: 12px; }
.fb-empty-questions h4 { margin: 0 0 6px; font-size: 1rem; color: #4f46e5; }
.fb-empty-questions p { margin: 0; font-size: .82rem; color: #8b83a9; line-height: 1.5; }

/* ── Question Card ── */
.fb-q-card {
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px;
    margin-bottom: 10px; transition: all .2s; overflow: hidden;
}
.fb-q-card:hover { border-color: #c4b5fd; }
.fb-q-card--expanded { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,.08); }

/* Drag handle */
.fb-q-drag { cursor: grab; color: #cbd5e1; font-size: 1.1rem; padding: 0 4px; user-select: none; flex-shrink: 0; }
.fb-q-drag:hover { color: #6366f1; }
.fb-q-drag-inline { display: inline-flex; vertical-align: middle; color: #6366f1; }
.fb-q-ghost { opacity: 0.4; background: #f0f0ff; border: 2px dashed #6366f1; border-radius: 12px; }

/* ── Collapsed State ── */
.fb-q-collapsed {
    display: flex; align-items: center; gap: 10px; padding: 14px 16px; cursor: pointer;
    transition: background .15s;
}
.fb-q-collapsed:hover { background: rgba(99,102,241,.03); }
.fb-q-collapsed__num {
    width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg,#6366f1,#4f46e5);
    color: #fff; font-size: .75rem; font-weight: 700; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.fb-q-collapsed__icon { font-size: 1rem; color: #94a3b8; flex-shrink: 0; line-height: 1; }
.fb-q-collapsed__text { flex: 1; min-width: 0; }
.fb-q-collapsed__text strong { display: block; font-size: .84rem; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fb-q-collapsed__text small { display: block; font-size: .7rem; color: #94a3b8; margin-top: 1px; }
.fb-q-collapsed__edit, .fb-q-collapsed__del {
    border: none; background: none; cursor: pointer; font-size: .9rem; padding: 6px; border-radius: 6px;
    transition: all .15s; flex-shrink: 0;
}
.fb-q-collapsed__edit { color: #6366f1; }
.fb-q-collapsed__edit:hover { background: rgba(99,102,241,.1); }
.fb-q-collapsed__del { color: #94a3b8; }
.fb-q-collapsed__del:hover { background: rgba(239,68,68,.1); color: #ef4444; }

/* ── Expanded State ── */
.fb-q-expanded__head {
    display: flex; align-items: center; gap: 10px; padding: 12px 16px;
    background: linear-gradient(135deg, #f5f3ff, #ede9fe); border-bottom: 1px solid #ddd6fe;
}
.fb-q-expanded__num {
    width: 26px; height: 26px; border-radius: 7px; background: linear-gradient(135deg,#6366f1,#4f46e5);
    color: #fff; font-size: .73rem; font-weight: 700; display: flex; align-items: center; justify-content: center;
}
.fb-q-expanded__title { flex: 1; font-size: .82rem; font-weight: 700; color: #4f46e5; }
.fb-q-expanded__close {
    border: none; background: linear-gradient(135deg,#10b981,#059669); color: #fff;
    padding: 6px 14px; border-radius: 8px; font-size: .78rem; font-weight: 700; cursor: pointer;
    transition: opacity .15s;
}
.fb-q-expanded__close:hover { opacity: .9; }

.fb-q-expanded__body { display: flex; gap: 20px; padding: 20px; }

/* ── Editor (left) ── */
.fb-q-editor { flex: 3; min-width: 0; }

/* Type pills */
.fb-type-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.fb-type-pill {
    display: flex; align-items: center; gap: 6px; padding: 8px 14px;
    border: 1.5px solid #e2e8f0; border-radius: 10px; background: #fff;
    cursor: pointer; transition: all .15s; font-size: .78rem; font-weight: 600; color: #64748b;
}
.fb-type-pill:hover { border-color: #a5b4fc; }
.fb-type-pill--active {
    border-color: #6366f1; background: rgba(99,102,241,.08); color: #4f46e5;
    box-shadow: 0 2px 8px rgba(99,102,241,.15);
}
.fb-type-pill__icon { font-size: .95rem; line-height: 1; }
.fb-type-pill__label { white-space: nowrap; }

/* Scale row */
.fb-scale-row { margin-top: 18px; }
.fb-scale-inputs { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
.fb-scale-label { color: var(--muted); font-weight: 600; font-size: .82rem; }

/* ── Option Chips ── */
.fb-options-row { margin-top: 18px; }
.fb-option-chips { display: flex; flex-direction: column; gap: 8px; margin-top: 6px; }
.fb-option-chip {
    display: flex; align-items: center; gap: 8px;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 6px 8px 6px 6px; transition: border-color .15s;
}
.fb-option-chip:hover { border-color: #a5b4fc; }
.fb-option-chip__num {
    width: 24px; height: 24px; border-radius: 6px; background: #6366f1;
    color: #fff; font-size: .68rem; font-weight: 700; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0;
}
.fb-option-chip__del {
    border: none; background: none; color: #94a3b8; cursor: pointer;
    padding: 4px 6px; border-radius: 6px; font-size: .85rem; flex-shrink: 0; transition: all .15s;
}
.fb-option-chip__del:hover { background: rgba(239,68,68,.1); color: #ef4444; }
.fb-option-add {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 10px;
    border: 1.5px dashed #c4b5fd; border-radius: 10px; background: none;
    padding: 10px 18px; color: #6366f1; font-weight: 700; font-size: .82rem;
    cursor: pointer; transition: all .15s;
}
.fb-option-add:hover { background: rgba(99,102,241,.05); border-color: #6366f1; }

/* ── Preview Panel ── */
.fb-preview { flex: 2; min-width: 240px; }
.fb-preview__label {
    font-size: .68rem; font-weight: 800; letter-spacing: .1em; color: #a78bfa;
    text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
}
.fb-preview__label::before { content: '👁️'; font-size: .75rem; }
.fb-preview__card {
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 16px;
    padding: 24px 22px; min-height: 120px; box-shadow: 0 4px 20px rgba(0,0,0,.04);
}
.fb-preview__q { font-size: .9rem; font-weight: 600; color: #1e293b; margin-bottom: 18px; line-height: 1.5; }

/* ── Rating Preview ── */
.fb-preview-rate-wrap { text-align: center; }
.fb-preview-rate__stars { display: flex; gap: 6px; justify-content: center; }
.fb-preview-rate__star {
    font-size: 34px; line-height: 1; cursor: default; color: #cbd5e1; transition: none; user-select: none;
}
.fb-preview-rate__star--on { color: #f59e0b; }
.fb-preview-rate__range { margin-top: 8px; font-size: .72rem; color: #94a3b8; font-weight: 600; }

/* ── Likert Preview (matches LikertInput.vue) ── */
.fb-preview-likert-wrap { text-align: center; }
.fb-preview-likert__opts { display: flex; gap: 8px; justify-content: center; }
.fb-preview-likert__opt {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px;
    font-size: .8rem; font-weight: 600; color: #94a3b8; background: #fff;
    cursor: default; user-select: none; min-width: 44px;
}
.fb-preview-likert__opt--on {
    border-color: #6366f1; background: rgba(99,102,241,.06); color: #6366f1;
}
.fb-preview-likert__labels {
    display: flex; justify-content: space-between; margin-top: 8px;
    font-size: .68rem; color: #94a3b8;
}

/* ── NPS Preview ── */
.fb-preview-nps-wrap { text-align: center; }
.fb-preview-nps__btns { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
.fb-preview-nps__btn {
    width: 34px; height: 34px; border-radius: 8px; color: #fff; font-size: .8rem;
    font-weight: 700; display: flex; align-items: center; justify-content: center;
    opacity: 0.6; cursor: default; user-select: none;
}
.fb-preview-nps__btn--demo {
    opacity: 1; transform: scale(1.12); box-shadow: 0 3px 10px rgba(0,0,0,.2); position: relative; z-index: 1;
}
.fb-preview-nps__labels { display: flex; justify-content: space-between; margin-top: 8px; font-size: .7rem; color: #94a3b8; font-weight: 600; }

/* Textarea preview */
.fb-preview-ta__box {
    padding: 14px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px;
    font-size: .85rem; color: #94a3b8; min-height: 80px; background: #fafafa;
}
.fb-preview-ta__counter { text-align: right; font-size: .7rem; color: #cbd5e1; margin-top: 4px; }

/* ── Radio Preview (realistic cards with radio dot) ── */
.fb-pv-radio { display: flex; flex-direction: column; gap: 8px; }
.fb-pv-radio__card {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px;
    border: 2px solid #e2e8f0; border-radius: 12px; background: #fff;
    cursor: default; user-select: none; transition: none;
}
.fb-pv-radio__card--sel {
    border-color: #6366f1; background: rgba(99,102,241,.04);
}
.fb-pv-radio__dot {
    width: 20px; height: 20px; border-radius: 50%; border: 2px solid #cbd5e1;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.fb-pv-radio__card--sel .fb-pv-radio__dot { border-color: #6366f1; }
.fb-pv-radio__dot--fill {
    width: 10px; height: 10px; border-radius: 50%; background: #6366f1;
}
.fb-pv-radio__text { font-size: .88rem; color: #475569; }
.fb-pv-radio__card--sel .fb-pv-radio__text { color: #6366f1; font-weight: 600; }

/* ── Checkbox Preview (realistic cards with checkbox) ── */
.fb-pv-check { display: flex; flex-direction: column; gap: 8px; }
.fb-pv-check__card {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px;
    border: 2px solid #e2e8f0; border-radius: 12px; background: #fff;
    cursor: default; user-select: none; transition: none;
}
.fb-pv-check__card--sel {
    border-color: #6366f1; background: rgba(99,102,241,.04);
}
.fb-pv-check__box {
    width: 20px; height: 20px; border-radius: 5px; border: 2px solid #cbd5e1;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    font-size: .7rem;
}
.fb-pv-check__card--sel .fb-pv-check__box {
    border-color: #6366f1; background: #6366f1; color: #fff;
}
.fb-pv-check__text { font-size: .88rem; color: #475569; }
.fb-pv-check__card--sel .fb-pv-check__text { color: #6366f1; font-weight: 600; }
/* Dropdown preview — realistic select */
.fb-preview-dd__box {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; border: 1.5px solid #c4b5fd; border-radius: 10px;
    font-size: .85rem; color: #6366f1; background: #fff;
    box-shadow: 0 0 0 3px rgba(99,102,241,.1); cursor: default;
}
.fb-preview-dd__menu {
    margin-top: 6px; border: 1px solid #e2e8f0; border-radius: 10px;
    overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,.08);
}
.fb-preview-dd__item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 16px; font-size: .82rem; color: #475569; background: #fff;
    border-bottom: 1px solid #f1f5f9; cursor: default;
}
.fb-preview-dd__item:last-child { border-bottom: none; }
.fb-preview-dd__item--sel { color: #6366f1; background: rgba(99,102,241,.05); font-weight: 600; }
.fb-preview-dd__item--sel i { font-size: .8rem; }
.fb-preview__empty { font-size: .8rem; color: #cbd5e1; font-style: italic; padding: 8px 0; }

/* ── Footer ── */
.fb-q-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 0 0; border-top: 1px solid #e2e8f0; margin-top: 16px;
}
.fb-q-footer__info { font-size: .73rem; color: var(--muted); font-weight: 600; }
.fb-q-footer__btns { display: flex; gap: 10px; }
</style>
