<!--
  WEB CAREER — Ambil foto verifikasi langsung dari kamera.

  Sengaja TIDAK memakai <input type="file" capture>. Di desktop atribut itu
  diabaikan dan dialognya jatuh ke pemilih berkas biasa, sehingga foto orang
  lain tetap bisa dikirim — padahal seluruh gunanya justru memastikan kandidat
  hadir saat pengambilan.

  Aliran kamera dimatikan begitu foto terambil dan saat komponen dilepas. Track
  yang menggantung membuat lampu kamera tetap menyala sepanjang sisa pengisian
  formulir, dan itu wajar dibaca kandidat sebagai perekaman diam-diam.
-->
<template>
    <div class="af">
        <div class="af__panggung" :class="{ 'is-aktif': kameraNyala || nilaiFoto }">
            <img v-if="nilaiFoto" :src="nilaiFoto" alt="Foto verifikasi" />
            <video v-show="kameraNyala && !nilaiFoto" ref="videoEl" autoplay playsinline muted></video>
            <!-- Foto sudah pernah diambil tapi gambarnya tidak ada di memori
                 komponen ini. Terjadi setiap kali kandidat pindah langkah lalu
                 kembali: layout bertahap melepas komponen langkah yang tidak
                 sedang dibuka, sehingga dataURL-nya hilang — padahal berkasnya
                 masih dipegang halaman induk dan tetap ikut terkirim.
                 Tanpa penanda ini panggungnya tampak kosong dan kandidat
                 mengira fotonya batal. -->
            <div v-if="!kameraNyala && adaFotoTersimpan" class="af__tersimpan">
                <i class="bi bi-check-circle-fill"></i>
                <strong>Foto sudah diambil</strong>
                <small>{{ namaTersimpan }}</small>
            </div>
            <div v-else-if="!kameraNyala && !nilaiFoto" class="af__idle">
                <i class="bi bi-person-bounding-box"></i>
                <span>Kamera belum aktif</span>
            </div>
            <canvas ref="canvasEl" hidden></canvas>
        </div>

        <p v-if="galatKamera" class="af__galat">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ galatKamera }}
        </p>

        <div class="af__aksi">
            <template v-if="!nilaiFoto && !adaFotoTersimpan">
                <button
                    v-if="!kameraNyala"
                    type="button"
                    class="af__btn af__btn--utama"
                    :disabled="disabled || menyalakan"
                    :onClick="disabled || menyalakan ? null : nyalakan"
                >
                    <i class="bi" :class="menyalakan ? 'bi-arrow-repeat af__spin' : 'bi-camera-video-fill'"></i>
                    {{ menyalakan ? 'Menunggu izin kamera…' : 'Aktifkan Kamera' }}
                </button>
                <template v-else>
                    <button type="button" class="af__btn af__btn--utama" :disabled="disabled" :onClick="disabled ? null : jepret">
                        <i class="bi bi-camera-fill"></i> Ambil Foto
                    </button>
                    <button type="button" class="af__btn" :disabled="disabled" :onClick="disabled ? null : matikan">
                        <i class="bi bi-x-lg"></i> Batal
                    </button>
                </template>
            </template>
            <button v-else type="button" class="af__btn" :disabled="disabled" :onClick="disabled ? null : ulangi">
                <i class="bi bi-arrow-repeat"></i> Ambil Ulang
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    // Nama berkas yang tersimpan di jawaban. Jadi satu-satunya bukti bahwa foto
    // pernah diambil setelah komponen ini dipasang ulang tanpa membawa gambarnya.
    namaTersimpan: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    // Kualitas JPEG. Cukup untuk pencocokan wajah, tapi tetap ringan diunggah
    // dari jaringan seluler yang jadi andalan sebagian besar pelamar.
    kualitas: { type: Number, default: 0.85 },
});

const emit = defineEmits(['update:modelValue', 'foto']);

const videoEl = ref(null);
const canvasEl = ref(null);
const kameraNyala = ref(false);
const menyalakan = ref(false);
const galatKamera = ref('');
const nilaiFoto = ref(props.modelValue || '');

/** Foto pernah diambil, tapi gambarnya tidak ada di komponen ini. */
const adaFotoTersimpan = computed(() => ! nilaiFoto.value && !! props.namaTersimpan);

let aliran = null;
/** Komponen sudah dilepas — dipakai membatalkan permintaan izin yang telat datang. */
let dibuang = false;

// Foto draf datang BELAKANGAN — URL-nya baru diketahui setelah permintaan draf
// ke server selesai, sementara komponen sudah terpasang lebih dulu. Tanpa
// pengawas ini foto yang sudah tersimpan tidak pernah tampil.
watch(() => props.modelValue, (v) => {
    if (v && v !== nilaiFoto.value) nilaiFoto.value = v;
});

/**
 * Menyalakan kamera.
 *
 * `getUserMedia` menunggu kandidat menjawab dialog izin peramban — bisa
 * berdetik-detik, dan selama itu ia bebas menekan Kembali atau mengklik
 * tombolnya lagi. Dua hal yang harus dijaga:
 *
 *   1. Kalau komponen sudah dilepas saat izin akhirnya diberikan, aliran yang
 *      baru lahir tidak bisa dijangkau siapa pun lagi — lampu kamera menyala
 *      terus sampai tab ditutup. Karena itu aliran yang datang terlambat
 *      langsung dihentikan di tempat.
 *   2. Klik ganda akan menimpa `aliran` dengan aliran kedua dan menelantarkan
 *      yang pertama, dengan akibat yang sama. Karena itu permintaan yang sedang
 *      berjalan mengunci tombolnya.
 */
