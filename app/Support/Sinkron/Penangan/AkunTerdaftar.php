<?php

namespace App\Support\Sinkron\Penangan;

use App\Support\Career\RegistrasiKandidatCat;
use App\Support\Sinkron\PeristiwaDitolak;
use App\Support\Sinkron\PetaId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akun.Terdaftar — kandidat mendaftar di situs kandidat.
 *
 * Zona dalam membuat SALINAN akunnya (tanpa sandi yang bisa dipakai: login
 * kandidat hanya di situs kandidat) supaya lamaran, jadwal, dan surel tetap
 * memakai tabel akun yang sudah ada. Akun kandidat lama dengan email yang
 * sama disambungkan, bukan digandakan. Lalu kandidat didaftarkan ke HCLearn
 * (Kode_Calon) sesudah commit.
 */
final class AkunTerdaftar implements Penangan
{
    public function tangani(array $muatan, object $baris): HasilPenanganan
    {
        $a = (array) ($muatan['akun'] ?? []);
        $idPublik = (int) ($a['id_publik'] ?? 0);
        $email = trim((string) ($a['email'] ?? ''));
        if ($idPublik < 1 || $email === '') {
            throw new PeristiwaDitolak('Data akun tidak lengkap.');
        }

        $id = PetaId::admin(PetaId::AKUN, $idPublik);
        if (! $id) {
            $ada = DB::table('N_WEB_CAREERS_Users')->where('Email', $email)->first(['Id_Users', 'Role']);
            if ($ada && $ada->Role !== 'KANDIDAT') {
                throw new PeristiwaDitolak('Email ini sudah dipakai akun tim rekrutmen. Hubungi tim rekrutmen.');
            }
            $id = $ada ? (int) $ada->Id_Users : null;
        }

        if ($id) {
            self::perbarui($id, $a, $email);
        } else {
            $id = (int) DB::table('N_WEB_CAREERS_Users')->insertGetId(self::profil($a) + [
                'Email' => $email,
                // Tidak ada sandi yang berlaku di zona dalam.
                'Password' => Hash::make(Str::random(48)),
                'Role' => 'KANDIDAT',
                'Email_Verified_At' => ! empty($a['email_terverifikasi']) ? now() : null,
                'Created_At' => now(),
                'Created_By' => $email,
                'Updated_At' => now(),
                'Updated_By' => $email,
            ], 'Id_Users');
        }

        PetaId::pasang(PetaId::AKUN, $idPublik, $id);

        $hasil = HasilPenanganan::ok('akun:'.$id);
        if (! DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $id)->value('Kode_Calon')) {
            $hasil->lalu(fn () => RegistrasiKandidatCat::daftarkan(
                $id,
                (string) ($a['nama'] ?? ''),
                $email,
                $a['no_hp'] ?? null,
                (string) ($a['nik'] ?? ''),
            ));
        }

        return $hasil;
    }

    /** Perbarui salinan akun dari muatan — dipakai juga Akun.Diperbarui. */
    public static function perbarui(int $id, array $a, ?string $oleh = null): void
    {
        $nilai = self::profil($a) + ['Updated_At' => now(), 'Updated_By' => $oleh];

        if (array_key_exists('email_terverifikasi', $a)) {
            // Waktu verifikasi pertama dipertahankan.
            $nilai['Email_Verified_At'] = ! empty($a['email_terverifikasi'])
                ? DB::raw('ISNULL([Email_Verified_At], GETDATE())')
                : null;
        }

        $email = trim((string) ($a['email'] ?? ''));
        if ($email !== '') {
            $lain = DB::table('N_WEB_CAREERS_Users')->where('Email', $email)->where('Id_Users', '<>', $id)->exists();
            if ($lain) {
                throw new PeristiwaDitolak('Email baru sudah dipakai akun lain di zona dalam.');
            }
            $nilai['Email'] = $email;
        }

        DB::table('N_WEB_CAREERS_Users')->where('Id_Users', $id)->update($nilai);
    }

    /** Kolom profil yang dibawa muatan akun (yang tidak dibawa, tidak disentuh). */
    private static function profil(array $a): array
    {
        $p = [];
        foreach ([
            'nama' => ['Nama', 150], 'no_hp' => ['No_Hp', 30], 'nik' => ['NIK', 16],
            'klasifikasi' => ['Klasifikasi', 20], 'status' => ['Status', 15], 'mulai_berlaku' => ['Mulai_Berlaku', 10],
        ] as $k => [$kolom, $maks]) {
            if (isset($a[$k]) && $a[$k] !== '') {
                $p[$kolom] = mb_substr((string) $a[$k], 0, $maks);
            }
        }
        if (array_key_exists('valid_until', $a)) {
            $p['Valid_Until'] = $a['valid_until'] ?: null;
        }
        if (array_key_exists('email_terverifikasi', $a)) {
            $p['Flag_Email_Verified'] = ! empty($a['email_terverifikasi']) ? 'Y' : 'T';
        }

        return $p;
    }
}
