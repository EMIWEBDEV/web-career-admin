/**
 * SKEMA — Form 1: Pendaftaran (gerbang saat melamar).
 *
 * Sesuai "List Data Form Pendaftaran 1 MT" pada berkas referensi
 * (docs/refrences/List Identitas Form Pendaftaran MT.xlsx, sheet "Form 1").
 *
 * Berkas itu memuat DUA daftar — varian Politeknik dan varian Rekrutmen Umum.
 * Keduanya TIDAK dipisah jadi dua formulir: isinya sama persis kecuali blok
 * pendidikan, jadi cukup satu formulir dengan percabangan.
 *
 * ══════════════════════════════════════════════════════════════════════
 *  DI SINILAH SELURUH ATURAN FORMULIR DITULIS.
 *  Menambah pertanyaan, mengubah opsi, atau menambah syarat tampil
 *  cukup menyunting berkas ini. Tabel database TIDAK perlu di-ALTER
 *  karena jawaban tersimpan sebagai JSON.
 * ══════════════════════════════════════════════════════════════════════
 *
 * BENTUK SATU FIELD:
 *   key            wajib, unik se-formulir — jadi nama kolom di Jawaban_Json
 *   label          pertanyaan yang dibaca kandidat
 *   tipe           text | textarea | number | date | select | radio |
 *                  checkbox | file | consent | prefill
 *   wajib          true/false
 *   opsi           daftar pilihan (untuk select/radio/checkbox)
 *   ph             placeholder di dalam kolom
 *   bantuan        keterangan kecil di bawah kolom
 *   penuh          true = kolom memakan lebar penuh
 *   dapat_disaring true = nilainya bisa dipakai syarat auto-gugur
 *   tampil_jika    { field, operator, nilai } — syarat kemunculan
 *                  operator: = != > < >= <=
 */
