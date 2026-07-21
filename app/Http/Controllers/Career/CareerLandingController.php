<?php

namespace App\Http\Controllers\Career;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * WEB CAREER — LANDING PAGE (DUMMY / FASE 1)
 * -------------------------------------------------------------
 * Halaman karir publik dummy. TIDAK terhubung ke database.
 * Semua data di bawah adalah data contoh (dummy) yang mensimulasikan
 * sumber data nyata: Career_Lowongan (dari MPP) + Career_Kegiatan (MT).
 *
 * Sengaja tanpa middleware/auth agar bisa dibuka bebas untuk demo alur.
 * Struktur data mengikuti arsitektur final Web Career:
 *   KEGIATAN (MT|REKRUTMEN) -> LOWONGAN (dari MPP) -> PIPELINE (stage dinamis)
 *
 * Lokasi grup: Palembang (Head Office) & Banyuasin (Pabrik).
 *
 * Route: GET /test/karir/landing-page  (name: career.landing)
 */
class CareerLandingController extends Controller
{
    public function index(Request $request)
    {
        $lowongan = $this->visibleLowongan();
        $programMt = $this->programMt();

        return Inertia::render('Career/LandingPage', [
            'meta' => $this->meta($lowongan, $programMt),
            'departments' => $this->departments(),
            'locations' => $this->locations(),
            'lowongan' => $lowongan,
            'programMt' => $programMt,
            'achievements' => $this->achievements(),
            'offices' => $this->offices(),
            'benefits' => $this->benefits(),
        ]);
    }

    /** Halaman auth kandidat — dummy (tanpa DB). Satu halaman, mode login/register. */
    public function login()
    {
        return Inertia::render('Career/Auth', ['mode' => 'login']);
    }

    public function register()
    {
        return Inertia::render('Career/Auth', ['mode' => 'register']);
    }

    /**
     * FORMULIR APPLY kandidat. Wizard STATIS multi-langkah (struktur = data predefined, bukan builder).
     * MT memakai **Form 1** (dari docs/refrences/List Identitas Form Pendaftaran MT.xlsx) saat apply +
     * verifikasi wajah wajib; **Form 2** (identitas tambahan/kontak darurat/kesiapan/dokumen/persetujuan)
     * diisi di tahap lanjut setelah lolos (akses `?form=2`). Rekrutmen umum memakai flow generik.
     */
    public function apply(Request $request, string $id)
    {
        $form = (int) $request->query('form', 1);
        return Inertia::render('Career/ApplyForm', array_merge($this->layoutShared(), [
            'flow' => $this->applyFlow($id, $form === 2 ? 2 : 1),
        ]));
    }

    private function applyFlow(string $id, int $form = 1): array
    {
        // Ambil data NYATA dari daftar lowongan / program MT berdasarkan id (yang diklik dari landing).
        $lo = collect($this->lowongan())->firstWhere('id', $id);
        $mt = collect($this->programMt())->firstWhere('id', $id);
        if ($mt) {
            $job = ['posisi' => $mt['nama'], 'program' => trim(($mt['batch'] ?? '') . ' · ' . ($mt['perusahaan'] ?? 'EVO Group')), 'kategori' => 'MT', 'lokasi' => $mt['lokasi'] ?? '—'];
        } elseif ($lo) {
            $job = ['posisi' => $lo['posisi'], 'program' => $lo['perusahaan'] ?? 'EVO Group', 'kategori' => 'REKRUTMEN', 'lokasi' => trim(($lo['lokasi'] ?? '') . ' · ' . ($lo['tempatKerja'] ?? ''), ' ·')];
        } else {
            $job = ['posisi' => 'Lowongan EVO Group', 'program' => 'EVO Group', 'kategori' => 'REKRUTMEN', 'lokasi' => 'Palembang'];
        }
        $isMt = $job['kategori'] === 'MT';

        if ($isMt && $form === 2) {
            $steps = $this->mtForm2Steps();
        } elseif ($isMt) {
            $steps = $this->mtForm1Steps();
        } else {
            $steps = $this->rekrutmenSteps();
        }

        return ['lowongan' => array_merge(['id' => $id], $job), 'form' => $form, 'steps' => $steps];
    }

    /**
     * Data katalog publik (tahapan pipeline + jadwal kegiatan + meta program) berdasarkan id.
     * Dipakai Portal Kandidat → halaman Detail Lamaran Terkirim agar tahapan & jadwal
     * SELARAS dengan sumber yang sama dipakai landing/detail (admin-aligned).
     */
    public function catalogItem(string $id): ?array
    {
        $mt = collect($this->programMt())->firstWhere('id', $id);
        if ($mt) {
            return [
                'id' => $id, 'kategori' => 'MT', 'jenis' => 'MT',
                'nama' => $mt['nama'], 'perusahaan' => $mt['perusahaan'] ?? 'EVO Group',
                'batch' => $mt['batch'] ?? null, 'lokasi' => $mt['lokasi'] ?? '—',
                'penempatan' => $mt['penempatan'] ?? null, 'durasi' => $mt['durasi'] ?? null,
                'ikatan' => $mt['ikatan'] ?? null, 'tipeKegiatan' => $mt['tipeKegiatan'] ?? null,
                'ringkasan' => $mt['ringkasan'] ?? null, 'deskripsi' => $mt['deskripsi'] ?? null,
                'benefit' => $mt['benefit'] ?? [], 'kriteria' => $mt['kriteria'] ?? [],
                'fasilitas' => $mt['fasilitas'] ?? [], 'tanggalPengumuman' => $mt['tanggalPengumuman'] ?? null,
                'pipeline' => $mt['pipeline'] ?? [], 'jadwal' => $mt['jadwal'] ?? [],
            ];
        }
        $lo = collect($this->lowongan())->firstWhere('id', $id);
        if ($lo) {
            return [
                'id' => $id, 'kategori' => 'REKRUTMEN', 'jenis' => 'REKRUTMEN',
                'nama' => $lo['posisi'], 'perusahaan' => $lo['perusahaan'] ?? 'EVO Group',
                'departemen' => $lo['departemen'] ?? null, 'level' => $lo['level'] ?? null,
                'tipeKerja' => $lo['tipeKerja'] ?? null,
                'lokasi' => trim(($lo['lokasi'] ?? '') . ' · ' . ($lo['tempatKerja'] ?? ''), ' ·'),
                'ringkasan' => $lo['ringkasan'] ?? null, 'deskripsi' => $lo['deskripsi'] ?? null,
                'benefit' => $lo['benefit'] ?? [], 'kriteria' => $lo['persyaratan'] ?? [],
                'skill' => $lo['skill'] ?? [], 'pipeline' => $lo['pipeline'] ?? [], 'jadwal' => [],
            ];
        }
        return null;
    }

