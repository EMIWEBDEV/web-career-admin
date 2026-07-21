<!--
  WEB CAREER — TEMPLATE 2: Identitas Bertahap.

  Banyak langkah dengan stepper, bagian bernama, mendukung bagian berulang,
  unggah berkas, dan pernyataan persetujuan. Dipakai formulir seperti
  "Form Identitas Peserta Rekrutmen MT" (Page 1-4) dan form MT lengkap
  (Identitas / Pendidikan / Organisasi / Pengalaman / Prestasi / Dokumen).

  Validasi berjalan PER LANGKAH: kandidat tidak bisa maju sebelum langkah
  yang sedang dibuka lengkap, tapi juga tidak dibombardir seluruh galat
  formulir sekaligus.
-->
<template>
    <div class="t2">
        <!-- ── Stepper ── -->
        <nav class="t2__steps">
            <button
                v-for="(L, i) in langkah"
                :key="i"
                class="t2__step"
                :class="{ done: i < aktif, cur: i === aktif }"
                type="button"
                :disabled="i > terjauh"
                @click="i <= terjauh && (aktif = i)"
            >
                <span class="t2__dot">
                    <i v-if="i < aktif" class="bi bi-check-lg"></i>
                    <i v-else class="bi" :class="L.ikon || 'bi-card-list'"></i>
                </span>
                <span class="t2__lbl">{{ L.judul }}</span>
            </button>
        </nav>

        <div v-if="!langkah.length" class="t2__kosong">
            <i class="bi bi-inbox"></i><span>Skema belum punya langkah.</span>
        </div>

        <!-- ── Isi langkah aktif ── -->
        <div v-else class="t2__panel">
            <header class="t2__head">
                <span class="t2__badge"><i class="bi" :class="kini.ikon || 'bi-card-list'"></i></span>
                <div>
                    <span class="t2__no">Langkah {{ aktif + 1 }} dari {{ langkah.length }}</span>
                    <h3>{{ kini.judul }}</h3>
                    <p v-if="kini.deskripsi">{{ kini.deskripsi }}</p>
                </div>
            </header>

            <BagianRenderer
                v-for="(B, iB) in bagianTerlihat"
                :key="iB"
                :bagian="B"
                :jawaban="jawaban"
                :disabled="disabled"
                @ubah="setNilai"
                @ubah-baris="setNilaiBaris"
                @berkas="(e) => $emit('berkas', e)"
            />

            <div v-if="galat.length" class="t2__galat">
                <strong><i class="bi bi-exclamation-triangle-fill"></i> Lengkapi dulu langkah ini:</strong>
                <ul><li v-for="(g, i) in galat" :key="i">{{ g }}</li></ul>
            </div>

            <footer class="t2__foot">
                <button class="t2__btn t2__btn--ghost" type="button" :disabled="aktif === 0" @click="mundur">
                    <i class="bi bi-arrow-left"></i> Kembali
                </button>
                <span class="t2__spacer"></span>
                <button v-if="!terakhir" class="t2__btn t2__btn--primary" type="button" :disabled="disabled" @click="maju">
                    Lanjut <i class="bi bi-arrow-right"></i>
                </button>
                <button v-else class="t2__btn t2__btn--primary" type="button" :disabled="disabled" @click="kirim">
                    <i class="bi bi-send-check-fill"></i> {{ labelKirim }}
                </button>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import BagianRenderer from '../../inti/BagianRenderer.vue';
import { bagianTampil, periksaLangkah } from '../../inti/aturan';

const props = defineProps({
    skema: { type: Object, required: true },
    modelValue: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    labelKirim: { type: String, default: 'Kirim Formulir' },
    // Langkah terakhir yang sudah pernah dicapai — dipulihkan dari
    // Formulir_Pengisian.Langkah_Terakhir agar kandidat bisa lanjut esok hari.
    langkahAwal: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'kirim', 'berkas', 'pindah-langkah']);

const langkah = computed(() => props.skema?.langkah || []);
const jawaban = computed(() => props.modelValue);

const aktif = ref(props.langkahAwal);
// Langkah terjauh yang sudah lolos validasi. Kandidat bebas mundur-maju di
// wilayah yang sudah dilewati, tapi tidak bisa melompati yang belum lengkap.
const terjauh = ref(props.langkahAwal);
const galat = ref([]);

const kini = computed(() => langkah.value[aktif.value] || {});
const terakhir = computed(() => aktif.value >= langkah.value.length - 1);
const bagianTerlihat = computed(() => bagianTampil(kini.value.bagian, jawaban.value));

watch(aktif, (v) => {
    galat.value = [];
    if (v > terjauh.value) terjauh.value = v;
    emit('pindah-langkah', v);
});

