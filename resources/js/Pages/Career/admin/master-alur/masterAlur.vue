<!-- WEB CAREER — Master Tahapan Seleksi / Alur (induk-detail: Alur + Tahap/Stages). DATA dari DB via /api/v1/master-alur. -->
<template>
    <Head><title>Master Tahapan Seleksi - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Tahapan Seleksi</h1>
                <p>Susun urutan tahap seleksi (alur) per kategori. Tahap <b>pihak ke-3</b> keputusannya otomatis sistem. Tahap "Tes Online" mengambil dari <b>Master Jenis Tes</b>.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Alur Baru</button>
            </div>
        </div>

        <div class="wca-legend">
            <span><i class="bi bi-person-workspace" style="color:var(--indigo)"></i> Internal — keputusan admin (loloskan/tolak)</span>
            <span><i class="bi bi-robot" style="color:#d97706"></i> Pihak ke-3 — konfirmasi otomatis sistem</span>
        </div>

        <div v-loading="loading" class="wca-acc">
            <div v-for="a in list" :key="a.id" class="wca-acc__item" :class="{ open: open === a.id }">
                <button class="wca-acc__head" @click="open = (open === a.id ? null : a.id)">
                    <span class="wca-acc__chev"><i class="bi bi-chevron-right"></i></span>
                    <span class="wca-acc__title">
                        <strong>{{ a.nama }}</strong>
                        <small>{{ a.kode }}<template v-if="a.deskripsi"> · {{ a.deskripsi }}</template></small>
                    </span>
                    <span class="wca-acc__tags">
                        <span class="wca-badge wca-b--indigo">{{ katLabel(a.kategori) }}</span>
                        <span class="wca-badge" :class="a.status === 'AKTIF' ? 'wca-b--green' : 'wca-b--slate'">{{ a.status }}</span>
                        <span class="wca-badge wca-b--slate">{{ a.stages.length }} tahap</span>
                    </span>
                    <span class="alr-head-act" @click.stop>
                        <el-switch :model-value="a.status === 'AKTIF'" @change="(v) => setStatus(a, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(a)"><i class="bi bi-pencil"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(a)"><i class="bi bi-trash"></i></button>
                    </span>
                </button>

                <div class="wca-acc__body">
                    <div class="wca-acc__inner">
                        <div class="alr-meta"><AuditStamp :by="a.createdBy" :at="a.createdAt" /></div>
                        <ol class="wca-flow">
                            <li v-for="(s, i) in a.stages" :key="i" class="wca-flow__step" :class="{ 'is-system': s.keputusan === 'SYSTEM' }">
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
                                        <span><i class="bi bi-tag"></i> {{ s.tipe }}</span>
                                        <span v-if="s.tesId"><i class="bi bi-cpu"></i> {{ s.tesId }}</span>
                                        <span v-if="s.formulirId"><i class="bi bi-input-cursor-text"></i> {{ s.formulirId }}</span>
                                        <span v-if="s.sla"><i class="bi bi-hourglass-split"></i> SLA {{ s.sla }}</span>
                                        <span class="wca-flow__dec" :class="s.keputusan === 'SYSTEM' ? 'sys' : 'man'">
                                            <i class="bi" :class="s.keputusan === 'SYSTEM' ? 'bi-cpu' : 'bi-hand-index-thumb'"></i>
                                            {{ s.keputusan === 'SYSTEM' ? 'Konfirmasi Sistem' : 'Keputusan Admin' }}
                                        </span>
                                        <span class="alr-pill" :class="`is-${(s.pengumuman || 'OTOMATIS').toLowerCase()}`">
                                            <i class="bi" :class="ikonPengumuman(s.pengumuman)"></i>
                                            {{ labelPengumuman(s.pengumuman) }}<template v-if="s.pengumuman === 'TERJADWAL' && s.jedaHari != null"> +{{ s.jedaHari }} hr</template>
                                        </span>
                                        <span v-if="s.notifikasi === false" class="alr-pill is-mute" title="Kandidat tidak dikirimi notifikasi saat hasil terbit">
                                            <i class="bi bi-bell-slash"></i> Tanpa notifikasi
                                        </span>
                                    </div>
                                </div>
                            </li>
                            <li v-if="!a.stages.length" class="alr-empty-stage">Belum ada tahap.</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty"><i class="bi bi-signpost-split"></i><h4>Belum ada alur</h4></div>
        </div>

        <!-- Modal buat/ubah alur — builder tahapan -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Alur Seleksi' : 'Buat Alur Seleksi'" subtitle="Pilih kategori & identitas, lalu susun tahapan (klik Tambah Tahap)." icon="bi-signpost-split" lg :save-label="editingId ? 'Perbarui' : 'Simpan Alur'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-signpost-split"></i> Detail Alur</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Untuk Kategori (kelompok)</label>
                        <RefSelect type="talent" v-model="form.kategori" placeholder="Pilih kelompok" />
                        <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem">Menentukan alur ini muncul untuk kategori mana saat buat program.</small>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Nama Alur</label><el-input v-model="form.nama" placeholder="mis. Alur Rekrutmen Ekspres" /></div>
                        <div><label class="wca-field-lbl">Deskripsi</label><el-input v-model="form.deskripsi" placeholder="Ringkasan singkat" /></div>
                    </div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label" style="display:flex;align-items:center;justify-content:space-between">
                    <span><i class="bi bi-list-ol"></i> Susun Tahapan ({{ form.stages.length }})</span>
                    <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addStage"><i class="bi bi-plus-circle"></i> Tambah Tahap</button>
                </div>
                <div v-if="!form.stages.length" class="wca-hint" style="margin:0 0 .6rem"><i class="bi bi-info-circle"></i> Belum ada tahap. Klik <b>Tambah Tahap</b> untuk mulai menyusun urutan seleksi.</div>

                <div v-for="(s, i) in form.stages" :key="i" class="wca-stagecard">
                    <div class="wca-stagecard__num">{{ i + 1 }}</div>
                    <div class="wca-stagecard__body">
                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">Nama Tahap</label><el-input v-model="s.label" placeholder="mis. Psikotes Online" /></div>
                            <div><label class="wca-field-lbl">Tipe</label>
                                <RefSelect type="tipe" v-model="s.tipe" placeholder="Pilih tipe" />
                            </div>
                        </div>
                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">Penyedia</label>
                                <RefSelect type="provider" v-model="s.provider" placeholder="Pilih penyedia" />
                            </div>
                            <div v-if="s.provider === 'THIRD_PARTY'"><label class="wca-field-lbl">Jenis Tes (pihak ke-3)</label>
                                <RefSelect type="tes" v-model="s.tesId" placeholder="Pilih jenis tes" clearable />
                            </div>
                            <div v-if="s.tipe === 'FORM' || s.tipe === 'DOCUMENT'"><label class="wca-field-lbl">Formulir yang Diisi</label>
                                <RefSelect type="formulir" v-model="s.formulirId" placeholder="Pilih formulir (Master Formulir)" clearable />
                            </div>
                        </div>
                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">SLA (opsional)</label><el-input v-model="s.sla" placeholder="mis. 3 hari" /></div>
                        </div>

                        <!-- PENGUMUMAN HASIL — kapan hasil tahap ini boleh dilihat kandidat.
                             Yang disimpan di sini cuma ATURAN-nya; tanggal pastinya diisi
                             saat penjadwalan angkatan, karena satu alur dipakai banyak program. -->
                        <div class="alr-ann">
                            <div class="alr-ann__head"><i class="bi bi-megaphone"></i> Pengumuman Hasil</div>
                            <div class="wca-frow">
                                <div>
                                    <label class="wca-field-lbl">Kapan hasil terlihat kandidat</label>
                                    <el-select v-model="s.pengumuman" style="width:100%">
                                        <el-option label="Otomatis — begitu keputusan dibuat" value="OTOMATIS" />
                                        <el-option label="Terjadwal — tunggu tanggal pengumuman" value="TERJADWAL" />
                                        <el-option label="Manual — admin yang menerbitkan" value="MANUAL" />
                                    </el-select>
                                </div>
                                <div v-if="s.pengumuman === 'TERJADWAL'">
                                    <label class="wca-field-lbl">Saran jeda (hari)</label>
                                    <el-input-number v-model="s.jedaHari" :min="0" :max="3650" controls-position="right" style="width:100%" />
                                </div>
                            </div>
                            <label class="alr-ann__check">
                                <el-checkbox v-model="s.notifikasi">Beri tahu kandidat (email/WA) saat hasil terbit</el-checkbox>
                            </label>
                            <p class="alr-ann__note">{{ catatanPengumuman(s) }}</p>
                        </div>

                        <span class="wca-flow__dec" :class="s.provider === 'THIRD_PARTY' ? 'sys' : 'man'" style="align-self:flex-start">
                            <i class="bi" :class="s.provider === 'THIRD_PARTY' ? 'bi-cpu' : 'bi-hand-index-thumb'"></i>
                            {{ s.provider === 'THIRD_PARTY' ? 'Konfirmasi Sistem' : 'Keputusan Admin' }}
                        </span>
                    </div>
                    <div class="wca-stagecard__actions">
                        <button class="wca-iconbtn" type="button" title="Naik" :disabled="i === 0" @click="moveStage(i, -1)"><i class="bi bi-chevron-up"></i></button>
                        <button class="wca-iconbtn" type="button" title="Turun" :disabled="i === form.stages.length - 1" @click="moveStage(i, 1)"><i class="bi bi-chevron-down"></i></button>
                        <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus" @click="removeStage(i)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Alur" :busy="deleting" confirm-label="Ya, Hapus" note="Alur & seluruh tahapannya akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus alur <strong>{{ delTarget?.nama }}</strong>?
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

