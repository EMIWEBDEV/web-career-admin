/**
 * WEB CAREER — Mesin aturan formulir.
 *
 * Berisi logika yang dipakai SEMUA formulir: syarat tampil, nilai awal,
 * dan validasi. Sengaja dipisah dari layout supaya menambah aturan baru
 * tidak menyentuh tampilan, dan sebaliknya.
 *
 * Tidak ada nama formulir apa pun di berkas ini.
 */

/**
 * Evaluasi satu syarat tampil.
 *
 * INI PENGGANTI IF/ELSE DI KODE TAMPILAN.
 * Aturannya ditulis sebagai DATA di skema.js masing-masing formulir:
 *
 *   tampil_jika: { field: 'jenis_kelamin', operator: '=', nilai: 'Perempuan' }
 *
 * Menambah syarat baru cukup menyunting skema.js — berkas ini tidak
 * perlu disentuh lagi.
 */
export function syaratTerpenuhi(syarat, jawaban) {
    if (!syarat || !syarat.field) return true;

    const kiri = jawaban?.[syarat.field];
    const kanan = syarat.nilai;

    // Checkbox menyimpan array -> "=" berarti "mengandung nilai ini".
    if (Array.isArray(kiri)) {
        return syarat.operator === '!=' ? !kiri.includes(kanan) : kiri.includes(kanan);
    }

    // Perbandingan angka dipakai HANYA bila kedua sisi memang angka.
    // Kalau dipaksa sebagai teks, '10' < '3' dan syarat IPK jadi salah.
    const angkaKiri = Number(kiri);
    const angkaKanan = Number(kanan);
    const keduanyaAngka =
        kiri !== '' && kiri !== null && kiri !== undefined && !Number.isNaN(angkaKiri) && !Number.isNaN(angkaKanan);

    const a = keduanyaAngka ? angkaKiri : String(kiri ?? '').trim().toLowerCase();
    const b = keduanyaAngka ? angkaKanan : String(kanan ?? '').trim().toLowerCase();

    switch (syarat.operator) {
        case '!=': return a !== b;
        case '>': return a > b;
        case '<': return a < b;
        case '>=': return a >= b;
        case '<=': return a <= b;
        default: return a === b;
    }
}

/** Field yang lolos syarat tampil pada kondisi jawaban saat ini. */
export function fieldTampil(field, jawaban) {
    return (field || []).filter((f) => syaratTerpenuhi(f.tampil_jika, jawaban));
}

/** Bagian yang masih punya minimal satu field terlihat. */
export function bagianTampil(bagian, jawaban) {
    return (bagian || []).filter((b) => b.berulang || fieldTampil(b.field, jawaban).length > 0);
}

/** Semua field sebuah skema, diratakan. */
export function semuaField(skema) {
    const out = [];
    (skema?.langkah || []).forEach((L) =>
        (L.bagian || []).forEach((B) => (B.field || []).forEach((f) => out.push(f))),
    );
    return out;
}

/**
 * Field bertanda `dapat_disaring` — nilainya diproyeksikan ke
 * N_WEB_CAREERS_Formulir_Jawaban_Index saat pengisian dikirim, supaya bisa
 * dipakai syarat auto-gugur di Program Kegiatan.
 */
export function fieldDapatDisaring(skema) {
    return semuaField(skema).filter((f) => f.dapat_disaring);
}

/** Nilai kosong yang sesuai tipe field. */
export function nilaiKosong(f) {
    if (f.tipe === 'checkbox') return [];
    if (f.tipe === 'consent') return false;
    if (f.tipe === 'number') return null;
    return '';
}

/** Key penampung sebuah bagian berulang (array of objek). */
export function kunciBagian(B) {
    return (
        B.key ||
        String(B.judul || 'bagian')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
    );
}

export function barisKosong(B) {
    const baris = {};
    (B.field || []).forEach((f) => (baris[f.key] = nilaiKosong(f)));
    return baris;
}

/**
 * Bangun objek jawaban awal dari skema.
 * Bagian berulang jadi array berisi satu baris kosong.
 * `profil` mengisi otomatis field bertipe prefill.
 */
export function jawabanAwal(skema, profil = {}) {
    const out = {};
    (skema?.langkah || []).forEach((L) => {
        (L.bagian || []).forEach((B) => {
            if (B.berulang) {
                out[kunciBagian(B)] = [barisKosong(B)];
                return;
            }
            (B.field || []).forEach((f) => {
                out[f.key] = f.tipe === 'prefill' ? (profil[f.prefill] ?? '') : nilaiKosong(f);
            });
        });
    });
    return out;
}

/**
 * Validasi satu langkah. Field yang sedang TERSEMBUNYI tidak ikut divalidasi —
 * memaksa mengisi kolom yang tidak terlihat adalah jebakan klasik form dinamis.
 *
 * @returns {string[]} daftar pesan galat (kosong = lolos)
 */
export function periksaLangkah(langkah, jawaban) {
    const galat = [];

    (langkah?.bagian || []).forEach((B) => {
        if (B.berulang) {
            const baris = jawaban[kunciBagian(B)] || [];
            baris.forEach((r, i) => {
                fieldTampil(B.field, r).forEach((f) => {
                    if (f.wajib && kosong(r[f.key])) {
                        galat.push(`${B.judul} baris ${i + 1}: "${f.label}" wajib diisi.`);
                    }
                });
            });
            return;
        }

        fieldTampil(B.field, jawaban).forEach((f) => {
            if (f.tipe === 'prefill') return;
            if (f.wajib && kosong(jawaban[f.key])) {
                galat.push(
                    f.tipe === 'consent' ? `Anda harus menyetujui: "${f.label}".` : `"${f.label}" wajib diisi.`,
                );
                return;
            }
            const gTelepon = galatTelepon(f, jawaban[f.key]);
            if (gTelepon) galat.push(gTelepon);
        });
    });

    return galat;
}

// Telepon internasional: kode negara + nomor. Panjang beda tiap negara, jadi
// cukup periksa minimal 8 digit (tak lagi wajib berawalan 62).
function galatTelepon(f, v) {
    if (f.tipe !== 'phone' || kosong(v)) return '';
    const s = String(v).replace(/\D/g, '');
    if (s.length < 8) return `"${f.label}" belum lengkap.`;
    return '';
}

function kosong(v) {
    if (Array.isArray(v)) return v.length === 0;
    if (typeof v === 'boolean') return v === false;
    return v === null || v === undefined || String(v).trim() === '';
}
