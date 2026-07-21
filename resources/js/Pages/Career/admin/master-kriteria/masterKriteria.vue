<!-- WEB CAREER — Master Kriteria (per-modul). CARD GRID. DATA via web route + ResponseHelper (axios), bukan props Inertia. -->
<template>
    <Head><title>Master Kriteria - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Kriteria</h1>
                <p>
                    Katalog <b>field kelayakan</b> yang bisa dievaluasi sistem. Program/posisi menyusun aturan dari
                    sini → pelamar yang tak memenuhi <b>otomatis gugur</b>.
                </p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate">
                    <i class="bi bi-plus-lg"></i> Kriteria Kustom
                </button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-info-circle"></i>
            <span
                ><b>Dinamis & scalable:</b> tiap kriteria = data (bukan hardcode). Tambah field baru di sini →
                langsung bisa dipakai di aturan program. <b>Tipe:</b> Angka (mis. umur ≤ 25), Berjenjang (mis. min
                S1), Daftar (mis. jurusan tertentu).</span
            >
        </div>

        <div v-loading="loading" class="wca-tipegrid">
            <div v-for="k in list" :key="k.id" class="wca-tipecard" :class="{ off: k.status !== 'AKTIF' }">
                <div class="wca-tipecard__head">
                    <span class="wca-tipecard__ico" :class="tipeKelas(k.tipe)"><i class="bi" :class="k.ikon"></i></span>
                    <div class="wca-tipecard__title">
                        <strong>{{ k.nama }}</strong><small>{{ k.field }}</small>
                    </div>
                    <span class="wca-badge" :class="k.sifat === 'SISTEM' ? 'wca-b--slate' : 'wca-b--indigo'">{{
                        k.sifat === 'SISTEM' ? 'Sistem' : 'Kustom'
                    }}</span>
                </div>
                <p class="wca-tipecard__desc">{{ k.deskripsi }}</p>
                <div class="wca-krmeta">
                    <span class="wca-badge" :class="tipeBadge(k.tipe)"
                        ><i class="bi" :class="tipeIkon(k.tipe)"></i> {{ tipeLabel(k.tipe) }}</span
                    >
                    <span v-if="k.satuan" class="wca-badge wca-b--slate">{{ k.satuan }}</span>
                    <span v-for="op in k.ops" :key="op" class="wca-oppill">{{ opLabel(op) }}</span>
                </div>
                <div v-if="k.opsi.length" class="wca-krtags">
                    <span v-for="o in k.opsi" :key="o" class="wca-chip">{{ o }}</span>
                </div>
                <div class="wca-tipecard__foot">
                    <span class="wca-badge wca-b--slate"><i class="bi bi-diagram-3"></i> {{ k.ops.length }} operator</span>
                    <div class="wca-tipecard__actions">
                        <el-switch :model-value="k.status === 'AKTIF'" @change="(v) => setStatus(k, v)" />
                        <button class="wca-iconbtn" title="Ubah" @click="openEdit(k)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="askRemove(k)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="wca-cardaudit"><AuditStamp :by="k.createdBy" :at="k.createdAt" /></div>
            </div>
            <div v-if="!loading && !list.length" class="wca-empty">
                <i class="bi bi-sliders2"></i>
                <h4>Belum ada kriteria</h4>
            </div>
        </div>

        <!-- Modal kriteria kustom -->
        <AdminModal
            :show="show"
            :title="editingId ? 'Ubah Kriteria' : 'Tambah Kriteria Kustom'"
            subtitle="Field kelayakan untuk mesin auto-gugur"
            icon="bi-sliders2"
            :save-label="editingId ? 'Perbarui Kriteria' : 'Simpan Kriteria'"
            @close="show = false"
            @save="save"
        >
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-sliders2"></i> Detail Kriteria</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Nama</label
                            ><el-input v-model="form.nama" placeholder="mis. Tinggi Badan" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Field (kunci data)</label
                            ><el-input v-model="form.field" placeholder="tinggi" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Tipe</label>
                        <el-select v-model="form.tipe" filterable placeholder="Pilih tipe" style="width: 100%" @change="onTipe">
                            <el-option label="Angka (≤ ≥ antara)" value="NUMBER" />
                            <el-option label="Berjenjang (min. tingkat)" value="ENUM_ORD" />
                            <el-option label="Daftar (termasuk/tidak)" value="LIST" />
                        </el-select>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Operator</label>
                        <el-select v-model="form.ops" multiple filterable placeholder="Pilih operator" style="width: 100%">
                            <el-option label="≤" value="<=" />
                            <el-option label="≥" value=">=" />
                            <el-option label="=" value="==" />
                            <el-option label="antara (BETWEEN)" value="BETWEEN" />
                            <el-option label="termasuk (IN)" value="IN" />
                            <el-option label="kecuali (NOT_IN)" value="NOT_IN" />
                        </el-select>
                    </div>
                    <div v-if="form.tipe === 'ENUM_ORD' || form.tipe === 'LIST'">
                        <label class="wca-field-lbl">Opsi (urut dari terendah)</label>
                        <el-select
                            v-model="form.opsi"
                            multiple
                            filterable
                            allow-create
                            default-first-option
                            placeholder="SMA, D3, S1, S2"
                            style="width: 100%"
                        />
                    </div>
                    <div class="wca-frow">
                        <div>
                            <label class="wca-field-lbl">Satuan (opsional)</label
                            ><el-input v-model="form.satuan" placeholder="tahun / cm" />
                        </div>
                        <div>
                            <label class="wca-field-lbl">Ikon (opsional)</label><IconPicker v-model="form.ikon" />
                        </div>
                    </div>
                    <div>
                        <label class="wca-field-lbl">Deskripsi</label
                        ><el-input v-model="form.deskripsi" type="textarea" :rows="2" placeholder="Jelaskan kriteria ini" />
                    </div>
                </div>
            </div>
        </AdminModal>

        <ConfirmModal
            :show="delShow"
            title="Hapus Kriteria"
            :busy="deleting"
            confirm-label="Ya, Hapus"
            note="Kriteria kustom akan dihapus permanen."
            @cancel="delShow = false"
            @confirm="confirmDelete"
        >
            Yakin ingin menghapus kriteria <strong>{{ delTarget?.nama }}</strong>?
        </ConfirmModal>

        <transition name="wca-toast"
            ><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition
        >
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import AdminModal from '@career/AdminModal.vue';
import ConfirmModal from '@career/ConfirmModal.vue';
import AuditStamp from '@career/AuditStamp.vue';
import IconPicker from '@career/IconPicker.vue';

