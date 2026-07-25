<template>
  <div class="wca-page">
    <div class="wca-page__header">
      <h1>🔬 Voice of Candidate</h1>
      <div class="wca-filters">
        <el-select v-model="filters.form_id" placeholder="Pilih Form" clearable @change="fetchData" style="width:200px">
          <el-option v-for="f in forms" :key="f.Id_Master_Feedback_Form" :label="f.Nama" :value="f.Id_Master_Feedback_Form" />
        </el-select>
        <el-select v-model="filters.program_id" placeholder="Semua Program" clearable @change="fetchData" style="width:180px">
          <el-option v-for="p in programs" :key="p.Id_Program" :label="p.Nama" :value="p.Id_Program" />
        </el-select>
        <el-date-picker v-model="dateRange" type="daterange" range-separator="—" start-placeholder="Mulai" end-placeholder="Akhir" @change="fetchData" />
      </div>
    </div>

    <!-- Layer 1: Overview KPI Cards -->
    <div class="wca-kpi-row">
      <div class="wca-kpi-card"><div class="wca-kpi__val">{{ overview.total_terisi }}</div><div class="wca-kpi__label">Total Respons</div></div>
      <div class="wca-kpi-card"><div class="wca-kpi__val">{{ overview.response_rate }}%</div><div class="wca-kpi__label">Response Rate</div></div>
      <div class="wca-kpi-card"><div class="wca-kpi__val">{{ npsScore }}</div><div class="wca-kpi__label">NPS</div></div>
      <div class="wca-kpi-card"><div class="wca-kpi__val">{{ avgRating }}/5</div><div class="wca-kpi__label">Avg Rating</div></div>
      <div class="wca-kpi-card"><div class="wca-kpi__val">{{ avgTime }}</div><div class="wca-kpi__label">Avg Waktu</div></div>
      <div class="wca-kpi-card"><div class="wca-kpi__val">{{ overview.total_dibuat }}</div><div class="wca-kpi__label">Total Dibuat</div></div>
    </div>

    <!-- Charts Row -->
    <div class="wca-charts-row">
      <div class="wca-chart-box">
        <h4>NPS Distribution</h4>
        <apexchart v-if="npsDistSeries.length" type="donut" :options="donutOpts" :series="npsDistSeries" height="240" />
      </div>
      <div class="wca-chart-box">
        <h4>Tren NPS (12 Bulan)</h4>
        <apexchart v-if="npsTrend.length" type="line" :options="trendOpts" :series="[{ name: 'NPS', data: npsTrend.map(t => t.nps) }]" height="240" />
      </div>
    </div>

    <!-- Program Comparison Table -->
    <div class="wca-section">
      <h4>Program Comparison</h4>
      <el-table :data="programComparison" size="small">
        <el-table-column prop="Program_Nama" label="Program" />
        <el-table-column label="Total Respons" width="120"><template #default="{row}">{{ row.total_respon }}</template></el-table-column>
        <el-table-column label="Avg Rating" width="100"><template #default="{row}">{{ fmt(row.avg_rating) }}</template></el-table-column>
      </el-table>
    </div>

    <!-- Layer 2: Per Pertanyaan (if form selected) -->
    <div v-if="filters.form_id && perPertanyaan" class="wca-section">
      <h4>Detail Per Pertanyaan</h4>
      <div v-for="p in perPertanyaan" :key="p.id" class="wca-q-detail">
        <div class="wca-q-detail__label">{{ p.label }} ({{ p.tipe }})</div>
        <div v-if="p.tipe === 'RATING' || p.tipe === 'LIKERT'" class="wca-q-detail__stats">
          Avg: {{ p.avg }} · Total: {{ p.total }} ·
          <span v-for="(v, k) in p.distribusi" :key="k">{{ k }}★: {{ v }} </span>
        </div>
        <div v-else-if="p.tipe === 'NPS'" class="wca-q-detail__stats">NPS: {{ p.nps }} · Total: {{ p.total }}</div>
        <div v-else-if="p.tipe === 'TEXTAREA'" class="wca-q-detail__text">
          <div v-for="(t, i) in p.recent" :key="i" class="wca-text-quote">"{{ t }}"</div>
          <span class="wca-text-more" v-if="p.total > 5">+{{ p.total - 5 }} lainnya</span>
        </div>
        <div v-else class="wca-q-detail__counts">
          <span v-for="(v, k) in p.counts" :key="k">{{ k }}: {{ v }} · </span>
        </div>
      </div>
    </div>

    <!-- Export -->
    <div class="wca-section" v-if="filters.form_id">
      <el-button type="success" @click="exportExcel" :loading="exporting">📥 Export Excel</el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import apexchart from 'vue3-apexcharts'