const API = '/api/v1/master-alur';
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
            form: { nama: '', kategori: '', deskripsi: '', stages: [] },
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
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k; },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data alur.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', kategori: '', deskripsi: '', stages: [] };
            this.show = true;
        },
        openEdit(a) {
            this.editingId = a.id;
            this.form = {
                nama: a.nama,
                kategori: a.kategori,
                deskripsi: a.deskripsi || '',
                stages: (a.stages || []).map((s) => ({
                    label: s.label,
                    tipe: s.tipe,
                    provider: s.provider || 'INTERNAL',
                    tesId: s.tesId ?? null,
                    formulirId: s.formulirId ?? null,
                    sla: s.sla || '',
                    pengumuman: s.pengumuman || 'OTOMATIS',
                    jedaHari: s.jedaHari ?? null,
                    notifikasi: s.notifikasi !== false,
                })),
            };
            this.show = true;
        },
        addStage() { this.form.stages.push({ label: '', tipe: '', provider: 'INTERNAL', tesId: null, formulirId: null, sla: '', pengumuman: 'OTOMATIS', jedaHari: null, notifikasi: true }); },
        removeStage(i) { this.form.stages.splice(i, 1); },

        labelPengumuman(m) { return { OTOMATIS: 'Umumkan otomatis', TERJADWAL: 'Umumkan terjadwal', MANUAL: 'Umumkan manual' }[m] || 'Umumkan otomatis'; },
        ikonPengumuman(m) { return { OTOMATIS: 'bi-lightning-charge-fill', TERJADWAL: 'bi-calendar-event-fill', MANUAL: 'bi-hand-index-thumb-fill' }[m] || 'bi-lightning-charge-fill'; },

        /** Kalimat konsekuensi, supaya admin paham akibat pilihannya tanpa menebak. */
        catatanPengumuman(s) {
            const notif = s.notifikasi !== false ? 'Kandidat dikirimi notifikasi.' : 'Kandidat TIDAK dikirimi notifikasi.';
            if (s.pengumuman === 'TERJADWAL') {
                const jeda = s.jedaHari != null && s.jedaHari !== '' ? ` Saat menjadwalkan angkatan, tanggal disarankan ${s.jedaHari} hari setelah tahap selesai — tetap bisa diubah.` : ' Tanggal pastinya diisi saat menjadwalkan angkatan.';
                return `Hasil ditahan sampai tanggal pengumuman terlewati.${jeda} ${notif}`;
            }
            if (s.pengumuman === 'MANUAL') {
                return `Hasil ditahan sampai admin menekan tombol umumkan — tidak ada tanggal otomatis. ${notif}`;
            }
            return `Hasil langsung terlihat kandidat begitu keputusan tahap dibuat. ${notif}`;
        },
        moveStage(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.form.stages.length) return;
            const arr = this.form.stages;
            [arr[i], arr[j]] = [arr[j], arr[i]];
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama alur wajib diisi.');
            if (!this.form.kategori) return this.notice('Kategori wajib dipilih.');
            if (this.form.stages.some((s) => !s.label || !s.label.trim())) return this.notice('Setiap tahap wajib punya label.');
            if (this.form.stages.some((s) => !s.tipe)) return this.notice('Setiap tahap wajib punya tipe.');
            this.saving = true;
            const payload = {
                nama: this.form.nama,
                kategori: this.form.kategori,
                deskripsi: this.form.deskripsi,
                stages: this.form.stages.map((s) => ({
                    label: s.label,
                    tipe: s.tipe,
                    provider: s.provider,
                    formulirId: (s.tipe === 'FORM' || s.tipe === 'DOCUMENT') ? (s.formulirId || null) : null,
                    tesId: s.provider === 'THIRD_PARTY' ? (s.tesId || null) : null,
                    sla: s.sla || '',
                    pengumuman: s.pengumuman || 'OTOMATIS',
                    // Jeda hanya bermakna untuk TERJADWAL; mode lain kirim null.
                    jedaHari: s.pengumuman === 'TERJADWAL' ? (s.jedaHari ?? null) : null,
                    notifikasi: s.notifikasi !== false,
                })),
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Alur diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Alur ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(a, v) {
            const prev = a.status;
            a.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${a.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Alur "${a.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                a.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(a) { this.delTarget = a; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const a = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${a.id}`, CFG);
                this.notice('Alur dihapus.');
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
.alr-head-act { display: inline-flex; align-items: center; gap: .4rem; margin-left: auto; }
.alr-meta { margin-bottom: .8rem; }
.alr-empty-stage { color: #94a3b8; font-size: 13px; }

/* Blok pengumuman di dalam kartu tahap — dibedakan agar terbaca sebagai
   aturan perilaku, bukan sekadar field tambahan. */
.alr-ann { border: 1px solid rgba(79, 70, 229, .16); border-radius: 12px; background: linear-gradient(180deg, rgba(79, 70, 229, .05), rgba(124, 58, 237, .04)); padding: .7rem .8rem; margin-top: .2rem; }
.alr-ann__head { display: flex; align-items: center; gap: .4rem; font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #4338ca; margin-bottom: .55rem; }
.alr-ann__check { display: block; margin-top: .5rem; }
.alr-ann__note { margin: .45rem 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }

/* Pil ringkas di daftar alur. Warna mengikuti sifat: hijau = langsung terbit,
   indigo = menunggu tanggal, amber = menunggu tindakan admin. */
.alr-pill { display: inline-flex; align-items: center; gap: .3rem; font-size: 11px; font-weight: 600; border-radius: 999px; padding: .12rem .5rem; }
.alr-pill.is-otomatis { color: #047857; background: rgba(16, 185, 129, .12); }
.alr-pill.is-terjadwal { color: #4338ca; background: rgba(79, 70, 229, .12); }
.alr-pill.is-manual { color: #b45309; background: rgba(245, 158, 11, .14); }
.alr-pill.is-mute { color: #64748b; background: #f1f5f9; }
@media (max-width: 560px) {
    .wca-frow { grid-template-columns: 1fr; }
}
</style>
