<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Karyawan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Helpers\Whatsapp;
use Carbon\Carbon;

class Users extends Seeder
{
    public function run()
    {
        Log::channel('CreateUserLog')->error("[" . Carbon::now() . "] Proses pemberian user dan akses dimulai.........");

        $karyawanData = Karyawan::whereNull('Tanggal_Resign')->whereIn("Kode_Karyawan", [
            'AGUNG WIJAYA',
'TRISNA JORDI',
'BAYU KURNIAWAN',
'RAMADAN',
'BAYU SAPUTRA',
'RAKHA',
'SINGGIH',
'HASANI HIDAYAH',
'HELDA',
'PANDA SAPUTRA',
'RIZKI WAHYUDI',
'ELZI',
'MICHAEL SAMUEL',
'JOLIANSYA',
'RICKY FIRDIANTO',
'ANDRE ADITIA',
'ANGGA SAPUTRA',
'MUHAMMADARIFIN',
'AGUNG WIDODO',
'VIRLI',
'FEBRIAN SAPUTRA',
'APRIANSYAH',
'EDI IRAWAN',
'FENI',
'KEMAS',
'TEDI HARDIYANTO',
'YANTO',
'WILLY HASTA',
'RYAN',
'ILHAM N '


        ])->get();
        // dd($karyawanData);
        $saltFront = env('SALT_FRONT');
        $saltBack = env('SALT_BACK');
        $passwordList = [];

        // Ambil daftar nama dari special access dan cari kode_karyawan-nya
        // $specialAccess = [
        //     'MOH.ARDIANSYAH','GALIH', 'AGUS WAHYU', 'AKONG', 'ALFAN', 'ALFIAN EKO', 'CHAIRUDDIN', 'DIDIK', 'DIMAS', 'DRH.NINA',
        //     'ENDRIEF', 'ERIK ESTRADA', 'HAERUDIN', 'HENDRIYANTO A', 'HERI SUDRAJAT', 'IVAN', 'JEMMY YONRI',
        //     'KHAERUL AZMI', 'LISKA', 'LIUS', 'M.REZA', 'MANSYUR M', 'MARDHAN', 'MARIO DESWAN', 'MARLISA',
        //     'MIEKI', 'PANDU', 'PARMANTO', 'PRIMA', 'PURWA', 'RANI', 'RIDWANSYAH', 'ROBY R', 'RUDI FIRMANSYAH',
        //     'SETYO NUGROHO', 'TANHAR MAHARSI', 'TEGUH', 'VERA ANISAH', 'WAHYU JATI', 'WILSON', 'YUSTINA'
        // ];

        // $specialAccess = Karyawan::whereIn(DB::raw('UPPER(Nama)'), $specialNames)
        //     ->pluck('Kode_Karyawan')
        //     ->toArray();

        foreach ($karyawanData as $karyawan) {
            $username = $this->generateUsername($karyawan->Kode_Karyawan);

            $divisionId = $karyawan->division ? $karyawan->division->ID_Divisi : null;
            $levelId = $karyawan->level ? $karyawan->level->ID_Level : null;

            if (!$divisionId || !$levelId) {
                $this->command->warn("Karyawan {$karyawan->Kode_Karyawan} tidak memiliki Division atau Level.");
                continue;
            }

            $existingUser = DB::table('KPI_Users')
                ->where('Kode_Users', $karyawan->Kode_Karyawan)
                ->first();

            $userId = null;

            DB::beginTransaction();
            try {
                if (!$existingUser) {
                    $randomNumber = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
                    $rawPassword = $username . '_' . $randomNumber;
                    $encryptedPassword = Hash::make($saltFront . $rawPassword . $saltBack);

                    $userId = DB::table('KPI_Users')->insertGetId([
                        'Username' => $username,
                        'Password' => $encryptedPassword,
                        'Email' => $karyawan->Email,
                        'Role' => 'user',
                        'Flag_Active' => 'Y',
                        'Kode_Users' => $karyawan->Kode_Karyawan,
                        'Address' => $karyawan->Alamat,
                        'Division_Id' => $divisionId,
                        'Level_Id' => $levelId,
                        'Nama' => $karyawan->Nama,
                        'No_Hp' => $karyawan->HP,
                    ]);

                    $karyawan->UserID_Web = $userId;
                    $karyawan->save();

                    $pesan = [
                        "messaging_product" => "whatsapp",
                        "to" => $karyawan->HP,
                        "type" => "template",
                        "template" => [
                            "name" => "notif_web_hc_new",
                            "language" => ["code" => "en", "policy" => "deterministic"],
                            "components" => [[
                                "type" => "body",
                                "parameters" => [
                                    ["type" => "text", "text" => $username],
                                    ["type" => "text", "text" => $karyawan->Nama],
                                    ["type" => "text", "text" => $rawPassword],
                                ]
                            ]]
                        ]
                    ];
                    $response = Whatsapp::send_message($pesan);
                    Log::channel('WaCreateUserLog')->warning('WA Response', ['pesan' => $response]);

                    $passwordList[] = [
                        'Nama' => $karyawan->Nama,
                        'Username' => $username,
                        'Raw_Password' => $rawPassword,
                    ];

                    $this->command->info("User {$karyawan->Kode_Karyawan} dibuat.");
                    Log::channel('CreateUserLog')->info("[" . Carbon::now() . "] User {$karyawan->Kode_Karyawan} ({$username}) dibuat.");
                } else {
                    $userId = $existingUser->Id_Users;
                    $this->command->info("User {$karyawan->Kode_Karyawan} sudah ada.");
                    Log::channel('CreateUserLog')->info("[" . Carbon::now() . "] User {$karyawan->Kode_Karyawan} sudah ada.");
                }

                $this->giveAccess($userId, $divisionId, $levelId, 'IzinPage', $karyawan->Kode_Karyawan);

                // if (in_array($karyawan->Kode_Karyawan, $specialAccess)) {
                //     $this->giveAccess($userId, $divisionId, $levelId, 'IzinPageApprover', $karyawan->Kode_Karyawan);
                // }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                $this->command->error("Error pada {$karyawan->Kode_Karyawan}: " . $e->getMessage());
                Log::channel('CreateUserLog')->error("[" . Carbon::now() . "] ERROR pada {$karyawan->Kode_Karyawan}: " . $e->getMessage());
            }
        }

        $this->exportPasswordList($passwordList);
        $this->command->info('Proses selesai.');
        Log::channel('CreateUserLog')->error("[" . Carbon::now() . "] Proses selesai");
    }

