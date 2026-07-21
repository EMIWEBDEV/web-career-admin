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
 *     template-1/            <- satu keluarga tampilan
 *       layout/
 *         SatuHalaman.vue      semua pertanyaan dalam satu layar
 *         Bertahap.vue         stepper, validasi per langkah
 *       form-1/
 *         skema.js             PERTANYAAN & SYARAT form pendaftaran
 *         Form1.vue            tampilan (memakai layout SatuHalaman)
 *       form-2/
 *         skema.js             PERTANYAAN & SYARAT form identitas lanjutan
 *         Form2.vue            tampilan (memakai layout Bertahap)
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
import { SKEMA as SKEMA_FORM_1 } from './template-1/form-1/skema';
import { SKEMA as SKEMA_FORM_2 } from './template-1/form-2/skema';
import { semuaField } from './inti/aturan';

export const FORMULIR = {
    FORMULIR_1: {
        komponen: Form1,
        skema: SKEMA_FORM_1,
        nama: 'Form 1 — Pendaftaran',
        keterangan: 'Satu halaman. Pertanyaan pendidikan bercabang mengikuti jenis institusi.',
        template: 'Template 1',
        layout: 'Satu halaman',
        berkas: 'template-1/form-1/skema.js',
    },
    FORMULIR_2: {
        komponen: Form2,
        skema: SKEMA_FORM_2,
        nama: 'Form 2 — Identitas Peserta',
        keterangan: 'Empat langkah: validasi data, identitas tambahan, kesiapan & dokumen, pernyataan.',
        template: 'Template 1',
        layout: 'Bertahap',
        berkas: 'template-1/form-2/skema.js',
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

