<!-- WEB CAREER — Render SATU field sesuai tipe di skema. Dipakai semua template. -->
<template>
    <div class="fr" :class="{ 'fr--full': field.penuh || lebarPenuh }">
        <label class="fr__lbl">
            {{ field.label }}
            <span v-if="field.wajib && field.tipe !== 'prefill'" class="fr__wajib">*</span>
            <span v-if="field.tipe === 'prefill'" class="fr__auto"><i class="bi bi-magic"></i> otomatis</span>
        </label>

        <!-- Terisi otomatis dari profil kandidat (Form 2 Page 1). -->
        <el-input v-if="field.tipe === 'prefill'" :model-value="String(nilai ?? '')" disabled placeholder="—" />

        <el-input
            v-else-if="field.tipe === 'text'"
            :model-value="nilai"
            :disabled="disabled"
            :placeholder="field.ph"
            @update:model-value="ubah"
        />

        <!-- Telepon Indonesia: WAJIB berawalan 62. Apa pun yang diketik
             (08.., 8.., +62..) dinormalkan jadi 628.. saat mengetik. -->
        <TeleponNegara
            v-else-if="field.tipe === 'phone'"
            :model-value="nilai || ''"
            :disabled="disabled"
            :placeholder="field.ph || '81234567890'"
            @update:model-value="ubah"
        />

        <el-input
            v-else-if="field.tipe === 'textarea'"
            :model-value="nilai"
            type="textarea"
            :rows="3"
            :disabled="disabled"
            :placeholder="field.ph"
            @update:model-value="ubah"
        />

        <el-input-number
            v-else-if="field.tipe === 'number'"
            :model-value="nilai"
            :min="field.min ?? undefined"
            :max="field.maks ?? undefined"
            :precision="field.desimal || 0"
            :step="field.desimal ? 0.05 : 1"
            :disabled="disabled"
            :placeholder="field.ph"
            controls-position="right"
            style="width: 100%"
            @update:model-value="ubah"
        />

        <el-date-picker
            v-else-if="field.tipe === 'date'"
            :model-value="nilai"
            type="date"
            value-format="YYYY-MM-DD"
            format="DD MMM YYYY"
            :disabled="disabled"
            :placeholder="field.ph || 'Pilih tanggal'"
            style="width: 100%"
            @update:model-value="ubah"
        />

        <!-- select: (1) OPSI DARI API + cascade (sumber_api: jenjang/jenis/kampus) —
             opsi menyesuaikan field induk (tergantung) & pencarian server-side utk
             kampus; (2) opsi dinamis konteks (sumber_opsi); (3) opsi statis skema.
             Field ber-sumber_opsi/api DIKUNCI ke daftar resmi (tanpa ketik bebas). -->
        <template v-else-if="field.tipe === 'select'">
            <el-select
                v-if="field.sumber_api"
                :model-value="nilai"
                filterable
                :remote="!!field.cari_async"
                :remote-method="field.cari_async ? cariJarakJauh : undefined"
                :allow-create="bolehKetik"
                :default-first-option="!!field.cari_async"
                :reserve-keyword="false"
                :loading="apiLoading"
                :disabled="disabled || (!!field.tergantung && !depNilai)"
                :placeholder="apiPlaceholder"
                style="width: 100%"
                @update:model-value="ubah"
                @visible-change="onDropdown"
            >
                <el-option v-for="o in opsiApiRender" :key="o.value" :value="o.value" :label="o.label">
                    <span style="display:inline-flex;align-items:center;gap:9px;min-width:0">
                        <img v-if="o.flag" :src="o.flag" width="22" height="16" style="border-radius:2px;flex:none;object-fit:cover;box-shadow:0 0 0 1px rgba(0,0,0,.08)" alt="" loading="lazy" />
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ o.label }}</span>
                    </span>
                </el-option>
            </el-select>
            <el-select
                v-else-if="opsiEfektif.length"
                :model-value="nilai"
                filterable
                :disabled="disabled"
                :placeholder="field.ph || 'Cari lalu pilih'"
                style="width: 100%"
                @update:model-value="ubah"
            >
                <el-option v-for="o in opsiEfektif" :key="o" :value="o" :label="o" />
            </el-select>
            <el-input
                v-else-if="field.sumber_opsi || field.sumber_api"
                model-value=""
                disabled
                placeholder="Daftar pilihan belum tersedia — hubungi admin"
            />
            <el-input
                v-else
                :model-value="nilai"
                :disabled="disabled"
                :placeholder="field.ph || 'Ketik jawaban'"
                @update:model-value="ubah"
            />
        </template>

        <el-radio-group
            v-else-if="field.tipe === 'radio'"
            :model-value="nilai"
            :disabled="disabled"
            @update:model-value="ubah"
        >
            <el-radio v-for="o in opsiEfektif" :key="o" :value="o">{{ o }}</el-radio>
        </el-radio-group>

        <el-checkbox-group
            v-else-if="field.tipe === 'checkbox'"
            :model-value="nilai || []"
            :disabled="disabled"
            @update:model-value="ubah"
        >
            <el-checkbox v-for="o in opsiEfektif" :key="o" :value="o">{{ o }}</el-checkbox>
        </el-checkbox-group>

        <!-- Berkas: file-nya sendiri disimpan di N_WEB_CAREERS_Formulir_Berkas,
             yang tersimpan di jawaban hanya nama berkasnya sebagai penanda. -->
        <div v-else-if="field.tipe === 'file'" class="fr__file">
            <div class="fr__file-box" :class="{ 'is-ada': nilai }">
                <i class="bi" :class="nilai ? 'bi-file-earmark-check-fill' : 'bi-cloud-arrow-up'"></i>
                <div class="fr__file-txt">
                    <strong>{{ nilai || 'Belum ada berkas' }}</strong>
                    <small>{{ field.accept || '.pdf' }} · maks {{ field.maks_mb || 2 }} MB</small>
                </div>
            </div>
            <el-upload
                :accept="field.accept || '.pdf'"
                :auto-upload="false"
                :show-file-list="false"
                :disabled="disabled"
                :on-change="pilihBerkas"
            >
                <button type="button" class="fr__btn" :disabled="disabled">
                    <i class="bi" :class="nilai ? 'bi-arrow-repeat' : 'bi-upload'"></i>
                    {{ nilai ? 'Ganti' : 'Unggah' }}
                </button>
            </el-upload>
        </div>

        <el-checkbox
            v-else-if="field.tipe === 'consent'"
            :model-value="!!nilai"
            :disabled="disabled"
            class="fr__consent"
            @update:model-value="ubah"
        >
            Saya menyetujui pernyataan di atas
        </el-checkbox>

        <el-input v-else :model-value="nilai" :disabled="disabled" @update:model-value="ubah" />

        <div v-if="field.bantuan" class="fr__bantuan">{{ field.bantuan }}</div>
        <div v-if="galat" class="fr__galat"><i class="bi bi-exclamation-circle"></i> {{ galat }}</div>
    </div>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import axios from 'axios';