    protected function clean($string)
    {
        return strtolower(preg_replace('/[^a-zA-Z]/', '', $string));
    }

    protected function generateUsername($kodeKaryawan)
    {
        $parts = preg_split('/\s+/', strtoupper($kodeKaryawan));
        $username = $this->clean($parts[0] ?? '');
        if (isset($parts[1])) {
            $username .= $this->clean($parts[1]);
        }

        $original = $username;
        while (DB::table('KPI_Users')->where('Username', $username)->exists()) {
            $username = substr($username, 0, -1);
            if (strlen($username) < 3) break;
        }

        return $username;
    }

    protected function giveAccess($userId, $divisionId, $levelId, $jenisPage, $kodeKaryawan)
    {
        $exists = DB::table('HRIS_Page_Access')
            ->where('UserID_Web', $userId)
            ->where('Flag_Active', 'Y')
            ->where('Jenis_Page', $jenisPage)
            ->exists();

        if (!$exists) {
            DB::table('HRIS_Page_Access')->insert([
                'Kode_Perusahaan' => '001',
                'ID_Level' => $levelId,
                'ID_Divisi' => $divisionId,
                'Jenis_Page' => $jenisPage,
                'UserID_Web' => $userId,
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Updated_At' => now()
            ]);
            $this->command->info("Akses {$jenisPage} diberikan ke {$kodeKaryawan}");
            Log::channel('CreateUserLog')->info("[" . Carbon::now() . "] Akses {$jenisPage} diberikan ke {$kodeKaryawan}.");
        }
    }

    protected function exportPasswordList($list)
    {
        if (empty($list)) return;

        $fileName = 'user_passwords_' . now()->format('Ymd_His') . '.csv';
        $headers = array_keys($list[0]);
        $csvContent = implode(',', $headers) . "\n";

        foreach ($list as $row) {
            $csvContent .= implode(',', array_map(fn($val) => '"' . str_replace('"', '""', $val) . '"', $row)) . "\n";
        }

        Storage::disk('local')->put($fileName, $csvContent);
    }
}
