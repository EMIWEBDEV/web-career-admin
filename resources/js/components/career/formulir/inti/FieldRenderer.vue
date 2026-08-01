<!-- WEB CAREER — Render SATU field sesuai tipe di skema. Dipakai semua template. -->
<template>
    <div class="fr" :class="{ 'fr--full': field.penuh || lebarPenuh }">
        <label class="fr__lbl">
            {{ field.label }}
            <span v-if="field.wajib && !prefillTerkunci" class="fr__wajib">*</span>
            <span v-if="prefillTerkunci" class="fr__auto"><i class="bi bi-magic"></i> otomatis</span>
            <span v-else-if="field.tipe === 'prefill'" class="fr__ubah"><i class="bi bi-pencil-fill"></i> dapat diubah</span>
        </label>

        <!-- Terisi otomatis dari profil kandidat, TERKUNCI. -->
        <el-input v-if="prefillTerkunci" :model-value="String(nilai ?? '')" disabled placeholder="—" />

        <!-- Prefill yang DIBUKA: disunting di tempatnya, bukan diketik ulang di
             kolom bebas. `tipe_buka` menentukan perlakuannya (teks/email/telepon). -->
        <TeleponNegara
            v-else-if="field.tipe === 'prefill' && field.tipe_buka === 'phone'"
            :model-value="String(nilai ?? '')"
            :disabled="disabled"
            :placeholder="field.ph || '81234567890'"
            @update:model-value="ubah"
        />

        <el-input
            v-else-if="field.tipe === 'prefill'"
            :model-value="nilai"
            :type="field.tipe_buka === 'email' ? 'email' : 'text'"
            :placeholder="field.ph || 'Tulis data yang benar'"
            @update:model-value="ubah"
        />

        <el-input
            v-else-if="field.tipe === 'text'"
            :model-value="nilai"
            :disabled="disabled"
            :placeholder="field.ph"
            @update:model-value="ubah"
        />

        <!-- Telepon SEMUA NEGARA — komponen yang sama dengan formulir apply,
             supaya kandidat tidak menemui dua gaya isian nomor yang berbeda. -->
        <TeleponNegara
            v-else-if="field.tipe === 'phone'"
            :model-value="String(nilai ?? '')"
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
        <el-upload
            v-else-if="field.tipe === 'file'"
            class="fr__drop"
            :class="{ 'is-ada': nilai, 'is-mati': disabled }"
            drag
            :accept="field.accept || '.pdf'"
            :auto-upload="false"
            :show-file-list="false"
            :disabled="disabled"
            :on-change="pilihBerkas"
        >
            <span class="fr__drop-ico">
                <i class="bi" :class="nilai ? 'bi-file-earmark-check-fill' : 'bi-cloud-arrow-up-fill'"></i>
            </span>
            <span class="fr__drop-txt">
                <button
                    v-if="nilai && urlPratinjau"
                    type="button"
                    class="fr__drop-nama"
                    @click.stop="lihatBerkas"
                >{{ nilai }}</button>
                <strong v-else>{{ nilai || 'Seret berkas ke sini' }}</strong>
                <small v-if="nilai">Berkas siap dikirim · klik untuk mengganti</small>
                <small v-else>atau klik untuk memilih · {{ field.accept || '.pdf' }} · maks {{ field.maks_mb || 2 }} MB</small>
            </span>
            <span class="fr__drop-act">{{ nilai ? 'Ganti' : 'Pilih' }}</span>
        </el-upload>


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

        <!-- Pratinjau berkas terpilih. Gambar (mis. foto KTP) ditampilkan apa
             adanya; PDF cukup tautan buka di tab baru karena tidak semua
             peramban bisa menyematkannya dengan andal. -->
        <div v-if="field.tipe === 'file' && nilai" class="fr__pv">
            <button v-if="urlPratinjau && gambarPratinjau" type="button" class="fr__pv-img" @click="lihatBerkas">
                <img :src="urlPratinjau" :alt="String(nilai)">
            </button>
            <!-- NAMA BERKAS-nya sendiri yang jadi tautan — itu yang pertama
                 dicari orang untuk diklik, bukan kata "Buka" di sebelahnya. -->
            <button v-else-if="urlPratinjau" type="button" class="fr__pv-doc" @click="lihatBerkas">
                <i class="bi bi-file-earmark-pdf-fill"></i>
                <span>{{ nilai }}</span>
                <em><i class="bi bi-eye-fill"></i> Lihat berkas</em>
            </button>
            <!-- Belum ada URL sama sekali (mis. simpan sementara gagal). Nama
                 tetap ditampilkan, tapi tanpa tautan yang pasti gagal dibuka. -->
            <span v-else class="fr__pv-doc is-mati" title="Berkas belum tersimpan di server">
                <i class="bi bi-file-earmark-fill"></i>
                <span>{{ nilai }}</span>
            </span>

            <!-- Salah pilih berkas harus bisa dibatalkan tanpa mengunggah ulang
                 berkas lain sebagai penggantinya. -->
            <button type="button" class="fr__pv-del" :disabled="disabled" title="Hapus berkas" @click="hapusBerkas">
                <i class="bi bi-trash3-fill"></i> Hapus
            </button>
        </div>

        <div v-if="field.bantuan" class="fr__bantuan">{{ field.bantuan }}</div>
        <div v-if="galat" class="fr__galat"><i class="bi bi-exclamation-circle"></i> {{ galat }}</div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { ambilOpsi, tunda } from './referensi';