import TeleponNegara from '@career/TeleponNegara.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Array, Object, null], default: null },
    disabled: { type: Boolean, default: false },
    galat: { type: String, default: '' },
    // Konteks opsi dinamis (mis. { kampus: ['ITB', ...] } dari Master Kampus).
    konteks: { type: Object, default: () => ({}) },
    // Seluruh jawaban bagian ini — dipakai field cascade untuk membaca induknya.
    jawaban: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'berkas']);

const nilai = computed(() => props.modelValue);

/**
 * Opsi statis/konteks (sumber_opsi). Untuk sumber_api dipakai jalur terpisah.
 */
const opsiEfektif = computed(() => {
    if (props.field.sumber_opsi) {
        return props.konteks?.[props.field.sumber_opsi] || [];
    }
    return props.field.opsi || [];
});

const lebarPenuh = computed(() => ['textarea', 'consent', 'checkbox'].includes(props.field.tipe));

// ══════════════ CASCADE PENDIDIKAN (sumber_api) ══════════════
const CFG = { headers: { Accept: 'application/json' } };
const ENDPOINT = {
    jenjang: '/api/v1/pendidikan/jenjang',
    jenis_institusi: '/api/v1/pendidikan/jenis-institusi',
    kampus: '/api/v1/pendidikan/kampus',
    fakultas: '/api/v1/pendidikan/fakultas',
    prodi: '/api/v1/pendidikan/prodi',
};

// Field yang boleh diisi di luar daftar. Master kampus & prodi tidak akan
// pernah lengkap — prodi baru dibuka tiap tahun dan kampus luar negeri
// penamaannya bebas. Mengunci pilihan hanya membuat pelamar mentok.
const bolehKetik = computed(() => !!props.field.boleh_ketik || !!props.field.cari_async);
const apiOpsi = ref([]); // [{ value, label, meta? }]
const apiLoading = ref(false);

