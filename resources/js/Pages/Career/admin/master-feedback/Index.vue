<template>
  <div class="wca-page">
    <div class="wca-page__header">
      <h1>Master Feedback Form</h1>
      <el-button type="primary" @click="openCreate">+ Tambah Form</el-button>
    </div>

    <div class="wca-split">
      <!-- Left: List -->
      <div class="wca-split__left">
        <el-input v-model="search" placeholder="Cari form..." clearable size="small" class="wca-search" />
        <el-table :data="filteredForms" highlight-current-row @row-click="selectForm" size="small" v-loading="loading">
          <el-table-column prop="Nama" label="Nama Form" />
          <el-table-column prop="Mode_Tampilan" label="Mode" width="80" />
          <el-table-column label="Aktif" width="60">
            <template #default="{ row }">
              <span :style="{ color: row.Flag_Aktif === 'Y' ? '#10b981' : '#94a3b8' }">
                {{ row.Flag_Aktif === 'Y' ? '✓' : '—' }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="Aksi" width="100">
            <template #default="{ row }">
              <el-button size="small" type="danger" text @click="deleteForm(row.Id_Master_Feedback_Form)">🗑</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- Right: Edit Form -->
      <div class="wca-split__right" v-if="selectedId">
        <h3>{{ editing ? 'Edit' : 'Detail' }} Form</h3>
        <el-form :model="form" label-position="top" size="small">
          <el-form-item label="Nama">
            <el-input v-model="form.Nama" />
          </el-form-item>
          <el-form-item label="Deskripsi">
            <el-input v-model="form.Deskripsi" type="textarea" :rows="2" />
          </el-form-item>
          <el-form-item label="Mode Tampilan">
            <el-radio-group v-model="form.Mode_Tampilan">
              <el-radio value="SCROLL">Single Page Scroll</el-radio>
              <el-radio value="WIZARD">Step-by-Step Wizard</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="Durasi (hari)">
            <el-input-number v-model="form.Durasi_Hari" :min="1" placeholder="Kosong = unlimited" />
          </el-form-item>
          <el-form-item label="Status Aktif">
            <el-switch v-model="form.Flag_Aktif" active-value="Y" inactive-value="T" />
          </el-form-item>
          <el-button type="primary" @click="saveForm" :loading="saving">Simpan Form</el-button>
        </el-form>

        <!-- Questions -->
        <h4 style="margin-top:24px">Pertanyaan</h4>
        <draggable v-model="form.pertanyaan" item-key="Urutan" handle=".drag-handle" @end="reorderQuestions">
          <template #item="{ element, index }">
            <div class="fb-q-item">
              <span class="drag-handle">⠿</span>
              <div class="fb-q-item__content">
                <div class="fb-q-item__header">
                  <span class="fb-q-num">{{ index + 1 }}</span>
                  <el-select v-model="element.Tipe" size="small" style="width:120px">
                    <el-option v-for="t in ['RATING','NPS','LIKERT','TEXTAREA','RADIO','CHECKBOX','DROPDOWN']" :key="t" :label="t" :value="t" />
                  </el-select>
                  <el-button size="small" type="danger" text @click="removeQuestion(index)">✕</el-button>
                </div>
                <el-input v-model="element.Label" placeholder="Label pertanyaan" size="small" style="margin-top:6px" />
                <div v-if="['RADIO','CHECKBOX','DROPDOWN'].includes(element.Tipe)" style="margin-top:6px">
                  <el-input v-model="opsiText[index]" placeholder="Opsi (pisahkan dengan koma)" size="small" />
                </div>
              </div>
            </div>
          </template>
        </draggable>
        <el-button size="small" @click="addQuestion" style="margin-top:8px">+ Tambah Pertanyaan</el-button>
        <el-button type="success" size="small" @click="saveQuestions" :loading="savingQ" style="margin-top:8px;margin-left:8px">Simpan Pertanyaan</el-button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import draggable from 'vuedraggable'

const forms = ref([])
const selectedId = ref(null)
const form = ref({ Nama: '', Deskripsi: '', Mode_Tampilan: 'SCROLL', Durasi_Hari: null, Flag_Aktif: 'Y', pertanyaan: [] })
const opsiText = ref({})
const search = ref('')
const loading = ref(false)
const saving = ref(false)
const savingQ = ref(false)
const editing = ref(false)

const filteredForms = computed(() => {
    if (!search.value) return forms.value
    return forms.value.filter(f => f.Nama.toLowerCase().includes(search.value.toLowerCase()))
})

async function loadForms() {
    loading.value = true
    const { data } = await axios.get('/api/v1/karir/master-feedback')
    forms.value = data.result || []
    loading.value = false
}

async function selectForm(row) {
    selectedId.value = row.Id_Master_Feedback_Form
    editing.value = true
    const { data } = await axios.get(`/api/v1/karir/master-feedback/${row.Id_Master_Feedback_Form}`)
    form.value = data.result
    form.value.pertanyaan = form.value.pertanyaan || []
    opsiText.value = {}
    form.value.pertanyaan.forEach((p, i) => {
        if (p.Opsi) opsiText.value[i] = p.Opsi.join(', ')
    })
}

function openCreate() {
    selectedId.value = null
    editing.value = true
    form.value = { Nama: '', Deskripsi: '', Mode_Tampilan: 'SCROLL', Durasi_Hari: null, Flag_Aktif: 'Y', pertanyaan: [] }
    opsiText.value = {}
}

async function saveForm() {
    saving.value = true
    const payload = {
        nama: form.value.Nama,
        deskripsi: form.value.Deskripsi,
        mode_tampilan: form.value.Mode_Tampilan,
        durasi_hari: form.value.Durasi_Hari,
        flag_aktif: form.value.Flag_Aktif,
    }
    if (selectedId.value) {
        await axios.put(`/api/v1/karir/master-feedback/${selectedId.value}`, payload)
    } else {
        const { data } = await axios.post('/api/v1/karir/master-feedback', payload)
        selectedId.value = data.result.id
    }
    saving.value = false
    loadForms()
}

async function deleteForm(id) {
    if (!confirm('Hapus form ini?')) return
    await axios.delete(`/api/v1/karir/master-feedback/${id}`)
    if (selectedId.value === id) { selectedId.value = null; form.value = { pertanyaan: [] } }
    loadForms()
}

function addQuestion() {
    form.value.pertanyaan.push({ Tipe: 'RATING', Label: '', Urutan: form.value.pertanyaan.length + 1 })
}

function removeQuestion(idx) { form.value.pertanyaan.splice(idx, 1) }

function reorderQuestions() {
    form.value.pertanyaan.forEach((p, i) => p.Urutan = i + 1)
}

async function saveQuestions() {
    savingQ.value = true
    const pertanyaan = form.value.pertanyaan.map((p, i) => ({
        ...p,
        urutan: i + 1,
        opsi: opsiText.value[i] ? opsiText.value[i].split(',').map(o => o.trim()).filter(Boolean) : (p.Opsi || null),
    }))
    await axios.post(`/api/v1/karir/master-feedback/${selectedId.value}/pertanyaan`, { pertanyaan })
    savingQ.value = false
    selectForm({ Id_Master_Feedback_Form: selectedId.value })
}

onMounted(loadForms)
</script>

<style scoped>
.wca-page { padding: 20px; }
.wca-page__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.wca-page__header h1 { margin: 0; font-size: 20px; }
.wca-split { display: flex; gap: 20px; }
.wca-split__left { flex: 1; min-width: 0; }
.wca-split__right { flex: 1; min-width: 0; background: #fff; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; }
.wca-search { margin-bottom: 10px; }
.fb-q-item { display: flex; align-items: flex-start; gap: 8px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.drag-handle { cursor: grab; color: #94a3b8; font-size: 18px; padding-top: 2px; }
.fb-q-item__content { flex: 1; }
.fb-q-item__header { display: flex; align-items: center; gap: 8px; }
.fb-q-num { width: 22px; height: 22px; border-radius: 6px; background: #6366f1; color: #fff; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
</style>
