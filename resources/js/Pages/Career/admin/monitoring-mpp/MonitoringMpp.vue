<!--
  Monitoring MPP (read-only) — Card Grid / Table List, klik → off-canvas detail.
  Search, filter, sort, & pagination dikerjakan di server (/api/v1/monitoring-mpp); search di-debounce.
-->
<template>
    <Head><title>Monitoring MPP - Web Career</title></Head>
    <div class="wca">
        <!-- Header -->
        <div class="wca-phead">
            <div>
                <h1>Monitoring MPP</h1>
                <p>Pantau seluruh permintaan Manpower Planning (MPP) beserta status &amp; kelengkapannya.</p>
            </div>
            <div class="wca-phead__actions">
                <div class="mpp-toggle" role="group" aria-label="Ubah tampilan">
                    <button class="wca-iconbtn" :class="{ 'is-active': view === 'grid' }" title="Tampilan kartu" :aria-pressed="view === 'grid'" @click="setView('grid')"><i class="bi bi-grid-3x3-gap-fill"></i></button>
                    <button class="wca-iconbtn" :class="{ 'is-active': view === 'table' }" title="Tampilan tabel" :aria-pressed="view === 'table'" @click="setView('table')"><i class="bi bi-list-ul"></i></button>
                </div>
            </div>
        </div>

        <div class="wca-stats">
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-clipboard-data"></i></span></div><div class="wca-stat__num">{{ stats.total }}</div><div class="wca-stat__label">Total MPP</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(16,185,129,.12);color:#059669"><i class="bi bi-play-circle"></i></span></div><div class="wca-stat__num">{{ stats.aktif }}</div><div class="wca-stat__label">Aktif</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(34,197,94,.12);color:#15803d"><i class="bi bi-check-circle"></i></span></div><div class="wca-stat__num">{{ stats.selesai }}</div><div class="wca-stat__label">Selesai</div></div>
            <div class="wca-stat"><div class="wca-stat__top"><span class="wca-stat__ico" style="background:rgba(239,68,68,.12);color:#b91c1c"><i class="bi bi-x-circle"></i></span></div><div class="wca-stat__num">{{ stats.dibatalkan }}</div><div class="wca-stat__label">Dibatalkan</div></div>
        </div>

        <div class="wca-toolbar">
            <div class="wca-search2">
                <i class="bi" :class="loading && ready ? 'bi-arrow-repeat mpp-spin' : 'bi-search'"></i>
                <input v-model="q" type="text" placeholder="Cari No Transaksi / Jabatan / Divisi…" @input="onSearchInput" />
            </div>
            <el-select v-model="fStatus" placeholder="Status" clearable class="mpp-filter" @change="reload">
                <el-option v-for="s in ['Aktif', 'Dibatalkan']" :key="s" :label="s" :value="s" />
            </el-select>
            <el-select v-model="fFlag" placeholder="Flag Selesai" clearable class="mpp-filter" @change="reload">
                <el-option v-for="f in ['Selesai', 'Belum Selesai']" :key="f" :label="f" :value="f" />
            </el-select>
            <el-select v-model="fDivisi" placeholder="Divisi" clearable filterable class="mpp-filter" @change="reload">
                <el-option v-for="d in divisiOptions" :key="d" :label="titleCase(d)" :value="d" />
            </el-select>
            <el-select v-model="fPeriode" placeholder="Periode" clearable class="mpp-filter" @change="reload">
                <el-option v-for="p in periodeOptions" :key="p" :label="periodeLabel(p)" :value="p" />
            </el-select>
        </div>

        <!-- Skeleton hanya saat load pertama; refetch berikutnya memakai dim halus (.mpp-busy). -->
        <template v-if="loading && !ready">
            <div v-if="view === 'grid'" class="mpp-grid">
                <div v-for="n in perPage" :key="n" class="mpp-skcard"></div>
            </div>
            <div v-else class="wca-card"><div class="wca-card__body--flush"><div class="mpp-skrows"><div v-for="n in perPage" :key="n" class="mpp-skrow"></div></div></div></div>
        </template>

        <div v-else-if="error" class="wca-card"><div class="wca-empty">
            <i class="bi bi-wifi-off"></i>
            <h4>Gagal memuat data MPP</h4>
            <button class="wca-btn wca-btn--primary wca-btn--sm" @click="fetchList"><i class="bi bi-arrow-clockwise"></i> Coba lagi</button>
        </div></div>

        <div v-else-if="!list.length" class="wca-card"><div class="wca-empty">
            <i class="bi bi-inbox"></i>
            <h4>{{ hasFilter ? 'Tidak ada MPP yang cocok dengan filter' : 'Belum ada data MPP' }}</h4>
            <button v-if="hasFilter" class="wca-btn wca-btn--ghost wca-btn--sm" @click="resetFilter"><i class="bi bi-x-circle"></i> Reset filter</button>
        </div></div>

        <template v-else>
            <div v-if="view === 'grid'" class="mpp-grid" :class="{ 'mpp-busy': loading }">
                <MppCard v-for="m in list" :key="m.no_transaksi" :mpp="m" @open="openDetail" />
            </div>

            <div v-else class="wca-card" :class="{ 'mpp-busy': loading }">
                <div class="wca-card__body--flush">
                    <div class="wca-tablewrap">
                        <table class="wca-table mpp-table">
                            <thead>
                                <tr>
                                    <th>No Transaksi</th>
                                    <th>Jabatan</th>
                                    <th>Divisi</th>
                                    <th>Sub Divisi</th>
                                    <th>Level</th>
                                    <th>Tipe Kerja</th>
                                    <th>Lokasi Kerja</th>
                                    <th>Exp. Level</th>
                                    <th class="mpp-num">Jml</th>
                                    <th class="mpp-sortable" @click="toggleSort('status')">Status <i class="bi" :class="sortIcon('status')"></i></th>
                                    <th>Flag Selesai</th>
                                    <th class="mpp-sortable" @click="toggleSort('tanggal_periode')">Periode <i class="bi" :class="sortIcon('tanggal_periode')"></i></th>
                                    <th>Penanggung Jawab</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in list" :key="m.no_transaksi" style="cursor:pointer" @click="openDetail(m.no_transaksi)">
                                    <td><span class="mpp-no">{{ m.no_transaksi }}</span></td>
                                    <td><strong>{{ titleCase(m.jabatan) }}</strong></td>
                                    <td>{{ titleCase(m.divisi) }}</td>
                                    <td>{{ titleCase(m.sub_divisi) }}</td>
                                    <td>{{ titleCase(m.level) }}</td>
                                    <td><span v-if="m.employment_type" class="mpp-tb-badge mpp-tb--emp">{{ m.employment_type }}</span><span v-else>—</span></td>
                                    <td><span v-if="m.workplace_type" class="mpp-tb-badge mpp-tb--wp">{{ m.workplace_type }}</span><span v-else>—</span></td>
                                    <td><span v-if="m.experience_level" class="mpp-tb-badge mpp-tb--exp">{{ m.experience_level }}</span><span v-else>—</span></td>
                                    <td class="mpp-num"><span class="wca-badge wca-b--slate"><i class="bi bi-people-fill"></i> {{ m.jumlah_rekruitmen }}</span></td>
                                    <td><span class="wca-badge" :class="statusBadge(m.status)">{{ m.status }}</span></td>
                                    <td><span class="mpp-flag" :class="{ 'is-done': isSelesai(m.flag_selesai) }"><span class="mpp-flag__dot"></span>{{ m.flag_selesai }}</span></td>
                                    <td>{{ formatTanggal(m.tanggal_periode) }}</td>
                                    <td>
                                        <div class="wca-table__name">
                                            <span class="wca-avatar wca-avatar--sm">{{ initials(m.penanggung_jawab) }}</span>
                                            <span>{{ namaLengkap(m.penanggung_jawab) }}</span>
                                        </div>
                                    </td>
                                    <td><button class="wca-iconbtn" title="Lihat detail" @click.stop="openDetail(m.no_transaksi)"><i class="bi bi-eye"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mpp-pagerow">
                <span class="mpp-count">Menampilkan {{ list.length }} dari {{ totalData }} MPP</span>
                <Pagination :current-page="page" :total-pages="totalPages" @page-change="changePage" />
            </div>
        </template>

        <MppDetailPanel :no="selectedNo" @close="selectedNo = null" />
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import MppCard from './MppCard.vue';
import MppDetailPanel from './MppDetailPanel.vue';
import Pagination from '../../../../components/ui/Pagination.vue';
import { formatTanggal, namaLengkap, initials, statusBadge, flagBadge, isSelesai, titleCase } from './mppHelpers';

