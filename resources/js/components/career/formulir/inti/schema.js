/**
 * WEB CAREER - Helper schema formulir dinamis.
 *
 * Schema DB dibuat kompatibel dengan renderer lama:
 *   { layout: 'SATU_HALAMAN'|'BERTAHAP', langkah: [{ bagian: [{ field: [] }] }] }
 */

const TIPE_VALID = new Set([
    'text',
    'textarea',
    'number',
    'date',
    'select',
    'radio',
    'checkbox',
    'file',
    'phone',
    'email',
    'consent',
    'prefill',
    'referensi',
    'currency',
    'bulan',
    'tahun',
    // Foto wajah yang diambil LANGSUNG dari kamera, bukan diunggah dari galeri.
    // Dipakai untuk verifikasi identitas: berkas hasil unggahan bisa berupa foto
    // siapa saja, sedangkan tangkapan kamera memaksa orangnya hadir saat itu.
    'foto',
]);

export function normalisasiSkema(skema) {
    const s = skema && typeof skema === 'object' ? skema : {};
    const layout = String(s.layout || 'SATU_HALAMAN').toUpperCase() === 'BERTAHAP' ? 'BERTAHAP' : 'SATU_HALAMAN';
    const langkah = Array.isArray(s.langkah) ? s.langkah : [];

    return {
        schema_version: Number(s.schema_version || 1),
        template: s.template || 'TEMPLATE_1',
        layout,
        langkah: langkah.map((L, i) => ({
            kode: String(L.kode || `LANGKAH_${i + 1}`).trim(),
            judul: String(L.judul || `Langkah ${i + 1}`).trim(),
            ikon: L.ikon || 'bi-card-list',
            deskripsi: L.deskripsi || '',
            tampil_jika: L.tampil_jika || null,
            bagian: normalisasiBagian(L.bagian, i),
        })),
    };
}

function normalisasiBagian(bagian, langkahIndex = 0) {
    return (Array.isArray(bagian) ? bagian : []).map((B, i) => ({
        key: B.key || '',
        // Judul eksplisit '' (mis. langkah bertahap yang cuma punya satu bagian,
        // sehingga header langkah sudah cukup) sengaja dibiarkan kosong — beda
        // dengan tidak diisi sama sekali, yang tetap dapat label bawaan.
        judul: B.judul === undefined || B.judul === null ? `Bagian ${i + 1}` : String(B.judul).trim(),
        deskripsi: B.deskripsi || '',
        berulang: !!B.berulang,
        maks_baris: Number(B.maks_baris || 5),
        tampil_jika: B.tampil_jika || null,
        field: normalisasiField(B.field, langkahIndex, i),
    }));
}

function normalisasiField(field, langkahIndex = 0, bagianIndex = 0) {
    return (Array.isArray(field) ? field : []).map((F, fieldIndex) => {
        const tipe = TIPE_VALID.has(String(F.tipe || '').toLowerCase()) ? String(F.tipe).toLowerCase() : 'text';
        const key = slugKey(F.key || F.label || 'field');
        return {
            ...F,
            field_id: String(F.field_id || F.id || fallbackFieldId(key, langkahIndex, bagianIndex, fieldIndex)),
            key,
            label: String(F.label || F.key || 'Pertanyaan').trim(),
            tipe,
            wajib: !!F.wajib,
            penuh: !!F.penuh || F.lebar === 'full',
            lebar_persen: normalisasiLebarPersen(F),
            lebar_jika: normalisasiLebarJika(F.lebar_jika),
            opsi: Array.isArray(F.opsi) ? F.opsi : [],
        };
    });
}

/**
 * Lebar BERSYARAT — lebar field berubah mengikuti jawaban field lain.
 *
 * Contoh: "Status Kemahasiswaan" memakan satu baris penuh selagi belum dijawab
 * atau dijawab "Sudah Lulus", lalu menyusut jadi setengah begitu dijawab
 * "Mahasiswa" — karena saat itu field "Semester" muncul di sebelahnya.
 *
 * Ditulis sebagai DATA di skema (diatur admin lewat Master Formulir), bukan
 * if/else per nama field di kode tampilan. Syaratnya memakai kosakata yang sama
 * dengan `tampil_jika` supaya admin tidak menghafal dua aturan berbeda.
 */