const API = '/api/v1/master-kriteria';
const CFG = { headers: { Accept: 'application/json' } };

export default {
    components: { Head, AdminModal, ConfirmModal, AuditStamp, IconPicker },
    data() {
        return {
            list: [],
            loading: false,
            show: false,
            editingId: null,
            form: { nama: '', field: '', tipe: 'NUMBER', satuan: '', ikon: '', ops: [], opsi: [], deskripsi: '' },
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
        tipeLabel(t) { return { NUMBER: 'Angka', ENUM_ORD: 'Berjenjang', LIST: 'Daftar' }[t] || t; },
        tipeBadge(t) { return { NUMBER: 'wca-b--indigo', ENUM_ORD: 'wca-b--amber', LIST: 'wca-b--sky' }[t] || 'wca-b--slate'; },
        tipeIkon(t) { return { NUMBER: 'bi-123', ENUM_ORD: 'bi-bar-chart-steps', LIST: 'bi-list-ul' }[t] || 'bi-dot'; },
        tipeKelas(t) { return { NUMBER: 'peri-man', ENUM_ORD: 'peri-cat', LIST: 'peri-doc' }[t] || ''; },
        opLabel(op) { return { '<=': '≤', '>=': '≥', '==': '=', BETWEEN: 'antara', IN: 'termasuk', NOT_IN: 'kecuali' }[op] || op; },
        onTipe() { if (this.form.tipe === 'NUMBER') this.form.opsi = []; },
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(API, CFG);
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data kriteria.');
            } finally {
                this.loading = false;
            }
        },
        openCreate() {
            this.editingId = null;
            this.form = { nama: '', field: '', tipe: 'NUMBER', satuan: '', ikon: '', ops: [], opsi: [], deskripsi: '' };
            this.show = true;
        },
        openEdit(k) {
            this.editingId = k.id;
            this.form = {
                nama: k.nama || '',
                field: k.field || '',
                tipe: k.tipe || 'NUMBER',
                satuan: k.satuan || '',
                ikon: k.ikon || '',
                ops: [...(k.ops || [])],
                opsi: [...(k.opsi || [])],
                deskripsi: k.deskripsi || '',
            };
            this.show = true;
        },
        async save() {
            if (this.saving) return;
            if (!this.form.nama.trim()) return this.notice('Nama wajib diisi.');
            if (!this.form.field.trim()) return this.notice('Field wajib diisi.');
            if (!this.form.tipe) return this.notice('Tipe wajib dipilih.');
            this.saving = true;
            const payload = {
                nama: this.form.nama,
                field: this.form.field,
                tipe: this.form.tipe,
                satuan: this.form.satuan,
                ikon: this.form.ikon,
                ops: [...this.form.ops],
                opsi: [...this.form.opsi],
                deskripsi: this.form.deskripsi,
            };
            try {
                if (this.editingId) {
                    await axios.put(`${API}/${this.editingId}`, payload, CFG);
                    this.notice('Kriteria diperbarui.');
                } else {
                    await axios.post(API, payload, CFG);
                    this.notice('Kriteria kustom ditambahkan.');
                }
                this.show = false;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menyimpan.');
            } finally {
                this.saving = false;
            }
        },
        async setStatus(k, v) {
            const prev = k.status;
            k.status = v ? 'AKTIF' : 'NONAKTIF';
            try {
                await axios.patch(`${API}/${k.id}/toggle`, { aktif: v }, CFG);
                this.notice(`Kriteria "${k.nama}" ${v ? 'diaktifkan' : 'dinonaktifkan'}.`);
            } catch (e) {
                k.status = prev;
                this.notice('Gagal mengubah status.');
            }
        },
        askRemove(k) {
            this.delTarget = k;
            this.delShow = true;
        },
        async confirmDelete() {
            if (this.deleting || !this.delTarget) return;
            const k = this.delTarget;
            this.deleting = true;
            try {
                await axios.delete(`${API}/${k.id}`, CFG);
                this.notice('Kriteria kustom dihapus.');
                this.delShow = false;
                this.delTarget = null;
                await this.load();
            } catch (e) {
                this.notice(e.response?.data?.message || 'Gagal menghapus.');
            } finally {
                this.deleting = false;
            }
        },
        notice(m) {
            this.toast = m;
            if (this.tm) clearTimeout(this.tm);
            this.tm = setTimeout(() => (this.toast = ''), 3000);
        },
    },
};
</script>

<style scoped>
.wca-cardaudit {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed rgba(11, 16, 51, 0.08);
}
</style>