const API = '/api/v1/monitoring-mpp';
const CFG = { headers: { Accept: 'application/json' } };
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const PAGE_SIZE = 9;
const SEARCH_DEBOUNCE = 400;

export default {
    components: { Head, MppCard, MppDetailPanel, Pagination },
    data() {
        return {
            list: [],
            loading: false,
            ready: false,
            error: false,
            q: '',
            fStatus: '',
            fFlag: '',
            fDivisi: '',
            fPeriode: '',
            view: 'grid',
            sortKey: 'tanggal_periode',
            sortDir: 'desc',
            page: 1,
            perPage: PAGE_SIZE,
            totalPages: 1,
            totalData: 0,
            divisiOptions: [],
            periodeOptions: [],
            stats: { total: 0, aktif: 0, selesai: 0, dibatalkan: 0 },
            selectedNo: null,
            searchTimer: null,
            reqSeq: 0, // cegah balasan out-of-order menimpa hasil terbaru
        };
    },
    computed: {
        hasFilter() {
            return !!(this.q.trim() || this.fStatus || this.fFlag || this.fDivisi || this.fPeriode);
        },
    },
    mounted() {
        this.fetchOptions();
        this.fetchList();
    },
    beforeUnmount() {
        if (this.searchTimer) clearTimeout(this.searchTimer);
    },
    methods: {
        formatTanggal,
        namaLengkap,
        initials,
        statusBadge,
        flagBadge,
        isSelesai,
        titleCase,
        periodeLabel(v) {
            const [y, m] = String(v).split('-');
            return `${BULAN[parseInt(m, 10) - 1] || m} ${y}`;
        },
        async fetchOptions() {
            try {
                const res = await axios.get(`${API}/options`, CFG);
                const r = res.data.result || {};
                this.divisiOptions = r.divisi || [];
                this.periodeOptions = r.periode || [];
                this.stats = r.stats || this.stats;
            } catch (e) {
                // Opsi filter gagal dimuat — biarkan kosong, list utama tetap jalan.
            }
        },
        async fetchList() {
            const seq = ++this.reqSeq;
            this.loading = true;
            this.error = false;
            const params = {
                page: this.page,
                per_page: this.perPage,
                sort: this.sortKey,
                dir: this.sortDir,
            };
            const s = this.q.trim();
            if (s) params.search = s;
            if (this.fStatus) params.status = this.fStatus;
            if (this.fFlag) params.flag = this.fFlag;
            if (this.fDivisi) params.divisi = this.fDivisi;
            if (this.fPeriode) params.periode = this.fPeriode;

            try {
                const res = await axios.get(API, { ...CFG, params });
                if (seq !== this.reqSeq) return; // sudah ada request lebih baru
                this.list = res.data.result || [];
                this.totalPages = res.data.total_page || 1;
                this.totalData = res.data.total_data || 0;
                // Jika halaman sekarang melebihi total (mis. setelah filter), tarik ke halaman terakhir.
                if (this.page > this.totalPages) {
                    this.page = this.totalPages;
                    return this.fetchList();
                }
            } catch (e) {
                if (seq !== this.reqSeq) return;
                this.error = true;
                this.list = [];
            } finally {
                if (seq === this.reqSeq) {
                    this.loading = false;
                    this.ready = true;
                }
            }
        },
        onSearchInput() {
            if (this.searchTimer) clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.reload(), SEARCH_DEBOUNCE);
        },
        // Search/filter/sort berubah → selalu balik ke halaman 1.
        reload() {
            this.page = 1;
            this.fetchList();
        },
        changePage(p) {
            if (p === this.page) return;
            this.page = p;
            this.fetchList();
        },
        setView(v) {
            this.view = v;
        },
        toggleSort(key) {
            if (this.sortKey === key) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDir = 'asc';
            }
            this.reload();
        },
        sortIcon(key) {
            if (this.sortKey !== key) return 'bi-chevron-expand mpp-sort-idle';
            return this.sortDir === 'asc' ? 'bi-chevron-up' : 'bi-chevron-down';
        },
        openDetail(no) {
            this.selectedNo = no;
        },
        resetFilter() {
            this.q = '';
            this.fStatus = '';
            this.fFlag = '';
            this.fDivisi = '';
            this.fPeriode = '';
            this.reload();
        },
    },
};
</script>

