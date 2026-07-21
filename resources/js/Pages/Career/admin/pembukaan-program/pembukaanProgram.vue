<!-- WEB CAREER — Admin: Pembukaan Program (publikasi program ke channel + window; detail kampus). DATA dari DB via /api/v1/pembukaan. -->
<template>
    <Head><title>Pembukaan Program - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Pembukaan Program</h1>
                <p>Publikasikan <b>1 program</b> ke banyak channel — <b>Umum</b> (landing publik) atau <b>Kampus</b> tertentu — masing-masing punya window sendiri.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Buka Program</button>
            </div>
        </div>

        <div class="wca-card">
            <div class="wca-card__body--flush">
                <div class="wca-tablewrap">
                    <table class="wca-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Program</th>
                                <th>Channel</th>
                                <th>Masa Berlaku</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>Dibuat Oleh</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody v-loading="loading">
                            <tr v-for="b in list" :key="b.id">
                                <td><strong>{{ b.kode }}</strong></td>
                                <td>
                                    <strong>{{ b.programNama }}</strong>
                                    <span class="wca-badge" :class="katBadge(b.kategori)" style="margin-left:.3rem">{{ katShort(b.kategori) }}</span>
                                    <div v-if="b.batchNama" style="margin-top:.25rem"><span class="wca-badge wca-b--slate"><i class="bi bi-collection"></i> {{ b.batchNama }}</span></div>
                                </td>
                                <td>
                                    <span class="wca-badge" :class="b.channel === 'KAMPUS' ? 'wca-b--indigo' : 'wca-b--green'"><i class="bi" :class="b.channel === 'KAMPUS' ? 'bi-mortarboard' : 'bi-globe'"></i> {{ b.channel === 'KAMPUS' ? 'Kampus' : 'Umum' }}</span>
                                    <div v-if="b.channel === 'KAMPUS' && b.kampus && b.kampus.length" class="pbk-chips">
                                        <span v-for="(k, i) in b.kampus" :key="i" class="pbk-chip">{{ k }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="wca-badge" :class="b.masaBerlaku === 'EVERGREEN' ? 'wca-b--green' : 'wca-b--slate'">{{ b.masaBerlaku === 'EVERGREEN' ? 'Evergreen' : 'Berbatas' }}</span>
                                </td>
                                <td><span style="font-size:.8rem;font-weight:700;color:var(--slate)">{{ windowLabel(b) }}</span></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:.45rem">
                                        <el-switch :model-value="b.statusPublish === 'TERBIT'" @change="(v) => setPublish(b, v)" />
                                        <span class="wca-badge" :class="b.statusPublish === 'TERBIT' ? 'wca-b--green' : 'wca-b--slate'">{{ b.statusPublish === 'TERBIT' ? 'Terbit' : 'Draft' }}</span>
                                    </div>
                                </td>
                                <td><AuditStamp :by="b.createdBy" :at="b.createdAt" /></td>
                                <td>
                                    <div style="display:flex;gap:.35rem;justify-content:flex-end">
                                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(b)"><i class="bi bi-pencil"></i></button>
                                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(b)"><i class="bi bi-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!loading && !list.length"><td colspan="8"><div class="wca-empty"><i class="bi bi-megaphone"></i><h4>Belum ada pembukaan</h4></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal buka/ubah -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Pembukaan' : 'Buka Program'" subtitle="Publikasikan program ke sebuah channel + window" icon="bi-megaphone" :save-label="editingId ? 'Perbarui' : 'Buka'" @close="show = false" @save="save">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-megaphone"></i> Detail Pembukaan</div>
                <div class="wca-form">
                    <div><label class="wca-field-lbl">Program</label>
                        <RefSelect type="program" v-model="form.program" placeholder="Pilih program" />
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Channel / Sumber</label>
                            <el-select filterable v-model="form.channel" placeholder="Pilih channel" style="width:100%" @change="onChannel">
                                <el-option label="Umum (landing publik)" value="UMUM" />
                                <el-option label="Kampus (whitelist)" value="KAMPUS" />
                            </el-select>
                        </div>
                        <div><label class="wca-field-lbl">Masa Berlaku</label>
                            <el-select filterable v-model="form.masaBerlaku" placeholder="Pilih" style="width:100%" @change="onMasa">
                                <el-option label="Berbatas (ada tanggal tutup)" value="BERBATAS" />
                                <el-option label="Evergreen (tanpa tanggal)" value="EVERGREEN" />
                            </el-select>
                        </div>
                    </div>
                    <div v-if="form.channel === 'KAMPUS'"><label class="wca-field-lbl">Kampus Sasaran (whitelist)</label>
                        <el-select v-model="form.kampusIds" multiple filterable placeholder="Pilih kampus" style="width:100%">
                            <el-option v-for="u in kampusOptions" :key="u.value" :label="u.label" :value="u.value" />
                        </el-select>
                        <small style="display:block;margin-top:.3rem;color:var(--muted);font-weight:700;font-size:.72rem"><i class="bi bi-list-check"></i> Daftar ini jadi <b>whitelist formulir pelamar</b>.</small>
                    </div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Tanggal Buka</label><el-date-picker v-model="form.buka" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" style="width:100%" /></div>
                        <div><label class="wca-field-lbl">Tanggal Tutup</label><el-date-picker v-model="form.tutup" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" style="width:100%" :disabled="form.masaBerlaku === 'EVERGREEN'" /></div>
                    </div>
                    <div><label class="wca-field-lbl">Status Publish</label>
                        <el-select filterable v-model="form.statusPublish" placeholder="Pilih status" style="width:100%">
                            <el-option label="Draft" value="DRAFT" />
                            <el-option label="Terbit" value="TERBIT" />
                        </el-select>
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal :show="delShow" title="Hapus Pembukaan" :busy="deleting" confirm-label="Ya, Hapus" note="Pembukaan program ini akan dihapus permanen." @cancel="delShow = false" @confirm="confirmDelete">
            Yakin ingin menghapus pembukaan <strong>{{ delTarget?.programNama }}</strong>?
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

const API = '/api/v1/pembukaan';
const CFG = { headers: { Accept: 'application/json' } };
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, RefSelect },
    data() {
        return {
            list: [],
            loading: false,
            kampusOptions: [],
            show: false,
            editingId: null,
            saving: false,
            form: { program: '', channel: 'UMUM', masaBerlaku: 'BERBATAS', buka: null, tutup: null, statusPublish: 'DRAFT', kampusIds: [] },
            delShow: false,
            delTarget: null,
            deleting: false,
            toast: '',
            tm: null,
        };
    },
    mounted() {
        this.load();
        this.loadKampus();
    },
    methods: {
        katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--sky'; },
        katShort(k) { return { MT: 'MT', INTERNSHIP: 'MAGANG', REKRUTMEN: 'REK' }[k] || 'REK'; },
        fmt(iso) {
            if (!iso) return '—';
            const d = new Date(String(iso).replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return iso;
            return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
        },
        windowLabel(b) { return b.masaBerlaku === 'EVERGREEN' ? 'Dibuka: ' + this.fmt(b.buka) + ' — tanpa batas' : `${this.fmt(b.buka)} – ${this.fmt(b.tutup)}`; },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data pembukaan.');
            } finally {
                this.loading = false;
            }
        },
        async loadKampus() {
            try {
                const res = await axios.get('/api/v1/karir/options/kampus', CFG);
                this.kampusOptions = res.data.result || [];
            } catch (e) {
                this.kampusOptions = [];
            }
        },
        blankForm() { return { program: '', channel: 'UMUM', masaBerlaku: 'BERBATAS', buka: null, tutup: null, statusPublish: 'DRAFT', kampusIds: [] }; },
        onChannel() { if (this.form.channel !== 'KAMPUS') this.form.kampusIds = []; },
        onMasa() { if (this.form.masaBerlaku === 'EVERGREEN') this.form.tutup = null; },
        openCreate() {
            this.editingId = null;
            this.form = this.blankForm();
            this.show = true;
        },
        openEdit(b) {
            this.editingId = b.id;
            this.form = {
                program: b.program || '',
                channel: b.channel || 'UMUM',
                masaBerlaku: b.masaBerlaku || 'BERBATAS',
                buka: b.buka || null,
                tutup: b.tutup || null,
                statusPublish: b.statusPublish || 'DRAFT',
                kampusIds: Array.isArray(b.kampusIds) ? [...b.kampusIds] : [],
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.program) return this.notice('Program wajib dipilih.');
            if (!this.form.channel) return this.notice('Channel wajib dipilih.');
            if (!this.form.masaBerlaku) return this.notice('Masa berlaku wajib dipilih.');
            if (this.form.channel === 'KAMPUS' && !this.form.kampusIds.length) return this.notice('Channel Kampus — pilih minimal 1 kampus.');
            this.saving = true;
            const payload = {
                program: this.form.program,
                channel: this.form.channel,
                masaBerlaku: this.form.masaBerlaku,
                buka: this.form.buka,
                tutup: this.form.masaBerlaku === 'EVERGREEN' ? null : this.form.tutup,
                statusPublish: this.form.statusPublish,
                kampusIds: this.form.channel === 'KAMPUS' ? this.form.kampusIds : [],
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Pembukaan diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Program dibuka.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setPublish(b, v) {
            const prev = b.statusPublish;
            b.statusPublish = v ? 'TERBIT' : 'DRAFT';
            try {
                await axios.patch(`${API}/${b.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Pembukaan "${b.programNama}" ${v ? 'diterbitkan' : 'dijadikan draft'}.`);
            } catch (e) {
                b.statusPublish = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(b) { this.delTarget = b; this.delShow = true; },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const b = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${b.id}`, CFG);
                this.notice('Pembukaan dihapus.');
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
.pbk-chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .35rem; }
.pbk-chip { display: inline-block; padding: .12rem .5rem; border-radius: 999px; background: #eef2ff; color: #4338ca; border: 1px solid rgba(79, 70, 229, .18); font: 700 .7rem 'Plus Jakarta Sans', sans-serif; }
@media (max-width: 720px) {
    .wca-tablewrap { overflow-x: auto; }
}
</style>
