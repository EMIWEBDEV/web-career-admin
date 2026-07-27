<!-- WEB CAREER — Master Tahapan Seleksi / Alur (induk-detail: Alur + Tahap/Stages). DATA dari DB via /api/v1/master-alur. -->
<template>
    <Head><title>Master Tahapan Seleksi - Web Career</title></Head>
    <div class="wca">
        <div class="pkg-head">
            <div class="pkg-head__l">
                <div class="pkg-head__title">
                    <span class="pkg-head__ico"><i class="bi bi-signpost-split"></i></span>
                    <h1>Master Tahapan Seleksi</h1>
                </div>
                <p>Susun urutan tahap seleksi per kategori — tes, mode keputusan, dan pengumuman diatur di tiap tahap.</p>
            </div>
            <button class="pkg-newbtn" @click="openCreate"><i class="bi bi-plus-lg"></i> Alur Baru</button>
        </div>

        <!-- FILTER PANEL — semua saringan dikirim ke backend (bukan disaring di browser). -->
        <div v-if="sheetOpen" class="alr-sheetbg" @click="sheetOpen = false"></div>
        <div class="alr-filter" :class="{ 'is-open': sheetOpen }">
            <div class="alr-filter__head">
                <span class="alr-filter__title"><i class="bi bi-funnel"></i> Filter Panel</span>
                <div class="alr-filter__act">
                    <button v-if="adaFilter" class="alr-filter__reset" type="button" @click="resetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    <button class="alr-filter__close" type="button" aria-label="Tutup filter" @click="sheetOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="alr-filter__grid">
                <div>
                    <label class="wca-field-lbl">Cari</label>
                    <el-input v-model="filters.q" placeholder="Nama / kode / deskripsi" clearable @input="cariDebounce">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                </div>
                <div>
                    <label class="wca-field-lbl">Kategori</label>
                    <RefSelect type="talent" v-model="filters.kategori" placeholder="Semua kategori" clearable />
                </div>
                <div>
                    <label class="wca-field-lbl">Status</label>
                    <el-select v-model="filters.status" placeholder="Semua status" clearable style="width:100%" @change="load">
                        <el-option label="Aktif" value="AKTIF" />
                        <el-option label="Nonaktif" value="NONAKTIF" />
                    </el-select>
                </div>
                <div>
                    <label class="wca-field-lbl">Tanggal Dibuat</label>
                    <el-date-picker
                        v-model="filters.rentang" type="daterange" value-format="YYYY-MM-DD"
                        start-placeholder="Dari" end-placeholder="Sampai" range-separator="—"
                        style="width:100%" @change="load"
                    />
                </div>
            </div>
        </div>

        <!-- FAB filter (mobile) -->
        <button class="alr-fab" type="button" aria-label="Buka filter" @click="sheetOpen = true">
            <i class="bi bi-funnel-fill"></i>
            <span v-if="jumlahFilter" class="alr-fab__badge">{{ jumlahFilter }}</span>
        </button>

        <div v-loading="loading" class="pkg-list">
            <div v-for="a in list" :key="a.id" class="pkg-card" :class="{ open: open === a.id }">
                <!-- header row (pola standar "Program Kegiatan") -->
                <div class="pkg-row">
                    <button type="button" class="pkg-chev" :class="{ open: open === a.id }" title="Buka detail" @click="open = (open === a.id ? null : a.id)"><i class="bi bi-chevron-right"></i></button>
                    <div class="pkg-row__main">
                        <button type="button" class="pkg-row__titlebtn" @click="open = (open === a.id ? null : a.id)">
                            <span class="pkg-row__title">{{ a.nama }}</span>
                        </button>
                        <div class="pkg-row__meta">
                            <span class="pkg-code">{{ a.kode }}</span>
                            <template v-if="a.deskripsi"><span class="pkg-sep"></span><span class="pkg-mi">{{ a.deskripsi }}</span></template>
                        </div>
                        <div class="pkg-pills">
                            <span class="pkg-pill pkg-pill--violet"><i class="bi bi-tags"></i> {{ katLabel(a.kategori) }}</span>
                            <span class="pkg-pill pkg-pill--struct"><i class="bi bi-list-ol"></i> {{ a.stages.length }} tahap</span>
                            <span class="pkg-pill" :class="a.status === 'AKTIF' ? 'pkg-pill--green' : 'pkg-pill--slate'"><span class="pkg-pill__dot"></span> {{ a.status }}</span>
                        </div>
                    </div>
                    <div class="pkg-row__act" @click.stop>
                        <el-switch :model-value="a.status === 'AKTIF'" @change="(v) => setStatus(a, v)" />
                        <button class="pkg-ibtn" title="Ubah" @click="openEdit(a)"><i class="bi bi-pencil"></i></button>
                        <button class="pkg-ibtn pkg-ibtn--danger" title="Hapus" @click="askRemove(a)"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

                <!-- creator strip -->
                <div class="pkg-creator">
                    <span class="pkg-creator__av" style="background:#6366f1">{{ initials(a.createdBy) }}</span>
                    <span class="pkg-creator__name">{{ a.createdBy || 'Sistem' }}</span>
                    <span class="pkg-creator__at"><i class="bi bi-clock"></i> {{ a.createdAt || '—' }}</span>
                </div>

                <!-- expanded detail -->
                <div v-if="open === a.id" class="pkg-detail">
                    <div class="pkg-dhead pkg-dhead--indigo"><i class="bi bi-list-ol"></i> TAHAPAN SELEKSI ({{ a.stages.length }})</div>
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
                                        <span v-if="s.formulirId"><i class="bi bi-input-cursor-text"></i> {{ s.formulirId }}</span>
                                        <span class="alr-pill is-mode"><i class="bi bi-diagram-3"></i> {{ namaMode(s.mode) }}</span>
                                        <span class="wca-flow__dec" :class="s.keputusan === 'SYSTEM' ? 'sys' : 'man'">
                                            <i class="bi" :class="s.keputusan === 'SYSTEM' ? 'bi-cpu' : 'bi-hand-index-thumb'"></i>
                                            {{ s.keputusan === 'SYSTEM' ? 'Konfirmasi Sistem' : 'Keputusan Admin' }}
                                        </span>
                                        <span class="alr-pill" :class="`is-${(s.pengumuman || 'OTOMATIS').toLowerCase()}`">
                                            <i class="bi" :class="ikonPengumuman(s.pengumuman)"></i>
                                            {{ labelPengumuman(s.pengumuman) }}<template v-if="modeButuhJeda(s.pengumuman) && s.jedaHari != null"> +{{ s.jedaHari }} hr</template>
                                        </span>
                                        <span v-if="s.notifikasi === false" class="alr-pill is-mute" title="Kandidat tidak dikirimi notifikasi saat hasil terbit">
                                            <i class="bi bi-bell-slash"></i> Tanpa notifikasi
                                        </span>
                                    </div>
                                    <!-- Sub-tes tahap: inilah yang dinilai mesin keputusan. -->
                                    <ul v-if="(s.tests || []).length" class="alr-subtes">
                                        <li v-for="(t, k) in s.tests" :key="k" :class="t.peran === 'INFORMATIF' ? 'is-info' : 'is-penentu'">
                                            <i class="bi" :class="t.jenisTes ? 'bi-robot' : 'bi-person-workspace'"></i>
                                            <b>{{ t.label }}</b>
                                            <span v-if="t.jenisTes" class="alr-subtes__tes">{{ t.jenisTes }}</span>
                                            <span class="alr-subtes__peran">{{ t.peran === 'INFORMATIF' ? 'informatif' : 'penentu' }}</span>
                                            <span v-if="!t.wajib" class="alr-subtes__opt">opsional</span>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                            <li v-if="!a.stages.length" class="alr-empty-stage">Belum ada tahap.</li>
                        </ol>
                </div>
            </div>
            <div v-if="!loading && !list.length" class="pkg-empty"><i class="bi bi-signpost-split"></i> {{ adaFilter ? 'Tidak ada alur yang cocok dengan filter.' : 'Belum ada alur.' }}</div>
        </div>

        <!-- Modal buat/ubah alur — builder tahapan -->
        <AdminModal :show="show" :title="editingId ? 'Ubah Alur Seleksi' : 'Buat Alur Seleksi'" subtitle="Identitas alur & susunan tahapan." icon="bi-signpost-split" lg :save-label="editingId ? 'Perbarui' : 'Simpan Alur'" @close="show = false" @save="save">
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
                            <div><label class="wca-field-lbl">Mode Keputusan</label>
                                <RefSelect type="mode-keputusan" v-model="s.mode" placeholder="Pilih mode" @picked="(o) => (s.modeInfo = o)" />
                                <small v-if="modeInfo(s)" class="alr-mode-note">
                                    <i class="bi" :class="modeInfo(s).ikon || 'bi-diagram-3'"></i> {{ modeInfo(s).deskripsi }}
                                </small>
                            </div>
                            <div v-if="butuhFormulir(s)"><label class="wca-field-lbl">Formulir yang Diisi</label>
                                <RefSelect type="formulir" v-model="s.formulirId" placeholder="Pilih formulir (Master Formulir)" clearable />
                            </div>
                        </div>

                        <!-- DAFTAR TES / AKTIVITAS — satu tahap bisa berisi banyak tes.
                             Ada Jenis Tes = tes HC Learn (otomatis); kosong = aktivitas
                             manual (wawancara/FGD). Peran INFORMATIF tidak menentukan lulus. -->
                        <div class="alr-tests">
                            <div class="alr-tests__head">
                                <span><i class="bi bi-list-check"></i> Daftar Tes / Aktivitas <template v-if="(s.tests || []).length">({{ s.tests.length }})</template><span v-else class="alr-tests__opt">— opsional</span></span>
                                <button class="wca-btn wca-btn--soft wca-btn--sm" type="button" @click="addTest(s)"><i class="bi bi-plus-circle"></i> Tambah Tes</button>
                            </div>
                            <div v-if="!(s.tests || []).length" class="alr-tests__empty">
                                <i class="bi bi-check-circle-fill"></i>
                                <div>
                                    <b>Biarkan kosong bila tahap ini hanya satu aktivitas.</b>
                                    Sistem otomatis menganggapnya 1 aktivitas bernama “{{ s.label || 'nama tahap' }}”.
                                    <br>Isi daftar ini <b>hanya</b> bila tahap berisi beberapa tes sekaligus — mis. FGD = Psikotes 1 + Psikotes 2 + Wawancara.
                                </div>
                            </div>
                            <div v-for="(t, k) in s.tests" :key="k" class="alr-test">
                                <span class="alr-test__no">{{ k + 1 }}</span>
                                <div class="alr-test__body">
                                    <div class="wca-frow">
                                        <div><label class="wca-field-lbl">Nama Tes / Aktivitas</label><el-input v-model="t.label" placeholder="mis. Papi Kostick" /></div>
                                        <div><label class="wca-field-lbl">Jenis Tes (kosong = manual)</label>
                                            <RefSelect type="tes" v-model="t.jenisTes" placeholder="Pilih jenis tes HC Learn" clearable />
                                        </div>
                                    </div>
                                    <div class="wca-frow">
                                        <div><label class="wca-field-lbl">Peran</label>
                                            <el-select v-model="t.peran" style="width:100%">
                                                <el-option label="Penentu — menentukan lulus/tidak" value="PENENTU" />
                                                <el-option label="Informatif — data saja, tidak menentukan" value="INFORMATIF" />
                                            </el-select>
                                        </div>
                                        <div class="alr-test__flags">
                                            <span class="alr-test__prov" :class="t.jenisTes ? 'is-sys' : 'is-man'">
                                                <i class="bi" :class="t.jenisTes ? 'bi-robot' : 'bi-person-workspace'"></i>
                                                {{ t.jenisTes ? 'Pihak ke-3 (otomatis)' : 'Internal (manual)' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus tes" @click="removeTest(s, k)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>

                        <!-- PENGUMUMAN HASIL — kapan hasil tahap ini boleh dilihat kandidat.
                             Yang disimpan di sini cuma ATURAN-nya; tanggal pastinya diisi
                             saat penjadwalan angkatan, karena satu alur dipakai banyak program. -->
                        <div class="alr-ann">
                            <div class="alr-ann__head"><i class="bi bi-megaphone"></i> Pengumuman Hasil</div>
                            <div class="wca-frow">
                                <div>
                                    <label class="wca-field-lbl">Kapan hasil terlihat kandidat</label>
                                    <!-- Opsi DIAMBIL DARI Master Mode Pengumuman (Flag_Aktif) — tak hardcode. -->
                                    <el-select v-model="s.pengumuman" style="width:100%" placeholder="Pilih mode" no-data-text="Tidak ada mode aktif">
                                        <el-option v-for="m in modePengumuman" :key="m.value" :label="m.label" :value="m.value" />
                                    </el-select>
                                </div>
                                <div v-if="modeButuhJeda(s.pengumuman)">
                                    <label class="wca-field-lbl">Saran jeda (hari)</label>
                                    <el-input-number v-model="s.jedaHari" :min="0" :max="3650" controls-position="right" style="width:100%" />
                                </div>
                            </div>
                            <label class="alr-ann__check">
                                <el-checkbox v-model="s.notifikasi">Beri tahu kandidat lewat email saat hasil terbit</el-checkbox>
                            </label>
                            <p class="alr-ann__note">{{ catatanPengumuman(s) }}</p>
                        </div>

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
            // Filter Panel — semua nilai dikirim ke backend saat berubah.
            filters: { q: '', kategori: null, status: null, rentang: null },
            sheetOpen: false,
            cariTimer: null,
            // Mode pengumuman AKTIF dari Master Mode Pengumuman (bukan hardcode).
            modePengumuman: [],
            // Mode keputusan AKTIF — bagaimana tahap menyimpulkan (multi-tes).
            modeKeputusan: [],
            form: { nama: '', kategori: '', deskripsi: '', stages: [] },
            delShow: false,
            delTarget: null,
            deleting: false,
            saving: false,
            toast: '',
            tm: null,
        };
    },
    watch: {
        // RefSelect hanya emit update:modelValue — pantau nilainya langsung.
        'filters.kategori'() { this.load(); },
    },
    mounted() {
        this.load();
        this.loadModePengumuman();
        this.loadModeKeputusan();
    },
    computed: {
        // Peta Kode Mode -> objek mode, untuk render label/ikon/catatan di daftar.
        modeMap() {
            const map = {};
            this.modePengumuman.forEach((m) => { map[m.value] = m; });
            return map;
        },
        adaFilter() {
            return !!(this.filters.q || this.filters.kategori || this.filters.status || (this.filters.rentang && this.filters.rentang.length));
        },
        jumlahFilter() {
            return [this.filters.q, this.filters.kategori, this.filters.status, this.filters.rentang?.length ? 1 : null].filter(Boolean).length;
        },
    },
    methods: {
        katLabel(k) { return { REKRUTMEN: 'Rekrutmen', MT: 'Management Trainee', INTERNSHIP: 'Internship' }[k] || k; },

        /** Tipe pengumpulan formulir/berkas menempelkan Master Formulir. */
        butuhFormulir(s) { return s.tipe === 'FORM' || s.tipe === 'DOCUMENT'; },

        /** Muat mode keputusan AKTIF (bagaimana tahap menyimpulkan). */
        async loadModeKeputusan() {
            try {
                this.modeKeputusan = (await axios.get('/api/v1/karir/options/mode-keputusan', CFG)).data.result || [];
            } catch (e) { this.modeKeputusan = []; }
        },
        /** Info mode terpilih (untuk kalimat efek di bawah dropdown). */
        modeInfo(s) { return this.modeKeputusan.find((m) => m.value === s.mode) || null; },
        /** Nama pendek mode untuk pil di daftar alur. */
        namaMode(kode) { return this.modeKeputusan.find((m) => m.value === kode)?.nama || kode || 'Manual'; },

        /**
         * Buang baris tes BAWAAN dari data yang dimuat untuk diedit.
         * Bawaan = tepat 1 tes, PENENTU, tanpa jenis tes, dan namanya sama
         * dengan nama tahap — persis yang dibuat backend saat daftar dikosongkan.
         * Tahap yang memang punya beberapa tes tidak tersentuh.
         */
        buangTesBawaan(s) {
            const t = s.tests || [];
            const bawaan = t.length === 1
                && !t[0].jenisTes
                && (t[0].peran || 'PENENTU') === 'PENENTU'
                && (t[0].label || '').trim() === (s.label || '').trim();

            return bawaan ? [] : t.map((x) => ({
                label: x.label,
                jenisTes: x.jenisTes ?? null,
                peran: x.peran || 'PENENTU',
                ambang: x.ambang ?? null,
            }));
        },
        addTest(s) {
            if (!Array.isArray(s.tests)) s.tests = [];
            s.tests.push({ label: '', jenisTes: null, peran: 'PENENTU', ambang: null });
        },
        removeTest(s, k) { s.tests.splice(k, 1); },

        /** Muat mode pengumuman AKTIF dari master (Flag_Aktif). */
        async loadModePengumuman() {
            try {
                const res = await axios.get('/api/v1/karir/options/mode-pengumuman', CFG);
                this.modePengumuman = res.data.result || [];
            } catch (e) { this.modePengumuman = []; }
        },
        /** Mode memakai input jeda hari? (mis. TERJADWAL) — dari flag master. */
        modeButuhJeda(kode) { return !!this.modeMap[kode]?.butuhJeda; },
        initials(name) {
            if (!name) return 'SY';
            const p = String(name).trim().split(/\s+/);
            return ((p[0]?.[0] || '') + (p[1]?.[0] || p[0]?.[1] || '')).toUpperCase() || 'SY';
        },
        async load() {
            this.loading = true;
            try {
                // Filter dikirim ke backend — daftar yang kembali sudah tersaring.
                const params = {
                    q: this.filters.q || undefined,
                    kategori: this.filters.kategori || undefined,
                    status: this.filters.status || undefined,
                    dari: this.filters.rentang?.[0] || undefined,
                    sampai: this.filters.rentang?.[1] || undefined,
                };
                const res = await axios.get(API, { ...CFG, params });
                this.list = res.data.result || [];
            } catch (e) {
                this.notice('Gagal memuat data alur.');
            } finally {
                this.loading = false;
            }
        },
        /** Ketik di kolom cari → tunggu 400ms lalu request backend. */
        cariDebounce() {
            if (this.cariTimer) clearTimeout(this.cariTimer);
            this.cariTimer = setTimeout(() => this.load(), 400);
        },
        resetFilter() {
            this.filters = { q: '', kategori: null, status: null, rentang: null };
            this.load();
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
                    mode: s.mode || 'MANUAL_REVIEW',
                    formulirId: s.formulirId ?? null,
                    // Baris tes BAWAAN (dibuat otomatis sistem untuk tahap satu
                    // aktivitas) sengaja TIDAK ditampilkan lagi saat mengedit —
                    // kalau ditampilkan, ia terlihat seolah wajib diisi dan
                    // namanya cuma menggandakan nama tahap. Daftar dibiarkan
                    // kosong; backend akan membuatkannya lagi saat disimpan.
                    tests: this.buangTesBawaan(s),
                    pengumuman: s.pengumuman || 'OTOMATIS',
                    jedaHari: s.jedaHari ?? null,
                    notifikasi: s.notifikasi !== false,
                })),
            };
            this.show = true;
        },
        addStage() { this.form.stages.push({ label: '', tipe: '', mode: 'MANUAL_REVIEW', formulirId: null, tests: [], pengumuman: 'OTOMATIS', jedaHari: null, notifikasi: true }); },
        removeStage(i) { this.form.stages.splice(i, 1); },

        // Label & ikon pil diambil dari master (fallback ke kode bila belum termuat).
        labelPengumuman(kode) { return this.modeMap[kode]?.nama || this.modeMap[kode]?.label || kode || 'Otomatis'; },
        ikonPengumuman(kode) { return this.modeMap[kode]?.ikon || 'bi-megaphone'; },

        /** Kalimat konsekuensi — deskripsi diambil dari master, jeda dari flag. */
        catatanPengumuman(s) {
            const notif = s.notifikasi !== false ? 'Kandidat diberi tahu lewat email.' : 'Kandidat TIDAK diberi tahu.';
            const mode = this.modeMap[s.pengumuman];
            const dasar = mode?.deskripsi || 'Atur kapan hasil tahap ini terlihat kandidat.';
            if (this.modeButuhJeda(s.pengumuman)) {
                const jeda = s.jedaHari != null && s.jedaHari !== '' ? ` Saat menjadwalkan angkatan, tanggal disarankan ${s.jedaHari} hari setelah tahap selesai — tetap bisa diubah.` : ' Tanggal pastinya diisi saat menjadwalkan angkatan.';
                return `${dasar}${jeda} ${notif}`;
            }
            return `${dasar} ${notif}`;
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
                    mode: s.mode || null,
                    // Provider tak dikirim — backend menurunkannya dari sub-tes.
                    formulirId: this.butuhFormulir(s) ? (s.formulirId || null) : null,
                    tests: (s.tests || []).filter((t) => (t.label || '').trim()).map((t) => ({
                        label: t.label,
                        jenisTes: t.jenisTes || null,
                        peran: t.peran || 'PENENTU',
                        ambang: t.ambang ?? null,
                    })),
                    pengumuman: s.pengumuman || 'OTOMATIS',
                    // Jeda hanya bermakna untuk mode ber-flag butuhJeda; lainnya null.
                    jedaHari: this.modeButuhJeda(s.pengumuman) ? (s.jedaHari ?? null) : null,
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

/* ── FILTER PANEL — card di desktop, bottom-sheet via FAB di mobile ── */
.alr-filter { background: #fff; border: 1px solid rgba(15, 23, 42, .08); border-radius: 16px; padding: .9rem 1rem 1rem; margin-bottom: 1rem; box-shadow: 0 8px 24px rgba(15, 23, 42, .04); }
.alr-filter__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .65rem; }
.alr-filter__title { display: inline-flex; align-items: center; gap: .45rem; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #4338ca; }
.alr-filter__act { display: inline-flex; align-items: center; gap: .4rem; }
.alr-filter__reset { display: inline-flex; align-items: center; gap: .3rem; border: 1px solid rgba(79, 70, 229, .25); background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; border-radius: 9px; padding: 4px 10px; cursor: pointer; }
.alr-filter__reset:hover { background: #e0e7ff; }
.alr-filter__close { display: none; border: none; background: transparent; color: #64748b; font-size: 15px; cursor: pointer; padding: 4px; }
.alr-filter__grid { display: grid; grid-template-columns: minmax(200px, 1.4fr) 1fr 1fr 1.4fr; gap: .7rem; align-items: end; }
@media (max-width: 960px) { .alr-filter__grid { grid-template-columns: 1fr 1fr; } }

/* FAB — hanya mobile */
.alr-fab { display: none; position: fixed; right: 18px; bottom: 20px; z-index: 70; width: 52px; height: 52px; border-radius: 50%; border: none; background: linear-gradient(135deg, #8b5cf6, #6366f1); color: #fff; font-size: 19px; cursor: pointer; box-shadow: 0 12px 28px rgba(99, 102, 241, .45); }
.alr-fab__badge { position: absolute; top: -4px; right: -4px; min-width: 19px; height: 19px; border-radius: 999px; background: #ef4444; color: #fff; font-size: 10.5px; font-weight: 800; display: grid; place-items: center; padding: 0 5px; border: 2px solid #fff; }
.alr-sheetbg { display: none; }

@media (max-width: 640px) {
    /* Panel disembunyikan; FAB membukanya sebagai bottom-sheet. */
    .alr-filter { display: none; }
    .alr-filter.is-open { display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 80; margin: 0; border-radius: 18px 18px 0 0; box-shadow: 0 -18px 40px rgba(15, 23, 42, .25); max-height: 78vh; overflow-y: auto; }
    .alr-filter__grid { grid-template-columns: 1fr; }
    .alr-filter__close { display: inline-flex; }
    .alr-fab { display: grid; place-items: center; }
    .alr-sheetbg { display: block; position: fixed; inset: 0; z-index: 75; background: rgba(15, 23, 42, .45); }
}
.alr-meta { margin-bottom: .8rem; }
.alr-empty-stage { color: #94a3b8; font-size: 13px; }

/* Kalimat efek mode keputusan di bawah dropdown-nya. */
.alr-mode-note { display: block; margin-top: .35rem; font-size: 11.5px; line-height: 1.5; color: #64748b; font-weight: 600; }

/* Blok daftar tes (sub-tes) di dalam kartu tahap pada modal builder. */
.alr-tests { border: 1px solid rgba(15, 23, 42, .1); border-radius: 12px; padding: .7rem .8rem; margin-top: .5rem; background: #f8fafc; }
.alr-tests__head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; font-size: 11.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: #334155; margin-bottom: .55rem; }
.alr-tests__opt { font-weight: 600; color: #94a3b8; text-transform: none; letter-spacing: 0; margin-left: .25rem; }
.alr-tests__empty { display: flex; align-items: flex-start; gap: .45rem; font-size: 11.5px; line-height: 1.6; color: #475569; padding: .5rem .6rem; border-radius: 9px; background: rgba(16, 185, 129, .07); border: 1px solid rgba(16, 185, 129, .2); }
.alr-tests__empty > i { color: #059669; font-size: 13px; margin-top: 1px; flex: none; }
.alr-test { display: flex; gap: .55rem; align-items: flex-start; padding: .6rem; border: 1px solid rgba(15, 23, 42, .08); border-radius: 10px; background: #fff; margin-bottom: .5rem; }
.alr-test__no { flex: none; width: 1.4rem; height: 1.4rem; border-radius: 50%; background: #e0e7ff; color: #4338ca; font-size: 11px; font-weight: 800; display: grid; place-items: center; }
.alr-test__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .45rem; }
.alr-test__flags { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; padding-top: 1.35rem; }
.alr-test__prov { display: inline-flex; align-items: center; gap: .3rem; font-size: 11px; font-weight: 700; }
.alr-test__prov.is-sys { color: #b45309; }
.alr-test__prov.is-man { color: #4338ca; }

/* Daftar sub-tes ringkas di tampilan daftar alur (read-only). */
.alr-subtes { list-style: none; margin: .5rem 0 0; padding: 0; display: flex; flex-direction: column; gap: .25rem; }
.alr-subtes li { display: flex; align-items: center; gap: .4rem; font-size: 11.5px; color: #475569; flex-wrap: wrap; }
.alr-subtes li.is-info { color: #64748b; }
.alr-subtes__tes { background: #eef2ff; color: #4338ca; border-radius: 999px; padding: .05rem .4rem; font-weight: 700; font-size: 10.5px; }
.alr-subtes__peran { background: #f1f5f9; color: #475569; border-radius: 999px; padding: .05rem .4rem; font-size: 10.5px; font-weight: 700; }
.alr-subtes li.is-info .alr-subtes__peran { background: #f8fafc; color: #94a3b8; }
.alr-subtes__opt { color: #94a3b8; font-size: 10.5px; font-style: italic; }
.alr-pill.is-mode { color: #4338ca; background: rgba(79, 70, 229, .1); }

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
