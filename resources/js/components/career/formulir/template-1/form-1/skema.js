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

                        {
                            key: 'nama_kampus',
                            label: 'Nama Kampus / Universitas',
                            tipe: 'select',
                            wajib: true,
                            ph: 'Cari nama kampus lalu pilih',
                            bantuan: 'Ketik untuk mencari, lalu pilih dari daftar resmi. Tidak ada? Hubungi admin.',
                            dapat_disaring: true,
                            // Opsi diambil dari MASTER KAMPUS (daftar resmi, bisa dicari).
                            // Channel UMUM/KAMPUS sudah digabung — kandidat WAJIB memilih
                            // dari daftar ini dan tidak boleh mengetik bebas (lihat FieldRenderer).
                            sumber_opsi: 'kampus',
                        },
                        {
                            key: 'jenis_institusi',
                            label: 'Jenis Institusi Pendidikan',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Politeknik', 'Universitas'],
                            bantuan: 'Menentukan pertanyaan pendidikan berikutnya.',
                            dapat_disaring: true,
                        },

                        // ── Percabangan Politeknik vs Universitas ──
                        // Menambah jalur baru (mis. Sekolah Vokasi) cukup menambah
                        // entri di sini — tidak ada if yang perlu disunting.
                        {
                            key: 'jurusan',
                            label: 'Jurusan',
                            tipe: 'text',
                            wajib: true,
                            ph: 'mis. Teknik Mesin',
                            tampil_jika: { field: 'jenis_institusi', operator: '=', nilai: 'Politeknik' },
                        },
                        {
                            key: 'fakultas',
                            label: 'Fakultas',
                            tipe: 'text',
                            wajib: true,
                            ph: 'mis. Fakultas Teknik',
                            tampil_jika: { field: 'jenis_institusi', operator: '=', nilai: 'Universitas' },
                        },
                        {
                            key: 'jenjang_politeknik',
                            label: 'Jenjang Pendidikan',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['D3', 'D4'],
                            dapat_disaring: true,
                            tampil_jika: { field: 'jenis_institusi', operator: '=', nilai: 'Politeknik' },
                        },
                        {
                            key: 'jenjang_universitas',
                            label: 'Jenjang Pendidikan',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['S1', 'S2'],
                            dapat_disaring: true,
                            tampil_jika: { field: 'jenis_institusi', operator: '=', nilai: 'Universitas' },
                        },

                        { key: 'program_studi', label: 'Program Studi', tipe: 'text', wajib: true, ph: 'mis. Teknik Industri' },
                        {
                            key: 'ipk',
                            label: 'IPK',
                            tipe: 'number',
                            wajib: true,
                            min: 0,
                            maks: 4,
                            desimal: 2,
                            ph: 'mis. 3.25',
                            dapat_disaring: true,
                        },
                    ],
                },
                {
                    judul: 'C. Kesediaan',
                    field: [
                        {
                            key: 'bersedia_ditempatkan',
                            label: 'Apakah bersedia ditempatkan di Pabrik Banyuasin?',
                            tipe: 'select',
                            wajib: true,
                            opsi: ['Ya', 'Tidak'],
                            penuh: true,
                            dapat_disaring: true,
                        },
                    ],
                },
            ],
        },
    ],
};

export default SKEMA;