// Nilai field INDUK (yang jadi acuan cascade), mis. jenis_institusi butuh jenjang.
const depNilai = computed(() => (props.field.tergantung ? (props.jawaban?.[props.field.tergantung] ?? '') : ''));

async function muatJenjang() {
    try {
        const r = await axios.get(ENDPOINT.jenjang, CFG);
        apiOpsi.value = (r.data.result || []).map((o) => ({ value: o.kode, label: o.nama }));
    } catch (e) { apiOpsi.value = []; }
}
async function muatJenis() {
    if (!depNilai.value) { apiOpsi.value = []; return; }
    try {
        const r = await axios.get(ENDPOINT.jenis_institusi, { ...CFG, params: { jenjang: depNilai.value } });
        apiOpsi.value = (r.data.result || []).map((o) => ({ value: o.kode, label: o.nama }));
    } catch (e) { apiOpsi.value = []; }
}
async function cariKampus(q) {
    if (!depNilai.value) { apiOpsi.value = []; return; }
    apiLoading.value = true;
    try {
        const r = await axios.get(ENDPOINT.kampus, { ...CFG, params: { jenis: depNilai.value, q: q || '', limit: 30 } });
        apiOpsi.value = (r.data.result || []).map((o) => ({
            value: o.value,
            label: o.label,
            flag: o.negaraKode ? `https://flagcdn.com/24x18/${String(o.negaraKode).toLowerCase()}.png` : '',
        }));
    } catch (e) { apiOpsi.value = []; } finally { apiLoading.value = false; }
}
// Jenjang yang sedang dipilih — dibutuhkan endpoint fakultas & prodi karena
// daftar prodi berbeda antara SMK, D3, dan S2 di kampus yang sama.
const jenjangNilai = computed(() => props.jawaban?.jenjang ?? '');
// Fakultas/jurusan terpilih (skema: saring_dari) → mempersempit daftar prodi.
const saringNilai = computed(() => (props.field.saring_dari ? (props.jawaban?.[props.field.saring_dari] ?? '') : ''));

async function muatFakultas() {
    if (!depNilai.value) { apiOpsi.value = []; return; }
    apiLoading.value = true;
    try {
        const r = await axios.get(ENDPOINT.fakultas, { ...CFG, params: { kampus: depNilai.value, jenjang: jenjangNilai.value } });
        // Cukup namanya — jumlah prodi tidak menolong pelamar memilih.
        apiOpsi.value = (r.data.result || []).map((o) => ({ value: o.value, label: o.nama }));
    } catch (e) { apiOpsi.value = []; } finally { apiLoading.value = false; }
}

async function cariProdi(q) {
    if (!depNilai.value) { apiOpsi.value = []; return; }
    apiLoading.value = true;
    try {
        const r = await axios.get(ENDPOINT.prodi, {
            ...CFG,
            params: { kampus: depNilai.value, jenjang: jenjangNilai.value, bidang: saringNilai.value, q: q || '', limit: 30 },
        });
        // Nama prodi saja, tanpa gelar — yang tersimpan pun namanya.
        apiOpsi.value = (r.data.result || []).map((o) => ({ value: o.value, label: o.label }));
    } catch (e) { apiOpsi.value = []; } finally { apiLoading.value = false; }
}

/** Pencarian jarak jauh (remote) — tujuannya ditentukan sumber_api field. */
function cariJarakJauh(q) {
    if (props.field.sumber_api === 'prodi') return cariProdi(q);
    return cariKampus(q);
}

function muatOpsiApi() {
    const s = props.field.sumber_api;
    if (s === 'jenjang') muatJenjang();
    else if (s === 'jenis_institusi') muatJenis();
    else if (s === 'fakultas') muatFakultas();
    else if (s === 'kampus') { apiOpsi.value = []; if (depNilai.value) cariKampus(''); }
    else if (s === 'prodi') { apiOpsi.value = []; if (depNilai.value) cariProdi(''); }
}

onMounted(() => { if (props.field.sumber_api) muatOpsiApi(); });

