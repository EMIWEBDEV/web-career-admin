<!-- WEB CAREER — Admin: Master Alur Seleksi (flow builder; third-party = keputusan sistem) -->
<template>
    <Head><title>Master Tahapan Seleksi - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Tahapan Seleksi</h1>
                <p>Susun urutan tahap seleksi (alur) per kategori. Tahap <b>pihak ke-3</b> keputusannya otomatis sistem — tak bisa loloskan/tolak manual di worklist. Tahap "Tes Online" mengambil dari <b>Master Jenis Tes</b>.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Alur Baru</button>
            </div>
        </div>

        <div class="wca-legend">
            <span><i class="bi bi-person-workspace" style="color:var(--indigo)"></i> Internal — keputusan admin (loloskan/tolak)</span>
            <span><i class="bi bi-robot" style="color:#d97706"></i> Pihak ke-3 — konfirmasi otomatis sistem</span>
        </div>

        <div class="wca-acc">
            <div v-for="a in list" :key="a.id" class="wca-acc__item" :class="{ open: open === a.id }">
                <button class="wca-acc__head" @click="toggle(a.id)">
                    <span class="wca-acc__chev"><i class="bi bi-chevron-right"></i></span>
                    <span class="wca-acc__title">
                        <strong>{{ a.nama }}</strong>
                        <small>{{ a.id }} · {{ a.deskripsi }}</small>
                    </span>
                    <span class="wca-acc__tags">
                        <span class="wca-badge" :class="{ 'wca-b--gold': a.kategori === 'MT', 'wca-b--green': a.kategori === 'INTERNSHIP', 'wca-b--sky': a.kategori === 'REKRUTMEN' }">{{ a.kategori }}</span>
                        <span class="wca-badge" :class="statusBadge(a.status)">{{ a.status }}</span>
                    </span>
                    <span class="wca-acc__kpi"><b>{{ a.stages.length }}</b><small>tahap</small></span>
                </button>

                <div class="wca-acc__body">
                    <div class="wca-acc__inner">
                        <ol class="wca-flow">
                            <li v-for="(s, i) in a.stages" :key="i" class="wca-flow__step" :class="{ 'is-system': s.decision === 'SYSTEM' }">
                                <span class="wca-flow__num">{{ i + 1 }}</span>
                                <div class="wca-flow__card">
                                    <div class="wca-flow__top">
                                        <strong>{{ s.label }}</strong>
                                        <span class="wca-badge" :class="s.provider === 'THIRD_PARTY' ? 'wca-b--amber' : 'wca-b--indigo'">
                                            <i class="bi" :class="s.provider === 'THIRD_PARTY' ? 'bi-robot' : 'bi-person-workspace'"></i>
                                            {{ s.provider === 'THIRD_PARTY' ? 'Pihak ke-3' : 'Internal' }}
                                        </span>
                                    </div>
                                    <div class="wca-flow__meta">
                                        <span><i class="bi bi-tag"></i> {{ tipeLabel(s.tipe) }}</span>
                                        <span v-if="s.formulirId"><i class="bi bi-input-cursor-text"></i> {{ formLabel(s.formulirId) }}</span>
                                        <span><i class="bi bi-hourglass-split"></i> SLA {{ s.sla }}</span>
                                        <span class="wca-flow__dec" :class="s.decision === 'SYSTEM' ? 'sys' : 'man'">
                                            <i class="bi" :class="s.decision === 'SYSTEM' ? 'bi-cpu' : 'bi-hand-index-thumb'"></i>
                                            {{ s.decision === 'SYSTEM' ? 'Konfirmasi Sistem' : 'Keputusan Admin' }}
                                        </span>
                                    </div>
                                    <p v-if="s.decision === 'SYSTEM'" class="wca-flow__note">
                                        <i class="bi bi-info-circle"></i> Hasil lolos/gagal masuk otomatis dari penyedia tes. Admin di worklist hanya memantau, tidak bisa terima/tolak manual.
                                    </p>
                                </div>
                            </li>
                        </ol>

                        <div class="wca-acc__foot">
                            <button class="wca-btn wca-btn--soft wca-btn--sm" @click="openStage(a)"><i class="bi bi-plus-circle"></i> Tambah Tahap</button>
                            <button class="wca-btn wca-btn--ghost wca-btn--sm" @click="notice('Ubah alur (demo dummy).')"><i class="bi bi-pencil"></i> Ubah Alur</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal alur baru — builder (kategori → identitas → susun tahapan inline) -->
        <AdminModal :show="showCreate" title="Buat Alur Seleksi" subtitle="Pilih kategori & identitas, lalu susun tahapan langsung (klik Tambah Tahap)." icon="bi-signpost-split" lg save-label="Simpan Alur" @close="showCreate = false" @save="saveCreate">
            <!-- 1. Detail -->
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-signpost-split"></i> Detail Alur</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Untuk Kategori (kelompok)</label>
                        <RefSelect type="talent" v-model="form.kategori" placeholder="Pilih kelompok" />
                        <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem">Menentukan alur ini muncul untuk kategori mana saat buat program.</small>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Alur</label><el-input v-model="form.nama" placeholder="Alur Rekrutmen Ekspres" /></div>
                        <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" placeholder="Ringkasan singkat" /></div>
                    </div>
                </div>
            </div>

            <!-- 2. Susun tahapan -->
            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-list-ol"></i> Susun Tahapan ({{ form.stages.length }})</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addFormStage"><i class="bi bi-plus-circle"></i> Tambah Tahap</button>
                </div>
                <div v-if="!form.stages.length" class="wca-hint" style="margin:0 0 .6rem"><i class="bi bi-info-circle"></i> Belum ada tahap. Klik <b>Tambah Tahap</b> untuk mulai menyusun urutan seleksi.</div>

                <div v-for="(s, i) in form.stages" :key="i" class="wca-stagecard">
                    <div class="wca-stagecard__num">{{ i + 1 }}</div>
                    <div class="wca-stagecard__body">
                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">Nama Tahap</label><el-input v-model="s.label" placeholder="mis. Psikotes Online" /></div>
                            <div><label class="wca-field-lbl">Tipe</label>
                                <RefSelect type="tipe" v-model="s.tipe" placeholder="Tipe" />
                            </div>
                        </div>
                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">Penyedia</label>
                                <RefSelect type="provider" v-model="s.provider" placeholder="Penyedia" />
                            </div>
                            <div v-if="s.provider === 'THIRD_PARTY'"><label class="wca-field-lbl">Jenis Tes (CAT)</label>
                                <RefSelect type="tes" v-model="s.tesId" placeholder="Pilih tes" />
                            </div>
                            <div v-if="s.tipe === 'FORM' || s.tipe === 'DOCUMENT'"><label class="wca-field-lbl">Formulir yang Diisi</label>
                                <RefSelect type="formulir" v-model="s.formulirId" placeholder="Pilih formulir (Master Formulir)" clearable />
                            </div>
                        </div>
                        <span class="wca-flow__dec" :class="s.provider === 'THIRD_PARTY' ? 'sys' : 'man'" style="align-self:flex-start">
                            <i class="bi" :class="s.provider === 'THIRD_PARTY' ? 'bi-cpu' : 'bi-hand-index-thumb'"></i>
                            {{ s.provider === 'THIRD_PARTY' ? 'Konfirmasi Sistem' : 'Keputusan Admin' }}
                        </span>
                    </div>
                    <div class="wca-stagecard__actions">
                        <button class="wca-iconbtn" title="Naik" :disabled="i === 0" @click="moveFormStage(i, -1)"><i class="bi bi-chevron-up"></i></button>
                        <button class="wca-iconbtn" title="Turun" :disabled="i === form.stages.length - 1" @click="moveFormStage(i, 1)"><i class="bi bi-chevron-down"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="removeFormStage(i)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <button class="wca-addrow" type="button" @click="addFormStage"><i class="bi bi-plus-circle"></i> Tambah Tahap</button>
            </div>
        </AdminModal>

        <!-- Modal tambah tahap -->
        <AdminModal :show="showStage" title="Tambah Tahap Seleksi" subtitle="Pilih penyedia — pihak ke-3 otomatis jadi keputusan sistem" icon="bi-diagram-2" save-label="Tambah Tahap" @close="showStage = false" @save="saveStage">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-diagram-2"></i> Tahap Baru</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Nama Tahap</label><el-input v-model="stage.label" placeholder="Tes Bahasa Inggris" /></div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Tipe</label>
                            <RefSelect type="tipe" v-model="stage.tipe" placeholder="Pilih tipe" />
                        </div>
                        <div><label class="wca-field-lbl">Penyedia</label>
                            <RefSelect type="provider" v-model="stage.provider" placeholder="Pilih penyedia" />
                        </div>
                    </div>
                    <div v-if="stage.provider === 'THIRD_PARTY'"><label class="wca-field-lbl">Jenis Tes (pihak ke-3)</label>
                        <RefSelect type="tes" v-model="stage.tesId" placeholder="Pilih jenis tes" />
                    </div>
                    <div v-if="stage.tipe === 'FORM' || stage.tipe === 'DOCUMENT'"><label class="wca-field-lbl">Formulir yang Diisi</label>
                        <RefSelect type="formulir" v-model="stage.formulirId" placeholder="Pilih formulir (Master Formulir)" clearable />
                    </div>
                    <div class="wca-flow__note" style="margin:0">
                        <i class="bi bi-info-circle"></i>
                        Keputusan: <b>{{ stage.provider === 'THIRD_PARTY' ? 'Konfirmasi Sistem (otomatis)' : 'Keputusan Admin (manual)' }}</b>
                    </div>
                </div>
            </div>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AdminModal from '@career/AdminModal.vue';
