<?php

namespace App\Console\Commands;

use App\Support\Sinkron\Keluar;
use App\Support\Sinkron\OperasiSinkron;
use App\Support\Sinkron\PendorongKeluar;
use App\Support\Sinkron\PetaId;
use App\Support\Sinkron\Potret\PembangunPotret;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * MIGRASI AWAL DUA ZONA — data kandidat yang lahir SEBELUM situs kandidat
 * dipisah, dipindahkan sekali ke database publik supaya kandidat lama bisa
 * masuk di situs kandidat dan melihat lamarannya:
 *
 *   akun kandidat      (sandi ikut — hash bcrypt yang sama; akun staf tidak)
 *   lamaran            (kode sama di kedua zona)
 *   isian formulir     (pengisian, indeks jawaban, berkas formulir)
 *   berkas aktivitas   (Lamaran_Tes_Berkas)
 *   potret portal      (dibangun & didorong seperti biasa)
 *
 * Id dipertahankan bila masih kosong di sisi publik (IDENTITY_INSERT), jadi
 * tautan lama tetap berlaku; bentrok → id baru + peta. Lamaran yang KODE-nya
 * sudah ada di publik dilewati — perintah ini aman diulang (melanjutkan).
 *
 * BUTUH LOGIN DATABASE PUBLIK BERHAK ALTER (IDENTITY_INSERT) & tulis tabel
 * milik — bukan peran wc_publik_worker. Bawaannya hanya RENCANA; tulis
 * sungguhan dengan --jalankan. Menolak berjalan di luar staging kecuali
 * --produksi disebut.
 */
class SinkronMigrasiAwal extends Command
{
    protected $signature = 'sinkron:migrasi-awal
        {--jalankan : Tulis sungguhan (tanpa ini hanya rencana)}
        {--dokumen : Dorong juga salinan bersih berkas kandidat ke bucket publik}
        {--produksi : Izinkan berjalan di server selain staging}
        {--oleh= : Nama/email operator untuk jejak (bawaan: pengguna@mesin)}';

    protected $description = 'Pindahkan akun kandidat, lamaran, isian & berkas lama ke database publik (sekali, aman diulang)';

    private Connection $pub;

    /** @var array<string, list<string>> */
    private array $kolom = [];

    /** Rencana saja tidak dicatat; tulis sungguhan (--jalankan) tercatat di Sinkron_Operasi. */
    public function handle(PendorongKeluar $pendorong): int
    {
        if (! $this->option('jalankan')) {
            return $this->migrasi($pendorong, new \ArrayObject);
        }

        return OperasiSinkron::jalankan($this, fn (\ArrayObject $catatan) => $this->migrasi($pendorong, $catatan));
    }

