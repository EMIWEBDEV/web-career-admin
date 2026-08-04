/**
 * WEB CAREER — Katalog formulir.
 *
 * SUSUNAN FOLDER
 *   formulir/
 *     index.js               <- berkas ini: daftar formulir yang tersedia
 *     inti/                  <- mesin bersama, tidak tahu nama formulir apa pun
 *       aturan.js              syarat tampil, nilai awal, validasi
 *       FieldRenderer.vue      render satu field sesuai tipenya
 *       BagianRenderer.vue     render satu bagian (biasa / berulang)
 *       blok.js                blok pertanyaan siap pakai (data diri, pendidikan)
 *       referensi.js           pengambil opsi dari server untuk tipe `referensi`
 *     template-1/            <- satu keluarga tampilan
 *       layout/
 *         SatuHalaman.vue      semua pertanyaan dalam satu layar
 *         Bertahap.vue         stepper, validasi per langkah
 *       form-1/                pendaftaran MT
 *       form-2/                identitas peserta MT (lanjutan)
 *       form-3/                pendaftaran rekrutmen umum
 *       form-4/                pendaftaran magang
 *       (tiap folder: skema.js = PERTANYAAN & SYARAT, FormN.vue = tampilan)
 *
 * Database hanya menyimpan KODE (Master_Formulir.Komponen_Kode). Peta di
 * bawah yang menerjemahkannya jadi komponen — jadi tidak ada "if nama
 * formulir" di mana pun.
 *
 * MENAMBAH FORMULIR BARU
 *   - Masih mirip yang sudah ada -> buat template-1/form-3/{skema.js,Form3.vue}
 *   - Tampilannya beda jauh      -> buat template-2/ dengan layout sendiri
 *   Lalu daftarkan satu baris di FORMULIR, dan INSERT satu baris di
 *   N_WEB_CAREERS_Master_Formulir. Tidak ada ALTER TABLE — jawaban kandidat
 *   tersimpan sebagai JSON.
 */
import Form1 from './template-1/form-1/Form1.vue';
import Form2 from './template-1/form-2/Form2.vue';
import Form3 from './template-1/form-3/Form3.vue';
import Form4 from './template-1/form-4/Form4.vue';
import DynamicForm from './DynamicForm.vue';
import { SKEMA as SKEMA_FORM_1 } from './template-1/form-1/skema';
import { SKEMA as SKEMA_FORM_2 } from './template-1/form-2/skema';
import { SKEMA as SKEMA_FORM_3 } from './template-1/form-3/skema';
import { SKEMA as SKEMA_FORM_4 } from './template-1/form-4/skema';
import { semuaField } from './inti/aturan';

export const FORMULIR = {
    FORMULIR_1: {
        komponen: Form1,
        skema: SKEMA_FORM_1,
        nama: 'Form 1 — Pendaftaran MT',
        keterangan: 'Satu halaman. Jenjang, institusi, dan jurusan dipilih berantai dari master pendidikan.',
        template: 'Template 1',
        layout: 'Satu halaman',
        berkas: 'template-1/form-1/skema.js',
    },
    FORMULIR_2: {
        komponen: Form2,
        skema: SKEMA_FORM_2,
        nama: 'Form 2 — Identitas Peserta MT',
        keterangan: 'Empat langkah: validasi data, identitas tambahan, kesiapan & dokumen, pernyataan.',
        template: 'Template 1',
        layout: 'Bertahap',
        berkas: 'template-1/form-2/skema.js',
    },
    FORMULIR_3: {
        komponen: Form3,
        skema: SKEMA_FORM_3,
        nama: 'Form 3 — Pendaftaran Rekrutmen',
        keterangan: 'Satu halaman. Terbuka semua jenjang, menimbang pengalaman kerja & kesediaan.',
        template: 'Template 1',
        layout: 'Satu halaman',
        berkas: 'template-1/form-3/skema.js',
    },
    FORMULIR_4: {
        komponen: Form4,
        skema: SKEMA_FORM_4,
        nama: 'Form 4 — Pendaftaran Magang',
        keterangan: 'Tiga langkah: data & pendidikan, rencana magang, dokumen & pernyataan.',
        template: 'Template 1',
        layout: 'Bertahap',
        berkas: 'template-1/form-4/skema.js',
    },
};

// ── Pembantu ────────────────────────────────────────────────────────

export function komponenFormulir(kode) {
    return FORMULIR[kode]?.komponen || null;
}

export function skemaFormulir(kode) {
    return FORMULIR[kode]?.skema || { langkah: [] };
}

export function infoFormulir(kode) {
    return FORMULIR[kode] || null;
}

export function komponenDinamis() {
    return DynamicForm;
}

export function skemaDariFormulir(formulir) {
    if (formulir?.schema) return formulir.schema;
    if (formulir?.schemaJson) return formulir.schemaJson;
    return skemaFormulir(formulir?.komponen);
}

/**
 * Peta `key → label` seluruh field sebuah formulir, termasuk field di dalam
 * bagian berulang.
 *
 * KENAPA PERLU
 * Yang tersimpan di database hanyalah pasangan kunci→jawaban; labelnya hidup di
 * skema (berkas ini). Layar peninjau karena itu terpaksa MENEBAK label dari
 * nama kunci — `v_nama` jadi "V Nama", `v_wa` jadi "V Wa", `dok_cv` jadi
 * "Dok Cv". Itu bahasa mesin yang bocor ke mata orang.
 *
 * Dengan peta ini, yang terbaca adalah label yang benar-benar dilihat kandidat
 * saat mengisi — jadi peninjau dan pengisi membaca pertanyaan yang sama.
 *
 * Kunci yang tidak ada di skema (formulir versi lama, field yang sudah dihapus)
 * TIDAK dibuang — pemanggil tetap menampilkannya dengan tebakan dari kuncinya.
 * Menyembunyikannya berarti jawaban yang pernah diberikan kandidat lenyap dari
 * layar tanpa ada yang tahu.
 */
export function labelField(kode) {
    const peta = {};
    const skema = FORMULIR[kode]?.skema;
    if (!skema) {
        return peta;
    }

    (skema.langkah || []).forEach((L) => {
        (L.bagian || []).forEach((B) => {
            (B.field || []).forEach((f) => {
                if (f.key && f.label) {
                    peta[f.key] = f.label;
                }
            });
        });
    });

    return peta;
}

/** Daftar untuk dropdown admin. */
export function daftarFormulir() {
    return Object.entries(FORMULIR).map(([kode, f]) => ({
        kode,
        nama: f.nama,
        keterangan: f.keterangan,
        template: f.template,
        layout: f.layout,
        berkas: f.berkas,
        jumlahLangkah: (f.skema.langkah || []).length,
        jumlahField: semuaField(f.skema).length,
    }));
}

// Diteruskan dari inti/ supaya pemakai cukup mengimpor dari satu tempat.
export {
    syaratTerpenuhi,
    fieldTampil,
    bagianTampil,
    semuaField,
    fieldDapatDisaring,
    nilaiKosong,
    kunciBagian,
    barisKosong,
    jawabanAwal,
    periksaLangkah,
} from './inti/aturan';

export { normalisasiSkema, skemaKosong, validasiSkema, slugKey, buatFieldId } from './inti/schema';