import { syaratTerpenuhi } from './aturan';
import TeleponNegara from '@career/TeleponNegara.vue';

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

// URL objek berkas yang baru dipilih (belum terkirim) + apakah ia gambar.
const pratinjau = ref('');
const pratinjauGambar = ref(false);

// Berkas draf yang sudah tersimpan di server, dititipkan lewat `konteks`.
// Dipakai setelah halaman dimuat ulang: URL objek lokal ikut hilang bersama
// komponennya, jadi tanpa ini berkas yang sudah ada tampak tak bisa dibuka.
const drafBerkas = computed(() => props.konteks?.berkasDraf?.[props.field.key] || null);
const urlPratinjau = computed(() => pratinjau.value || drafBerkas.value?.url || '');
const gambarPratinjau = computed(
    () => pratinjauGambar.value || String(drafBerkas.value?.mime || '').startsWith('image/'),
);

// Prefill TANPA `buka_jika` selalu terkunci — perilaku lama tetap utuh untuk
// formulir lain yang memakainya sebagai tampilan baca-saja.
const prefillTerkunci = computed(
    () => props.field.tipe === 'prefill'
        && !(props.field.buka_jika && syaratTerpenuhi(props.field.buka_jika, props.jawabanKonteks)),
);

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

/**
 * Terima berkas dari tombol MAUPUN dari seret-lepas.
 *
 * Atribut `accept` hanya menyaring dialog pilih berkas — seret-lepas melewatinya
 * begitu saja. Tanpa pemeriksaan di sini, berkas .exe yang diseret tetap
 * terpasang dan baru ditolak server setelah kandidat menekan Kirim.
 */
/**
 * Batalkan berkas yang sudah dipilih.
 *
 * URL objeknya dilepas supaya salinan berkas tidak menggantung di memori
 * peramban, dan induknya diberi tahu lewat `hapus: true` agar berkas yang
 * telanjur dikumpulkan untuk dikirim ikut dibuang — kalau tidak, nama berkas
 * hilang dari layar tapi isinya tetap terkirim.
 */
/**
 * Minta induk membuka berkas di MODAL halaman, bukan tab baru.
 *
 * Tab baru melempar kandidat keluar dari formulir yang sedang diisi; modal
 * membiarkannya memeriksa berkas lalu langsung melanjutkan. Modalnya milik
 * halaman, jadi tampilannya sama dengan pratinjau berkas lain di Web Careers.
 */