<style scoped>
/* Toggle grid/table */
.mpp-toggle {
    display: inline-flex;
    gap: 0.3rem;
    background: #f1f5f9;
    padding: 0.25rem;
    border-radius: 0.7rem;
}
.mpp-toggle .wca-iconbtn.is-active {
    background: linear-gradient(120deg, #4f46e5, #7c3aed);
    color: #fff;
    border-color: transparent;
}

/* Filter select — samakan tinggi & radius dengan kotak search (.wca-search2 = 2.5rem). */
.mpp-filter {
    flex: 0 1 160px;
    min-width: 130px;
}
.mpp-filter :deep(.el-select__wrapper) {
    min-height: 2.5rem;
    border-radius: 0.7rem;
}

/* Spinner kecil di kotak search saat refetch */
.mpp-spin {
    display: inline-block;
    animation: mppSpin 0.8s linear infinite;
}
@keyframes mppSpin {
    to {
        transform: rotate(360deg);
    }
}

/* Card grid responsif: 3 kolom desktop / 2 tablet / 1 mobile */
.mpp-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 1024px) {
    .mpp-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .mpp-grid {
        grid-template-columns: 1fr;
    }
}

/* Dim halus saat refetch (paging/filter) tanpa mengganti skeleton */
.mpp-busy {
    opacity: 0.55;
    pointer-events: none;
    transition: opacity 0.15s ease;
}

