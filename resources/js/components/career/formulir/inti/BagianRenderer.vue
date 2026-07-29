<!-- WEB CAREER — Render SATU bagian formulir: biasa atau berulang (repeater). -->
<template>
    <section class="bg">
        <header v-if="bagian.judul" class="bg__head">
            <h4>{{ bagian.judul }}</h4>
            <p v-if="bagian.deskripsi">{{ bagian.deskripsi }}</p>
        </header>

        <!-- ── Bagian BERULANG: Pengalaman Kerja, Organisasi, Prestasi ──
             Jawabannya array objek, satu objek per baris. -->
        <template v-if="bagian.berulang">
            <div v-for="(baris, i) in baris" :key="i" class="bg__baris">
                <div class="bg__baris-head">
                    <span class="bg__baris-no">{{ i + 1 }}</span>
                    <button
                        v-if="!disabled && baris.length > 1"
                        class="bg__hapus"
                        type="button"
                        title="Hapus baris"
                        @click="hapusBaris(i)"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="bg__grid">
                    <FieldRenderer
                        v-for="f in fieldTerlihat(baris)"
                        :key="f.key"
                        :field="f"
                        :model-value="baris[f.key]"
                        :disabled="disabled"
                        :konteks="konteksOpsi"
                        :jawaban="baris"
                        @update:model-value="(v) => ubahBaris(i, f.key, v)"
                        @berkas="(e) => $emit('berkas', { ...e, bagian: kunci, baris: i })"
                    />
                </div>
            </div>

            <button
                v-if="!disabled && baris.length < (bagian.maks_baris || 5)"
                class="bg__tambah"
                type="button"
                @click="tambahBaris"
            >
                <i class="bi bi-plus-lg"></i> Tambah {{ bagian.judul || 'baris' }}
                <small>({{ baris.length }}/{{ bagian.maks_baris || 5 }})</small>
            </button>
        </template>

        <!-- ── Bagian biasa ── -->
        <div v-else class="bg__grid">
            <FieldRenderer
                v-for="f in fieldTerlihat(jawaban)"
                :key="f.key"
                :field="f"
                :model-value="jawaban[f.key]"
                :disabled="disabled"
                :konteks="konteksOpsi"
                :jawaban="jawaban"
                :galat="galat[f.key] || ''"
                @update:model-value="(v) => $emit('ubah', f.key, v)"
                @berkas="(e) => $emit('berkas', e)"
            />
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import FieldRenderer from './FieldRenderer.vue';
import { fieldTampil, kunciBagian, barisKosong } from './aturan';

const props = defineProps({
    bagian: { type: Object, required: true },
    jawaban: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    galat: { type: Object, default: () => ({}) },
    // Konteks pembukaan (opsi dinamis field, mis. kampus whitelist).
    konteksOpsi: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['ubah', 'ubah-baris', 'berkas']);

const kunci = computed(() => kunciBagian(props.bagian));
const baris = computed(() => props.jawaban[kunci.value] || []);

/**
 * Syarat tampil dievaluasi terhadap KONTEKS yang benar: di bagian berulang,
 * acuannya jawaban baris itu sendiri, bukan jawaban formulir secara global.
 * Kalau tidak, satu baris bisa menyembunyikan field di baris lain.
 */
function fieldTerlihat(konteks) {
    return fieldTampil(props.bagian.field, konteks);
}

function ubahBaris(i, key, nilai) {
    emit('ubah-baris', kunci.value, i, key, nilai);
}

function tambahBaris() {
    emit('ubah', kunci.value, [...baris.value, barisKosong(props.bagian)]);
}

function hapusBaris(i) {
    emit('ubah', kunci.value, baris.value.filter((_, j) => j !== i));
}
</script>

<style scoped>
.bg { margin-bottom: 1.1rem; }

.bg__head { margin-bottom: .7rem; }
.bg__head h4 { margin: 0; font-size: .95rem; font-weight: 800; color: #0f172a; letter-spacing: -.01em; }
.bg__head p { margin: .2rem 0 0; font-size: 12px; color: #64748b; line-height: 1.55; }

.bg__grid { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }

.bg__baris { border: 1px solid rgba(11, 16, 51, .09); border-radius: 12px; padding: .8rem; margin-bottom: .6rem; background: #f8fafc; }
.bg__baris-head { display: flex; align-items: center; margin-bottom: .6rem; }
.bg__baris-no { width: 1.5rem; height: 1.5rem; display: grid; place-items: center; border-radius: 50%; background: rgba(79, 70, 229, .12); color: #4338ca; font-size: 11px; font-weight: 700; }
.bg__hapus { margin-left: auto; border: 0; background: transparent; color: #cbd5e1; font-size: .9rem; cursor: pointer; padding: .1rem .3rem; }
.bg__hapus:hover { color: #dc2626; }

.bg__tambah { width: 100%; border: 1px dashed rgba(79, 70, 229, .3); border-radius: 10px; background: transparent; color: #4338ca; font: inherit; font-size: 12px; font-weight: 600; padding: .5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: .35rem; transition: background 160ms ease; }
.bg__tambah:hover { background: rgba(79, 70, 229, .07); }
.bg__tambah small { color: #94a3b8; font-weight: 500; }

@media (max-width: 700px) {
    .bg__grid { grid-template-columns: 1fr; }
}
</style>