function normalisasiLebarJika(aturan) {
    if (!aturan || typeof aturan !== 'object' || !aturan.field) return null;
    return {
        field: String(aturan.field),
        operator: String(aturan.operator || '='),
        nilai: aturan.nilai ?? '',
        lebar_persen: Math.min(100, Math.max(33, Math.round(Number(aturan.lebar_persen) || 100))),
    };
}

export function slugKey(s) {
    return (
        String(s || 'field')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9_]+/g, '_')
            .replace(/^[0-9]+/g, '')
            .replace(/^_+|_+$/g, '') || 'field'
    );
}

export function buatFieldId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `fld_${crypto.randomUUID()}`;
    }
    return `fld_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function fallbackFieldId(key, langkahIndex, bagianIndex, fieldIndex) {
    return `fld_legacy_${langkahIndex + 1}_${bagianIndex + 1}_${fieldIndex + 1}_${slugKey(key)}`;
}

function normalisasiLebarPersen(F) {
    if (F.penuh || F.lebar === 'full') return 100;
    const dariPersen = Number(F.lebar_persen || F.width_percent || 0);
    if (dariPersen) return Math.min(100, Math.max(33, Math.round(dariPersen)));
    const span = Number(F.lebar_span || F.kolom || 0);
    if (span >= 3) return 100;
    if (span === 2) return 67;
    return 33;
}

export function skemaKosong() {
    return {
        schema_version: 1,
        template: 'TEMPLATE_1',
        layout: 'SATU_HALAMAN',
        langkah: [
            {
                kode: 'PENDAFTARAN',
                judul: 'Data Pendaftaran',
                ikon: 'bi-person-vcard',
                bagian: [
                    {
                        judul: 'Data Diri',
                        field: [
                            {
                                field_id: buatFieldId(),
                                key: 'nama_lengkap',
                                label: 'Nama Lengkap',
                                tipe: 'text',
                                wajib: true,
                                penuh: false,
                                lebar_persen: 33,
                            },
                            {
                                field_id: buatFieldId(),
                                key: 'email',
                                label: 'Email',
                                tipe: 'email',
                                wajib: true,
                                penuh: false,
                                lebar_persen: 33,
                            },
                        ],
                    },
                ],
            },
        ],
    };
}

export function validasiSkema(skema) {
    const s = normalisasiSkema(skema);
    const errors = [];
    if (!s.langkah.length) errors.push('Minimal harus ada satu langkah.');

    const keys = new Set();
    const ids = new Set();
    s.langkah.forEach((L, li) => {
        if (!L.bagian.length) errors.push(`Langkah ${li + 1} belum punya section.`);
        L.bagian.forEach((B, bi) => {
            if (!B.field.length) errors.push(`Section ${bi + 1} pada langkah ${li + 1} belum punya field.`);
            B.field.forEach((F) => {
                if (!F.key) errors.push(`Ada field tanpa key pada langkah ${li + 1}.`);
                if (!/^[a-z][a-z0-9_]*$/.test(F.key))
                    errors.push(`Key "${F.key}" hanya boleh huruf kecil, angka, dan underscore; harus diawali huruf.`);
                if (keys.has(F.key)) errors.push(`Key "${F.key}" dipakai lebih dari sekali.`);
                keys.add(F.key);
                if (!F.field_id) errors.push(`Field "${F.label}" belum punya ID sistem.`);
                if (ids.has(F.field_id)) errors.push(`ID sistem field "${F.label}" terduplikasi.`);
                ids.add(F.field_id);
                if (['select', 'radio', 'checkbox'].includes(F.tipe) && !F.opsi.length) {
                    errors.push(`Field "${F.label}" membutuhkan minimal satu opsi.`);
                }
                if (F.tipe === 'file' && !F.accept) {
                    errors.push(`Field "${F.label}" perlu aturan tipe file.`);
                }
            });
        });
    });

    return { ok: errors.length === 0, errors, skema: s };
}