// Induk berubah → muat ulang opsi. Pengosongan nilai anak ditangani layout
// (reset_anak) supaya rantai jenjang→jenis→kampus konsisten.
watch(depNilai, () => { if (props.field.sumber_api) muatOpsiApi(); });

// Ganti fakultas → daftar prodi ikut menyempit. Nilai prodi yang terlanjur
// terisi dikosongkan lewat reset_anak di layout, bukan di sini.
watch(saringNilai, () => { if (props.field.sumber_api === 'prodi') cariProdi(''); });

function onDropdown(open) {
    if (!open || apiOpsi.value.length || !depNilai.value) return;
    if (props.field.sumber_api === 'kampus') cariKampus('');
    else if (props.field.sumber_api === 'prodi') cariProdi('');
    else if (props.field.sumber_api === 'fakultas') muatFakultas();
}

// Opsi API + jamin nilai tersimpan tetap tampil (mis. saat meninjau lamaran).
const opsiApiRender = computed(() => {
    const list = apiOpsi.value.slice();
    const v = props.modelValue;
    if (v && !list.some((o) => o.value === v)) list.unshift({ value: v, label: v });
    return list;
});

const apiPlaceholder = computed(() => {
    if (props.field.tergantung && !depNilai.value) {
        return {
            jenjang: 'Pilih jenjang dulu',
            jenis_institusi: 'Pilih jenis institusi dulu',
            nama_kampus: 'Pilih nama kampus / sekolah dulu',
        }[props.field.tergantung] || 'Lengkapi isian sebelumnya';
    }
    return props.field.ph || 'Pilih';
});

function ubah(v) {
    emit('update:modelValue', v);
}

/**
 * Normalkan nomor telepon Indonesia agar SELALU berawalan 62 (tanpa +).
 */
function normalTelepon(raw) {
    let s = String(raw ?? '').replace(/\D/g, '');
    if (!s) return '';
    if (s.startsWith('620')) s = '62' + s.slice(3);
    else if (s.startsWith('62')) s = s;
    else if (s.startsWith('0')) s = '62' + s.slice(1);
    else if (s.startsWith('8')) s = '62' + s;
    else s = '62' + s;
    return s;
}

function ubahTelepon(v) {
    emit('update:modelValue', normalTelepon(v));
}

function pilihBerkas(uf) {
    const file = uf.raw || uf;
    const maks = (props.field.maks_mb || 2) * 1024 * 1024;
    if (file.size > maks) {
        emit('berkas', { field: props.field, galat: `Berkas melebihi ${props.field.maks_mb || 2} MB.` });
        return;
    }
    emit('update:modelValue', file.name);
    emit('berkas', { field: props.field, file });
}
</script>

<style scoped>
.fr { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
.fr--full { grid-column: 1 / -1; }

.fr__lbl { font-size: 12px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: .3rem; }
.fr__wajib { color: #dc2626; }
.fr__auto { font-size: 10.5px; font-weight: 600; color: #4338ca; background: rgba(79, 70, 229, .1); border-radius: 999px; padding: .05rem .4rem; }

.fr__bantuan { font-size: 11px; color: #94a3b8; line-height: 1.5; }
.fr__galat { font-size: 11.5px; color: #dc2626; display: flex; align-items: center; gap: .25rem; }

.fr__optmain { font-weight: 600; }
.fr__optmeta { display: block; font-size: 10.5px; color: #94a3b8; line-height: 1.2; }

.fr__file { display: flex; align-items: center; gap: .5rem; }
.fr__file-box { flex: 1; min-width: 0; display: flex; align-items: center; gap: .5rem; padding: .5rem .65rem; border: 1px dashed rgba(11, 16, 51, .18); border-radius: 10px; background: #f8fafc; }
.fr__file-box.is-ada { border-style: solid; border-color: rgba(16, 185, 129, .4); background: rgba(16, 185, 129, .06); }
.fr__file-box .bi { font-size: 1.05rem; color: #64748b; }
.fr__file-box.is-ada .bi { color: #059669; }
.fr__file-txt { min-width: 0; display: flex; flex-direction: column; }
.fr__file-txt strong { font-size: 12px; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fr__file-txt small { font-size: 10.5px; color: #94a3b8; }

.fr__btn { flex: none; border: 0; border-radius: 9px; padding: .5rem .8rem; background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; }
.fr__btn:disabled { opacity: .5; cursor: not-allowed; }

.fr__consent { white-space: normal; height: auto; align-items: flex-start; }
</style>