const overview = ref({ total_dibuat: '-', total_terisi: '-', response_rate: '-', avg_waktu_detik: 0 })
const programComparison = ref([])
const npsTrend = ref([])
const perPertanyaan = ref(null)
const forms = ref([])
const programs = ref([])
const filters = ref({ form_id: null, program_id: null })
const dateRange = ref(null)
const exporting = ref(false)

const npsScore = computed(() => {
    const stat = overview.value.skor_stats?.find(s => s.Tipe === 'NPS')
    return stat ? Math.round(stat.rata2) : '-'
})
const avgRating = computed(() => {
    const stat = overview.value.skor_stats?.find(s => s.Tipe === 'RATING' || s.Tipe === 'LIKERT')
    return stat ? parseFloat(stat.rata2).toFixed(1) : '-'
})
const avgTime = computed(() => {
    const s = overview.value.avg_waktu_detik
    if (!s || s === 0) return '-'
    return s < 60 ? Math.round(s) + 'd' : Math.round(s / 60) + 'm'
})
const npsDistSeries = computed(() => {
    const stat = overview.value.skor_stats?.find(s => s.Tipe === 'NPS')
    return stat ? [stat.jumlah] : []
})

const donutOpts = { labels: ['NPS Responses'], colors: ['#6366f1', '#10b981', '#f59e0b'] }
const trendOpts = {
    chart: { toolbar: { show: false } },
    xaxis: { categories: npsTrend.value.map(t => t.bulan) },
    colors: ['#6366f1'],
    stroke: { curve: 'smooth', width: 3 },
}

function fmt(v) { return v ? parseFloat(v).toFixed(1) : '-' }

async function loadMeta() {
    const [fRes, pRes] = await Promise.all([
        axios.get('/api/v1/karir/options/form-feedback'),
        axios.get('/api/v1/karir/options/program'),
    ])
    forms.value = fRes.data.result || []
    programs.value = pRes.data.result || []
}

async function fetchData() {
    const params = { form_id: filters.value.form_id, program_id: filters.value.program_id }
    if (dateRange.value) {
        params.date_from = dateRange.value[0]?.toISOString().split('T')[0]
        params.date_to = dateRange.value[1]?.toISOString().split('T')[0]
    }
    const { data } = await axios.get('/api/v1/karir/feedback/chart', { params })
    overview.value = data.result?.overview || overview.value
    programComparison.value = data.result?.program_comparison || []
    npsTrend.value = data.result?.nps_trend || []
    perPertanyaan.value = data.result?.per_pertanyaan
}

async function exportExcel() {
    exporting.value = true
    await axios.post('/api/v1/karir/feedback/export', {
        form_id: filters.value.form_id,
        program_id: filters.value.program_id,
        date_from: dateRange.value?.[0]?.toISOString().split('T')[0],
        date_to: dateRange.value?.[1]?.toISOString().split('T')[0],
    })
    exporting.value = false
}

onMounted(async () => { await loadMeta(); fetchData() })
</script>

<style scoped>
.wca-page { padding: 20px; }
.wca-page__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.wca-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.wca-kpi-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.wca-kpi-card { flex: 1; min-width: 120px; background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); border: 1px solid rgba(99,102,241,0.08); border-radius: 12px; padding: 16px; text-align: center; }
.wca-kpi__val { font-size: 24px; font-weight: 800; color: #1e293b; }
.wca-kpi__label { font-size: 12px; color: #94a3b8; margin-top: 4px; }
.wca-charts-row { display: flex; gap: 16px; margin-bottom: 20px; }
.wca-chart-box { flex: 1; background: #fff; border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0; }
.wca-chart-box h4 { margin: 0 0 12px; font-size: 14px; }
.wca-section { background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 16px; }
.wca-section h4 { margin: 0 0 12px; }
.wca-q-detail { padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
.wca-q-detail__label { font-weight: 600; color: #334155; }
.wca-q-detail__stats { font-size: 12px; color: #64748b; margin-top: 4px; }
.wca-q-detail__text { margin-top: 4px; }
.wca-text-quote { font-size: 12px; color: #64748b; font-style: italic; padding: 4px 0; }
.wca-text-more { font-size: 12px; color: #6366f1; cursor: pointer; }
</style>