function lihatBerkas() {
    if (!urlPratinjau.value) return;
    emit('berkas', {
        field: props.field,
        lihat: { url: urlPratinjau.value, nama: String(nilai.value || 'Berkas'), gambar: gambarPratinjau.value },
    });
}

function hapusBerkas() {
    if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
    pratinjau.value = '';
    pratinjauGambar.value = false;
    emit('update:modelValue', '');
    emit('berkas', { field: props.field, hapus: true });
}

function pilihBerkas(uf) {
    const file = uf.raw || uf;
    const izin = (props.field.accept || '.pdf')
        .split(',')
        .map((x) => x.trim().toLowerCase())
        .filter(Boolean);

    const ext = '.' + (file.name.split('.').pop() || '').toLowerCase();
    const cocok = izin.some((a) => (a.startsWith('.') ? a === ext : file.type === a));

    if (!cocok) {
        emit('berkas', {
            field: props.field,
            galat: `"${file.name}" ditolak — hanya menerima ${izin.join(', ')}.`,
        });
        return;
    }

    const maks = (props.field.maks_mb || 2) * 1024 * 1024;
    if (file.size > maks) {
        emit('berkas', {
            field: props.field,
            galat: `"${file.name}" melebihi ${props.field.maks_mb || 2} MB.`,
        });
        return;
    }

    // Pratinjau lokal: URL sementara dari berkas yang baru dipilih, supaya
    // kandidat bisa memastikan yang terunggah memang benar SEBELUM mengirim.
    if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
    pratinjau.value = URL.createObjectURL(file);
    pratinjauGambar.value = file.type.startsWith('image/');

    emit('update:modelValue', file.name);
    emit('berkas', { field: props.field, file });
}
</script>