// Skema berganti (mis. pratinjau template lain) -> mulai lagi dari awal.
watch(() => props.skema, () => {
    aktif.value = 0;
    terjauh.value = 0;
    galat.value = [];
});

function setNilai(key, nilai) {
    emit('update:modelValue', { ...jawaban.value, [key]: nilai });
}

function setNilaiBaris(kunciBagian, i, key, nilai) {
    const arr = [...(jawaban.value[kunciBagian] || [])];
    arr[i] = { ...arr[i], [key]: nilai };
    setNilai(kunciBagian, arr);
}

function periksa() {
    galat.value = periksaLangkah(kini.value, jawaban.value);
    return galat.value.length === 0;
}

function maju() {
    if (!periksa()) return;
    aktif.value = Math.min(aktif.value + 1, langkah.value.length - 1);
}

function mundur() {
    galat.value = [];
    aktif.value = Math.max(aktif.value - 1, 0);
}

function kirim() {
    if (!periksa()) return;
    // Sapu seluruh langkah sebelum benar-benar mengirim: syarat tampil bisa
    // berubah setelah kandidat mengedit jawaban di langkah awal, sehingga
    // field yang tadinya tersembunyi jadi wajib.
    const semua = langkah.value.flatMap((L) => periksaLangkah(L, jawaban.value));
    if (semua.length) {
        galat.value = semua;
        return;
    }
    emit('kirim', jawaban.value);
}
</script>

<style scoped>
.t2 { max-width: 48rem; margin: 0 auto; }

/* ── Stepper ── */
.t2__steps { display: flex; gap: .3rem; overflow-x: auto; padding-bottom: .7rem; margin-bottom: 1rem; border-bottom: 1px solid rgba(11, 16, 51, .09); }
.t2__step { flex: 1; min-width: 6rem; display: flex; flex-direction: column; align-items: center; gap: .3rem; border: 0; background: transparent; cursor: pointer; padding: .3rem .2rem; font: inherit; }
.t2__step:disabled { cursor: not-allowed; opacity: .45; }
.t2__dot { width: 1.9rem; height: 1.9rem; display: grid; place-items: center; border-radius: 50%; background: #eef2f7; color: #94a3b8; font-size: .8rem; transition: all 200ms ease; }
.t2__step.done .t2__dot { background: rgba(16, 185, 129, .15); color: #059669; }
.t2__step.cur .t2__dot { background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 6px 14px -6px rgba(79, 70, 229, .9); }
.t2__lbl { font-size: 10.5px; font-weight: 600; color: #94a3b8; text-align: center; line-height: 1.3; }
.t2__step.cur .t2__lbl { color: #4338ca; }
.t2__step.done .t2__lbl { color: #059669; }

/* ── Panel ── */
.t2__kosong { display: flex; align-items: center; gap: .5rem; padding: 2rem; justify-content: center; color: #94a3b8; font-size: 13px; }
.t2__head { display: flex; align-items: flex-start; gap: .8rem; margin-bottom: 1.1rem; }
.t2__badge { flex: none; width: 2.5rem; height: 2.5rem; display: grid; place-items: center; border-radius: .9rem; background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; font-size: 1.05rem; }
.t2__no { font-size: 10.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #7c3aed; }
.t2__head h3 { margin: .1rem 0 0; font-size: 1.1rem; font-weight: 800; color: #0f172a; letter-spacing: -.015em; }
.t2__head p { margin: .15rem 0 0; font-size: 12.5px; color: #64748b; line-height: 1.55; }

.t2__galat { margin-top: 1rem; padding: .75rem .9rem; border: 1px solid rgba(239, 68, 68, .25); border-radius: 12px; background: rgba(239, 68, 68, .07); color: #b91c1c; font-size: 12.5px; }
.t2__galat strong { display: flex; align-items: center; gap: .35rem; }
.t2__galat ul { margin: .4rem 0 0; padding-left: 1.2rem; line-height: 1.7; }

.t2__foot { display: flex; align-items: center; gap: .5rem; margin-top: 1.3rem; padding-top: .9rem; border-top: 1px solid rgba(11, 16, 51, .08); }
.t2__spacer { flex: 1; }
.t2__btn { border: 0; border-radius: .8rem; padding: .65rem 1.2rem; font: inherit; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: .4rem; transition: filter 160ms ease, transform 160ms ease; }
.t2__btn:disabled { opacity: .45; cursor: not-allowed; }
.t2__btn--ghost { background: #f1f5f9; color: #475569; }
.t2__btn--primary { background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; box-shadow: 0 10px 22px -12px rgba(79, 70, 229, .9); }
.t2__btn--primary:hover:not(:disabled) { filter: brightness(1.06); transform: translateY(-1px); }

@media (max-width: 620px) {
    .t2__lbl { display: none; }
    .t2__step { min-width: 2.6rem; }
}
</style>
