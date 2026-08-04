<?php

namespace App\Support\Career;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FormulirSchema
{
    public static function punyaTabelVersi(): bool
    {
        try {
            return Schema::hasTable('N_WEB_CAREERS_Master_Formulir_Versi');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function punyaKolomPengisianSnapshot(): bool
    {
        try {
            return Schema::hasColumn('N_WEB_CAREERS_Formulir_Pengisian', 'Schema_Snapshot_Json');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function punyaKolomDrafSnapshot(): bool
    {
        try {
            return Schema::hasColumn('N_WEB_CAREERS_Formulir_Draf', 'Schema_Snapshot_Json');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function publishedByKode(?string $kode): ?array
    {
        if (! $kode) {
            return null;
        }

        $form = DB::table('N_WEB_CAREERS_Master_Formulir')
            ->where('Kode', $kode)
            ->where('Flag_Aktif', 'Y')
            ->first();

        return $form ? self::payload($form, self::versiPublished((int) $form->Id_Master_Formulir)) : null;
    }

    /**
     * Ambil schema versi TERTENTU (dibekukan saat tahap dibuat), bukan yang
     * sedang published sekarang. Bila $versi null (tahap lama, dibuat sebelum
     * mekanisme pembekuan versi ada), jatuh ke publishedByKode seperti biasa.
     */
    public static function byKodeDanVersi(?string $kode, ?int $versi): ?array
    {
        if (! $kode) {
            return null;
        }

        if ($versi === null) {
            return self::publishedByKode($kode);
        }

        $form = DB::table('N_WEB_CAREERS_Master_Formulir')
            ->where('Kode', $kode)
            ->first();

        if (! $form) {
            return null;
        }

        $versiRow = self::punyaTabelVersi()
            ? DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
                ->where('Master_Formulir_Id', $form->Id_Master_Formulir)
                ->where('Versi', $versi)
                ->first()
            : null;

        return self::payload($form, $versiRow ?? self::versiPublished((int) $form->Id_Master_Formulir));
    }

    public static function pendaftaranUntukKategori(string $kategori): ?array
    {
        $komponen = match (strtoupper($kategori)) {
            'MT' => 'FORMULIR_1',
            'INTERNSHIP', 'MAGANG' => 'FORMULIR_4',
            default => 'FORMULIR_3',
        };

        $form = DB::table('N_WEB_CAREERS_Master_Formulir')
            ->where('Komponen_Kode', $komponen)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_Master_Formulir')
            ->first();

        return $form ? self::payload($form, self::versiPublished((int) $form->Id_Master_Formulir)) : null;
    }

    public static function payload(?object $form, ?object $versi): array
    {
        $schema = $versi ? (json_decode($versi->Schema_Json ?: '{}', true) ?: null) : null;
        $fallback = $schema && ($schema['layout'] ?? null) === 'REGISTRY_FALLBACK';

        return [
            'id' => $form?->Id_Master_Formulir ? \Vinkla\Hashids\Facades\Hashids::encode($form->Id_Master_Formulir) : null,
            'kode' => $form->Kode ?? null,
            'nama' => $form->Nama ?? null,
            'komponen' => $form->Komponen_Kode ?? ($schema['fallback_komponen'] ?? null),
            'schema' => $fallback ? null : $schema,
            'versiId' => $versi?->Id_Master_Formulir_Versi ? \Vinkla\Hashids\Facades\Hashids::encode($versi->Id_Master_Formulir_Versi) : null,
            'versiRealId' => $versi?->Id_Master_Formulir_Versi ?? null,
            'versi' => $versi ? (int) $versi->Versi : null,
            'schemaSnapshot' => $fallback ? null : $schema,
        ];
    }

    public static function kolomSnapshotInsert(?array $payload): array
    {
        if (! $payload || ! self::punyaKolomPengisianSnapshot()) {
            return [];
        }

        return [
            'Master_Formulir_Versi_Id' => $payload['versiRealId'] ?? null,
            'Formulir_Versi' => $payload['versi'] ?? null,
            'Schema_Snapshot_Json' => ! empty($payload['schemaSnapshot'])
                ? json_encode($payload['schemaSnapshot'], JSON_UNESCAPED_UNICODE)
                : null,
        ];
    }

    private static function versiPublished(int $formulirId): ?object
    {
        if (! self::punyaTabelVersi()) {
            return null;
        }

        return DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
            ->where('Master_Formulir_Id', $formulirId)
            ->where('Status', 'PUBLISHED')
            ->orderByDesc('Versi')
            ->first();
    }
}