    private function faceStep(): array
    {
        return ['key' => 'FACE', 'tipe' => 'FACE', 'judul' => 'Verifikasi Wajah', 'ikon' => 'bi-camera', 'deskripsi' => 'Ambil satu foto wajah untuk verifikasi identitas — langkah terakhir sebelum finalisasi.'];
    }

    private function reviewStep(): array
    {
        return ['key' => 'REVIEW', 'tipe' => 'REVIEW', 'judul' => 'Review & Finalisasi', 'ikon' => 'bi-send-check'];
    }

    /** Flow generik rekrutmen umum (bukan MT). */
    private function rekrutmenSteps(): array
    {
        return [
            ['key' => 'DIRI', 'tipe' => 'FORM', 'judul' => 'Data Diri', 'ikon' => 'bi-person-vcard', 'fields' => [
                ['key' => 'nama', 'label' => 'Nama Lengkap', 'tipe' => 'text', 'required' => true, 'ph' => 'Sesuai KTP'],
                ['key' => 'nik', 'label' => 'NIK', 'tipe' => 'text', 'required' => true, 'ph' => '16 digit'],
                ['key' => 'jkel', 'label' => 'Jenis Kelamin', 'tipe' => 'select', 'required' => true, 'opsi' => ['Laki-laki', 'Perempuan']],
                ['key' => 'lahir', 'label' => 'Tanggal Lahir', 'tipe' => 'date', 'required' => true],
                ['key' => 'hp', 'label' => 'No. HP / WhatsApp', 'tipe' => 'text', 'required' => true, 'ph' => '08xx'],
                ['key' => 'email', 'label' => 'Email', 'tipe' => 'text', 'required' => true, 'ph' => 'nama@email.com'],
                ['key' => 'alamat', 'label' => 'Alamat Domisili', 'tipe' => 'textarea', 'required' => true, 'full' => true],
            ]],
            ['key' => 'DIDIK', 'tipe' => 'FORM', 'judul' => 'Pendidikan', 'ikon' => 'bi-mortarboard', 'fields' => [
                ['key' => 'jenjang', 'label' => 'Jenjang', 'tipe' => 'select', 'required' => true, 'opsi' => ['SMA', 'SMK', 'D3', 'D4', 'S1', 'S2']],
                ['key' => 'kampus', 'label' => 'Institusi / Kampus', 'tipe' => 'text', 'required' => true],
                ['key' => 'jurusan', 'label' => 'Jurusan', 'tipe' => 'text', 'required' => true],
                ['key' => 'ipk', 'label' => 'IPK', 'tipe' => 'number', 'required' => true, 'ph' => '3.50'],
                ['key' => 'lulus', 'label' => 'Tahun Lulus', 'tipe' => 'number', 'required' => true, 'ph' => '2024'],
            ]],
            ['key' => 'KERJA', 'tipe' => 'FORM', 'judul' => 'Pengalaman', 'ikon' => 'bi-briefcase', 'opsional' => true, 'repeat' => true, 'itemLabel' => 'Pengalaman', 'fields' => [
                ['key' => 'perusahaan', 'label' => 'Perusahaan / Instansi', 'tipe' => 'text'],
                ['key' => 'posisiKerja', 'label' => 'Posisi / Jabatan', 'tipe' => 'text'],
                ['key' => 'mulai', 'label' => 'Tanggal Mulai', 'tipe' => 'date'],
                ['key' => 'selesai', 'label' => 'Tanggal Selesai', 'tipe' => 'date', 'disableIf' => 'sekarang'],
                ['key' => 'sekarang', 'label' => 'Masih berlangsung sampai sekarang', 'tipe' => 'switch', 'full' => true],
                ['key' => 'deskripsiKerja', 'label' => 'Deskripsi Tugas / Pencapaian', 'tipe' => 'textarea', 'full' => true],
            ]],
            ['key' => 'BERKAS', 'tipe' => 'UPLOAD', 'judul' => 'Unggah Berkas', 'ikon' => 'bi-paperclip', 'files' => [
                ['key' => 'cv', 'label' => 'CV / Resume', 'required' => true, 'accept' => '.pdf', 'hint' => 'PDF'],
                ['key' => 'ktp', 'label' => 'KTP', 'required' => true, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'PDF / JPG'],
                ['key' => 'ijazah', 'label' => 'Ijazah / Transkrip', 'required' => true, 'accept' => '.pdf', 'hint' => 'PDF'],
                ['key' => 'pasfoto', 'label' => 'Pas Foto', 'required' => false, 'accept' => '.jpg,.jpeg,.png', 'hint' => 'JPG / PNG'],
            ]],
            ['key' => 'SEDIA', 'tipe' => 'PERNYATAAN', 'judul' => 'Pernyataan & Kesediaan', 'ikon' => 'bi-check2-square', 'items' => [
                'Data yang saya isi benar & dapat dipertanggungjawabkan',
                'Bersedia ditempatkan di seluruh unit EVO Group',
                'Bersedia mengikuti seluruh tahapan seleksi',
            ]],
            $this->faceStep(),
            $this->reviewStep(),
        ];
    }

