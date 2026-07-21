<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KonversiToCutiApprovalConfigSeeder extends Seeder
{
    public function run(): void
    {
        $tableConfig = 'N_HRIS_Approval_Config';
        $tableDetail = 'N_HRIS_Approval_Config_Detail';
        $sourceCode = 'DAY_CONVERSION';
        $targetCode = 'KONVERSI_TO_CUTI';

        $sourceConfig = DB::table($tableConfig)->where('Kode_Config', $sourceCode)->first();
        if (!$sourceConfig) {
            $this->command?->warn("Source approval config {$sourceCode} tidak ditemukan.");
            return;
        }

        $existingTarget = DB::table($tableConfig)->where('Kode_Config', $targetCode)->first();
        if ($existingTarget) {
            $this->command?->info("Approval config {$targetCode} sudah ada.");
            return;
        }

        $configColumns = collect(Schema::getColumnListing($tableConfig));
        $detailColumns = collect(Schema::getColumnListing($tableDetail));

        $configPayload = collect(get_object_vars($sourceConfig))
            ->except(['Id'])
            ->filter(fn($value, $key) => $configColumns->contains($key))
            ->toArray();

        $configPayload['Kode_Config'] = $targetCode;
        if ($configColumns->contains('Nama_Config')) {
            $configPayload['Nama_Config'] = 'Konversi Lembur ke Bayar Hutang Cuti';
        }
        if ($configColumns->contains('Deskripsi')) {
            $configPayload['Deskripsi'] = 'Approval flow untuk konversi lembur ke pembayaran hutang cuti.';
        }
        if ($configColumns->contains('updated_at')) {
            $configPayload['updated_at'] = now();
        }
        if ($configColumns->contains('Updated_At')) {
            $configPayload['Updated_At'] = now();
        }
        if ($configColumns->contains('created_at') && empty($configPayload['created_at'])) {
            $configPayload['created_at'] = now();
        }
        if ($configColumns->contains('Created_At') && empty($configPayload['Created_At'])) {
            $configPayload['Created_At'] = now();
        }

        $targetConfigId = DB::table($tableConfig)->insertGetId($configPayload);

        $sourceDetails = DB::table($tableDetail)
            ->where('Id_Approval_Config', $sourceConfig->Id)
            ->orderBy('Order_Approval')
            ->get();

        foreach ($sourceDetails as $detail) {
            $detailPayload = collect(get_object_vars($detail))
                ->except(['Id'])
                ->filter(fn($value, $key) => $detailColumns->contains($key))
                ->toArray();

            $detailPayload['Id_Approval_Config'] = $targetConfigId;
            if ($detailColumns->contains('updated_at')) {
                $detailPayload['updated_at'] = now();
            }
            if ($detailColumns->contains('Updated_At')) {
                $detailPayload['Updated_At'] = now();
            }
            if ($detailColumns->contains('created_at') && empty($detailPayload['created_at'])) {
                $detailPayload['created_at'] = now();
            }
            if ($detailColumns->contains('Created_At') && empty($detailPayload['Created_At'])) {
                $detailPayload['Created_At'] = now();
            }

            DB::table($tableDetail)->insert($detailPayload);
        }

        $this->command?->info("Approval config {$targetCode} berhasil dibuat dari {$sourceCode}.");
    }
}