async function nyalakan() {
    if (menyalakan.value || kameraNyala.value) return;

    galatKamera.value = '';
    if (! navigator.mediaDevices?.getUserMedia) {
        galatKamera.value = 'Peramban ini tidak mendukung akses kamera. Coba Chrome atau Safari versi terbaru.';

        return;
    }

    menyalakan.value = true;
    try {
        const baru = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });

        // Dibatalkan selagi izin ditunggu (komponen dilepas, atau matikan()
        // dipanggil). Aliran ini tidak akan pernah dipakai — hentikan sekarang.
        if (dibuang || ! menyalakan.value) {
            baru.getTracks().forEach((t) => t.stop());

            return;
        }

        aliran = baru;
        kameraNyala.value = true;
        // Elemen <video> baru benar-benar ada di DOM setelah v-show ikut
        // berubah, jadi pemasangan srcObject menunggu satu putaran.
        await Promise.resolve();
        if (videoEl.value) videoEl.value.srcObject = aliran;
    } catch (e) {
        kameraNyala.value = false;
        galatKamera.value = e?.name === 'NotAllowedError'
            ? 'Izin kamera ditolak. Aktifkan izin kamera untuk situs ini lalu coba lagi.'
            : 'Kamera tidak dapat diakses. Pastikan halaman dibuka lewat HTTPS dan tidak ada aplikasi lain yang memakainya.';
    } finally {
        menyalakan.value = false;
    }
}

function matikan() {
    // Menandai permintaan yang mungkin sedang menunggu izin sebagai batal.
    menyalakan.value = false;
    if (aliran) {
        aliran.getTracks().forEach((t) => t.stop());
        aliran = null;
    }
    kameraNyala.value = false;
}

/**
 * Sisi terpanjang foto. Server menolak berkas di atas 2 MB, dan kamera ponsel
 * kelas atas menangkap 4K — satu jepretan bisa menembus batas itu lalu ditolak
 * setelah kandidat mengira fotonya sudah masuk. 1280 px lebih dari cukup untuk
 * mencocokkan wajah, dan hasilnya konsisten di bawah beberapa ratus kilobyte.
 */
const SISI_MAKS = 1280;

function jepret() {
    const v = videoEl.value;
    const c = canvasEl.value;
    if (! v || ! c || ! v.videoWidth) return;

    const skala = Math.min(1, SISI_MAKS / Math.max(v.videoWidth, v.videoHeight));
    c.width = Math.round(v.videoWidth * skala);
    c.height = Math.round(v.videoHeight * skala);
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);

    const dataUrl = c.toDataURL('image/jpeg', props.kualitas);
    nilaiFoto.value = dataUrl;
    matikan();

    emit('update:modelValue', dataUrl);
    emit('foto', dataUrl);
}

function ulangi() {
    nilaiFoto.value = '';
    emit('update:modelValue', '');
    emit('foto', '');
    nyalakan();
}

onBeforeUnmount(() => {
    dibuang = true;
    matikan();
});
</script>

<style scoped>
.af { display: flex; flex-direction: column; gap: .5rem; }

/* Blok kamera ditaruh di tengah kolomnya. Field ini hampir selalu memakai
   lebar penuh, jadi panggung 22rem yang menempel ke kiri menyisakan ruang
   kosong lebar di kanan dan terbaca seperti tata letak yang belum selesai. */
.af__panggung {
    position: relative;
    aspect-ratio: 4 / 3;
    width: 100%;
    max-width: 22rem;
    margin-inline: auto;
    border-radius: 12px;
    overflow: hidden;
    background: #f1f5f9;
    border: 1.5px dashed rgba(11, 16, 51, .16);
    display: grid;
    place-items: center;
}
.af__panggung.is-aktif { border-style: solid; border-color: rgba(79, 70, 229, .35); }
.af__panggung img,
.af__panggung video { width: 100%; height: 100%; object-fit: cover; display: block; }
/* Pratinjau kamera dicerminkan supaya terasa seperti cermin. Tanpa ini gerakan
   ke kanan tampak ke kiri dan kandidat kesulitan memposisikan wajahnya. */
.af__panggung video { transform: scaleX(-1); }

.af__idle { display: flex; flex-direction: column; align-items: center; gap: .35rem; color: #94a3b8; font-size: 12px; }
.af__idle .bi { font-size: 1.8rem; }

.af__tersimpan { display: flex; flex-direction: column; align-items: center; gap: .2rem; padding: 0 1rem; text-align: center; color: #059669; }
.af__tersimpan .bi { font-size: 1.8rem; }
.af__tersimpan strong { font-size: 12.5px; font-weight: 800; }
.af__tersimpan small { font-size: 11px; color: #94a3b8; overflow-wrap: anywhere; }

.af__spin { display: inline-block; animation: afSpin .9s linear infinite; }
@keyframes afSpin { to { transform: rotate(360deg); } }

.af__galat { margin: 0 auto; max-width: 22rem; font-size: 11.5px; line-height: 1.5; color: #b91c1c; display: flex; align-items: flex-start; justify-content: center; gap: .3rem; text-align: center; }
.af__galat .bi { flex: none; margin-top: .1rem; }

.af__aksi { display: flex; flex-wrap: wrap; justify-content: center; gap: .4rem; }
.af__btn {
    border: 1px solid rgba(11, 16, 51, .14); border-radius: .7rem; background: #fff; color: #475569;
    font: inherit; font-size: 12.5px; font-weight: 700; padding: .5rem .9rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: .35rem; transition: filter 150ms ease;
}
.af__btn:disabled { opacity: .5; cursor: not-allowed; }
.af__btn--utama { border-color: transparent; background: linear-gradient(140deg, #4f46e5, #7c3aed); color: #fff; }
.af__btn:hover:not(:disabled) { filter: brightness(1.05); }
</style>