    /** MT — FORM 1 (pendaftaran awal saat apply). Field mengikuti "Form 1" (varian umum) di Excel. */
    private function mtForm1Steps(): array
    {
        $kampus = ['Universitas Sriwijaya', 'Politeknik Negeri Sriwijaya', 'Universitas Indonesia', 'Institut Teknologi Bandung', 'Universitas Gadjah Mada', 'IPB University', 'Universitas Padjadjaran', 'Institut Teknologi Sepuluh Nopember', 'Universitas Bina Darma', 'Lainnya'];

        return [
            ['key' => 'DIRI', 'tipe' => 'FORM', 'judul' => 'Data Diri', 'ikon' => 'bi-person-vcard', 'fields' => [
                ['key' => 'nama', 'label' => 'Nama Lengkap Sesuai ID', 'tipe' => 'text', 'required' => true, 'ph' => 'Sesuai KTP'],
                ['key' => 'lahir', 'label' => 'Tanggal Lahir', 'tipe' => 'date', 'required' => true],
                ['key' => 'jkel', 'label' => 'Jenis Kelamin', 'tipe' => 'select', 'required' => true, 'opsi' => ['Laki-Laki', 'Perempuan']],
                ['key' => 'hp', 'label' => 'No. Handphone Aktif (WA)', 'tipe' => 'text', 'required' => true, 'ph' => '08xx'],
                ['key' => 'email', 'label' => 'Email', 'tipe' => 'text', 'required' => true, 'ph' => 'nama@email.com'],
                ['key' => 'statusMhs', 'label' => 'Status Kemahasiswaan', 'tipe' => 'select', 'required' => true, 'opsi' => ['Mahasiswa', 'Sudah Lulus']],
                ['key' => 'semester', 'label' => 'Semester saat ini', 'tipe' => 'number', 'required' => true, 'ph' => 'mis. 6', 'showIf' => ['key' => 'statusMhs', 'value' => 'Mahasiswa']],
            ]],
            ['key' => 'DIDIK', 'tipe' => 'FORM', 'judul' => 'Pendidikan', 'ikon' => 'bi-mortarboard', 'fields' => [
                ['key' => 'kampus', 'label' => 'Nama Kampus', 'tipe' => 'select', 'required' => true, 'opsi' => $kampus],
                ['key' => 'institusi', 'label' => 'Jenis Institusi Pendidikan', 'tipe' => 'select', 'required' => true, 'opsi' => ['Politeknik', 'Universitas']],
                ['key' => 'jurusan', 'label' => 'Jurusan / Fakultas', 'tipe' => 'text', 'required' => true],
                ['key' => 'prodi', 'label' => 'Program Studi', 'tipe' => 'text', 'required' => true],
                ['key' => 'jenjang', 'label' => 'Jenjang Pendidikan', 'tipe' => 'select', 'required' => true, 'opsi' => ['D3', 'D4', 'S1', 'S2']],
                ['key' => 'ipk', 'label' => 'IPK', 'tipe' => 'number', 'required' => true, 'ph' => '3.50'],
                ['key' => 'bersediaBanyuasin', 'label' => 'Bersedia ditempatkan di Pabrik Banyuasin?', 'tipe' => 'select', 'required' => true, 'opsi' => ['Ya', 'Tidak'], 'full' => true],
            ]],
            $this->faceStep(),
            $this->reviewStep(),
        ];
    }

