<!-- WEB CAREER — Master Jadwal Kegiatan (induk-detail: Jadwal + Agenda). DATA dari DB via /api/v1/master-jadwal. -->
<template>
    <Head><title>Master Jadwal Kegiatan - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Jadwal Kegiatan</h1>
                <p>Timeline satu gelombang: aktivitas pendukung (rapat, campaign, evaluasi) <b>+ tahapan seleksi</b>. Tiap agenda bertanggal.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Jadwal Baru</button>
            </div>
        </div>

        <div v-loading="loading" class="wca-acc">
            <div v-for="j in list" :key="j.id" class="wca-acc__item" :class="{ open: open === j.id }">
                <button class="wca-acc__head" @click="open = (open === j.id ? null : j.id)">
                    <span class="wca-acc__chev"><i class="bi bi-chevron-right"></i></span>
                    <span class="wca-acc__title">
                        <strong>{{ j.kegiatan }}</strong>
                        <small>{{ j.kode }} · Alur {{ j.alurNama || j.alur || '—' }}</small>
                    </span>
                    <span class="wca-acc__tags">
                        <span class="wca-badge wca-b--indigo">{{ katLabel(j.kategori) }}</span>
                        <span class="wca-badge" :class="j.status === 'AKTIF' ? 'wca-b--green' : 'wca-b--slate'">{{ j.status }}</span>
                        <span class="wca-badge wca-b--slate">{{ j.agenda.length }} agenda</span>
                    </span>
                    <span class="jdw-head-act" @click.stop>
                        <el-switch :model-value="j.status === 'AKTIF'" @change="(v) => setStatus(j, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(j)"><i class="bi bi-pencil"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(j)"><i class="bi bi-trash"></i></button>
                    </span>
                </button>
                <div class="wca-acc__body">
                    <div class="wca-acc__inner">
                        <div class="jdw-meta"><AuditStamp :by="j.createdBy" :at="j.createdAt" /></div>
                        <ol class="wca-jlist">
                            <li v-for="(a, i) in j.agenda" :key="i">
                                <span class="wca-jtag" :style="{ color: jenisWarna(a.jenis), background: jenisWarna(a.jenis) + '1a' }"><i class="bi" :class="jenisIkon(a.jenis)"></i> {{ jenisLabel(a.jenis) }}</span>
                                <span class="wca-jlist__lbl">{{ a.label }}</span>
                                <span class="wca-jlist__date"><i class="bi bi-calendar3"></i> {{ fmt(a.mulai) }} – {{ fmt(a.selesai) }}</span>
                            </li>
                            <li v-if="!j.agenda.length" class="jdw-empty-agenda">Belum ada agenda.</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty"><i class="bi bi-calendar3-range"></i><h4>Belum ada jadwal</h4></div>
        </div>

        <!-- Modal buat/ubah jadwal -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Jadwal' : 'Buat Jadwal Kegiatan'" subtitle="Isi identitas jadwal lalu susun agenda (rapat/campaign/seleksi/evaluasi)" icon="bi-calendar3-range" lg :save-label="editingId ? 'Perbarui' : 'Simpan Jadwal'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-info-circle"></i> Identitas Jadwal</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Kegiatan</label><el-input v-model="form.kegiatan" placeholder="mis. Rekrutmen Reguler Q3" /></div>
                        <div><label class="wca-field-lbl">Kategori</label><RefSelect type="talent" v-model="form.kategori" placeholder="Pilih kategori" /></div>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Alur Seleksi</label><RefSelect type="alur" v-model="form.alur" placeholder="Pilih alur" clearable /></div>
                    </div>
                    <!-- Status tidak ditanyakan: jadwal yang baru dibuat pasti aktif,
                         dan mematikannya sudah tersedia lewat toggle di daftar. -->
                    <div class="mjd-statusnote">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Jadwal langsung <strong>Aktif</strong> setelah dibuat. Nonaktifkan lewat toggle di daftar bila perlu.</span>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-list-ol"></i> Agenda ({{ form.agenda.length }})</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addRow"><i class="bi bi-plus-circle"></i> Tambah Agenda</button>
                </div>
                <div class="jdw-agenda">
                    <div v-for="(s, i) in form.agenda" :key="i" class="jdw-arow">
                        <span class="jdw-arow__num">{{ i + 1 }}</span>
                        <div class="jdw-arow__grid">
                            <!-- Tiap field col-6 (2 per baris) — lebih lega daripada 4 berdesakan. -->
                            <div class="jdw-col6">
                                <label class="wca-field-lbl">Jenis</label>
                                <el-select filterable v-model="s.jenis" placeholder="Pilih jenis agenda" style="width:100%">
                                    <el-option v-for="o in jenisOptions" :key="o.value" :label="o.label" :value="o.value">
                                        <div class="jdw-opt">
                                            <span class="jdw-opt__ic" :style="{ color: o.warna }"><i class="bi" :class="o.ikon"></i></span>
                                            <span class="jdw-opt__txt">
                                                <strong>{{ o.label }}</strong>
                                                <small>{{ o.deskripsi }}</small>
                                            </span>
                                        </div>
                                    </el-option>
                                </el-select>
                                <div v-if="jenisInfo(s.jenis)" class="jdw-hint">
                                    <i class="bi" :class="jenisInfo(s.jenis).ikon" :style="{ color: jenisInfo(s.jenis).warna }"></i>
                                    {{ jenisInfo(s.jenis).deskripsi }}
                                </div>
                            </div>
                            <div class="jdw-col6"><label class="wca-field-lbl">Label</label><el-input v-model="s.label" placeholder="mis. Psikotes Online" /></div>
                            <div class="jdw-col6"><label class="wca-field-lbl">Mulai</label><el-date-picker v-model="s.mulai" type="date" value-format="YYYY-MM-DD" placeholder="Mulai" style="width:100%" /></div>
                            <div class="jdw-col6"><label class="wca-field-lbl">Selesai</label><el-date-picker v-model="s.selesai" type="date" value-format="YYYY-MM-DD" placeholder="Selesai" style="width:100%" /></div>
                        </div>
                        <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus agenda" @click="removeRow(i)"><i class="bi bi-trash"></i></button>
                    </div>
                    <div v-if="!form.agenda.length" class="jdw-empty-agenda" style="padding:.8rem">Belum ada agenda — klik "Tambah Agenda".</div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Jadwal" :busy="deleting" confirm-label="Ya, Hapus" note="Jadwal & seluruh agenda-nya akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus jadwal <strong>{{ delTarget?.kegiatan }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import RefSelect from '@career/RefSelect.vue';

const API = '/api/v1/master-jadwal';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    data() {
        return {
            list: [],
            loading: false,
            open: null,
            show: false,
            editingId: null,
            form: { kegiatan: '', kategori: '', alur: '', agenda: [] },
            // Jenis agenda dimuat dari DB (satu sumber: label + deskripsi + ikon + warna),
            // bukan lagi dua peta hardcode yang tercecer di sini.
            jenisOptions: [],
            delShow: false,
            delTarget: null,
            deleting: false,
            saving: false,
            toast: '',
            tm: null,
        };
    },
    mounted() {
        this.load();
        this.loadJenis();
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k; },
        async loadJenis() {
            try {
                const res = await axios.get('/api/v1/karir/options/jenis-agenda', CFG);
                this.jenisOptions = res.data.result || [];
            } catch (e) {
                this.jenisOptions = [];
            }
        },
        jenisInfo(j) { return this.jenisOptions.find((o) => o.value === j) || null; },
        jenisLabel(j) { return this.jenisInfo(j)?.label || j; },
        jenisIkon(j) { return this.jenisInfo(j)?.ikon || 'bi-dot'; },
        jenisWarna(j) { return this.jenisInfo(j)?.warna || '#64748b'; },
        fmt(d) {
            if (!d) return '—';
            const dt = new Date(String(d).replace(' ', 'T'));
            return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data jadwal.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { kegiatan: '', kategori: '', alur: '', agenda: [] };
            this.show = true;
        },
        openEdit(j) {
            this.editingId = j.id;
            this.form = {
                kegiatan: j.kegiatan,
                kategori: j.kategori,
                alur: j.alur || '',
                status: j.status,
                agenda: (j.agenda || []).map((a) => ({ jenis: a.jenis, label: a.label, mulai: a.mulai, selesai: a.selesai })),
            };
            this.show = true;
        },
        addRow() { this.form.agenda.push({ jenis: 'RAPAT', label: '', mulai: null, selesai: null }); },
        removeRow(i) { this.form.agenda.splice(i, 1); },
        async save() {
            if (this.saving) return;
            if (!this.form.kegiatan.trim()) return this.notice('Nama kegiatan wajib diisi.');
            if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
            if (this.form.agenda.some((a) => !a.label || !a.label.trim())) return this.notice('Setiap agenda wajib punya label.');
            this.saving = true;
            const payload = { ...this.form };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Jadwal diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Jadwal ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(j, v) {
            const prev = j.status;
            j.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${j.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Jadwal "${j.kegiatan}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                j.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(j) { this.delTarget = j; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const j = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${j.id}`, CFG);
                this.notice('Jadwal dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(x) { this.toast = x; if (this.tm) clearTimeout(this.tm); this.tm = setTimeout(() => (this.toast = ''), 3000); },
    },
};
</script>

<style scoped>
.mjd-statusnote { display: flex; align-items: center; gap: .45rem; font-size: 12.5px; color: #047857; background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.25); border-radius: 10px; padding: .55rem .7rem; }
.jdw-head-act { display: inline-flex; align-items: center; gap: .4rem; margin-left: auto; }
.jdw-meta { margin-bottom: .8rem; }
.jdw-empty-agenda { color: #94a3b8; font-size: 13px; }
.jdw-agenda { display: flex; flex-direction: column; gap: .6rem; }
.jdw-arow { display: flex; align-items: flex-start; gap: .6rem; background: #f8fafc; border: 1px solid rgba(11, 16, 51, .07); border-radius: 12px; padding: .7rem; }
.jdw-arow__num { flex: 0 0 auto; width: 26px; height: 26px; border-radius: 8px; background: #4f46e5; color: #fff; display: grid; place-items: center; font: 700 12px 'Plus Jakarta Sans', sans-serif; margin-top: 1.5rem; }
/* Grid 2 kolom (tiap field col-6 dari 12) — lebih lega daripada 4 berdesakan. */
.jdw-arow__grid { flex: 1; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem .7rem; min-width: 0; }
.jdw-col6 { min-width: 0; }
.jdw-hint { display: flex; align-items: center; gap: .35rem; margin-top: .3rem; font-size: 11px; color: #64748b; line-height: 1.4; }
.jdw-opt { display: flex; align-items: center; gap: .5rem; padding: .1rem 0; }
.jdw-opt__ic { flex: none; font-size: 1rem; }
.jdw-opt__txt { display: flex; flex-direction: column; line-height: 1.3; }
.jdw-opt__txt small { color: #94a3b8; font-size: 11px; }
@media (max-width: 560px) {
    .jdw-arow__grid { grid-template-columns: 1fr; }
    .jdw-arow__num { display: none; }
}
</style>