    private function migrasi(PendorongKeluar $pendorong, \ArrayObject $catatan): int
    {
        $host = (string) config('database.connections.'.config('sinkron.koneksi_publik', 'pengguna').'.host');
        if (! str_contains($host, 'team311') && ! $this->option('produksi')) {
            $this->error("Database publik di {$host} bukan staging. Sebut --produksi bila memang disengaja.");

            return self::INVALID;
        }
        $this->pub = PendorongKeluar::publik();

        $kandidat = DB::table('N_WEB_CAREERS_Users')->where('Role', 'KANDIDAT')->orderBy('Id_Users')->get();
        $sudahPeta = DB::table(PetaId::TABEL)->where('Jenis_Entitas', PetaId::AKUN)->pluck('Kunci_Publik', 'Id_Admin');
        $emailPublik = $this->pub->table('N_WEB_CAREERS_Users')->pluck('Id_Users', 'Email')->mapWithKeys(fn ($v, $k) => [mb_strtolower($k) => (int) $v]);
        $lamaran = DB::table('N_WEB_CAREERS_Lamaran')->orderBy('Id_Lamaran')->get(['Id_Lamaran', 'Kode', 'Id_Users']);
        $kodePublik = $this->pub->table('N_WEB_CAREERS_Lamaran')->pluck('Kode')->flip();

        $baru = $kandidat->filter(fn ($u) => ! isset($sudahPeta[$u->Id_Users]) && ! isset($emailPublik[mb_strtolower($u->Email)]));
        $sambung = $kandidat->filter(fn ($u) => ! isset($sudahPeta[$u->Id_Users]) && isset($emailPublik[mb_strtolower($u->Email)]));
        $lamaranBaru = $lamaran->filter(fn ($l) => ! isset($kodePublik[$l->Kode]));

        $this->table(['Bagian', 'Jumlah'], [
            ['Akun kandidat di admin', $kandidat->count()],
            ['  sudah berpeta', $kandidat->count() - $baru->count() - $sambung->count()],
            ['  disalin baru ke publik', $baru->count()],
            ['  disambungkan (email sudah ada di publik)', $sambung->count()],
            ['Lamaran di admin', $lamaran->count()],
            ['  belum ada di publik (dipindahkan)', $lamaranBaru->count()],
        ]);

        if (! $this->option('jalankan')) {
            $this->warn('RENCANA saja — tidak ada yang ditulis. Ulangi dengan --jalankan.');

            return self::SUCCESS;
        }

        // ── 1. Akun ─────────────────────────────────────────────────────
        foreach ($sambung as $u) {
            PetaId::pasang(PetaId::AKUN, $emailPublik[mb_strtolower($u->Email)], (int) $u->Id_Users);
        }
        $bar = $this->output->createProgressBar($baru->count());
        foreach ($baru as $u) {
            $idPub = $this->pub->transaction(fn () => $this->sisip('N_WEB_CAREERS_Users', (array) $u, [
                // Kode reset / verifikasi lama tidak ikut — sekali pakai & sudah basi.
                'Reset_Otp_Hash' => null, 'Reset_Otp_Expired_At' => null, 'Email_Verif_Token' => null, 'Email_Verif_Expired_At' => null,
            ], 'Id_Users'));
            PetaId::pasang(PetaId::AKUN, $idPub, (int) $u->Id_Users);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info('Akun selesai.');

        // ── 2. Lamaran + isian + berkas ─────────────────────────────────
        $bar = $this->output->createProgressBar($lamaranBaru->count());
        $galat = 0;
        $dipindah = [];
        foreach ($lamaranBaru as $l) {
            try {
                $this->pindahLamaran((int) $l->Id_Lamaran);
                $dipindah[] = (int) $l->Id_Lamaran;
            } catch (\Throwable $e) {
                $galat++;
                $this->newLine();
                $this->error("Lamaran {$l->Kode}: ".$e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info('Lamaran dipindahkan: '.count($dipindah).", galat {$galat}.");
        $catatan['jumlah'] = count($dipindah);
        $catatan['akun_baru'] = $baru->count();
        $catatan['akun_disambung'] = $sambung->count();
        $catatan['lamaran'] = OperasiSinkron::daftar($dipindah);
        $catatan['galat'] = $galat;

        // ── 3. Potret ───────────────────────────────────────────────────
        $bar = $this->output->createProgressBar(count($dipindah));
        foreach ($dipindah as $id) {
            try {
                PembangunPotret::segarkan($id);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Potret lamaran #{$id}: ".$e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->line('dorong: '.json_encode($pendorong->dorongHabis(1800)));

        return $galat ? self::FAILURE : self::SUCCESS;
    }

    private function pindahLamaran(int $lamaranId): void
    {
        $l = (array) DB::table('N_WEB_CAREERS_Lamaran')->where('Id_Lamaran', $lamaranId)->first();
        $idUser = PetaId::publik(PetaId::AKUN, (int) $l['Id_Users']);
        if ($idUser === null) {
            throw new \RuntimeException('akunnya belum berpasangan di publik.');
        }
        $idUser = (int) $idUser;
        $peta = [];

        $this->pub->transaction(function () use ($l, $idUser, $lamaranId, &$peta) {
            $idLam = $this->sisip('N_WEB_CAREERS_Lamaran', $l, ['Id_Users' => $idUser, 'Sinkron_Versi' => 0], 'Id_Lamaran');

            foreach (DB::table('N_WEB_CAREERS_Formulir_Pengisian')->where('Lamaran_Id', $lamaranId)->orderBy('Id_Formulir_Pengisian')->get() as $p) {
                $idPng = $this->sisip('N_WEB_CAREERS_Formulir_Pengisian', (array) $p, ['Lamaran_Id' => $idLam, 'Id_Users' => $idUser], 'Id_Formulir_Pengisian');
                $peta[] = [PetaId::FORMULIR, (string) $p->Kode, (int) $p->Id_Formulir_Pengisian];

                foreach (DB::table('N_WEB_CAREERS_Formulir_Jawaban_Index')->where('Formulir_Pengisian_Id', $p->Id_Formulir_Pengisian)->get() as $j) {
                    $this->sisip('N_WEB_CAREERS_Formulir_Jawaban_Index', (array) $j, ['Formulir_Pengisian_Id' => $idPng, 'Lamaran_Id' => $idLam, 'Id_Users' => $idUser], 'Id_Formulir_Jawaban_Index');
                }
                foreach (DB::table('N_WEB_CAREERS_Formulir_Berkas')->where('Formulir_Pengisian_Id', $p->Id_Formulir_Pengisian)->orderBy('Id_Formulir_Berkas')->get() as $b) {
                    $idB = $this->sisip('N_WEB_CAREERS_Formulir_Berkas', (array) $b, ['Formulir_Pengisian_Id' => $idPng, 'Id_Users' => $idUser], 'Id_Formulir_Berkas');
                    if ($this->option('dokumen') && $b->Path_File) {
                        $this->dokumen('formulir-berkas:'.$idB, (string) $l['Kode'], (string) $b->Nama_Asli, (string) $b->Path_File, $b->Mime, $b->Ukuran_Byte);
                    }
                }
            }

            foreach (DB::table('N_WEB_CAREERS_Lamaran_Tes_Berkas')->where('Lamaran_Id', $lamaranId)->orderBy('Id_Lamaran_Tes_Berkas')->get() as $t) {
                $idT = $this->sisip('N_WEB_CAREERS_Lamaran_Tes_Berkas', (array) $t, ['Lamaran_Id' => $idLam, 'Id_Users' => $idUser], 'Id_Lamaran_Tes_Berkas');
                $peta[] = [PetaId::BERKAS, 'tes:'.$idT, (int) $t->Id_Lamaran_Tes_Berkas];
                if ($this->option('dokumen') && $t->Path_File) {
                    $this->dokumen('tes-berkas:'.$idT, (string) $l['Kode'], (string) $t->Nama_File, (string) $t->Path_File, $t->Mime, $t->Ukuran);
                }
            }
        });

        foreach ($peta as [$jenis, $kunci, $idAdmin]) {
            PetaId::pasang($jenis, $kunci, $idAdmin);
        }
    }

    /**
     * Sisipkan satu baris admin ke tabel publik bernama sama — kolom yang ada
     * di kedua sisi saja. Id admin dipakai bila masih kosong di publik.
     *
     * @return int id baris di publik
     */
    private function sisip(string $tabel, array $baris, array $timpa, string $kolomId): int
    {
        $kolom = $this->kolom[$tabel] ??= collect($this->pub->select('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)', ['dbo.'.$tabel]))->pluck('name')->all();
        $nilai = array_intersect_key(array_merge($baris, $timpa), array_flip($kolom));
        unset($nilai['Sinkron_At']);

        $id = $baris[$kolomId] ?? null;
        if ($id !== null && ! $this->pub->table($tabel)->where($kolomId, $id)->exists()) {
            $this->pub->unprepared("SET IDENTITY_INSERT dbo.[{$tabel}] ON");
            try {
                $this->pub->table($tabel)->insert($nilai);
            } finally {
                $this->pub->unprepared("SET IDENTITY_INSERT dbo.[{$tabel}] OFF");
            }

            return (int) $id;
        }

        unset($nilai[$kolomId]);

        return (int) $this->pub->table($tabel)->insertGetId($nilai, $kolomId);
    }

    private function dokumen(string $kunci, string $kode, string $nama, string $path, $mime, $ukuran): void
    {
        Keluar::antrekan(Keluar::DOKUMEN, $kunci, [
            'kunci' => $kunci,
            'kode_lamaran' => $kode,
            'jenis' => 'BERKAS_KANDIDAT',
            'nama' => $nama,
            'path' => $path,
            'mime' => $mime,
            'ukuran' => $ukuran !== null ? (int) $ukuran : null,
            'sha256' => null,
            'aktif' => true,
        ]);
    }
}
