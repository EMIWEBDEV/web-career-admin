<!-- WEB CAREER — Render SATU field sesuai tipe di skema. Dipakai semua template. -->
<template>
    <div
        class="fr"
        :class="{
            'fr--full': field.penuh || lebarPenuh,
            'fr--consent': field.tipe === 'consent',
            'fr--consent-aktif': field.tipe === 'consent' && !!nilai,
        }"
        :style="gayaLebar"
    >
        <!-- Consent: label pendek saja di sini -- teks pernyataan lengkapnya
             jadi label CHECKBOX itu sendiri di bawah (satu sumber teks, bukan
             diulang dua kali dengan kata-kata berbeda). -->
        <label v-if="field.tipe === 'consent'" class="fr__lbl fr__lbl--consent">
            <i class="bi bi-shield-check fr__consent-ico"></i> Pernyataan Persetujuan
            <span v-if="field.wajib" class="fr__wajib">*</span>
        </label>
        <label v-else class="fr__lbl">
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
            :maxlength="field.maks_panjang || undefined"
            :inputmode="field.hanya_angka ? 'numeric' : undefined"
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
             (tanpa allow-create). Kalau daftar belum ada -> input terkunci, bukan bebas.
             `bebas_ketik` — opsi statis dipakai sebagai SARAN saja, kandidat tetap
             boleh mengetik jawabannya sendiri (mis. pekerjaan/pendidikan orang tua). -->
        <template v-else-if="field.tipe === 'select'">
            <el-select
                v-if="opsiEfektif.length"
                :model-value="nilai"
                filterable
                :allow-create="!!field.bebas_ketik"
                :default-first-option="!!field.bebas_ketik"
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

        <!-- bulan/tahun: dipakai memisah field "Bulan/Tahun" jadi dua kolom
             sendiri-sendiri (mis. mulai/selesai bekerja) tanpa mengetik bebas. -->
        <el-date-picker
            v-else-if="field.tipe === 'bulan'"
            :model-value="nilai"
            type="month"
            value-format="YYYY-MM"
            format="MMM YYYY"
            :disabled="disabled"
            :placeholder="field.ph || 'Pilih bulan'"
            style="width: 100%"
            @update:model-value="(v) => ubah(v ?? '')"
        />
        <el-date-picker
            v-else-if="field.tipe === 'tahun'"
            :model-value="nilai"
            type="year"
            value-format="YYYY"
            format="YYYY"
            :disabled="disabled"
            :placeholder="field.ph || 'Pilih tahun'"
            style="width: 100%"
            @update:model-value="(v) => ubah(v ?? '')"
        />

        <!-- referensi: opsi DICARI ke server sambil mengetik, bukan dikirim di
             muka. Master Kampus berisi ratusan ribu baris — mustahil dimuat
             seluruhnya. Field terkunci ke daftar resmi, KECUALI ditandai
             `bebas_ketik` — mis. kampus/prodi yang belum masuk daftar resmi
             tetap boleh diketik manual, daftar tetap dipakai sebagai saran. -->
        <el-select
            v-else-if="field.tipe === 'referensi'"
            :model-value="nilai || undefined"
            filterable
            remote
            clearable
            :allow-create="!!field.bebas_ketik"
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

        <!-- currency: dipakai untuk nominal Rupiah (mis. ekspektasi gaji).
             el-input BIASA (bukan el-input-number) supaya format "Rp 5.000.000"
             tampil LANGSUNG sambil mengetik -- formatter el-input-number di Element
             Plus baru menata ulang tampilan saat blur, bukan tiap ketukan. -->
        <el-input
            v-else-if="field.tipe === 'currency'"
            :model-value="formatRupiah(nilai)"
            :disabled="disabled"
            :placeholder="field.ph || 'Rp 0'"
            inputmode="numeric"
            @update:model-value="ubahRupiah"
        />

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
        <template v-else-if="field.tipe === 'file'">
            <!-- Belum ada berkas -> dropzone ringkas. -->
            <el-upload
                v-if="!nilai"
                class="fr__drop"
                :class="{ 'is-mati': disabled }"
                drag
                :accept="field.accept || '.pdf'"
                :auto-upload="false"
                :show-file-list="false"
                :disabled="disabled"
                :on-change="pilihBerkas"
            >
                <span class="fr__drop-ico"><i class="bi bi-cloud-arrow-up-fill"></i></span>
                <span class="fr__drop-txt">
                    <strong>Klik atau seret berkas ke sini</strong>
                    <small>{{ field.accept || '.pdf' }} · maks {{ field.maks_mb || 2 }} MB</small>
                </span>
            </el-upload>

            <!-- Sudah ada berkas -> satu baris ringkas: pratinjau + nama + ganti + hapus,
                 bukan lagi dropzone besar DITAMBAH blok pratinjau terpisah di bawahnya. -->
            <div v-else class="fr__chip">
                <button
                    type="button"
                    class="fr__chip-pv"
                    :class="{ 'is-kosong': !urlPratinjau }"
                    :disabled="!urlPratinjau"
                    :title="urlPratinjau ? 'Lihat berkas' : 'Berkas belum tersimpan di server'"
                    @click="lihatBerkas"
                >
                    <img v-if="urlPratinjau && gambarPratinjau" :src="urlPratinjau" :alt="String(nilai)" />
                    <i v-else class="bi" :class="urlPratinjau ? 'bi-file-earmark-pdf-fill' : 'bi-file-earmark-fill'"></i>
                </button>
                <button
                    type="button"
                    class="fr__chip-nama"
                    :disabled="!urlPratinjau"
                    :title="urlPratinjau ? 'Lihat berkas' : 'Berkas belum tersimpan di server'"
                    @click="lihatBerkas"
                >{{ nilai }}</button>
                <el-upload
                    class="fr__chip-ganti"
                    :accept="field.accept || '.pdf'"
                    :auto-upload="false"
                    :show-file-list="false"
                    :disabled="disabled"
                    :on-change="pilihBerkas"
                >
                    <button type="button" class="fr__chip-btn" :disabled="disabled" title="Ganti berkas">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </el-upload>
                <button
                    type="button"
                    class="fr__chip-btn fr__chip-btn--danger"
                    :disabled="disabled"
                    title="Hapus berkas"
                    @click="hapusBerkas"
                >
                    <i class="bi bi-trash3-fill"></i>
                </button>
            </div>
        </template>


        <el-checkbox
            v-else-if="field.tipe === 'consent'"
            :model-value="!!nilai"
            :disabled="disabled"
            class="fr__consent"
            @update:model-value="ubah"
        >
            {{ field.label }}
        </el-checkbox>

        <el-input v-else :model-value="nilai" :disabled="disabled" @update:model-value="ubah" />

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
const lebarPenuh = computed(() => false);
const lebarGrid = computed(() => {
    if (props.field.penuh || lebarPenuh.value) return 12;
    const persen = Number(props.field.lebar_persen || 33);
    return Math.min(12, Math.max(4, Math.round((Math.min(100, Math.max(33, persen)) / 100) * 12)));
});
const gayaLebar = computed(() => ({ '--fr-span': String(lebarGrid.value) }));

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

