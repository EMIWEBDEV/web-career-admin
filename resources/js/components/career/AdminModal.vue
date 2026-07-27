<!-- WEB CAREER — Modal reusable, kulit desain "Modal Jadwal Kegiatan" (Claude
     Design): header gradien lavender + tile ikon ungu + tombol tutup berputar,
     body bergradasi lembut, footer catatan kiri + tombol Batal/Simpan gradien.
     Struktur slot & emit TIDAK berubah — semua halaman pemakai tetap kompatibel. -->
<template>
    <teleport to="body">
        <transition name="wca-modal">
            <!-- Klik latar SENGAJA tidak menutup modal: satu klik meleset saat
                 mengisi form panjang (mis. susunan tahapan alur) akan membuang
                 seluruh isian tanpa peringatan. Penutupan hanya lewat tombol
                 Batal / X. Klik latar dibalas goyangan singkat sebagai isyarat
                 bahwa modal memang sengaja bertahan — bukan aplikasi macet. -->
            <div v-if="show" class="wca-modal-mask wca" @click.self="tolakTutup">
                <div
                    class="wca-modal"
                    :class="{ 'wca-modal--lg': lg, 'wca-modal--xl': xl, 'is-busy': busy, 'is-nudge': nudge }"
                    role="dialog" aria-modal="true" :aria-busy="busy"
                >
                    <!-- Garis progres tipis di puncak modal: penanda proses berjalan
                         yang tetap terlihat walau tombol sudah tergulir keluar layar. -->
                    <div v-if="busy" class="wca-modal__bar" aria-hidden="true"></div>

                    <div class="wca-modal__head">
                        <div class="wca-modal__headglow"></div>
                        <span class="wca-modal__icon"><i class="bi" :class="icon"></i></span>
                        <div class="wca-modal__titles">
                            <h3>{{ title }}</h3>
                            <p v-if="subtitle">{{ subtitle }}</p>
                        </div>
                        <button class="wca-modal__close" type="button" aria-label="Tutup" :disabled="busy" @click="tutup">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="wca-modal__body"><slot /></div>

                    <div class="wca-modal__foot">
                        <div v-if="footNote" class="wca-modal__footnote">
                            <i class="bi" :class="busy ? 'bi-hourglass-split' : 'bi-shield-check'"></i>
                            <span>{{ busy ? busyLabel : footNote }}</span>
                        </div>
                        <div class="wca-modal__footbtns">
                            <slot name="footer">
                                <button class="wca-btn wca-btn--ghost" type="button" :disabled="busy" @click="tutup">
                                    <i class="bi bi-x-lg"></i> {{ cancelLabel }}
                                </button>
                                <button class="wca-btn wca-btn--dark" type="button" :disabled="busy" @click="$emit('save')">
                                    <span v-if="busy" class="wca-spin" aria-hidden="true"></span>
                                    <i v-else class="bi bi-save"></i>
                                    {{ busy ? busyLabel : saveLabel }}
                                </button>
                            </slot>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: 'bi-window-stack' },
    lg: { type: Boolean, default: false },
    xl: { type: Boolean, default: false },
    saveLabel: { type: String, default: 'Simpan Data' },
    cancelLabel: { type: String, default: 'Batal' },
    footNote: { type: String, default: 'Periksa kembali data sebelum disimpan.' },
    /* Proses simpan sedang berjalan: tombol dikunci + spinner, modal tak bisa
       ditutup. Mencegah klik ganda (data dobel) sekaligus memberi tahu pengguna
       bahwa kliknya SUDAH diterima. */
    busy: { type: Boolean, default: false },
    busyLabel: { type: String, default: 'Menyimpan…' },
});

const emit = defineEmits(['close', 'save']);

/** Tutup hanya lewat tombol; diabaikan selama proses simpan berjalan. */
function tutup() {
    if (! props.busy) emit('close');
}

/* Klik latar: modal TIDAK ditutup (lihat catatan di template). Beri goyangan
   singkat supaya pengguna paham modal sengaja bertahan. */
const nudge = ref(false);
let nudgeTimer = null;

function tolakTutup() {
    if (nudgeTimer) clearTimeout(nudgeTimer);
    nudge.value = true;
    nudgeTimer = setTimeout(() => (nudge.value = false), 420);
}

onBeforeUnmount(() => nudgeTimer && clearTimeout(nudgeTimer));
</script>