export const SKEMA = {
    template: 'TEMPLATE_1',
    layout: 'SATU_HALAMAN',
    langkah: [
        {
            kode: 'PENDAFTARAN',
            judul: 'Data Pendaftaran',
            ikon: 'bi-person-vcard',
            bagian: [
                {
                    judul: 'A. Data Diri',
                    field: [
                        {
                            key: 'nama_lengkap',
                            label: 'Nama Lengkap Sesuai ID',
                            tipe: 'text',
                            wajib: true,
                            ph: 'Sesuai KTP / kartu identitas',
                        },
                        { key: 'tanggal_lahir', label: 'Tanggal Lahir', tipe: 'date', wajib: true },
                        {
                            key: 'jenis_kelamin',
                            label: 'Jenis Kelamin',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Laki-Laki', 'Perempuan'],
                            // Ditandai dapat_disaring supaya bisa jadi acuan syarat
                            // pertanyaan lain DAN syarat auto-gugur di Program Kegiatan.
                            dapat_disaring: true,
                        },
                        {
                            key: 'no_hp',
                            label: 'No. Handphone Aktif (WA)',
                            tipe: 'phone',
                            wajib: true,
                            ph: '628xxxxxxxxx',
                            bantuan: 'Wajib berawalan 62. Ketik 08… otomatis jadi 628…',
                        },
                        { key: 'email', label: 'Email', tipe: 'text', wajib: true, ph: 'nama.lengkap@gmail.com' },
                    ],
                },
                {
                    judul: 'B. Pendidikan',
                    field: [
                        {
                            key: 'status_kemahasiswaan',
                            label: 'Status Kemahasiswaan',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Mahasiswa', 'Sudah Lulus'],
                            dapat_disaring: true,
                        },
                        {
                            // Hanya relevan bila masih kuliah — persis catatan di berkas
                            // referensi: "Jika masih mahasiswa … semester berapa".
                            key: 'semester',
                            label: 'Semester Saat Ini',
                            tipe: 'number',
                            wajib: true,
                            min: 1,
                            maks: 14,
                            tampil_jika: { field: 'status_kemahasiswaan', operator: '=', nilai: 'Mahasiswa' },
                        },

                        // ══ CASCADE PENDIDIKAN — Jenjang → Jenis Institusi → Nama Kampus ══
                        // Data dari master (opsi via sumber_api). Kandidat pilih JENJANG
                        // dulu; jenis institusi menyesuaikan jenjang (tabel binding); nama
                        // kampus/sekolah dicari server-side terfilter jenis (autocomplete).
                        {
                            key: 'jenjang',
                            label: 'Jenjang Pendidikan',
                            tipe: 'select',
                            wajib: true,
                            // Opsi dari /api/v1/pendidikan/jenjang (Master Jenjang).
                            sumber_api: 'jenjang',
                            // Seluruh keturunan disebut eksplisit — pengosongan
                            // hanya satu tingkat, tidak menurun sendiri.
                            reset_anak: ['jenis_institusi', 'nama_kampus', 'jurusan', 'program_studi'],
                            ph: 'Pilih jenjang pendidikan',
                            bantuan: 'Pilih jenjang lebih dulu — menentukan jenis institusi & daftar kampus/sekolah.',
                            dapat_disaring: true,
                        },
                        {
                            key: 'jenis_institusi',
                            label: 'Jenis Institusi Pendidikan',
                            tipe: 'select',
                            wajib: true,
                            // Opsi menyesuaikan jenjang terpilih (tabel binding Jenis↔Jenjang).
                            sumber_api: 'jenis_institusi',
                            tergantung: 'jenjang',
                            reset_anak: ['nama_kampus', 'jurusan', 'program_studi'],
                            ph: 'Pilih jenis institusi',
                            bantuan: 'Universitas, Politeknik, SMA, SMK, dst — sesuai jenjang.',
                            tampil_jika: { field: 'jenjang', operator: '!=', nilai: '' },
                            dapat_disaring: true,
                        },
                        {
                            key: 'nama_kampus',
                            label: 'Nama Kampus / Sekolah',
                            tipe: 'select',
                            wajib: true,
                            penuh: true, // col-12 (lebar penuh) — muncul setelah jenjang + jenis
                            // Pencarian server-side (autocomplete) terfilter jenis institusi.
                            sumber_api: 'kampus',
                            cari_async: true,
                            tergantung: 'jenis_institusi',
                            reset_anak: ['jurusan', 'program_studi'],
                            ph: 'Ketik untuk mencari nama…',
                            bantuan: 'Ketik nama lalu pilih dari daftar. Tidak ada? Ketik langsung lalu tekan Enter.',
                            tampil_jika: { field: 'jenis_institusi', operator: '!=', nilai: '' },
                            dapat_disaring: true,
                        },
                        // Jurusan & Program Studi ikut cascade dari kampus terpilih,
                        // tapi TETAP boleh diketik sendiri (boleh_ketik). Master prodi
                        // tidak akan pernah lengkap — prodi baru dibuka tiap tahun, dan
                        // kampus luar negeri penamaannya bebas. Mengunci pilihan hanya
                        // akan membuat pelamar mentok di tengah formulir.
                        {
                            key: 'jurusan',
                            label: 'Jurusan / Fakultas / Program Keahlian',
                            tipe: 'select',
                            wajib: true,
                            sumber_api: 'fakultas',
                            tergantung: 'nama_kampus',
                            reset_anak: ['program_studi'],
                            boleh_ketik: true,
                            ph: 'Pilih atau ketik sendiri',
                            bantuan: 'Tidak ada di daftar? Ketik langsung lalu tekan Enter.',
                            tampil_jika: { field: 'nama_kampus', operator: '!=', nilai: '' },
                            dapat_disaring: true,
                        },
                        {
                            key: 'program_studi',
                            label: 'Program Studi',
                            tipe: 'select',
                            wajib: true,
                            sumber_api: 'prodi',
                            cari_async: true,
                            tergantung: 'nama_kampus',
                            // Dipersempit oleh jurusan/fakultas yang dipilih di atas.
                            saring_dari: 'jurusan',
                            boleh_ketik: true,
                            ph: 'Ketik untuk mencari, atau ketik sendiri',
                            bantuan: 'Tidak ada di daftar? Ketik langsung lalu tekan Enter.',
                            tampil_jika: { field: 'nama_kampus', operator: '!=', nilai: '' },
                            dapat_disaring: true,
                        },
                        {
                            key: 'ipk',
                            label: 'IPK / Nilai Akhir',
                            tipe: 'number',
                            wajib: true,
                            min: 0,
                            maks: 4,
                            desimal: 2,
                            ph: 'mis. 3.25',
                            tampil_jika: { field: 'jenis_institusi', operator: '!=', nilai: '' },
                            dapat_disaring: true,
                        },
                        {
                            // Sebaris dengan IPK (kiri-kanan). Ditaruh di bagian
                            // yang sama karena tiap bagian punya grid sendiri —
                            // beda bagian tidak akan pernah bersebelahan.
                            key: 'bersedia_ditempatkan',
                            label: 'Bersedia ditempatkan di Pabrik Banyuasin?',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Ya', 'Tidak'],
                            dapat_disaring: true,
                        },
                    ],
                },
            ],
        },
    ],
};

export default SKEMA;