import RefSelect from '@career/RefSelect.vue';
import { statusBadge } from './careerAdmin';

const props = defineProps({
    alur: { type: Array, default: () => [] },
    tesOptions: { type: Array, default: () => [] },
    tipeOptions: { type: Array, default: () => [] },
    formulirOptions: { type: Array, default: () => [] },
});

function formLabel(id) { return props.formulirOptions.find((f) => f.id === id)?.nama || id; }

const list = reactive(props.alur.map((a) => ({ ...a, stages: a.stages.map((s) => ({ ...s })) })));
const open = ref(list[0]?.id ?? null);
function toggle(id) {
    open.value = open.value === id ? null : id;
}

function tipeLabel(t) {
    return (
        props.tipeOptions.find((o) => o.kode === t)?.nama ||
        { FORM: 'Formulir', ADMIN_SCREENING: 'Screening', HCLEARN_TEST: 'Tes Online', INTERVIEW: 'Wawancara', DOCUMENT: 'Dokumen', OFFERING: 'Penawaran' }[t] ||
        t
    );
}

// Alur baru — builder (susun tahapan inline, multiple)
const showCreate = ref(false);
const form = reactive({ nama: '', kategori: 'REKRUTMEN', deskripsi: '', stages: [] });
function newStage(preset = {}) {
    return { label: '', tipe: 'INTERVIEW', provider: 'INTERNAL', tesId: props.tesOptions[0]?.id ?? '', formulirId: '', ...preset };
}
function openCreate() {
    Object.assign(form, {
        nama: '', kategori: 'REKRUTMEN', deskripsi: '',
        // mulai dengan tahap pendaftaran (bisa dihapus/diubah)
        stages: [newStage({ label: 'Pendaftaran & Berkas', tipe: 'FORM' })],
    });
    showCreate.value = true;
}
function addFormStage() {
    form.stages.push(newStage());
}
function removeFormStage(i) {
    form.stages.splice(i, 1);
}
function moveFormStage(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= form.stages.length) return;
    const arr = form.stages;
    [arr[i], arr[j]] = [arr[j], arr[i]];
}
function saveCreate() {
    const id = 'ALR-' + (list.length + 1);
    const stages = form.stages.map((s) => ({
        key: 'CUSTOM', label: s.label || 'Tahap', tipe: s.tipe, provider: s.provider,
        decision: s.provider === 'THIRD_PARTY' ? 'SYSTEM' : 'MANUAL',
        tesId: s.provider === 'THIRD_PARTY' ? s.tesId : null,
        formulirId: (s.tipe === 'FORM' || s.tipe === 'DOCUMENT') ? (s.formulirId || null) : null,
        sla: s.provider === 'THIRD_PARTY' ? 'Auto' : '3 hari',
    }));
    list.unshift({
        id, nama: form.nama || 'Alur Baru', kategori: form.kategori, status: 'AKTIF', deskripsi: form.deskripsi || '—',
        stages: stages.length ? stages : [{ key: 'FORM', label: 'Pendaftaran & Berkas', tipe: 'FORM', provider: 'INTERNAL', decision: 'MANUAL', sla: '—' }],
    });
    notice(`Alur "${form.nama || 'baru'}" dibuat dengan ${stages.length} tahap (demo dummy).`);
    showCreate.value = false;
    open.value = id;
}