/* Baris pagination + info jumlah */
.mpp-pagerow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.mpp-pagerow :deep(.pagination-container) {
    border-top: none;
    padding: 1rem 0;
}
.mpp-count {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--muted);
}

/* Table */
.mpp-no {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.78rem;
    font-weight: 800;
    color: #4338ca;
}
.mpp-num {
    text-align: center;
}
.mpp-table th.mpp-sortable {
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}
.mpp-table th.mpp-sortable:hover {
    color: var(--primary);
}
.mpp-sort-idle {
    opacity: 0.4;
}
.wca-table__name {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    white-space: nowrap;
}

/* Flag selesai (dipakai di tabel) */
.mpp-flag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    font-weight: 800;
    color: #94a3b8;
    white-space: nowrap;
}
.mpp-flag__dot {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 50%;
    background: #cbd5e1;
    flex: none;
}
.mpp-flag.is-done {
    color: #15803d;
}
.mpp-flag.is-done .mpp-flag__dot {
    background: #22c55e;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18);
}

/* Table badges (Employment / Workplace / ExpLevel) — ikut warna card */
.mpp-tb-badge {
    display: inline-block;
    padding: .2rem .55rem;
    border-radius: .5rem;
    font-size: .72rem;
    font-weight: 800;
    white-space: nowrap;
}
.mpp-tb--emp { background: rgba(99,102,241,.1); color: #4338ca; border: 1px solid rgba(99,102,241,.15); }
.mpp-tb--wp  { background: rgba(16,185,129,.1); color: #0f766e; border: 1px solid rgba(16,185,129,.15); }
.mpp-tb--exp { background: rgba(245,158,11,.1); color: #b45309; border: 1px solid rgba(245,158,11,.18); }

/* Skeleton */
.mpp-skcard {
    height: 12.5rem;
    border-radius: 1.1rem;
}
.mpp-skrows {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 0.5rem;
}
.mpp-skrow {
    height: 3rem;
    border-radius: 0.5rem;
}
.mpp-skcard,
.mpp-skrow {
    background: linear-gradient(90deg, #f1f5f9 25%, #e9eef5 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: mppShimmer 1.3s ease infinite;
}
@keyframes mppShimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: -100% 0;
    }
}
</style>
