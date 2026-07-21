<!-- WEB CAREER — Admin: Pembukaan Program (publikasi 1 program ke banyak channel: Umum/Kampus, tanpa duplikasi). -->
<template>
    <Head><title>Pembukaan Program - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Pembukaan Program</h1>
                <p>Publikasikan <b>1 program</b> ke banyak channel — <b>Umum</b> (landing publik) atau <b>Kampus</b> tertentu — masing-masing punya window sendiri. Tak perlu buat program berkali-kali.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Buka Program</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-megaphone"></i>
            <span><b>Umum</b> = tampil di landing publik, siapa saja apply (form universitas bebas). <b>Kampus</b> = ditargetkan ke kampus tertentu — daftar kampus jadi <b>whitelist</b> pilihan universitas di form pelamar. MT dibuka <b>per batch</b>. Untuk umum + kampus: buat <b>dua pembukaan</b> (program/batch sama). Program <b>Selesai</b> tak bisa dibuka.</span>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2"><i class="bi bi-search"></i><input v-model="q" type="text" placeholder="Cari program…" /></div>
            <div class="wca-chips">
                <button class="wca-chip" :class="{ active: ch === '' }" @click="ch = ''">Semua</button>
                <button class="wca-chip" :class="{ active: ch === 'UMUM' }" @click="ch = 'UMUM'">Umum</button>
                <button class="wca-chip" :class="{ active: ch === 'KAMPUS' }" @click="ch = 'KAMPUS'">Kampus</button>
            </div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead><tr><th>Program</th><th>Channel</th><th>Masa Berlaku</th><th>Pelamar</th><th>Publish</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="b in filtered" :key="b.id">
                                <td>
                                    <strong>{{ b.programNama }}</strong>
                                    <span class="wca-badge" :class="katBadge(b.kategori)" style="margin-left:.3rem">{{ katShort(b.kategori) }}</span>
                                    <br /><small style="color:var(--muted);font-weight:700">{{ b.id }}</small>
                                    <span v-if="b.batchNama" class="wca-badge wca-b--slate" style="margin-left:.35rem"><i class="bi bi-collection"></i> {{ b.batchNama }}</span>
                                </td>
                                <td>
                                    <span class="wca-badge" :class="b.channel === 'KAMPUS' ? 'wca-b--indigo' : 'wca-b--green'"><i class="bi" :class="b.channel === 'KAMPUS' ? 'bi-mortarboard' : 'bi-globe'"></i> {{ b.channel === 'KAMPUS' ? 'Kampus' : 'Umum' }}</span>
                                    <div v-if="b.channel === 'KAMPUS' && b.kampus.length" style="margin-top:.35rem;font-size:.74rem;font-weight:700;color:var(--muted)">{{ b.kampus.join(' · ') }}</div>
                                </td>
                                <td>
                                    <span class="wca-badge" :class="b.masaBerlaku === 'EVERGREEN' ? 'wca-b--green' : 'wca-b--slate'">{{ b.masaBerlaku === 'EVERGREEN' ? 'Evergreen' : 'Berbatas' }}</span>
                                    <div style="margin-top:.3rem;font-size:.76rem;font-weight:700;color:var(--slate)">{{ windowLabel(b) }}</div>
                                </td>
                                <td><strong>{{ b.pelamar }}</strong></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.45rem">
                                        <el-switch :model-value="b.statusPublish === 'TERBIT'" @change="(v) => setPublish(b, v)" />
                                        <span style="font-size:.74rem;font-weight:800" :style="{ color: b.statusPublish === 'TERBIT' ? '#059669' : 'var(--muted)' }">{{ b.statusPublish === 'TERBIT' ? 'Terbit' : 'Draft' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(b)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="remove(b)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length"><td colspan="6"><div class="wca-empty"><i class="bi bi-megaphone"></i><h4>Belum ada pembukaan</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal buka/edit -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Pembukaan' : 'Buka Program'" subtitle="Publikasikan program ke sebuah channel + window" icon="bi-megaphone" :save-label="editingId ? 'Perbarui' : 'Buka'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-megaphone"></i> Detail Pembukaan</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Program</label>
                        <el-select v-model="form.programId" placeholder="Pilih program" @change="onProgram">
                            <el-option v-for="p in programOptions" :key="p.id" :label="`${p.nama} (${p.kategori})`" :value="p.id" />
                        </el-select>
                    </div>
                    <div v-if="isMTsel"><label class="wca-field-lbl">Batch / Gelombang</label>
                        <el-select v-model="form.batchId" placeholder="Pilih batch yang dibuka" style="width:100%">
                            <el-option v-for="b in batchOptions" :key="b.id" :label="b.nama" :value="b.id" />
                        </el-select>
                        <small v-if="!batchOptions.length" style="display:block;margin-top:.3rem;color:#b45309;font-weight:700;font-size:.72rem"><i class="bi bi-exclamation-triangle"></i> Program MT ini belum punya batch — tambahkan dulu di Program Kegiatan.</small>
                        <small v-else style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem"><i class="bi bi-info-circle"></i> Tiap gelombang MT dibuka terpisah — pilih batch mana yang pembukaan ini tayangkan.</small>
                    </div>
                    <div><label class="wca-field-lbl">Channel / Sumber</label>
                        <el-select v-model="form.channel" placeholder="Pilih channel" @change="onChannel">
                            <el-option v-for="s in sumberOptions" :key="s.kode" :label="s.nama" :value="s.kode" />
                            <el-option v-if="!editingId" label="Umum + Kampus (buat 2 pembukaan sekaligus)" value="KEDUANYA" />
                        </el-select>
                        <small v-if="channelDesc" style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem"><i class="bi bi-info-circle"></i> {{ channelDesc }}</small>
                    </div>
                    <div v-if="needsKampus"><label class="wca-field-lbl">Kampus Sasaran (whitelist)</label>
                        <el-select v-model="form.kampus" multiple placeholder="Pilih kampus" style="width:100%">
                            <el-option v-for="u in kampusAvail" :key="u" :label="u" :value="u" />
                        </el-select>
                        <small v-if="isInternshipSel" style="display:block;margin-top:.3rem;color:#4338ca;font-weight:700;font-size:.72rem"><i class="bi bi-shield-check"></i> Internship: hanya kampus dengan <b>MoU aktif</b> yang bisa dipilih (dari Master Kemitraan).</small>
                        <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem"><i class="bi bi-list-check"></i> Daftar ini jadi <b>whitelist formulir pelamar</b>: pilihan universitas di form dikunci ke kampus sasaran ini.</small>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Masa Berlaku</label>
                            <el-select v-model="form.masaBerlaku" placeholder="Pilih">
                                <el-option label="Berbatas (ada tanggal tutup)" value="BERBATAS" />
                                <el-option label="Evergreen (tanpa tanggal)" value="EVERGREEN" />
                            </el-select>
                        </div>
                        <div><label class="wca-field-lbl">Tanggal Buka</label><el-date-picker v-model="form.buka" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" /></div>
                        <div v-if="form.masaBerlaku === 'BERBATAS'"><label class="wca-field-lbl">Tanggal Tutup</label><el-date-picker v-model="form.tutup" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" /></div>
                    </div>
                    <div class="wca-flow__note" style="margin:0" v-if="errs.length"><i class="bi bi-exclamation-triangle-fill" style="color:#dc2626"></i> <span style="color:#b45309">{{ errs.join(' · ') }}</span></div>
                </div>
            </div>
            <template #footer>
                <button class="wca-btn wca-btn--ghost" @click="show = false"><i class="bi bi-x-circle"></i> Batal</button>
                <button class="wca-btn wca-btn--dark" :disabled="errs.length > 0" @click="save"><i class="bi bi-check-circle-fill"></i> {{ editingId ? 'Perbarui' : 'Buka' }}</button>
            </template>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AdminModal from '@career/AdminModal.vue';

const props = defineProps({
    pembukaan: { type: Array, default: () => [] },
    programOptions: { type: Array, default: () => [] },
    kampusOptions: { type: Array, default: () => [] },
    kampusMou: { type: Array, default: () => [] },
    sumberOptions: { type: Array, default: () => [] },
});
const channelDesc = computed(() => {
    if (form.channel === 'KEDUANYA') return 'Membuat DUA pembukaan sekaligus: satu Umum (landing publik) + satu Kampus (whitelist), program/batch/window sama.';
    return props.sumberOptions.find((s) => s.kode === form.channel)?.deskripsi ?? '';
});
const needsKampus = computed(() => form.channel === 'KAMPUS' || form.channel === 'KEDUANYA');

function katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--sky'; }
function katShort(k) { return { MT: 'MT', INTERNSHIP: 'MAGANG', REKRUTMEN: 'REK' }[k] || 'REK'; }

const list = reactive(props.pembukaan.map((b) => ({ ...b, kampus: [...b.kampus] })));
const q = ref('');
const ch = ref('');
const filtered = computed(() => {
    const s = q.value.trim().toLowerCase();
    return list.filter((b) => {
        if (ch.value && b.channel !== ch.value) return false;
        if (!s) return true;
        return b.programNama.toLowerCase().includes(s);
    });
});

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmt(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
}
function windowLabel(b) { return b.masaBerlaku === 'EVERGREEN' ? 'Dibuka: ' + fmt(b.buka) + ' — tanpa batas' : `${fmt(b.buka)} – ${fmt(b.tutup)}`; }

const show = ref(false);
const editingId = ref(null);
const form = reactive({ programId: '', batchId: '', channel: 'UMUM', kampus: [], masaBerlaku: 'BERBATAS', buka: '', tutup: '' });

const selectedProgram = computed(() => props.programOptions.find((p) => p.id === form.programId) || null);
const isMTsel = computed(() => selectedProgram.value?.kategori === 'MT');
const isInternshipSel = computed(() => selectedProgram.value?.kategori === 'INTERNSHIP');
const batchOptions = computed(() => selectedProgram.value?.batch ?? []);
// Whitelist kampus: Internship → hanya ber-MoU aktif; lainnya → semua kampus aktif.
const kampusAvail = computed(() => (isInternshipSel.value ? props.kampusOptions.filter((u) => props.kampusMou.includes(u)) : props.kampusOptions));

const errs = computed(() => {
    const e = [];
    if (!form.programId) e.push('Pilih program');
    if (isMTsel.value && !form.batchId) e.push('MT — pilih batch');
    if (needsKampus.value) {
        if (!form.kampus.length) e.push('Channel Kampus — pilih minimal 1 kampus');
        else if (form.kampus.some((u) => !kampusAvail.value.includes(u))) e.push('Ada kampus tak valid (nonaktif / tanpa MoU)');
    }
    if (form.channel === 'UMUM' && form.kampus.length) e.push('Channel Umum tak boleh punya kampus sasaran');
    if (!form.buka) e.push('Isi tanggal buka');
    if (form.masaBerlaku === 'BERBATAS') {
        if (!form.tutup) e.push('Isi tanggal tutup');
        else if (form.buka && form.buka > form.tutup) e.push('Buka harus sebelum tutup');
    }
    return e;
});

function onProgram() { form.batchId = ''; form.kampus = []; } // reset saat ganti program
function onChannel() { if (form.channel === 'UMUM') form.kampus = []; } // UMUM → tak boleh ada kampus

function reset() {
    Object.assign(form, { programId: props.programOptions[0]?.id ?? '', batchId: '', channel: 'UMUM', kampus: [], masaBerlaku: 'BERBATAS', buka: '', tutup: '' });
}
function openCreate() {
    editingId.value = null;
    reset();
    show.value = true;
}
function openEdit(b) {
    editingId.value = b.id;
    Object.assign(form, { programId: b.programId, batchId: b.batchId || '', channel: b.channel, kampus: [...b.kampus], masaBerlaku: b.masaBerlaku, buka: b.buka || '', tutup: b.tutup || '' });
    show.value = true;
}
function save() {
    if (errs.value.length) return;
    const prog = props.programOptions.find((p) => p.id === form.programId);
    const batch = batchOptions.value.find((x) => x.id === form.batchId);
    const base = {
        programId: form.programId, programNama: prog?.nama ?? 'Program', kategori: prog?.kategori ?? 'REKRUTMEN',
        batchId: isMTsel.value ? form.batchId : null, batchNama: isMTsel.value ? (batch?.nama ?? null) : null,
        masaBerlaku: form.masaBerlaku, buka: form.buka, tutup: form.masaBerlaku === 'EVERGREEN' ? null : form.tutup,
    };
    // Umum + Kampus → buat DUA pembukaan sekaligus (program/batch/window sama).
    if (form.channel === 'KEDUANYA') {
        const n = list.length;
        list.unshift({ id: 'PUB-' + (n + 2), ...base, channel: 'KAMPUS', kampus: [...form.kampus], statusPublish: 'DRAFT', pelamar: 0 });
        list.unshift({ id: 'PUB-' + (n + 1), ...base, channel: 'UMUM', kampus: [], statusPublish: 'DRAFT', pelamar: 0 });
        notice('Dua pembukaan dibuat: Umum + Kampus — set Terbit agar tampil (demo dummy).');
        show.value = false;
        return;
    }
    const payload = { ...base, channel: form.channel, kampus: form.channel === 'KAMPUS' ? [...form.kampus] : [] };
    if (editingId.value) {
        const row = list.find((x) => x.id === editingId.value);
        if (row) Object.assign(row, payload);
        notice('Pembukaan diperbarui (demo dummy).');
    } else {
        list.unshift({ id: 'PUB-' + (list.length + 1), ...payload, statusPublish: 'DRAFT', pelamar: 0 });
        notice('Program dibuka — set Terbit agar tampil (demo dummy).');
    }
    show.value = false;
}
function setPublish(b, v) {
    b.statusPublish = v ? 'TERBIT' : 'DRAFT';
    notice(`Pembukaan ${v ? 'diterbitkan' : 'dijadikan draft'} (demo dummy).`);
}
function remove(b) {
    const i = list.findIndex((x) => x.id === b.id);
    if (i >= 0) list.splice(i, 1);
    notice('Pembukaan dihapus (demo dummy).');
}

const toast = ref('');
let tm = null;
function notice(m) {
    toast.value = m;
    if (tm) clearTimeout(tm);
    tm = setTimeout(() => (toast.value = ''), 3000);
}
</script>