// Tambah tahap
const showStage = ref(false);
let stageTarget = null;
const stage = reactive({ label: '', tipe: 'HCLEARN_TEST', provider: 'INTERNAL', tesId: '', formulirId: '' });
function openStage(a) {
    stageTarget = a;
    Object.assign(stage, { label: '', tipe: 'HCLEARN_TEST', provider: 'INTERNAL', tesId: props.tesOptions[0]?.id ?? '', formulirId: '' });
    showStage.value = true;
}
function saveStage() {
    if (stageTarget) {
        stageTarget.stages.push({
            key: 'CUSTOM', label: stage.label || 'Tahap Baru', tipe: stage.tipe,
            provider: stage.provider, decision: stage.provider === 'THIRD_PARTY' ? 'SYSTEM' : 'MANUAL',
            tesId: stage.provider === 'THIRD_PARTY' ? stage.tesId : null,
            formulirId: (stage.tipe === 'FORM' || stage.tipe === 'DOCUMENT') ? (stage.formulirId || null) : null,
            sla: stage.provider === 'THIRD_PARTY' ? 'Auto' : '3 hari',
        });
    }
    notice('Tahap ditambahkan (demo dummy).');
    showStage.value = false;
}

const toast = ref('');
let t = null;
function notice(m) {
    toast.value = m;
    if (t) clearTimeout(t);
    t = setTimeout(() => (toast.value = ''), 3000);
}
</script>
