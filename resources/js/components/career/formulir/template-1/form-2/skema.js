/**
 * SKEMA — Form 2: Identitas Peserta (lanjutan, bertahap).
 *
 * Sesuai "Form Identitas Peserta Rekrutmen — Management Trainee EVO Group"
 * pada berkas referensi (sheet "Form 2"), termasuk pembagian Page 1-4.
 * Diisi SETELAH kandidat lolos tahap awal, bukan saat mendaftar.
 *
 *   Page 1  A. Validasi Data Peserta      (prefill dari profil + opsi koreksi)
 *   Page 2  B. Identitas Tambahan + C. Kontak Darurat
 *   Page 3  D. Kesiapan Penempatan & Kerja + E. Kelengkapan Dokumen
 *   Page 4  F. Pernyataan Persetujuan
 *
 * ══════════════════════════════════════════════════════════════════════
 *  DI SINILAH SELURUH ATURAN FORMULIR DITULIS.
 *  Bentuk field & daftar operator syarat: lihat form-1/skema.js.
 * ══════════════════════════════════════════════════════════════════════
 */
export const SKEMA = {
    template: 'TEMPLATE_1',
    layout: 'BERTAHAP',
    langkah: [
        // ── PAGE 1 ────────────────────────────────────────────────────
        {
            kode: 'VALIDASI',
            judul: 'Validasi Data',
            ikon: 'bi-person-check',
            deskripsi: 'Periksa data yang sudah kami miliki. Beri tahu bila ada yang perlu diperbarui.',
            bagian: [
                {
                    judul: 'A. Validasi Data Peserta',
                    deskripsi: 'Data di bawah terisi otomatis dari akun Anda — tidak perlu diketik ulang.',
                    field: [
                        { key: 'v_nama', label: 'Nama Lengkap', tipe: 'prefill', prefill: 'nama' },
                        { key: 'v_email', label: 'Email Terdaftar', tipe: 'prefill', prefill: 'email' },
                        { key: 'v_wa', label: 'No. WhatsApp Terdaftar', tipe: 'prefill', prefill: 'hp' },
                        {
                            key: 'data_sesuai',
                            label: 'Apakah data di atas sudah sesuai?',
                            tipe: 'radio',
                            wajib: true,
                            opsi: ['Sesuai', 'Perlu diperbarui'],
                            penuh: true,
                        },
                        // Kolom koreksi baru muncul bila memang ada yang salah —
                        // inilah "Buka Opsi Edit" di berkas referensi.
                        {
                            key: 'data_koreksi',
                            label: 'Tuliskan data yang benar',
                            tipe: 'textarea',
                            wajib: true,
                            ph: 'mis. No. WhatsApp yang benar: 62812xxxxxxx',
                            tampil_jika: { field: 'data_sesuai', operator: '=', nilai: 'Perlu diperbarui' },
                        },
                    ],
                },
            ],
        },

        // ── PAGE 2 ────────────────────────────────────────────────────
        {
            kode: 'IDENTITAS',
            judul: 'Identitas Tambahan',
            ikon: 'bi-house-heart',
            bagian: [
                {
                    judul: 'B. Identitas Tambahan',
                    field: [
                        { key: 'alamat_ktp', label: 'Alamat Lengkap (Sesuai KTP)', tipe: 'textarea', wajib: true, ph: 'Jalan, RT/RW, kelurahan, kecamatan, kota, provinsi' },
                        { key: 'alamat_domisili', label: 'Alamat Domisili Saat Ini', tipe: 'textarea', wajib: false, ph: 'Kosongkan jika sama dengan alamat KTP', bantuan: 'Tidak perlu diisi bila sama dengan alamat KTP.' },
                        { key: 'perguruan_tinggi', label: 'Nama Perguruan Tinggi', tipe: 'text', wajib: true, dapat_disaring: true },
                        { key: 'tahun_lulus', label: 'Tahun Lulus / Perkiraan Lulus', tipe: 'text', wajib: true, ph: 'mis. 2026' },
                        {
                            key: 'ketersediaan_proses',
                            label: 'Status Ketersediaan Mengikuti Proses Rekrutmen',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Siap mengikuti seluruh proses', 'Perlu penyesuaian jadwal'],
                            dapat_disaring: true,
                        },
                        {
                            key: 'mulai_bekerja',
                            label: 'Ketersediaan Mulai Bekerja',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Segera', '1 bulan', '2 bulan', '3 bulan'],
                            dapat_disaring: true,
                        },
                    ],
                },
                {
                    judul: 'C. Kontak Darurat',
                    deskripsi: 'Dihubungi hanya bila terjadi keadaan mendesak selama proses seleksi.',
                    field: [
                        { key: 'darurat_nama', label: 'Nama Kontak Darurat', tipe: 'text', wajib: true },
                        { key: 'darurat_hubungan', label: 'Hubungan dengan Peserta', tipe: 'text', wajib: true, ph: 'mis. Orang tua / Saudara' },
                        { key: 'darurat_hp', label: 'No. Handphone Kontak Darurat', tipe: 'phone', wajib: true, ph: '628xxxxxxxxx', bantuan: 'Wajib berawalan 62. Ketik 08… otomatis jadi 628…' },
                    ],
                },
            ],
        },

        // ── PAGE 3 ────────────────────────────────────────────────────
        {
            kode: 'KESIAPAN',
            judul: 'Kesiapan & Dokumen',
            ikon: 'bi-clipboard-check',
            bagian: [
                {
                    judul: 'D. Kesiapan Penempatan & Kerja',
                    field: [
                        { key: 'siap_plant', label: 'Bersedia ditempatkan di area Plant / Pabrik', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        { key: 'siap_shift', label: 'Bersedia bekerja dengan sistem shift jika dibutuhkan', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        { key: 'siap_durasi_mt', label: 'Bersedia mengikuti program Management Trainee sesuai durasi dan ketentuan perusahaan', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        { key: 'siap_ikatan_dinas', label: 'Bersedia menjalani ikatan dinas selama 2 tahun jika lulus dari program MT', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        { key: 'punya_pengalaman', label: 'Memiliki pengalaman magang/kerja/praktik industri di area produksi/manufaktur', tipe: 'radio', wajib: true, opsi: ['Ya', 'Tidak'], penuh: true, dapat_disaring: true },
                        // Penjelasan hanya diminta bila menjawab Ya.
                        {
                            key: 'pengalaman_uraian',
                            label: 'Jelaskan secara singkat pengalaman tersebut',
                            tipe: 'textarea',
                            wajib: false,
                            ph: 'Nama perusahaan, posisi, durasi, dan tugas utama',
                            tampil_jika: { field: 'punya_pengalaman', operator: '=', nilai: 'Ya' },
                        },
                    ],
                },
                {
                    judul: 'E. Kelengkapan Dokumen',
                    deskripsi: 'Format PDF. Ukuran dibatasi agar penyimpanan tidak cepat penuh.',
                    field: [
                        { key: 'dok_cv', label: 'Upload CV Terbaru', tipe: 'file', wajib: true, accept: '.pdf', maks_mb: 2 },
                        { key: 'dok_transkrip', label: 'Upload Transkrip Nilai', tipe: 'file', wajib: true, accept: '.pdf', maks_mb: 2 },
                        { key: 'dok_ijazah', label: 'Upload Ijazah / Surat Keterangan Lulus', tipe: 'file', wajib: false, accept: '.pdf', maks_mb: 2 },
                        { key: 'dok_sertifikat', label: 'Upload Sertifikat Pendukung (jika ada)', tipe: 'file', wajib: false, accept: '.pdf', maks_mb: 5 },
                    ],
                },
            ],
        },

        // ── PAGE 4 ────────────────────────────────────────────────────
        {
            kode: 'PERSETUJUAN',
            judul: 'Pernyataan',
            ikon: 'bi-patch-check',
            deskripsi: 'Baca dan setujui seluruh pernyataan berikut sebelum mengirim.',
            bagian: [
                {
                    judul: 'F. Pernyataan Persetujuan',
                    field: [
                        { key: 'setuju_data_benar', label: 'Saya menyatakan bahwa seluruh data dan dokumen yang saya berikan adalah benar dan dapat dipertanggungjawabkan.', tipe: 'consent', wajib: true },
                        { key: 'setuju_ikut_seleksi', label: 'Saya bersedia mengikuti seluruh tahapan seleksi Management Trainee Production sesuai ketentuan EVO Group.', tipe: 'consent', wajib: true },
                        { key: 'setuju_data_pribadi', label: 'Saya memberikan persetujuan kepada EVO Group untuk menggunakan data pribadi saya hanya untuk keperluan proses rekrutmen dan seleksi karyawan.', tipe: 'consent', wajib: true },
                    ],
                },
            ],
        },
    ],
};

export default SKEMA;
