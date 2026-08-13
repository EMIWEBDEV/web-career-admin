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
                    :class="[
                        `wca-modal--${ukuran}`,
                        { 'is-busy': busy, 'is-nudge': nudge },
                    ]"
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

                    <!-- Isi modal. Bila pemakainya mengisi slot `sticky`, bilah itu
                         dipasang sebagai anak PERTAMA yang menempel, dan padding body
                         dipindah ke pembungkus dalam.

                         Kenapa begitu dan bukan margin negatif seperti dulu: margin
                         negatif harus persis sebesar padding body, dan begitu salah
                         satunya berubah (breakpoint, penyetelan tema) sisanya menjadi
                         celah — jalur tempat isi yang tergulir terlihat menyembul di
                         atas bilah. Dengan padding dipindah ke dalam, bilahnya duduk
                         di koordinat nol scrollport tanpa satu angka pun yang perlu
                         dicocokkan. -->
                    <div class="wca-modal__body" :class="{ 'has-sticky': !! $slots.sticky }">
                        <div v-if="$slots.sticky" class="wca-modal__stickybar"><slot name="sticky" /></div>
                        <div class="wca-modal__bodyin"><slot /></div>
                    </div>

                    <div class="wca-modal__foot" :class="{ 'wca-modal__foot--blok': footBlok }">
                        <div v-if="footNote" class="wca-modal__footnote">
                            <i class="bi" :class="busy ? 'bi-hourglass-split' : 'bi-shield-check'"></i>
                            <span>{{ busy ? busyLabel : footNote }}</span>
                        </div>
                        <div class="wca-modal__footbtns">
                            <slot name="footer">
                                <button class="wca-btn wca-btn--ghost" type="button" :disabled="busy" @click="tutup">
                                    <i class="bi bi-x-lg"></i> {{ cancelLabel }}
                                </button>
                                <button class="wca-btn wca-btn--dark" type="button" :disabled="busy || saveDisabled" @click="$emit('save')">
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
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: 'bi-window-stack' },
    /* UKURAN MODULAR — satu prop, bukan empat boolean yang bisa menyala
       bersamaan. `sm` untuk konfirmasi sebaris, `md` bawaan, `lg`/`xl` untuk
       formulir & peninjauan, `full` untuk layar kerja penuh.
       Prop boolean lama tetap dihormati (lihat `ukuran`) supaya belasan
       halaman yang sudah memakai :lg / :xl tidak perlu disentuh. */
    /* Nilai asing (salah ketik `size="XL"`, `size="besar"`) ditolak di sini —
       kalau lolos, ia menghasilkan kelas CSS yang tak pernah ada dan modalnya
       tampil seukuran bawaan tanpa satu pun petunjuk kenapa. */
    size: {
        type: String,
        default: '',
        validator: (v) => ['', 'sm', 'md', 'lg', 'xl', 'full'].includes(v),
    },
    lg: { type: Boolean, default: false },
    xl: { type: Boolean, default: false },
    full: { type: Boolean, default: false },
    xxl: { type: Boolean, default: false },
    saveLabel: { type: String, default: 'Simpan Data' },
    saveDisabled: { type: Boolean, default: false },
    cancelLabel: { type: String, default: 'Batal' },
    footNote: { type: String, default: 'Periksa kembali data sebelum disimpan.' },
    /* Kaki modal berisi PANEL, bukan dua tombol berjajar. Dipakai jendela
       peninjauan (worklist) yang menaruh keterangan + deretan keputusan di
       kakinya; tanpa ini isinya menciut ke lebar isi dan merapat ke kanan. */
    footBlok: { type: Boolean, default: false },
    /* Proses simpan sedang berjalan: tombol dikunci + spinner, modal tak bisa
       ditutup. Mencegah klik ganda (data dobel) sekaligus memberi tahu pengguna
       bahwa kliknya SUDAH diterima. */
    busy: { type: Boolean, default: false },
    busyLabel: { type: String, default: 'Menyimpan…' },
});

const emit = defineEmits(['close', 'save']);

/**
 * Ukuran yang benar-benar dipakai.
 *
 * `size` menang bila diisi. Selebihnya diturunkan dari prop boolean lama —
 * dari yang TERBESAR lebih dulu, karena dua boolean yang menyala bersamaan
 * (mis. :lg :xl) dulu diselesaikan CSS dengan urutan berkas, dan urutan itu
 * bukan sesuatu yang bisa dibaca dari halaman pemakainya.
 */
const ukuran = computed(() => {
    if (props.size) return props.size;
    if (props.full || props.xxl) return 'full';
    if (props.xl) return 'xl';
    if (props.lg) return 'lg';

    return 'md';
});

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