<style scoped>
.fr { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
.fr--full { grid-column: 1 / -1; }

.fr__lbl { font-size: 12px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: .3rem; }
.fr__wajib { color: #dc2626; }
.fr__ubah { display: inline-flex; align-items: center; gap: 4px; margin-left: 6px; padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, .12); }
.fr__auto { font-size: 10.5px; font-weight: 600; color: #4338ca; background: rgba(79, 70, 229, .1); border-radius: 999px; padding: .05rem .4rem; }

.fr__bantuan { font-size: 11px; color: #94a3b8; line-height: 1.5; }
.fr__galat { font-size: 11.5px; color: #dc2626; display: flex; align-items: center; gap: .25rem; }


/* -- Pilihan Ya/Tidak: satu baris rata -------------------------------------
   Element Plus memberi el-radio margin kanan 30px bawaan dan tinggi tetap 32px,
   sehingga opsi terpilih tampak bergeser dari yang tidak, dan antar pertanyaan
   tidak sejajar. Diseragamkan lewat flex + gap. */
.fr :deep(.el-radio-group),
.fr :deep(.el-checkbox-group) { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem 1.75rem; }
.fr :deep(.el-radio),
.fr :deep(.el-checkbox) { margin: 0 !important; height: auto; min-width: 4.5rem; }
.fr :deep(.el-radio__label),
.fr :deep(.el-checkbox__label) { padding-left: .5rem; font-size: 13px; font-weight: 600; }

/* -- Unggah berkas: dropzone selebar kolom -------------------------------- */
.fr__drop { display: block; width: 100%; }
.fr__drop :deep(.el-upload) { display: block; width: 100%; }
.fr__drop :deep(.el-upload-dragger) {
    display: flex; align-items: center; gap: .85rem;
    width: 100%; padding: 1rem 1.1rem;
    border: 1.5px dashed rgba(99, 102, 241, .32); border-radius: 16px;
    background: linear-gradient(135deg, rgba(99, 102, 241, .05), rgba(139, 92, 246, .03));
    transition: border-color .18s ease, background .18s ease, transform .18s ease, box-shadow .18s ease;
}
.fr__drop :deep(.el-upload-dragger:hover),
.fr__drop :deep(.el-upload-dragger.is-dragover) {
    border-color: #6366f1; background: rgba(99, 102, 241, .09);
    transform: translateY(-2px); box-shadow: 0 12px 26px -14px rgba(79, 70, 229, .8);
}
.fr__drop-ico { flex: 0 0 auto; width: 44px; height: 44px; border-radius: 13px; display: grid; place-items: center; background: linear-gradient(140deg, #8b5cf6, #6366f1); color: #fff; font-size: 1.15rem; box-shadow: 0 8px 18px -10px rgba(79, 70, 229, .9); }
.fr__drop-txt { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; text-align: left; line-height: 1.4; }
.fr__drop-txt strong { font-size: 13.5px; font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fr__drop-txt small { font-size: 11.5px; color: #8b93a7; }
.fr__drop-act { flex: 0 0 auto; padding: .45rem .95rem; border-radius: 999px; font-size: 12px; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, .12); }

/* Sudah ada berkas -> hijau, supaya beda jelas dari yang masih kosong. */
.fr__drop.is-ada :deep(.el-upload-dragger) { border-style: solid; border-color: rgba(16, 185, 129, .45); background: rgba(16, 185, 129, .07); }
.fr__drop.is-ada .fr__drop-ico { background: linear-gradient(140deg, #34d399, #10b981); box-shadow: 0 8px 18px -10px rgba(16, 185, 129, .9); }
.fr__drop.is-ada .fr__drop-act { color: #059669; background: rgba(16, 185, 129, .14); }
.fr__drop.is-mati { opacity: .55; pointer-events: none; }

.fr__pv { margin-top: .5rem; display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
.fr__pv-del { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem .8rem; border: 1px solid rgba(220, 38, 38, .28); border-radius: 10px; background: #fff; color: #dc2626; font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; transition: background .16s ease, border-color .16s ease; }
.fr__pv-del:hover:not(:disabled) { background: #fef2f2; border-color: rgba(220, 38, 38, .5); }
.fr__pv-del:disabled { opacity: .5; cursor: not-allowed; }
.fr__pv-img { display: inline-block; padding: 0; border: 0; background: none; cursor: zoom-in; border-radius: 12px; overflow: hidden; border: 1px solid rgba(11, 16, 51, .12); line-height: 0; }
.fr__pv-img img { display: block; max-width: 180px; max-height: 130px; object-fit: cover; }
.fr__pv-doc { font: inherit; cursor: pointer; display: inline-flex; align-items: center; gap: .5rem; padding: .45rem .75rem; border-radius: 10px; background: #f8fafc; border: 1px solid rgba(11, 16, 51, .1); font-size: 12.5px; font-weight: 600; color: #334155; text-decoration: none; max-width: 100%; }
.fr__pv-doc .bi { color: #dc2626; font-size: 1rem; flex: none; }
.fr__pv-doc span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fr__pv-doc em { font-style: normal; color: #4f46e5; font-weight: 700; flex: none; display: inline-flex; align-items: center; gap: .3rem; }
.fr__pv-doc:hover { border-color: rgba(79, 70, 229, .45); background: #f5f3ff; }
.fr__drop-nama { border: 0; background: none; padding: 0; cursor: pointer; font-family: inherit; text-align: left; font-size: 13.5px; font-weight: 700; color: #4f46e5; text-decoration: underline; text-underline-offset: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fr__drop-nama:hover { color: #4338ca; }
.fr__pv-doc.is-mati { color: #64748b; }
.fr__pv-doc.is-mati .bi { color: #94a3b8; }

@media (max-width: 520px) {
    .fr__drop :deep(.el-upload-dragger) { flex-wrap: wrap; gap: .6rem; }
    .fr__drop-txt { flex: 1 1 100%; order: 3; }
    .fr__drop-act { margin-left: auto; }
}

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