    /** MT — FORM 2 (identitas tambahan, diisi setelah LOLOS ke tahap berikutnya). Field mengikuti "Form 2" di Excel. */
    private function mtForm2Steps(): array
    {
        $yn = ['Ya', 'Tidak'];

        return [
            ['key' => 'VALIDASI', 'tipe' => 'FORM', 'judul' => 'Validasi Data Peserta', 'ikon' => 'bi-clipboard-check', 'deskripsi' => 'Data ini terisi otomatis dari pendaftaran (Form 1). Konfirmasi kebenarannya.', 'fields' => [
                ['key' => 'namaPre', 'label' => 'Nama Lengkap', 'tipe' => 'text', 'readonly' => true, 'ph' => '(otomatis dari pendaftaran)'],
                ['key' => 'emailPre', 'label' => 'Email Terdaftar', 'tipe' => 'text', 'readonly' => true, 'ph' => '(otomatis dari pendaftaran)'],
                ['key' => 'waPre', 'label' => 'No. WhatsApp Terdaftar', 'tipe' => 'text', 'readonly' => true, 'ph' => '(otomatis dari pendaftaran)'],
                ['key' => 'dataSesuai', 'label' => 'Apakah data di atas sudah sesuai?', 'tipe' => 'select', 'required' => true, 'opsi' => ['Sesuai', 'Perlu diperbarui'], 'full' => true],
                ['key' => 'dataBaru', 'label' => 'Tuliskan data yang benar', 'tipe' => 'textarea', 'full' => true, 'showIf' => ['key' => 'dataSesuai', 'value' => 'Perlu diperbarui']],
            ]],
            ['key' => 'IDENTITAS', 'tipe' => 'FORM', 'judul' => 'Identitas Tambahan', 'ikon' => 'bi-person-lines-fill', 'fields' => [
                ['key' => 'alamatKtp', 'label' => 'Alamat Lengkap (Sesuai KTP)', 'tipe' => 'textarea', 'required' => true, 'full' => true],
                ['key' => 'alamatDomisili', 'label' => 'Alamat Domisili Saat Ini (kosongkan jika sama dengan KTP)', 'tipe' => 'textarea', 'full' => true],
                ['key' => 'perguruanTinggi', 'label' => 'Nama Perguruan Tinggi', 'tipe' => 'text', 'required' => true],
                ['key' => 'tahunLulus', 'label' => 'Tahun Lulus / Perkiraan Lulus', 'tipe' => 'text', 'required' => true, 'ph' => 'mis. 2025'],
                ['key' => 'statusKetersediaan', 'label' => 'Status Ketersediaan Mengikuti Proses', 'tipe' => 'select', 'required' => true, 'opsi' => ['Siap mengikuti seluruh proses', 'Perlu penyesuaian jadwal']],
                ['key' => 'mulaiKerja', 'label' => 'Ketersediaan Mulai Bekerja', 'tipe' => 'select', 'required' => true, 'opsi' => ['Segera', '1 bulan', '2 bulan', '3 bulan']],
            ]],
            ['key' => 'DARURAT', 'tipe' => 'FORM', 'judul' => 'Kontak Darurat', 'ikon' => 'bi-telephone-plus', 'fields' => [
                ['key' => 'namaDarurat', 'label' => 'Nama Kontak Darurat', 'tipe' => 'text', 'required' => true],
                ['key' => 'hubunganDarurat', 'label' => 'Hubungan dengan Peserta', 'tipe' => 'text', 'required' => true],
                ['key' => 'hpDarurat', 'label' => 'No. Handphone Kontak Darurat', 'tipe' => 'text', 'required' => true, 'ph' => '08xx'],
            ]],
            ['key' => 'KESIAPAN', 'tipe' => 'FORM', 'judul' => 'Kesiapan Penempatan & Kerja', 'ikon' => 'bi-briefcase', 'fields' => [
                ['key' => 'plant', 'label' => 'Bersedia ditempatkan di area Plant / Pabrik', 'tipe' => 'select', 'required' => true, 'opsi' => $yn, 'full' => true],
                ['key' => 'shift', 'label' => 'Bersedia bekerja dengan sistem shift jika dibutuhkan', 'tipe' => 'select', 'required' => true, 'opsi' => $yn, 'full' => true],
                ['key' => 'durasiMt', 'label' => 'Bersedia mengikuti program MT sesuai durasi & ketentuan', 'tipe' => 'select', 'required' => true, 'opsi' => $yn, 'full' => true],
                ['key' => 'ikatanDinas', 'label' => 'Bersedia menjalani ikatan dinas 2 tahun jika lulus', 'tipe' => 'select', 'required' => true, 'opsi' => $yn, 'full' => true],
                ['key' => 'pengalamanProduksi', 'label' => 'Punya pengalaman magang/kerja/praktik di produksi/manufaktur', 'tipe' => 'select', 'required' => true, 'opsi' => $yn, 'full' => true],
                ['key' => 'pengalamanJelas', 'label' => 'Jika Ya, jelaskan singkat pengalaman tersebut', 'tipe' => 'textarea', 'full' => true, 'showIf' => ['key' => 'pengalamanProduksi', 'value' => 'Ya']],
            ]],
            ['key' => 'DOKUMEN', 'tipe' => 'UPLOAD', 'judul' => 'Kelengkapan Dokumen', 'ikon' => 'bi-paperclip', 'files' => [
                ['key' => 'cv', 'label' => 'CV Terbaru', 'required' => true, 'accept' => '.pdf', 'hint' => 'PDF'],
                ['key' => 'transkrip', 'label' => 'Transkrip Nilai', 'required' => true, 'accept' => '.pdf', 'hint' => 'PDF'],
                ['key' => 'ijazah', 'label' => 'Ijazah / Surat Keterangan Lulus', 'required' => false, 'accept' => '.pdf', 'hint' => 'PDF'],
                ['key' => 'sertifikat', 'label' => 'Sertifikat Pendukung (jika ada)', 'required' => false, 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'PDF / JPG'],
            ]],
            ['key' => 'PERSETUJUAN', 'tipe' => 'PERNYATAAN', 'judul' => 'Pernyataan Persetujuan', 'ikon' => 'bi-check2-square', 'items' => [
                'Saya menyatakan seluruh data & dokumen yang saya berikan benar dan dapat dipertanggungjawabkan.',
                'Saya bersedia mengikuti seluruh tahapan seleksi Management Trainee sesuai ketentuan EVO Group.',
                'Saya menyetujui penggunaan data pribadi hanya untuk keperluan proses rekrutmen & seleksi.',
            ]],
            $this->reviewStep(),
        ];
    }

    /** Halaman detail lowongan (punya route sendiri, memakai CareerLayout). */
    public function showLowongan(string $id)
    {
        $job = collect($this->lowongan())->firstWhere('id', $id);
        abort_unless($job, 404);

        return Inertia::render('Career/DetailLowongan', array_merge($this->layoutShared(), [
            'lowongan' => $job,
        ]));
    }

    /** Halaman detail Management Trainee (punya route sendiri, memakai CareerLayout). */
    public function showMt(string $id)
    {
        $mt = collect($this->programMt())->firstWhere('id', $id);
        abort_unless($mt, 404);

        return Inertia::render('Career/DetailMt', array_merge($this->layoutShared(), [
            'programMt' => $mt,
        ]));
    }

    /** Payload bersama yang dibutuhkan CareerLayout (navbar + footer) di semua halaman. */
    private function layoutShared(): array
    {
        return [
            'hasMt' => count($this->programMt()) > 0,
            'offices' => $this->offices(),
        ];
    }

    private function meta(array $lowongan, array $programMt): array
    {
        return [
            'brand' => 'EVO Group Career',
            'tagline' => 'Naik level bersama ekosistem people, pet, & manufacturing.',
            'totalLowongan' => count($lowongan),
            'totalDepartemen' => count($this->departments()),
            'totalKota' => count($this->locations()),
            'totalProgramMt' => count($programMt),
        ];
    }

    private function departments(): array
    {
        return [
            'Sales & Distribution',
            'Marketing',
            'Supply Chain & Warehouse',
            'Production',
            'Technology',
            'People & Culture (HR)',
            'Finance & Accounting',
        ];
    }

    /** Hanya 2 lokasi operasional grup. */
    private function locations(): array
    {
        return ['Palembang', 'Banyuasin'];
    }

    /**
     * REKRUTMEN — daftar lowongan (disimulasikan tarikan dari MPP).
     * Head Office (Palembang) untuk fungsi korporat; Pabrik (Banyuasin) untuk produksi.
     */
    /**
     * ATURAN TAMPIL LANDING:
     *  - Lewat tanggal tutup  → HILANG otomatis (di-filter di sini).
     *  - Kuota penuh          → TETAP tampil (frontend menonaktifkan tombol apply).
     *  - EVERGREEN (tanggalTutup kosong) → tampil terus.
     */
    private function visibleLowongan(): array
    {
        $today = now()->toDateString();

        return array_values(array_filter($this->lowongan(), function ($l) use ($today) {
            $tutup = $l['tanggalTutup'] ?? null;
            return empty($tutup) || $tutup >= $today; // evergreen ATAU belum lewat tanggal
        }));
    }

