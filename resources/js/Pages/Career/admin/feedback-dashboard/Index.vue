<!-- WEB CAREER — Feedback Dashboard: analitik data feedback dengan KPI cards, chart, program comparison, dan detail per pertanyaan. -->
<template>
    <Head><title>Feedback Dashboard - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>🔬 Feedback</h1>
                <p>Analytics & monitoring feedback kandidat — NPS, rating, Likert, jawaban teks, dan tracking pengisian.</p>
            </div>
            <div class="wca-phead__actions">
                <div class="fb-tabs">
                    <button :class="['fb-tab', { 'fb-tab--active': tab === 'analytics' }]" @click="tab = 'analytics'">📊 Analytics</button>
                    <button :class="['fb-tab', { 'fb-tab--active': tab === 'monitoring' }]" @click="switchToMonitoring()">👁️ Monitoring</button>
                </div>
            </div>
        </div>

        <!-- ═══ ANALYTICS ═══ -->
        <template v-if="tab === 'analytics'">
        <div class="wca-phead__filters" style="margin-bottom:16px;display:flex;gap:8px">
            <el-select v-model="filters.form_id" placeholder="Pilih Form" clearable @change="fetchData" size="small" style="width:200px">
                <el-option v-for="f in forms" :key="f.Id_Master_Feedback_Form" :label="f.Nama" :value="f.Id_Master_Feedback_Form" />
            </el-select>
            <el-date-picker v-model="dateRange" type="daterange" range-separator="—" start-placeholder="Mulai" end-placeholder="Akhir" size="small" @change="fetchData" style="width:240px" />
        </div>

        <!-- KPI Row -->
        <div class="wca-stats">
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-check2-circle"></i></span></div>
                <div class="wca-stat__num">{{ overview.total_terisi }}</div>
                <div class="wca-stat__label">Total Respons</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-percent"></i></span></div>
                <div class="wca-stat__num">{{ overview.response_rate }}%</div>
                <div class="wca-stat__label">Response Rate</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-graph-up-arrow"></i></span></div>
                <div class="wca-stat__num">{{ npsScore }}</div>
                <div class="wca-stat__label">NPS Score</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-star-fill"></i></span></div>
                <div class="wca-stat__num">{{ avgRating }}</div>
                <div class="wca-stat__label">Avg Rating</div>
            </div>
            <div class="wca-stat">
                <div class="wca-stat__top"><span class="wca-stat__ico"><i class="bi bi-clock"></i></span></div>
                <div class="wca-stat__num">{{ avgTime }}</div>
                <div class="wca-stat__label">Rata² Waktu Isi</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="wca-grid wca-grid--2" style="margin-bottom:20px">
            <div class="wca-card">
                <div class="wca-card__head">
                    <h3><i class="bi bi-pie-chart"></i> NPS Distribution</h3>
                </div>
                <div class="wca-card__body">
                    <apexchart v-if="npsDistSeries.length" type="donut" :options="donutOpts" :series="npsDistSeries" height="220" />
                    <div v-else class="wca-hint"><i class="bi bi-info-circle"></i> Belum ada data NPS.</div>
                </div>
            </div>

            <div class="wca-card">
                <div class="wca-card__head">
                    <h3><i class="bi bi-graph-up"></i> Tren NPS (12 Bulan)</h3>
                </div>
                <div class="wca-card__body">
                    <apexchart v-if="npsTrend.length" type="line" :options="trendOpts" :series="[{ name: 'NPS', data: npsTrend.map(t => t.nps) }]" height="220" />
                    <div v-else class="wca-hint"><i class="bi bi-info-circle"></i> Belum ada data tren.</div>
                </div>
            </div>
        </div>

        <!-- Program Comparison -->
        <div class="wca-card" style="margin-bottom:20px">
            <div class="wca-card__head">
                <h3><i class="bi bi-bar-chart"></i> Program Comparison</h3>
                <span class="wca-badge wca-b--slate">{{ programComparison.length }} program</span>
            </div>
            <div class="wca-card__body--flush" v-if="programComparison.length">
                <div class="wca-list">
                    <div v-for="p in programComparison" :key="p.Id_Program" class="wca-listrow">
                        <span class="wca-avatar wca-avatar--sm" style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;font-size:.7rem;font-weight:700">
                            {{ initials(p.Program_Nama) }}
                        </span>
                        <div class="wca-listrow__main">
                            <strong>{{ p.Program_Nama }}</strong>
                            <small>{{ p.total_respon }} respons · Avg Rating: {{ fmt(p.avg_rating) }}</small>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="wca-card__body"><div class="wca-hint"><i class="bi bi-info-circle"></i> Belum ada data program.</div></div>
        </div>

        <!-- Per Pertanyaan (Layer 2) -->
        <div v-if="filters.form_id && perPertanyaan" class="wca-card" style="margin-bottom:20px">
            <div class="wca-card__head">
                <h3><i class="bi bi-list-ol"></i> Detail Per Pertanyaan</h3>
                <span class="wca-badge wca-b--indigo">{{ perPertanyaan.length }} pertanyaan</span>
            </div>
            <div class="wca-card__body">
                <div v-for="p in perPertanyaan" :key="p.id" class="wca-stagecard" style="margin-bottom:12px">
                    <div class="wca-stagecard__num" style="font-size:.65rem">{{ p.label.substring(0, 2) }}</div>
                    <div class="wca-stagecard__body">
                        <strong style="font-size:.85rem">{{ p.label }}</strong>
                        <span class="wca-badge wca-b--slate" style="margin-left:8px">{{ p.tipe }}</span>
                        <div style="margin-top:6px">
                            <template v-if="p.tipe === 'RATING' || p.tipe === 'LIKERT'">
                                <span style="font-size:.82rem;color:var(--muted)">Avg: <b>{{ p.avg }}</b> · Total: {{ p.total }} · </span>
                                <span v-for="(v, k) in p.distribusi" :key="k" style="font-size:.75rem;color:var(--muted)">{{ k }}★: {{ v }} </span>
                            </template>
                            <template v-else-if="p.tipe === 'NPS'">
                                <span style="font-size:.82rem;color:var(--muted)">NPS: <b>{{ p.nps }}</b> · Total: {{ p.total }}</span>
                            </template>
                            <template v-else-if="p.tipe === 'TEXTAREA'">
                                <div v-for="(t, i) in p.recent" :key="i" style="font-size:.8rem;color:var(--muted);font-style:italic;padding:4px 0;border-bottom:1px solid var(--border-light)">"{{ t }}"</div>
                                <span v-if="p.total > 5" style="font-size:.75rem;color:var(--primary);cursor:pointer">+{{ p.total - 5 }} jawaban lainnya</span>
                            </template>
                            <template v-else>
                                <span v-for="(v, k) in p.counts" :key="k" style="font-size:.75rem;color:var(--muted)">{{ k }}: {{ v }} · </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export -->
        <div v-if="filters.form_id" style="text-align:right">
            <button class="wca-btn wca-btn--primary" @click="exportExcel" :disabled="exporting">
                <i class="bi" :class="exporting ? 'bi-hourglass-split' : 'bi-download'"></i>
                {{ exporting ? 'Mengexport...' : 'Export Excel' }}
            </button>
        </div>
        </template>

        <!-- ═══ MONITORING ═══ -->
        <template v-if="tab === 'monitoring'">
        <div class="wca-stats" style="margin-bottom:16px">
            <div class="wca-stat"><div class="wca-stat__num">{{ monKpi.total }}</div><div class="wca-stat__label">Total</div></div>
            <div class="wca-stat"><div class="wca-stat__num">{{ monKpi.terisi }}</div><div class="wca-stat__label">Terisi</div></div>
            <div class="wca-stat"><div class="wca-stat__num" style="color:#f59e0b">{{ monKpi.pending }}</div><div class="wca-stat__label">Pending</div></div>
            <div class="wca-stat"><div class="wca-stat__num" style="color:#ef4444">{{ monKpi.expired }}</div><div class="wca-stat__label">Expired</div></div>
            <div class="wca-stat"><div class="wca-stat__num">{{ monKpi.response_rate }}%</div><div class="wca-stat__label">Resp. Rate</div></div>
        </div>
        <div class="wca-phead__filters" style="margin-bottom:12px;display:flex;gap:8px">
            <el-select v-model="monFilters.form_id" placeholder="Semua Form" clearable @change="loadMonitoring" size="small" style="width:180px">
                <el-option v-for="f in forms" :key="f.Id_Master_Feedback_Form" :label="f.Nama" :value="f.Id_Master_Feedback_Form" />
            </el-select>
            <el-select v-model="monFilters.program_id" placeholder="Semua Program" clearable @change="loadMonitoring" size="small" style="width:180px">
                <el-option v-for="p in programs" :key="p.Id_Program" :label="p.Nama" :value="p.Id_Program" />
            </el-select>
            <el-select v-model="monFilters.status" placeholder="Semua Status" clearable @change="loadMonitoring" size="small" style="width:140px">
                <el-option label="Pending" value="MENUNGGU" /><el-option label="Terisi" value="TERISI" /><el-option label="Expired" value="EXPIRED" />
            </el-select>
            <el-input v-model="monFilters.search" placeholder="Cari nama/kode..." clearable @input="loadMonitoring" size="small" style="width:180px" />
        </div>
        <el-table :data="monList" size="small" v-loading="monLoading" @selection-change="onMonSelect">
            <el-table-column type="selection" width="40" />
            <el-table-column prop="Nama_Kandidat" label="Nama" />
            <el-table-column prop="Kode_Lamaran" label="Lamaran" width="130" />
            <el-table-column prop="Program_Nama" label="Program" width="100" />
            <el-table-column prop="Form_Nama" label="Form" width="100" />
            <el-table-column prop="Hasil_Akhir" label="Hasil" width="80">
                <template #default="{row}"><span :style="{color:row.Hasil_Akhir==='DITERIMA'?'#10b981':'#ef4444'}">{{row.Hasil_Akhir==='DITERIMA'?'LOLOS':'GGL'}}</span></template>
            </el-table-column>
            <el-table-column prop="Status_Pengisian" label="Status" width="80">
                <template #default="{row}"><span :style="{color:row.Status_Pengisian==='TERISI'?'#10b981':'#f59e0b'}">{{row.Status_Pengisian==='TERISI'?'Terisi':'Pending'}}</span></template>
            </el-table-column>
            <el-table-column label="Aksi" width="160">
                <template #default="{row}">
                    <el-button size="small" type="primary" text @click="openReassign(row)">🔄 Ganti Form</el-button>
                    <el-button size="small" type="warning" text @click="resendSingle(row)">📧 Resend</el-button>
                </template>
            </el-table-column>
        </el-table>
        <div v-if="monSelected.length" style="margin-top:12px;display:flex;gap:8px">
            <el-button size="small" type="primary" @click="bulkReassign">🔄 Ganti Form ({{monSelected.length}})</el-button>
            <el-button size="small" type="warning" @click="bulkResend">📧 Resend Email ({{monSelected.length}})</el-button>
        </div>
        <div style="margin-top:12px;text-align:center" v-if="monTotal > monList.length">
            <el-pagination small layout="prev,next" :total="monTotal" :page-size="20" @current-change="(p)=>loadMonitoring(p)" />
        </div>

        <!-- Reassign Modal -->
        <el-dialog v-model="reassignOpen" title="Ganti Form Feedback" width="420px">
            <p style="margin:0 0 12px;font-size:.9rem">Pilih form baru untuk {{reassignCount}} feedback.</p>
            <el-select v-model="reassignFormId" placeholder="Pilih form..." style="width:100%">
                <el-option v-for="f in forms" :key="f.Id_Master_Feedback_Form" :label="f.Nama" :value="f.Id_Master_Feedback_Form" />
            </el-select>
            <el-checkbox v-model="reassignResend" style="margin-top:10px">Kirim ulang email feedback</el-checkbox>
            <template #footer>
                <el-button @click="reassignOpen=false">Batal</el-button>
                <el-button type="primary" @click="doReassign" :disabled="!reassignFormId">Ganti Form</el-button>
            </template>
        </el-dialog>

        <!-- Resend Modal -->
        <el-dialog v-model="resendOpen" title="Kirim Ulang Email" width="380px">
            <p style="margin:0;font-size:.9rem">Kirim ulang link feedback ke {{resendCount}} kandidat?</p>
            <template #footer>
                <el-button @click="resendOpen=false">Batal</el-button>
                <el-button type="primary" @click="doResend">Kirim</el-button>
            </template>
        </el-dialog>
        </template>
    </div>
