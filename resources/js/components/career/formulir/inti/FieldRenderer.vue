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
        <el-input
            v-else-if="field.tipe === 'phone'"
            :model-value="nilai"
            :disabled="disabled"
            :placeholder="field.ph || '628xxxxxxxxxx'"
            inputmode="numeric"
            maxlength="16"
            @update:model-value="ubahTelepon"
        >
            <template #prepend>+</template>
        </el-input>

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

        <!-- select: opsi statis dari skema, ATAU dinamis dari konteks (sumber_opsi,
             mis. kampus dari Master Kampus). Field ber-sumber_opsi DIKUNCI ke daftar
             resmi: bisa dicari (filterable) tapi kandidat TIDAK boleh mengetik bebas
             (tanpa allow-create). Kalau daftar belum ada -> input terkunci, bukan bebas. -->
        <template v-else-if="field.tipe === 'select'">
            <el-select
                v-if="opsiEfektif.length"
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
                v-else-if="field.sumber_opsi"
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

        <!-- referensi: opsi DICARI ke server sambil mengetik, bukan dikirim di
             muka. Master Kampus berisi ratusan ribu baris — mustahil dimuat
             seluruhnya. Field terkunci ke daftar resmi (tanpa allow-create). -->
        <el-select
            v-else-if="field.tipe === 'referensi'"
            :model-value="nilai || undefined"
            filterable
            remote
            clearable
            :remote-method="cariReferensi"
            :loading="memuat"
            default-first-option
            :disabled="disabled || indukBelumDiisi"
            :placeholder="placeholderReferensi"
            reserve-keyword
            style="width: 100%"
            @visible-change="(buka) => buka && cariReferensi('')"
            @update:model-value="(v) => ubah(v ?? '')"
        >
            <el-option v-for="o in opsiReferensi" :key="o.nilai" :value="o.nilai" :label="o.label">
                <span class="fr__opsi">{{ o.label }}</span>
                <span v-if="o.ket" class="fr__opsi-ket">{{ o.ket }}</span>
            </el-option>
        </el-select>

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
import { computed, ref, watch } from 'vue';
import { ambilOpsi, tunda } from './referensi';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Array, Object, null], default: null },
    disabled: { type: Boolean, default: false },
    galat: { type: String, default: '' },
    // Konteks opsi dinamis (mis. { kampus: ['ITB', ...] }) untuk field
    // ber-sumber_opsi. Daftar panjang sekarang memakai tipe `referensi`.
    konteks: { type: Object, default: () => ({}) },
    // Jawaban tetangga — acuan field bertipe `referensi` untuk merantai
    // penyaringnya (jenjang -> jenis institusi -> kampus). Di bagian berulang
    // isinya jawaban BARIS itu, bukan seluruh formulir.
    jawabanKonteks: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'berkas']);

const nilai = computed(() => props.modelValue);

/**
 * Opsi yang benar-benar dipakai: dari konteks bila field menandai `sumber_opsi`
 * (mis. kampus dari whitelist pembukaan), selain itu dari opsi statis skema.
 */
const opsiEfektif = computed(() => {
    if (props.field.sumber_opsi) {
        return props.konteks?.[props.field.sumber_opsi] || [];
    }
    return props.field.opsi || [];
});

// Consent & textarea selalu memakan lebar penuh — dipaksa di sini supaya
// admin tidak perlu ingat mencentang "lebar penuh" untuk keduanya.
const lebarPenuh = computed(() => ['textarea', 'consent', 'checkbox'].includes(props.field.tipe));

/* ── Field bertipe `referensi` ──────────────────────────────────────────
   Dua macam penyaring, dan bedanya penting:
     bergantung — induk yang WAJIB terisi lebih dulu. Selama kosong, field
                  ini terkunci; menawarkan 328 ribu kampus tanpa jenjang
                  hanya membuat kandidat tersesat.
     saring     — penyempit opsional. Kalau terisi dipakai, kalau belum
                  daftar tetap bisa dibuka (cuma lebih lebar).
*/
const opsiReferensi = ref([]);
const memuat = ref(false);

function nilaiInduk(peta) {
    const out = {};
    for (const [param, key] of Object.entries(peta || {})) {
        const v = props.jawabanKonteks?.[key];
        out[param] = v === null || v === undefined ? '' : String(v);
    }
    return out;
}

const indukWajib = computed(() => nilaiInduk(props.field.bergantung));
const indukSaring = computed(() => nilaiInduk(props.field.saring));
const indukBelumDiisi = computed(() => Object.values(indukWajib.value).some((v) => v === ''));

const placeholderReferensi = computed(() => {
    if (!indukBelumDiisi.value) return props.field.ph || 'Ketik untuk mencari';
    return props.field.ph_terkunci || 'Lengkapi pertanyaan sebelumnya dulu';
});

async function muat(cari) {
    if (indukBelumDiisi.value) {
        opsiReferensi.value = [];
        return;
    }
    memuat.value = true;
    const hasil = await ambilOpsi(
        props.field.sumber,
        { cari, ...indukWajib.value, ...indukSaring.value },
        props.field.key,
    );
    // null = permintaan dibatalkan karena ada ketikan lebih baru; jangan
    // menimpa daftar yang sedang tampil dengan hasil usang.
    if (hasil !== null) opsiReferensi.value = sertakanNilaiTerpilih(hasil);
    memuat.value = false;
}

/**
 * Jawaban yang sudah tersimpan harus tetap terbaca walau tidak ikut terbawa
 * hasil pencarian terakhir — kalau tidak, membuka kembali formulir yang sudah
 * diisi memperlihatkan kolom kosong seolah jawabannya hilang.
 */
function sertakanNilaiTerpilih(daftar) {
    const v = props.modelValue;
    if (!v || daftar.some((o) => o.nilai === v)) return daftar;
    return [{ nilai: v, label: String(v), ket: null }, ...daftar];
}

const cariReferensi = tunda((cari) => muat(String(cari || '')));

// Induk berubah -> pilihan anak hampir pasti tidak berlaku lagi (prodi S1
// tidak masuk akal setelah jenjang diganti SMK). Dikosongkan supaya tidak ada
// kombinasi mustahil yang lolos ke database.
watch(
    () => JSON.stringify([indukWajib.value, indukSaring.value]),
    () => {
        opsiReferensi.value = [];
        if (props.disabled) return;
        if (props.modelValue) emit('update:modelValue', '');
    },
);

// Nilai tersimpan perlu dimunculkan sebagai opsi sejak awal, tanpa menunggu
// kandidat membuka dropdown-nya.
watch(
    () => props.modelValue,
    (v) => {
        if (v && !opsiReferensi.value.some((o) => o.nilai === v)) {
            opsiReferensi.value = sertakanNilaiTerpilih(opsiReferensi.value);
        }
    },
    { immediate: true },
);

function ubah(v) {
    emit('update:modelValue', v);
}

/**
 * Normalkan nomor telepon Indonesia agar SELALU berawalan 62 (tanpa +).
 *   08123..  -> 628123..   (0 diganti 62)
 *   8123..   -> 628123..   (langsung ditambah 62)
 *   +62 / 62 -> tetap 62..
 *   620..    -> 62..        (buang 0 setelah 62, mis. hasil ketik 62 lalu 08)
 * Disimpan sebagai '628xxxxxxxxx'; tampilan diberi awalan '+' oleh prepend.
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

/* Opsi referensi: nama di kiri, keterangan (kota / gelar) menepi ke kanan. */
.fr__opsi { float: left; }
.fr__opsi-ket { float: right; margin-left: 1.2rem; color: #94a3b8; font-size: 11.5px; }
</style>
