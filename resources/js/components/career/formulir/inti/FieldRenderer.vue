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

        <el-select
            v-else-if="field.tipe === 'select'"
            :model-value="nilai"
            filterable
            :disabled="disabled"
            :placeholder="field.ph || 'Pilih salah satu'"
            style="width: 100%"
            @update:model-value="ubah"
        >
            <el-option v-for="o in field.opsi || []" :key="o" :value="o" :label="o" />
        </el-select>

        <el-radio-group
            v-else-if="field.tipe === 'radio'"
            :model-value="nilai"
            :disabled="disabled"
            @update:model-value="ubah"
        >
            <el-radio v-for="o in field.opsi || []" :key="o" :value="o">{{ o }}</el-radio>
        </el-radio-group>

        <el-checkbox-group
            v-else-if="field.tipe === 'checkbox'"
            :model-value="nilai || []"
            :disabled="disabled"
            @update:model-value="ubah"
        >
            <el-checkbox v-for="o in field.opsi || []" :key="o" :value="o">{{ o }}</el-checkbox>
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
import { computed } from 'vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Array, Object, null], default: null },
    disabled: { type: Boolean, default: false },
    galat: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'berkas']);

const nilai = computed(() => props.modelValue);

// Consent & textarea selalu memakan lebar penuh — dipaksa di sini supaya
// admin tidak perlu ingat mencentang "lebar penuh" untuk keduanya.
const lebarPenuh = computed(() => ['textarea', 'consent', 'checkbox'].includes(props.field.tipe));

function ubah(v) {
    emit('update:modelValue', v);
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
</style>