</template>

<script>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import apexchart from 'vue3-apexcharts';

export default {
    components: { Head, apexchart },
    data() {
        return {
            overview: { total_dibuat: 0, total_terisi: 0, response_rate: 0, avg_waktu_detik: 0, skor_stats: [] },
            programComparison: [], npsTrend: [], perPertanyaan: null,
            forms: [], programs: [],
            filters: { form_id: null }, dateRange: null, exporting: false,
            tab: 'analytics',
            // Monitoring
            monKpi: { total: 0, terisi: 0, pending: 0, expired: 0, response_rate: 0 },
            monList: [], monTotal: 0, monLoading: false, monSelected: [],
            monFilters: { form_id: null, program_id: null, status: 'MENUNGGU', search: '' },
            reassignOpen: false, reassignFormId: null, reassignIds: [], reassignResend: false,
            resendOpen: false, resendIds: [],
        };
    },
    computed: {
        reassignCount() { return this.reassignIds.length || 1; },
        resendCount() { return this.resendIds.length || 1; },
        npsScore() {
            const stat = this.overview.skor_stats?.find(s => s.Tipe === 'NPS');
            return stat ? Math.round(stat.rata2) : '—';
        },
        avgRating() {
            const stat = this.overview.skor_stats?.find(s => s.Tipe === 'RATING' || s.Tipe === 'LIKERT');
            return stat ? parseFloat(stat.rata2).toFixed(1) : '—';
        },
        avgTime() {
            const s = this.overview.avg_waktu_detik;
            if (!s || s === 0) return '—';
            return s < 60 ? Math.round(s) + 'd' : Math.round(s / 60) + 'm';
        },
        npsDistSeries() {
            const stat = this.overview.skor_stats?.find(s => s.Tipe === 'NPS');
            return stat ? [stat.jumlah] : [];
        },
        donutOpts() { return { labels: ['NPS Responses'], colors: ['#6366f1', '#10b981', '#f59e0b'], chart: { toolbar: { show: false } } }; },
        trendOpts() {
            return {
                chart: { toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                xaxis: { categories: this.npsTrend.map(t => t.bulan) },
                colors: ['#6366f1'],
                stroke: { curve: 'smooth', width: 3 },
                tooltip: { y: { formatter: (v) => 'NPS ' + v } },
            };
        },
    },
    mounted() { this.loadMeta().then(() => this.fetchData()); },
    methods: {
        async loadMeta() {
            try {
                const { data } = await axios.get('/api/v1/karir/master-feedback');
                this.forms = data.result || [];
            } catch (e) { /* silent */ }
        },
        async fetchData() {
            const params = {};
            if (this.filters.form_id) params.form_id = this.filters.form_id;
            if (this.dateRange) {
                params.date_from = this.dateRange[0]?.toISOString().split('T')[0];
                params.date_to = this.dateRange[1]?.toISOString().split('T')[0];
            }
            try {
                const { data } = await axios.get('/api/v1/karir/feedback/chart', { params });
                this.overview = data.result?.overview || this.overview;
                this.programComparison = data.result?.program_comparison || [];
                this.npsTrend = data.result?.nps_trend || [];
                this.perPertanyaan = data.result?.per_pertanyaan;
            } catch (e) { /* silent */ }
        },
        async exportExcel() {
            if (!this.filters.form_id) return;
            this.exporting = true;
            const payload = { form_id: this.filters.form_id };
            if (this.dateRange) {
                payload.date_from = this.dateRange[0]?.toISOString().split('T')[0];
                payload.date_to = this.dateRange[1]?.toISOString().split('T')[0];
            }
            await axios.post('/api/v1/karir/feedback/export', payload);
            this.exporting = false;
        },
        fmt(v) { return v ? parseFloat(v).toFixed(1) : '—'; },
        initials(name) {
            if (!name) return '?';
            return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
        },
        // ── Monitoring ──
        async switchToMonitoring() { this.tab = 'monitoring'; if (!this.monList.length) { await this.loadMeta(); this.loadMonitoring(); } },
        async loadMonitoring(page = 1) {
            this.monLoading = true;
            const p = { ...this.monFilters, page, limit: 20 };
            const [k, d] = await Promise.all([
                axios.get('/api/v1/karir/feedback/monitoring-kpi', { params: this.monFilters }),
                axios.get('/api/v1/karir/feedback/monitoring', { params: p }),
            ]);
            this.monKpi = k.data.result; this.monList = d.data.result; this.monTotal = d.data.total_data; this.monLoading = false;
        },
        onMonSelect(items) { this.monSelected = items.map(i => i.Id_Feedback_Jawaban); },
        openReassign(row) { this.reassignIds = [row.Id_Feedback_Jawaban]; this.reassignFormId = null; this.reassignResend = false; this.reassignOpen = true; },
        bulkReassign() { if (!this.monSelected.length) return; this.reassignIds = [...this.monSelected]; this.reassignFormId = null; this.reassignResend = false; this.reassignOpen = true; },
        async doReassign() {
            if (!this.reassignFormId || !this.reassignIds.length) return;
            await axios.post('/api/v1/karir/feedback/reassign', { feedback_ids: this.reassignIds, new_form_id: this.reassignFormId, resend_email: this.reassignResend });
            this.reassignOpen = false; this.loadMonitoring();
        },
        resendSingle(row) { this.resendIds = [row.Id_Feedback_Jawaban]; this.resendOpen = true; },
        bulkResend() { if (!this.monSelected.length) return; this.resendIds = [...this.monSelected]; this.resendOpen = true; },
        async doResend() { await axios.post('/api/v1/karir/feedback/resend', { feedback_ids: this.resendIds }); this.resendOpen = false; },
    },
};
</script>

<style scoped>
.fb-tabs { display: flex; gap: 4px; }
.fb-tab { padding: 8px 18px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; font-size: .84rem; font-weight: 600; cursor: pointer; color: #64748b; transition: all .15s; }
.fb-tab--active { background: #6366f1; color: #fff; border-color: #6366f1; }
.fb-tab:hover:not(.fb-tab--active) { border-color: #a5b4fc; color: #6366f1; }
</style>