    private function lowongan(): array
    {
        return [
            [
                'id' => 'RC-2026-001',
                'posisi' => 'Sales Executive (Pet Retail)',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Sales & Distribution',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Min. 1 tahun',
                'kuota' => 4,
                'kuotaTerisi' => 1,
                'pelamar' => 37,
                'tanggalTutup' => '2026-08-15',
                'unggulan' => true,
                'ringkasan' => 'Menjadi ujung tombak penjualan produk pet food premium ke jaringan retail dan pet shop modern.',
                'skill' => ['Negosiasi', 'Relationship', 'Target Oriented', 'MS Office'],
                'deskripsi' => 'Sebagai Sales Executive, Anda bertanggung jawab mengembangkan penjualan produk Evopet (Life Cat, Ori Cat, Life Dog) di area yang ditentukan, membangun hubungan dengan mitra retail, serta memastikan pencapaian target penjualan bulanan.',
                'tanggungJawab' => [
                    'Mencapai target penjualan bulanan sesuai area yang ditetapkan.',
                    'Membangun & memelihara hubungan baik dengan pet shop dan retailer.',
                    'Melakukan kunjungan rutin serta merchandising produk di toko.',
                    'Menyusun laporan penjualan dan aktivitas kompetitor.',
                ],
                'persyaratan' => [
                    'Pendidikan min. D3/S1 semua jurusan.',
                    'Pengalaman min. 1 tahun di bidang sales (fresh graduate berprestasi dipertimbangkan).',
                    'Memiliki SIM C dan bersedia mobilitas tinggi.',
                    'Komunikatif, ulet, dan berorientasi target.',
                ],
                'benefit' => ['Gaji pokok + komisi', 'Tunjangan transport', 'BPJS Kesehatan & Ketenagakerjaan', 'Jenjang karir jelas'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Psikotes Online'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview HR'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-002',
                'posisi' => 'Fullstack Web Developer',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Technology',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Mid',
                'pengalaman' => 'Min. 2 tahun',
                'kuota' => 2,
                'kuotaTerisi' => 0,
                'pelamar' => 58,
                'tanggalTutup' => '2026-08-30',
                'unggulan' => true,
                'ringkasan' => 'Membangun & memelihara platform internal HCIS serta sistem operasional grup berbasis Laravel + Vue.',
                'skill' => ['Laravel', 'Vue.js', 'MySQL/MSSQL', 'REST API', 'Git'],
                'deskripsi' => 'Bergabung dengan tim Technology untuk mengembangkan produk digital internal, mulai dari HCIS, sistem KPI, hingga platform karir. Anda akan bekerja end-to-end dari perancangan hingga deployment.',
                'tanggungJawab' => [
                    'Mengembangkan fitur baru pada aplikasi internal (Laravel + Inertia + Vue).',
                    'Menulis kode yang bersih, teruji, dan mudah dipelihara.',
                    'Berkolaborasi dengan tim produk & QA dalam siklus pengembangan.',
                    'Melakukan optimasi performa dan perbaikan bug.',
                ],
                'persyaratan' => [
                    'S1 Teknik Informatika / setara.',
                    'Pengalaman min. 2 tahun dengan PHP (Laravel) dan JavaScript framework.',
                    'Paham konsep REST API, database relasional, dan Git flow.',
                    'Mampu bekerja mandiri maupun tim.',
                ],
                'benefit' => ['Gaji kompetitif', 'Remote/Hybrid friendly', 'Perangkat kerja disediakan', 'Budget pengembangan skill'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Technical Test'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview Teknis'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview Culture-fit'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-003',
                'posisi' => 'Digital Marketing Specialist',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Marketing',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Min. 1 tahun',
                'kuota' => 2,
                'kuotaTerisi' => 1,
                'pelamar' => 44,
                'tanggalTutup' => '2026-08-20',
                'unggulan' => false,
                'ringkasan' => 'Merancang & mengeksekusi kampanye digital untuk brand pet food Evopet di berbagai kanal.',
                'skill' => ['Meta Ads', 'Google Ads', 'Copywriting', 'Analytics', 'Content Planning'],
                'deskripsi' => 'Anda akan mengelola performa kampanye digital, meningkatkan brand awareness, dan mendorong penjualan online untuk portofolio brand Evopet.',
                'tanggungJawab' => [
                    'Merencanakan & mengeksekusi kampanye di Meta, Google, dan marketplace.',
                    'Menganalisis performa kampanye dan menyusun laporan.',
                    'Berkolaborasi dengan tim konten dan desain.',
                    'Mengelola anggaran iklan agar efisien.',
                ],
                'persyaratan' => [
                    'S1 Marketing / Komunikasi / setara.',
                    'Pengalaman mengelola paid ads min. 1 tahun.',
                    'Menguasai tools analytics dan reporting.',
                    'Kreatif, data-driven, dan up-to-date dengan tren digital.',
                ],
                'benefit' => ['Gaji + bonus performa', 'BPJS lengkap', 'Lingkungan kreatif', 'Pelatihan digital rutin'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Studi Kasus Marketing'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-004',
                'posisi' => 'Warehouse Supervisor',
                'perusahaan' => 'PT Evo Manufacturing Indonesia',
                'departemen' => 'Supply Chain & Warehouse',
                'lokasi' => 'Banyuasin',
                'tempatKerja' => 'Pabrik',
                'tipeKerja' => 'Full-time',
                'level' => 'Supervisor',
                'pengalaman' => 'Min. 3 tahun',
                'kuota' => 1,
                'kuotaTerisi' => 1,
                'pelamar' => 21,
                'tanggalTutup' => '2026-09-05',
                'unggulan' => false,
                'ringkasan' => 'Memimpin operasional gudang, memastikan akurasi stok, dan efisiensi alur keluar-masuk barang.',
                'skill' => ['WMS', 'Inventory Control', 'Leadership', 'K3', 'Reporting'],
                'deskripsi' => 'Mengelola tim gudang untuk memastikan penerimaan, penyimpanan, dan pengiriman barang berjalan akurat, aman, dan tepat waktu.',
                'tanggungJawab' => [
                    'Mengawasi operasional harian gudang dan tim.',
                    'Menjaga akurasi stok melalui stock opname berkala.',
                    'Memastikan penerapan standar K3 di area gudang.',
                    'Menyusun laporan operasional gudang.',
                ],
                'persyaratan' => [
                    'D3/S1 semua jurusan.',
                    'Pengalaman min. 3 tahun di operasional gudang, min. 1 tahun sebagai supervisor.',
                    'Menguasai sistem WMS dan Ms. Excel.',
                    'Tegas, teliti, dan mampu memimpin tim.',
                ],
                'benefit' => ['Tunjangan jabatan', 'BPJS lengkap', 'Uang makan & shift', 'Jenjang karir'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Psikotes Online'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview Manajemen'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-005',
                'posisi' => 'Production Quality Analyst',
                'perusahaan' => 'PT Evo Manufacturing Indonesia',
                'departemen' => 'Production',
                'lokasi' => 'Banyuasin',
                'tempatKerja' => 'Pabrik',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Fresh graduate welcome',
                'kuota' => 3,
                'kuotaTerisi' => 2,
                'pelamar' => 29,
                'tanggalTutup' => '2026-08-25',
                'unggulan' => false,
                'ringkasan' => 'Menjaga standar mutu produk pet food sepanjang proses produksi melalui pengujian & kontrol kualitas.',
                'skill' => ['QC/QA', 'GMP', 'Analisis Lab', 'HACCP', 'Dokumentasi'],
                'deskripsi' => 'Bertanggung jawab memastikan setiap batch produksi memenuhi standar mutu dan keamanan pangan sebelum didistribusikan.',
                'tanggungJawab' => [
                    'Melakukan pengujian mutu bahan baku dan produk jadi.',
                    'Menerapkan standar GMP dan HACCP di lini produksi.',
                    'Mendokumentasikan hasil pengujian dan tindakan korektif.',
                    'Berkoordinasi dengan tim produksi terkait temuan mutu.',
                ],
                'persyaratan' => [
                    'S1 Teknologi Pangan / Kimia / Biologi.',
                    'Fresh graduate dipersilakan; pengalaman QC nilai plus.',
                    'Memahami dasar GMP, HACCP, dan analisis laboratorium.',
                    'Teliti, jujur, dan disiplin.',
                ],
                'benefit' => ['Gaji pokok + tunjangan', 'BPJS lengkap', 'Uang shift', 'Pelatihan mutu'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Teknis & Psikotes'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-006',
                'posisi' => 'HR Generalist',
                'perusahaan' => 'PT Graha Maju Nusantara',
                'departemen' => 'People & Culture (HR)',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Min. 2 tahun',
                'kuota' => 1,
                'kuotaTerisi' => 0,
                'pelamar' => 33,
                'tanggalTutup' => null, // EVERGREEN — tanpa batas waktu, di-share terus
                'unggulan' => false,
                'ringkasan' => 'Menangani siklus HR end-to-end: rekrutmen, administrasi, hingga employee engagement.',
                'skill' => ['Recruitment', 'Payroll', 'UU Ketenagakerjaan', 'People Skills', 'HRIS'],
                'deskripsi' => 'Menjadi mitra bisnis HR yang mendukung operasional people di entitas grup, dari hiring hingga pengembangan karyawan.',
                'tanggungJawab' => [
                    'Mengelola proses rekrutmen dan onboarding karyawan.',
                    'Menangani administrasi kepegawaian dan payroll dasar.',
                    'Mendukung program engagement dan pengembangan karyawan.',
                    'Memastikan kepatuhan terhadap regulasi ketenagakerjaan.',
                ],
                'persyaratan' => [
                    'S1 Psikologi / Manajemen SDM / Hukum.',
                    'Pengalaman min. 2 tahun sebagai HR generalist.',
                    'Memahami UU Ketenagakerjaan terbaru.',
                    'Empatik, rapi, dan dapat menjaga kerahasiaan.',
                ],
                'benefit' => ['Gaji kompetitif', 'BPJS lengkap', 'Cuti sesuai regulasi', 'Lingkungan suportif'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Psikotes Online'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview HR'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview Manajemen'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-007',
                'posisi' => 'Finance & Accounting Staff',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Finance & Accounting',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Full-time',
                'level' => 'Staff',
                'pengalaman' => 'Min. 1 tahun',
                'kuota' => 2,
                'kuotaTerisi' => 1,
                'pelamar' => 40,
                'tanggalTutup' => '2026-09-01',
                'unggulan' => false,
                'ringkasan' => 'Mengelola pencatatan transaksi keuangan, rekonsiliasi, dan pelaporan pajak dasar.',
                'skill' => ['Accounting', 'Pajak', 'Excel', 'Accurate/SAP', 'Ketelitian'],
                'deskripsi' => 'Mendukung operasional keuangan perusahaan dengan memastikan pencatatan yang akurat dan pelaporan yang tepat waktu.',
                'tanggungJawab' => [
                    'Mencatat dan memverifikasi transaksi keuangan harian.',
                    'Melakukan rekonsiliasi bank dan buku besar.',
                    'Menyiapkan dokumen perpajakan bulanan.',
                    'Mendukung penyusunan laporan keuangan.',
                ],
                'persyaratan' => [
                    'S1 Akuntansi.',
                    'Pengalaman min. 1 tahun di bidang accounting/tax.',
                    'Menguasai Ms. Excel dan software akuntansi.',
                    'Teliti, jujur, dan disiplin waktu.',
                ],
                'benefit' => ['Gaji + THR', 'BPJS lengkap', 'Pelatihan pajak', 'Jenjang karir'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Screening CV'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Teknis Akuntansi'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran & Onboarding'],
                ],
            ],
            [
                'id' => 'RC-2026-008',
                'posisi' => 'Content Creator & Social Media (Internship)',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'departemen' => 'Marketing',
                'lokasi' => 'Palembang',
                'tempatKerja' => 'Head Office',
                'tipeKerja' => 'Internship',
                'level' => 'Internship',
                'pengalaman' => 'Fresh graduate / Mahasiswa',
                'kuota' => 3,
                'kuotaTerisi' => 3,
                'pelamar' => 62,
                'tanggalTutup' => '2026-06-25', // sudah lewat tanggal → otomatis hilang dari landing
                'unggulan' => false,
                'ringkasan' => 'Membuat konten kreatif seputar dunia pet untuk media sosial brand Evopet.',
                'skill' => ['Content Creation', 'Video Editing', 'Canva', 'Storytelling', 'Kreativitas'],
                'deskripsi' => 'Program magang untuk kamu yang suka dunia hewan peliharaan dan kreatif membuat konten. Kamu akan terlibat langsung dalam produksi konten harian.',
                'tanggungJawab' => [
                    'Membuat konten foto/video untuk Instagram & TikTok.',
                    'Menyusun ide kampanye konten mingguan.',
                    'Membantu menjawab interaksi audiens.',
                    'Riset tren konten pet & kompetitor.',
                ],
                'persyaratan' => [
                    'Mahasiswa tingkat akhir / fresh graduate.',
                    'Menguasai dasar editing video & desain (Canva/CapCut).',
                    'Aktif di media sosial dan paham tren.',
                    'Menyukai hewan peliharaan jadi nilai plus.',
                ],
                'benefit' => ['Uang saku magang', 'Sertifikat magang', 'Mentoring langsung', 'Peluang jadi karyawan tetap'],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Lamaran & Portfolio'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tugas Kreatif'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Interview User'],
                    ['tipe' => 'OFFERING', 'label' => 'Penawaran Magang'],
                ],
            ],
        ];
    }

    /**
     * MANAGEMENT TRAINEE — "Kegiatan" ber-branding (Career_Kegiatan jenis MT).
     * Bersifat DINAMIS: bila array kosong, section MT + menu navbar otomatis hilang.
     *
     * Contoh dua model kegiatan:
     *  - EDP  : kegiatan khusus KAMPUS TERPILIH (by invitation), status BUKA.
     *  - STP  : kegiatan UMUM/terbuka, status PENUH (kuota sudah terisi) — contoh state penuh.
     */
    private function programMt(): array
    {
        return [
            [
                'id' => 'MT-2026-EDP',
                'nama' => 'EVO Development Program (EDP) 2026',
                'tagline' => 'Kaderisasi calon pemimpin masa depan EVO Group.',
                'jenis' => 'MT',
                'batch' => 'Batch 5',
                'perusahaan' => 'EVO Group',
                'status' => 'BUKA', // BUKA | PENUH | SEGERA
                'tipeKegiatan' => 'Kampus Terpilih (By Invitation)',
                'lokasi' => 'Palembang (Head Office)',
                'penempatan' => 'Palembang & Banyuasin',
                'durasi' => '12 bulan program akselerasi',
                'ikatan' => 'Ikatan dinas 2 tahun',
                'kuota' => 10,
                'kuotaTerisi' => 3,
                'pelamar' => 214,
                'tanggalBuka' => '2026-07-01',
                'tanggalTutup' => '2026-08-31',
                'tanggalPengumuman' => '2026-09-20',
                'targetKampus' => ['ITB', 'UI', 'UGM', 'IPB', 'Unpad', 'ITS', 'Unsri'],
                'ringkasan' => 'Kegiatan pengembangan intensif 12 bulan untuk lulusan terbaik dari kampus mitra, disiapkan menjadi future leader di lini bisnis EVO Group.',
                'deskripsi' => 'EVO Development Program (EDP) adalah kegiatan Management Trainee unggulan yang dibuka secara khusus untuk kampus mitra terpilih. Peserta menjalani rotasi lintas divisi, mentoring langsung dari BOD, serta proyek nyata berdampak bisnis sebelum ditempatkan pada posisi manajerial.',
                'catatanKegiatan' => 'Kegiatan ini dibuka melalui jalur undangan ke kampus mitra. Pendaftaran umum akan diverifikasi terhadap daftar kampus terpilih.',
                'benefit' => [
                    'Gaji & tunjangan kompetitif sejak hari pertama',
                    'Rotasi lintas divisi & lintas entitas grup',
                    'Mentoring langsung dari jajaran Direksi',
                    'Fast-track ke posisi manajerial',
                    'Sertifikat program kepemimpinan',
                ],
                'kriteria' => [
                    'Fresh graduate S1/S2, maks. 2 tahun kelulusan.',
                    'IPK minimal 3.25 dari 4.00.',
                    'Usia maksimal 26 tahun.',
                    'Berasal dari kampus mitra terpilih.',
                    'Aktif berorganisasi & memiliki jiwa kepemimpinan.',
                    'Bersedia ditempatkan di seluruh area operasional grup.',
                ],
                'fasilitas' => ['Mess/akomodasi', 'Asuransi kesehatan', 'Laptop kerja', 'Coaching berkala'],
                'jadwal' => [
                    ['label' => 'Registrasi & Seleksi Administrasi', 'tanggal' => '1 Jul – 7 Sep 2026'],
                    ['label' => 'Tes Potensi Akademik & Psikotes', 'tanggal' => '12 Sep 2026'],
                    ['label' => 'Pengisian Biodata Lanjutan', 'tanggal' => '13 – 14 Sep 2026'],
                    ['label' => 'Tes Potensi Akademik & Psikotes 2', 'tanggal' => '15 Sep 2026'],
                    ['label' => 'Wawancara 1 (HR & Psikolog)', 'tanggal' => '17 Sep 2026'],
                    ['label' => 'Wawancara 2 (Direksi)', 'tanggal' => '19 Sep 2026'],
                    ['label' => 'Onboarding & Program Dimulai', 'tanggal' => '1 Okt 2026'],
                ],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Registrasi & Seleksi Administrasi'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Potensi Akademik & Psikotes'],
                    ['tipe' => 'FORM2', 'label' => 'Pengisian Biodata Lanjutan'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Potensi Akademik & Psikotes 2'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Wawancara 1'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Wawancara 2'],
                    ['tipe' => 'ONBOARDING', 'label' => 'Onboarding'],
                ],
            ],
            [
                'id' => 'MT-2026-STP',
                'nama' => 'Sales Trainee Program (STP) 2026',
                'tagline' => 'Jalur cepat menjadi Sales Leader profesional.',
                'jenis' => 'MT',
                'batch' => 'Batch 3',
                'perusahaan' => 'PT Evo Nusa Bersaudara',
                'status' => 'PENUH', // contoh: kuota sudah penuh
                'tipeKegiatan' => 'Umum / Terbuka',
                'lokasi' => 'Palembang (Head Office)',
                'penempatan' => 'Sumatera Selatan',
                'durasi' => '9 bulan program terstruktur',
                'ikatan' => 'Ikatan dinas 1 tahun',
                'kuota' => 8,
                'kuotaTerisi' => 8,
                'pelamar' => 138,
                'tanggalBuka' => '2026-06-01',
                'tanggalTutup' => '2026-07-31',
                'tanggalPengumuman' => '2026-08-20',
                'targetKampus' => ['Semua Universitas'],
                'ringkasan' => 'Kegiatan percepatan karir bagi lulusan yang bercita-cita membangun karir di dunia sales & distribusi FMCG pet food.',
                'deskripsi' => 'Sales Trainee Program membekali peserta dengan kemampuan sales, leadership, dan analisis pasar melalui kombinasi kelas, coaching lapangan, dan penugasan area nyata hingga siap memimpin tim penjualan.',
                'catatanKegiatan' => 'Kuota batch ini telah terpenuhi. Pantau terus untuk pembukaan batch berikutnya.',
                'benefit' => [
                    'Gaji pokok + insentif penjualan',
                    'Sertifikasi program sales profesional',
                    'Coaching lapangan intensif',
                    'Promosi ke Sales Supervisor setelah lulus program',
                ],
                'kriteria' => [
                    'Fresh graduate D3/S1 semua jurusan.',
                    'IPK minimal 3.00 dari 4.00.',
                    'Memiliki SIM C & bersedia mobilitas tinggi.',
                    'Berorientasi target & menyukai tantangan lapangan.',
                    'Bersedia ditempatkan di area Sumatera Selatan.',
                ],
                'fasilitas' => ['Uang transport lapangan', 'Asuransi kesehatan', 'Seragam kerja', 'Coaching mingguan'],
                'jadwal' => [
                    ['label' => 'Registrasi & Seleksi Administrasi', 'tanggal' => '1 Jun – 31 Jul 2026'],
                    ['label' => 'Tes Potensi Akademik & Psikotes', 'tanggal' => '5 Agu 2026'],
                    ['label' => 'Pengisian Biodata Lanjutan', 'tanggal' => '6 – 7 Agu 2026'],
                    ['label' => 'Tes Potensi Akademik & Psikotes 2', 'tanggal' => '8 Agu 2026'],
                    ['label' => 'Wawancara 1 (HR)', 'tanggal' => '10 Agu 2026'],
                    ['label' => 'Wawancara 2 (Sales Manager)', 'tanggal' => '12 Agu 2026'],
                    ['label' => 'Onboarding & Training Kelas', 'tanggal' => '1 Sep 2026'],
                ],
                'pipeline' => [
                    ['tipe' => 'FORM', 'label' => 'Registrasi & Seleksi Administrasi'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Potensi Akademik & Psikotes'],
                    ['tipe' => 'FORM2', 'label' => 'Pengisian Biodata Lanjutan'],
                    ['tipe' => 'HCLEARN_TEST', 'label' => 'Tes Potensi Akademik & Psikotes 2'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Wawancara 1'],
                    ['tipe' => 'INTERVIEW', 'label' => 'Wawancara 2'],
                    ['tipe' => 'ONBOARDING', 'label' => 'Onboarding'],
                ],
            ],
        ];
    }

    /** Pencapaian perusahaan — angka untuk section achievement (animated counter). */
    private function achievements(): array
    {
        return [
            ['icon' => 'bi-people-fill', 'value' => 1200, 'suffix' => '+', 'label' => 'Karyawan Aktif', 'desc' => 'Tersebar di seluruh entitas grup'],
            ['icon' => 'bi-calendar2-heart-fill', 'value' => 15, 'suffix' => '+', 'label' => 'Tahun Berkarya', 'desc' => 'Tumbuh sejak 2011'],
            ['icon' => 'bi-geo-alt-fill', 'value' => 2, 'suffix' => ' Lokasi', 'label' => 'Pusat Operasional', 'desc' => 'Palembang & Banyuasin'],
            ['icon' => 'bi-box-seam-fill', 'value' => 20, 'suffix' => '+', 'label' => 'Brand Produk', 'desc' => 'Life Cat, Ori Dog, dll.'],
            ['icon' => 'bi-buildings-fill', 'value' => 3, 'suffix' => ' Entitas', 'label' => 'Perusahaan Grup', 'desc' => 'ENB, EMI, GMN'],
        ];
    }

    /** Kantor & lokasi operasional — section "Lokasi Kami". Hanya 2. */
    private function offices(): array
    {
        return [
            [
                'kota' => 'Palembang',
                'nama' => 'EVO Group Head Office',
                'tipe' => 'Kantor Pusat',
                'alamat' => 'Jl. Kolonel H. Barlian, Palembang, Sumatera Selatan',
                'karyawan' => 460,
                'unggulan' => true,
                'tag' => ['HR', 'Finance', 'Marketing', 'Technology', 'Sales'],
            ],
            [
                'kota' => 'Banyuasin',
                'nama' => 'EVO Group Manufacturing Plant',
                'tipe' => 'Pabrik',
                'alamat' => 'Kawasan Industri, Banyuasin, Sumatera Selatan',
                'karyawan' => 740,
                'unggulan' => false,
                'tag' => ['Production', 'Quality Control', 'Maintenance'],
            ],
        ];
    }

    /** Alasan bergabung — highlight chip di hero. */
    private function benefits(): array
    {
        return [
            ['icon' => 'bi-graph-up-arrow', 'title' => 'Jenjang Karier Jelas'],
            ['icon' => 'bi-mortarboard', 'title' => 'Budaya Belajar'],
            ['icon' => 'bi-heart-pulse', 'title' => 'Benefit Kompetitif'],
            ['icon' => 'bi-people', 'title' => 'Tim Suportif'],
        ];
    }
}
