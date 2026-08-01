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
                                        <span v-if="s.tuntas" class="alr-pill is-tuntas" title="Tahap ini menutup proses — kandidat dinyatakan diterima di sini">
                                            <i class="bi bi-flag-fill"></i> Titik Tuntas
                                        </span>
                                        <span v-if="s.talentPool" class="alr-pill is-talent" title="Kandidat tak lolos di tahap ini bisa dialihkan ke Talent Pool">
                                            <i class="bi bi-stars"></i> Cut-off Talent Pool
                                        </span>
                                    </div>
                                    <!-- Sub-tes tahap: inilah yang dinilai mesin keputusan. -->
                                    <ul v-if="(s.tests || []).length" class="alr-subtes">
                                        <li v-for="(t, k) in s.tests" :key="k" :class="t.peran === 'INFORMATIF' ? 'is-info' : 'is-penentu'">
                                            <i class="bi" :class="tesOnline(t.tipe || s.tipe) ? 'bi-robot' : 'bi-person-workspace'"></i>
                                            <b>{{ t.label }}</b>
                                            <span class="alr-subtes__tes">{{ namaTipe(t.tipe || s.tipe) }}</span>
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
        <AdminModal :busy="saving" :show="show" :title="editingId ? 'Ubah Alur Seleksi' : 'Buat Alur Seleksi'" subtitle="Identitas alur & susunan tahapan." icon="bi-signpost-split" lg :save-label="editingId ? 'Perbarui' : 'Simpan Alur'" @close="show = false" @save="save">
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

                <!-- CUT-OFF TALENT POOL — SATU titik saja. Tahap dari sini sampai akhir
                     otomatis aktif; tak perlu klik per tahap. -->
                <div v-if="form.stages.length" class="alr-tpcut" :class="{ 'is-on': form.talentPoolMulai > 0 }">
                    <div class="alr-tpcut__l">
                        <span class="alr-tpcut__ico"><i class="bi bi-stars"></i></span>
                        <div class="alr-tpcut__txt">
                            <b>Cut-off ke Talent Pool</b>
                            <small>Kandidat yang <b>tidak lolos</b> mulai tahap terpilih <b>sampai tahap akhir</b> boleh disimpan ke Talent Pool. Pilih <b>satu</b> titik mulai — tahap sesudahnya otomatis ikut.</small>
                        </div>
                    </div>
                    <el-select v-model="form.talentPoolMulai" style="width:230px" placeholder="Nonaktif">
                        <el-option :value="0" label="Nonaktif — tanpa cut-off" />
                        <el-option v-for="(s, i) in form.stages" :key="i" :value="i + 1" :label="`Mulai Tahap ${i + 1}${s.label ? ' — ' + s.label : ''}`" />
                    </el-select>
                </div>

                <div v-for="(s, i) in form.stages" :key="i" class="wca-stagecard" :class="{ 'is-tp': tahapTalentPool(i) }">
                    <div class="wca-stagecard__num">{{ i + 1 }}</div>
                    <div class="wca-stagecard__body">
                        <div v-if="tahapTalentPool(i)" class="alr-tpflag"><i class="bi bi-stars"></i> Talent Pool aktif — kandidat tak lolos di tahap ini bisa disimpan (dari cut-off Tahap {{ form.talentPoolMulai }}).</div>
                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">Nama Tahap</label><el-input v-model="s.label" placeholder="mis. Psikotes Online" /></div>
                            <div><label class="wca-field-lbl">Tipe</label>
                                <!-- Ganti tipe bisa membuat mode keputusan yang dipilih
                                     tak berlaku lagi (mis. jadi ujian online) — selaraskan
                                     saat itu juga, jangan biarkan ketahuan saat menyimpan. -->
                                <RefSelect type="tipe" v-model="s.tipe" placeholder="Pilih tipe" @update:model-value="samakanMode(s)" />
                            </div>
                        </div>
                        <div v-if="butuhFormulir(s)" class="wca-frow">
                            <div><label class="wca-field-lbl">Formulir yang Diisi</label>
                                <RefSelect type="formulir" v-model="s.formulirId" placeholder="Pilih formulir (Master Formulir)" clearable />
                            </div>
                        </div>

                        <!-- DAFTAR TES / AKTIVITAS — satu tahap bisa berisi banyak tes.
                             Online atau manual ditentukan TIPE TAHAP di atas, bukan
                             per aktivitas: tipe "Tes Online" = semua aktivitasnya ujian
                             HC Learn yang dijadwalkan; tipe lain = ditangani tim.
                             Peran INFORMATIF tidak menentukan lulus. -->
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
                                        <div><label class="wca-field-lbl">Tipe Aktivitas</label>
                                            <RefSelect type="tipe" v-model="t.tipe" :placeholder="`Ikut tahap (${namaTipe(s.tipe)})`" clearable @update:model-value="samakanMode(s)" />
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
                                            <span class="alr-test__prov" :class="tesOnline(t.tipe || s.tipe) ? 'is-sys' : 'is-man'">
                                                <i class="bi" :class="tesOnline(t.tipe || s.tipe) ? 'bi-robot' : 'bi-person-workspace'"></i>
                                                {{ tesOnline(t.tipe || s.tipe) ? 'Ujian online — dijadwalkan di Penjadwalan' : 'Ditangani tim — hasilnya dicatat di Worklist' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <button class="wca-iconbtn wca-iconbtn--danger" type="button" title="Hapus tes" @click="removeTest(s, k)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>

                        <!-- MODE KEPUTUSAN — sengaja DI BAWAH daftar tes, karena ini
                             kesimpulan ATAS tes-tes di atasnya. Pilihannya menyesuaikan:
                             1 aktivitas → cukup "otomatis vs admin"; 2+ aktivitas →
                             aturan menunggu ikut berarti, jadi seluruh mode ditampilkan. -->
                        <div class="alr-dec" :class="{ 'is-multi': jumlahTes(s) >= 2 }">
                            <div class="alr-dec__head">
                                <i class="bi bi-signpost-2"></i>
                                <span>Cara tahap ini menyimpulkan</span>
                                <span class="alr-dec__count">{{ jumlahTes(s) }} aktivitas</span>
                            </div>

                            <el-select v-model="s.mode" style="width:100%" placeholder="Pilih cara menyimpulkan">
                                <el-option v-for="m in modeTampil(s)" :key="m.value" :label="labelMode(m, s)" :value="m.value" />
                            </el-select>

                            <p class="alr-dec__note">
                                <i class="bi" :class="modeInfo(s)?.ikon || 'bi-info-circle'"></i>
                                <span>{{ catatanMode(s) }}</span>
                            </p>
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

                        <!-- Cut-off Talent Pool kini SATU titik di atas (bukan per tahap). -->

                        <!-- UPLOAD BERKAS HASIL — mis. MCU (PDF/JPG dari requester) atau
                             hasil wawancara. Bisa diwajibkan atau opsional per tahap. -->
                        <div class="alr-tp alr-up" :class="{ 'is-on': s.uploadHasil }">
                            <div class="alr-tp__main">
                                <span class="alr-tp__ico"><i class="bi bi-paperclip"></i></span>
                                <div class="alr-tp__txt">
                                    <b>Upload berkas hasil (PDF/JPG)</b>
                                    <small>Aktifkan agar admin/requester mengunggah hasil di tahap ini (mis. MCU, hasil wawancara). <template v-if="s.uploadHasil">Centang <em>“wajib”</em> bila berkas harus ada sebelum Loloskan.</template></small>
                                </div>
                            </div>
                            <div class="alr-up__ctl">
                                <label v-if="s.uploadHasil" class="alr-up__wajib"><el-checkbox v-model="s.wajibUpload" /> wajib</label>
                                <el-switch v-model="s.uploadHasil" />
                            </div>
                        </div>

                        <!-- TITIK TUNTAS. Alur kerap memuat tahap administratif
                             SESUDAH kandidat sebenarnya sudah diterima (tanda
                             tangan kontrak, onboarding). Tanpa penanda ini
                             kandidat yang sudah memegang surat penawaran tetap
                             "Berjalan" dan kuota belum terpotong padahal
                             kursinya sudah terisi. -->
                        <div class="alr-tp" :class="{ 'is-on': s.tuntas }">
                            <div class="alr-tp__main">
                                <span class="alr-tp__ico"><i class="bi bi-flag-fill"></i></span>
                                <div class="alr-tp__txt">
                                    <b>Tahap ini menutup proses seleksi</b>
                                    <small>
                                        Begitu tahap ini diloloskan, kandidat langsung dinyatakan
                                        <b>DITERIMA</b> dan kuota terpotong. Tahap sesudahnya tetap
                                        dikerjakan (mis. tanda tangan kontrak, onboarding) tapi tidak
                                        lagi menentukan diterima atau tidaknya.
                                    </small>
                                </div>
                            </div>
                            <el-switch v-model="s.tuntas" @change="hanyaSatuTuntas(i)" />
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
            // Tipe tahap + perilakunya ('CAT' = ujian online berjadwal).
            tipeTahap: [],
            // talentPoolMulai: 0 = nonaktif; N = cut-off Talent Pool mulai tahap ke-N (sampai akhir).
            form: { nama: '', kategori: '', deskripsi: '', stages: [], talentPoolMulai: 0 },
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
        // Jaga cut-off tetap valid saat jumlah tahap berubah (mis. tahap dihapus).
        'form.stages.length'(n) { if (this.form.talentPoolMulai > n) this.form.talentPoolMulai = n; },
    },
    mounted() {
        this.load();
        this.loadModePengumuman();
        this.loadModeKeputusan();
        this.loadTipeTahap();
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

        /** Info satu tipe dari master (bukan daftar kode yang ditulis di sini). */
        infoTipe(kode) { return this.tipeTahap.find((t) => t.value === kode) || null; },
        namaTipe(kode) { return this.infoTipe(kode)?.label || kode || '—'; },

        /**
         * Tipe ini menempelkan Master Formulir? — dari flag master, bukan daftar
         * kode. Dulu di sini tertulis `tipe === 'FORM' || tipe === 'DOCUMENT'`,
         * sehingga tipe baru yang juga berbasis formulir tak akan pernah bisa
         * memilih formulirnya tanpa mengubah file ini.
         */
        butuhFormulir(s) { return this.infoTipe(s.tipe)?.formulir === true; },

        /**
         * Aktivitas bertipe ini ujian online (CAT)? — dari Perilaku di Master
         * Tipe Tahap. Menentukan aktivitas itu dijadwalkan lewat Penjadwalan
         * (token HCLearn) atau dikerjakan tim & dicatat di Worklist.
         */
        tesOnline(kode) { return this.infoTipe(kode)?.perilaku === 'CAT'; },

        /** Muat tipe tahap AKTIF beserta perilaku & flag-nya. */
        async loadTipeTahap() {
            try {
                this.tipeTahap = (await axios.get('/api/v1/karir/options/tipe', CFG)).data.result || [];
            } catch (e) { this.tipeTahap = []; }
        },

        /** Muat mode keputusan AKTIF (bagaimana tahap menyimpulkan). */
        async loadModeKeputusan() {
            try {
                this.modeKeputusan = (await axios.get('/api/v1/karir/options/mode-keputusan', CFG)).data.result || [];
            } catch (e) { this.modeKeputusan = []; }
        },
        /** Info mode terpilih (untuk kalimat efek di bawah dropdown). */
        modeInfo(s) { return this.modeKeputusan.find((m) => m.value === s.mode) || null; },

        /** Jumlah aktivitas nyata: daftar kosong tetap dihitung 1 (dibuat sistem). */
        jumlahTes(s) { return Math.max(1, (s.tests || []).length); },

        /** Tahap ini memuat aktivitas ujian online? (daftar kosong = ikut tipe tahap) */
        adaUjianOnline(s) {
            const t = s.tests || [];

            return t.length ? t.some((x) => this.tesOnline(x.tipe || s.tipe)) : this.tesOnline(s.tipe);
        },

        /**
         * Pilihan mode yang MASUK AKAL untuk tahap ini.
         *
         * Dua penyaringan, keduanya berdasar KOLOM PERILAKU mode (bukan kodenya):
         *
         * 1. Tahap ber-UJIAN ONLINE tidak boleh memakai mode yang gagalnya
         *    menggantung (`autoGugur` mati). Hasil CAT sudah final & objektif;
         *    membiarkan kandidat yang jelas gagal menunggu keputusan admin cuma
         *    menumpuk antrean. Server juga menolaknya — menawarkannya di sini
         *    berarti menjanjikan sesuatu yang akan diam-diam diubah saat disimpan.
         *
         * 2. Dengan 1 aktivitas, "tunggu semua" dan "tunggu tes terakhir" berakhir
         *    sama persis. Maka mode dikelompokkan per PASANGAN perilaku yang
         *    benar-benar berbeda — maju-otomatis dan gugur-otomatis — dan tiap
         *    pasangan diwakili satu. Dulu pengelompokannya hanya melihat
         *    maju-otomatis, sehingga mode "gagal otomatis gugur, lulus tetap
         *    dikonfirmasi admin" tak pernah muncul di layar sama sekali.
         */
        modeTampil(s) {
            const online = this.adaUjianOnline(s);
            const layak = this.modeKeputusan.filter((m) => !online || m.autoGugur);
            if (this.jumlahTes(s) >= 2) return layak;

            const wakil = [];
            for (const m of layak) {
                if (!wakil.some((w) => w.autoLanjut === m.autoLanjut && w.autoGugur === m.autoGugur)) wakil.push(m);
            }

            return wakil;
        },

        /** Label dropdown: disederhanakan saat tahap hanya satu aktivitas. */
        labelMode(m, s) {
            if (this.jumlahTes(s) >= 2) return m.label;
            if (m.autoLanjut) return 'Otomatis — lulus maju sendiri, gagal langsung tidak lolos';

            return m.autoGugur
                ? 'Gagal otomatis tidak lolos — kelulusan dikonfirmasi admin'
                : 'Manual — admin yang memutuskan lulus maupun gagal';
        },

        /** Kalimat akibat, ditulis mengikuti jumlah aktivitas nyata di tahap ini. */
        catatanMode(s) {
            const m = this.modeInfo(s);
            if (!m) return 'Pilih bagaimana tahap ini menyimpulkan hasil.';

            const n = this.jumlahTes(s);
            if (n < 2) {
                if (m.autoLanjut) {
                    return 'Begitu aktivitas ini selesai: lulus → kandidat langsung maju ke tahap berikutnya, gagal → langsung dinyatakan tidak lolos. Admin tidak mengetuk palu di tahap ini.';
                }

                return m.autoGugur
                    ? 'Gagal → kandidat langsung dinyatakan tidak lolos tanpa menunggu admin. Lulus → kandidat TETAP di tahap ini sampai admin menekan Loloskan; di portalnya tertulis hasil sedang ditinjau.'
                    : 'Setelah aktivitas ini selesai, tahap ditandai SIAP DIPUTUS — admin yang menekan lolos/tolak, baik untuk yang lulus maupun yang gagal.';
            }

            const tunggu = { SEMUA: `menunggu ${n} aktivitas selesai`, TERAKHIR: 'menunggu aktivitas terakhir selesai', SEGERA: 'dievaluasi begitu hasil pertama masuk' }[m.tunggu] || 'dievaluasi';
            const lanjut = m.autoLanjut ? 'lalu maju otomatis' : 'lalu menunggu keputusan admin';
            const gugur = m.autoGugur ? ' Ada tes penentu gagal → kandidat langsung gugur.' : '';

            return `Tahap ${tunggu}, ${lanjut}.${gugur}`;
        },

        /**
         * Jaga agar mode terpilih selalu ada di daftar yang ditampilkan.
         * Dipanggil saat tes ditambah/dihapus — mis. admin memilih "tunggu tes
         * terakhir" (hanya masuk akal untuk 2+ tes) lalu menghapus tesnya.
         */
        samakanMode(s) {
            const boleh = this.modeTampil(s);
            if (boleh.some((m) => m.value === s.mode)) return;

            // Mode lama tak lagi ditawarkan (mis. tipe tahap diubah jadi Tes
            // Online, sehingga mode yang gagalnya menggantung tak berlaku lagi).
            // Pilih penggantinya yang PERILAKU MAJU-nya sama, bukan sekadar
            // yang pertama di daftar — jatuh ke "maju otomatis" tanpa diminta
            // akan diam-diam mengubah arti tahap yang sudah disusun admin.
            const lama = this.modeKeputusan.find((m) => m.value === s.mode);
            const sepadan = lama ? boleh.find((m) => m.autoLanjut === lama.autoLanjut) : null;
            s.mode = (sepadan || boleh[0])?.value || null;
        },
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
                && (t[0].peran || 'PENENTU') === 'PENENTU'
                && (t[0].tipe || s.tipe) === s.tipe
                && (t[0].label || '').trim() === (s.label || '').trim();

            return bawaan ? [] : t.map((x) => ({
                label: x.label,
                // Kosongkan bila sama dengan tahap → tampil sebagai "ikut tahap".
                tipe: x.tipe && x.tipe !== s.tipe ? x.tipe : null,
                peran: x.peran || 'PENENTU',
                ambang: x.ambang ?? null,
            }));
        },
        addTest(s) {
            if (!Array.isArray(s.tests)) s.tests = [];
            s.tests.push({ label: '', tipe: null, peran: 'PENENTU', ambang: null });
            this.samakanMode(s);
        },
        removeTest(s, k) {
            s.tests.splice(k, 1);
            this.samakanMode(s);
        },

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
            this.form = { nama: '', kategori: '', deskripsi: '', stages: [], talentPoolMulai: 0 };
            this.show = true;
        },
        openEdit(a) {
            this.editingId = a.id;
            const stages = (a.stages || []).map((s) => ({
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
                talentPool: s.talentPool === true,
                uploadHasil: s.uploadHasil === true,
                wajibUpload: s.wajibUpload === true,
                tuntas: s.tuntas === true,
            }));
            // Turunkan titik cut-off dari data: tahap PERTAMA yang talentPool aktif.
            const idx = stages.findIndex((s) => s.talentPool);
            this.form = {
                nama: a.nama,
                kategori: a.kategori,
                deskripsi: a.deskripsi || '',
                stages,
                talentPoolMulai: idx >= 0 ? idx + 1 : 0,
            };
            this.show = true;
        },
        /** Tahap ke-i (0-based) termasuk cut-off Talent Pool? (dari titik mulai sampai akhir). */
        tahapTalentPool(i) { return this.form.talentPoolMulai > 0 && (i + 1) >= this.form.talentPoolMulai; },
        /**
         * Titik tuntas hanya boleh SATU per alur.
         *
         * Dua titik tuntas berarti dua momen "kandidat diterima" yang saling
         * bertentangan, dan kuota akan terpotong pada yang mana pun lebih dulu
         * dilewati — tidak bisa ditebak. Menyalakan yang baru mematikan yang lama.
         */
        hanyaSatuTuntas(idx) {
            if (!this.form.stages[idx]?.tuntas) return;

            this.form.stages.forEach((s, i) => {
                if (i !== idx) s.tuntas = false;
            });
        },
        addStage() { this.form.stages.push({ label: '', tipe: '', mode: 'MANUAL_REVIEW', formulirId: null, tests: [], pengumuman: 'OTOMATIS', jedaHari: null, notifikasi: true, uploadHasil: false, wajibUpload: false, tuntas: false }); },
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
                stages: this.form.stages.map((s, i) => ({
                    label: s.label,
                    tipe: s.tipe,
                    mode: s.mode || null,
                    // Provider tak dikirim — backend menurunkannya dari sub-tes.
                    formulirId: this.butuhFormulir(s) ? (s.formulirId || null) : null,
                    // Tiap aktivitas membawa TIPE-nya sendiri; kosong = ikut tahap.
                    tests: (s.tests || []).filter((t) => (t.label || '').trim()).map((t) => ({
                        label: t.label,
                        tipe: t.tipe || null,
                        peran: t.peran || 'PENENTU',
                        ambang: t.ambang ?? null,
                    })),
                    pengumuman: s.pengumuman || 'OTOMATIS',
                    // Jeda hanya bermakna untuk mode ber-flag butuhJeda; lainnya null.
                    jedaHari: this.modeButuhJeda(s.pengumuman) ? (s.jedaHari ?? null) : null,
                    notifikasi: s.notifikasi !== false,
                    // Cut-off Talent Pool DITURUNKAN dari satu titik (talentPoolMulai):
                    // tahap ke-N sampai akhir → 'Y'. Tak lagi per-tahap manual.
                    talentPool: this.tahapTalentPool(i),
                    uploadHasil: s.uploadHasil === true,
                    wajibUpload: s.wajibUpload === true,
                tuntas: s.tuntas === true,
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

/* Blok "cara tahap menyimpulkan" — diletakkan SETELAH daftar tes karena ia
   adalah kesimpulan atas tes-tes tersebut. Diberi nada indigo agar terbaca
   sebagai keputusan, bukan sekadar isian tambahan. */
.alr-dec { margin-top: .55rem; padding: .7rem .8rem; border-radius: 12px; border: 1px solid rgba(79, 70, 229, .18); background: linear-gradient(180deg, rgba(79, 70, 229, .05), rgba(124, 58, 237, .04)); }
.alr-dec.is-multi { border-color: rgba(79, 70, 229, .34); box-shadow: 0 4px 14px rgba(79, 70, 229, .08); }
.alr-dec__head { display: flex; align-items: center; gap: .4rem; font-size: 11.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: #4338ca; margin-bottom: .5rem; }
.alr-dec__count { margin-left: auto; text-transform: none; letter-spacing: 0; font-size: 10.5px; font-weight: 700; color: #4338ca; background: rgba(79, 70, 229, .1); border-radius: 999px; padding: 1px 8px; }
.alr-dec__note { display: flex; align-items: flex-start; gap: .4rem; margin: .5rem 0 0; font-size: 11.5px; line-height: 1.55; color: #64748b; }
.alr-dec__note > i { color: #4338ca; margin-top: 1px; flex: none; }

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
.alr-pill.is-tuntas { color: #059669; background: rgba(16, 185, 129, .12); }
.alr-pill.is-talent { color: #a16207; background: rgba(234, 179, 8, .16); }

/* Kartu switch Cut-off Talent Pool di dalam editor tahap. Netral saat mati,
   menyala kuning-emas saat aktif agar terbaca sebagai "jalur khusus". */
.alr-tp { display: flex; align-items: center; gap: .8rem; justify-content: space-between; margin-top: .5rem; padding: .7rem .8rem; border-radius: 12px; border: 1px dashed rgba(15, 23, 42, .16); background: #f8fafc; transition: background .2s, border-color .2s; }
.alr-tp.is-on { border-style: solid; border-color: rgba(234, 179, 8, .5); background: linear-gradient(180deg, rgba(234, 179, 8, .1), rgba(245, 158, 11, .05)); }
.alr-tp__main { display: flex; align-items: flex-start; gap: .55rem; min-width: 0; }
.alr-tp__ico { flex: none; width: 1.9rem; height: 1.9rem; border-radius: 9px; display: grid; place-items: center; background: #fff7ed; color: #d97706; font-size: 15px; border: 1px solid rgba(234, 179, 8, .3); }
.alr-tp.is-on .alr-tp__ico { background: #fde68a; color: #92400e; }
.alr-tp__txt { min-width: 0; }
.alr-tp__txt b { display: block; font-size: 12.5px; color: #1e293b; }
.alr-tp__txt small { display: block; font-size: 11px; line-height: 1.5; color: #64748b; margin-top: .1rem; }
.alr-tp__txt em { color: #b45309; font-style: normal; font-weight: 700; }

/* Cut-off Talent Pool — SATU titik di atas daftar tahap. */
.alr-tpcut { display: flex; align-items: center; justify-content: space-between; gap: .9rem; padding: .8rem .9rem; border-radius: 13px; border: 1px solid rgba(15, 23, 42, .12); background: #f8fafc; margin: 0 0 .8rem; flex-wrap: wrap; }
.alr-tpcut.is-on { border-color: rgba(217, 119, 6, .5); background: linear-gradient(180deg, rgba(234, 179, 8, .1), rgba(245, 158, 11, .04)); }
.alr-tpcut__l { display: flex; align-items: flex-start; gap: .55rem; min-width: 0; flex: 1; }
.alr-tpcut__ico { flex: none; width: 2rem; height: 2rem; border-radius: 9px; display: grid; place-items: center; background: #fff7ed; color: #d97706; font-size: 15px; border: 1px solid rgba(234, 179, 8, .3); }
.alr-tpcut.is-on .alr-tpcut__ico { background: #fde68a; color: #92400e; }
.alr-tpcut__txt { min-width: 0; }
.alr-tpcut__txt b { display: block; font-size: 13px; color: #1e293b; }
.alr-tpcut__txt small { display: block; font-size: 11px; line-height: 1.5; color: #64748b; margin-top: .1rem; }
/* Penanda tahap yang tercakup cut-off (read-only, otomatis). */
.wca-stagecard.is-tp { border-color: rgba(234, 179, 8, .45); box-shadow: 0 0 0 1px rgba(234, 179, 8, .18); }
.alr-tpflag { display: flex; align-items: center; gap: .4rem; font-size: 11px; font-weight: 700; color: #a16207; background: rgba(234, 179, 8, .14); border-radius: 8px; padding: .4rem .6rem; margin-bottom: .5rem; }
.alr-tpflag > i { color: #d97706; }
/* Kartu upload hasil — nada biru saat aktif (beda dari talent pool yang kuning). */
.alr-up.is-on { border-color: rgba(79, 70, 229, .45); background: linear-gradient(180deg, rgba(79, 70, 229, .07), rgba(99, 102, 241, .03)); }
.alr-up .alr-tp__ico { background: #eef2ff; color: #4f46e5; border-color: rgba(79, 70, 229, .3); }
.alr-up.is-on .alr-tp__ico { background: #c7d2fe; color: #3730a3; }
.alr-up__ctl { display: inline-flex; align-items: center; gap: .7rem; flex: none; }
.alr-up__wajib { display: inline-flex; align-items: center; gap: .3rem; font-size: 11.5px; font-weight: 700; color: #4338ca; white-space: nowrap; }
@media (max-width: 560px) {
    .wca-frow { grid-template-columns: 1fr; }
}
</style>