/** Format angka polos -> "Rp 5.000.000" untuk tampilan field `currency`. */
function formatRupiah(v) {
    const angka = String(v ?? '').replace(/\D/g, '');
    if (!angka) return '';
    return 'Rp ' + angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/** Ketikan "Rp 5.000.000" -> disaring jadi angka murni sebelum disimpan. */
function ubahRupiah(v) {
    const angka = String(v ?? '').replace(/\D/g, '');
    ubah(angka ? Number(angka) : null);
}

function ubah(v) {
    // `hanya_angka` — mis. NIK: apa pun yang diketik disaring jadi digit saja,
    // supaya tidak ada validasi tambahan yang bisa dilewati lewat tempel teks.
    if (props.field.hanya_angka && typeof v === 'string') {
        v = v.replace(/\D/g, '');
        if (props.field.maks_panjang) v = v.slice(0, props.field.maks_panjang);
    }
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
.fr { display: flex; flex-direction: column; gap: .35rem; min-width: 0; grid-column: span var(--fr-span, 4); }
.fr--full { grid-column: 1 / -1; }

.fr__lbl { font-size: 12px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: .3rem; }
.fr__wajib { color: #dc2626; }
.fr__ubah { display: inline-flex; align-items: center; gap: 4px; margin-left: 6px; padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 700; color: #4f46e5; background: rgba(99, 102, 241, .12); }
.fr__auto { font-size: 10.5px; font-weight: 600; color: #4338ca; background: rgba(79, 70, 229, .1); border-radius: 999px; padding: .05rem .4rem; }

.fr__bantuan { font-size: 11px; color: #94a3b8; line-height: 1.5; }
.fr__galat { font-size: 11.5px; color: #dc2626; display: flex; align-items: center; gap: .25rem; }

/* -- Ukuran seragam untuk SEMUA kontrol (input, select, number, date, upload
   trigger) — sebelumnya tiap komponen Element Plus punya tinggi bawaan
   sendiri-sendiri (32px vs 40px vs custom), jadi baris terlihat tidak rata. */
.fr :deep(.el-input__wrapper),
.fr :deep(.el-textarea__inner),
.fr :deep(.el-select__wrapper) {
    min-height: 40px;
    border-radius: 10px;
    box-shadow: 0 0 0 1px rgba(11, 16, 51, .12) inset;
}
.fr :deep(.el-input__wrapper.is-focus),
.fr :deep(.el-select__wrapper.is-focused) {
    box-shadow: 0 0 0 1px #6366f1 inset;
}
.fr :deep(.el-textarea__inner) { border-radius: 10px; padding-top: .55rem; }
.fr :deep(.el-input-number) { width: 100%; }
.fr :deep(.el-input-number .el-input__wrapper) { padding-left: .9rem; }
.fr :deep(.el-input-number.is-without-controls .el-input__inner) { text-align: left; }


/* -- Pilihan Ya/Tidak: satu baris rata -------------------------------------
   Element Plus memberi el-radio margin kanan 30px bawaan dan tinggi tetap 32px,
   sehingga opsi terpilih tampak bergeser dari yang tidak, dan antar pertanyaan
   tidak sejajar. Diseragamkan lewat flex + gap. */
.fr :deep(.el-radio-group),
.fr :deep(.el-checkbox-group) { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .6rem; }
.fr :deep(.el-radio),
.fr :deep(.el-checkbox) {
    margin: 0 !important; height: auto; min-height: 40px; min-width: 4.5rem;
    padding: 0 .9rem; border: 1.5px solid rgba(11, 16, 51, .13); border-radius: 10px;
    transition: border-color .15s ease, background .15s ease;
}
.fr :deep(.el-radio:hover),
.fr :deep(.el-checkbox:hover) { border-color: rgba(99, 102, 241, .45); }
.fr :deep(.el-radio.is-checked),
.fr :deep(.el-checkbox.is-checked) { border-color: #6366f1; background: rgba(99, 102, 241, .07); }
.fr :deep(.el-radio__label),
.fr :deep(.el-checkbox__label) { padding-left: .5rem; font-size: 13px; font-weight: 600; }

/* -- Unggah berkas: dropzone ringkas saat KOSONG --------------------------- */
.fr__drop { display: block; width: 100%; }
.fr__drop :deep(.el-upload) { display: block; width: 100%; }
.fr__drop :deep(.el-upload-dragger) {
    display: flex; align-items: center; gap: .6rem;
    width: 100%; min-height: 40px; padding: .4rem .7rem;
    border: 1.5px dashed rgba(99, 102, 241, .32); border-radius: 10px;
    background: linear-gradient(135deg, rgba(99, 102, 241, .05), rgba(139, 92, 246, .03));
    transition: border-color .16s ease, background .16s ease;
}
.fr__drop :deep(.el-upload-dragger:hover),
.fr__drop :deep(.el-upload-dragger.is-dragover) { border-color: #6366f1; background: rgba(99, 102, 241, .09); }
.fr__drop-ico { flex: 0 0 auto; width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; background: linear-gradient(140deg, #8b5cf6, #6366f1); color: #fff; font-size: .85rem; }
.fr__drop-txt { flex: 1; min-width: 0; text-align: left; line-height: 1.3; }
.fr__drop-txt strong { display: block; font-size: 12.5px; font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fr__drop-txt small { font-size: 10.5px; color: #8b93a7; }
.fr__drop.is-mati { opacity: .55; pointer-events: none; }

/* -- Berkas TERPASANG: satu baris ringkas (pratinjau + nama + ganti + hapus),
   menggantikan dropzone besar + blok pratinjau terpisah yang tadinya tampil
   berbarengan dan memakan tempat, apalagi di dalam bagian berulang. -------- */
.fr__chip {
    display: flex; align-items: center; gap: .5rem; width: 100%; min-height: 40px;
    padding: .3rem .4rem; border: 1.5px solid rgba(16, 185, 129, .35); border-radius: 10px;
    background: rgba(16, 185, 129, .06);
}
.fr__chip-pv {
    flex: none; width: 30px; height: 30px; border-radius: 8px; overflow: hidden;
    border: 0; padding: 0; display: grid; place-items: center; cursor: zoom-in;
    background: #fff; color: #059669; font-size: .95rem;
}
.fr__chip-pv img { width: 100%; height: 100%; object-fit: cover; }
.fr__chip-pv.is-kosong { cursor: default; color: #94a3b8; }
.fr__chip-nama {
    flex: 1; min-width: 0; border: 0; background: none; padding: 0; text-align: left;
    font: inherit; font-size: 12.5px; font-weight: 700; color: #0f766e; cursor: pointer;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fr__chip-nama:disabled { color: #475569; cursor: default; }
.fr__chip-btn {
    flex: none; width: 28px; height: 28px; border-radius: 8px; border: 0;
    display: grid; place-items: center; background: rgba(15, 23, 42, .06); color: #475569;
    cursor: pointer; transition: background .15s ease, color .15s ease;
}
.fr__chip-btn:hover:not(:disabled) { background: rgba(99, 102, 241, .14); color: #4f46e5; }
.fr__chip-btn:disabled { opacity: .5; cursor: not-allowed; }
.fr__chip-btn--danger:hover:not(:disabled) { background: rgba(220, 38, 38, .12); color: #dc2626; }

.fr__consent { white-space: normal; height: auto; align-items: flex-start; }

/* -- Kartu persetujuan: teks pernyataan panjang lebih nyaman dibaca dalam
   kotak sendiri daripada tercampur sebagai "field" biasa, dan berubah warna
   begitu dicentang supaya jelas mana yang sudah disetujui. */
.fr--consent {
    grid-column: 1 / -1; padding: .9rem 1.05rem; border: 1.5px solid rgba(11, 16, 51, .12);
    border-radius: 14px; background: #f8fafc; transition: border-color .15s ease, background .15s ease;
}
.fr--consent-aktif { border-color: #6366f1; background: rgba(99, 102, 241, .06); }
.fr--consent .fr__lbl { font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: .55rem; line-height: 1.55; }
.fr__consent-ico { color: #6366f1; font-size: 13px; }
.fr--consent :deep(.el-checkbox__label) { white-space: normal; line-height: 1.5; font-size: 13px; font-weight: 600; color: #1e293b; padding-left: .6rem; }

/* Opsi referensi: nama di kiri, keterangan (kota / gelar) menepi ke kanan. */
.fr__opsi { float: left; }
.fr__opsi-ket { float: right; margin-left: 1.2rem; color: #94a3b8; font-size: 11.5px; }
</style>
