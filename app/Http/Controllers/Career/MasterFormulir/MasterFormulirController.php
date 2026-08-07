<?php

namespace App\Http\Controllers\Career\MasterFormulir;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Support\Career\KatalogField;
use App\Support\Career\KatalogPrefill;
use App\Support\Career\KodeUnik;
use App\Support\CareerShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * WEB CAREER - MASTER FORMULIR.
 *
 * Master formulir adalah katalog template reusable.
 * Pengikatan ke program/tahapan seleksi dilakukan di modul Master Tahapan Seleksi,
 * bukan saat membuat atau mengedit master formulir.
 *
 * Komponen_Kode tetap dibaca untuk kompatibilitas data legacy, tetapi tidak lagi
 * menjadi pilihan atau payload konfigurasi master baru.
 */
class MasterFormulirController extends Controller
{
    public function index()
    {
        return Inertia::render(
            'Career/admin/master-formulir/masterFormulir',
            CareerShell::props('/master-formulir', 'Master Formulir', [
                // Kosakata isi-otomatis dikirim dari server supaya daftar yang
                // dilihat admin dan data yang benar-benar sampai ke formulir
                // berasal dari satu tempat.
                'katalogPrefill' => KatalogPrefill::untukEditor(),
            ])
        );
    }

    public function list()
    {
        try {
            $rows = DB::table('N_WEB_CAREERS_Master_Formulir as f')
                ->leftJoin('N_WEB_CAREERS_Users as u', 'u.Id_Users', '=', 'f.Created_By_Id')
                ->orderBy('f.Id_Master_Formulir')
                ->select('f.*', 'u.Nama as Pembuat')
                ->get();

            $versi = $this->versiPerFormulir($rows->pluck('Id_Master_Formulir')->all());

            $out = $rows->map(fn ($f) => [
                'id' => Hashids::encode($f->Id_Master_Formulir),
                'kode' => $f->Kode,
                'nama' => $f->Nama,
                'kategori' => $f->Kategori,
                'jenis' => $f->Kategori,
                'deskripsi' => $f->Deskripsi,
                'komponen' => $f->Komponen_Kode ?? null,
                'petunjuk' => $f->Petunjuk ?? null,
                'status' => $f->Flag_Aktif === 'Y' ? 'AKTIF' : 'NONAKTIF',
                'published' => $versi[$f->Id_Master_Formulir]['published'] ?? null,
                'draft' => $versi[$f->Id_Master_Formulir]['draft'] ?? null,
                'versiTerbit' => $versi[$f->Id_Master_Formulir]['published']['versi'] ?? null,
                'createdBy' => $f->Pembuat ?: $f->Created_By,
                'createdAt' => $f->Created_At,
            ])->values();

            return ResponseHelper::success($out, 'Data formulir dimuat');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal memuat formulir: ' . $e->getMessage());

            return ResponseHelper::error('Gagal memuat data formulir', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate($this->rules());
            $guard = $this->guardSchema($data);
            if ($guard) {
                return $guard;
            }

            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');
            $now = now();
            $kode = $this->kodeUnik($data['nama']);

            $id = DB::table('N_WEB_CAREERS_Master_Formulir')->insertGetId([
                'Kode' => $kode,
                'Nama' => $data['nama'],
                'Kategori' => $data['kategori'] ?? null,
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Komponen_Kode' => null,
                'Petunjuk' => $data['petunjuk'] ?? null,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ], 'Id_Master_Formulir');

            if (! empty($data['schema'])) {
                $valid = $this->validasiSchema($data['schema']);
                if (! $valid['ok']) {
                    return ResponseHelper::error($valid['pesan'], 422);
                }
                $this->simpanVersi((int) $id, $valid['schema'], 'DRAFT', $data['catatan'] ?? 'Draft awal');
            }

            Log::channel('web_career')->info("Master formulir dibuat ({$kode}) oleh {$userName}");

            return ResponseHelper::success(['id' => Hashids::encode($id)], 'Formulir berhasil didaftarkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error('Gagal membuat formulir: ' . $e->getMessage());

            return ResponseHelper::error('Gagal menyimpan data', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate($this->rules());
            $guard = $this->guardSchema($data);
            if ($guard) {
                return $guard;
            }

            DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->update([
                'Nama' => $data['nama'],
                'Kategori' => $data['kategori'] ?? null,
                'Deskripsi' => $data['deskripsi'] ?? null,
                'Petunjuk' => $data['petunjuk'] ?? null,
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);

            if (! empty($data['schema'])) {
                $valid = $this->validasiSchema($data['schema']);
                if (! $valid['ok']) {
                    return ResponseHelper::error($valid['pesan'], 422);
                }
                $guardKey = $this->guardKeyPublished((int) $realId, $valid['schema']);
                if ($guardKey) {
                    return ResponseHelper::error($guardKey, 422);
                }
                $this->simpanVersi((int) $realId, $valid['schema'], 'DRAFT', $data['catatan'] ?? 'Draft diperbarui');
            }

            return ResponseHelper::success(null, 'Formulir diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal update formulir #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal memperbarui data', 500);
        }
    }

    public function publish(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            if (! DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->exists()) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }
            if (! $this->punyaTabelVersi()) {
                return ResponseHelper::error('Tabel versi formulir belum tersedia. Jalankan DDL master formulir dinamis dulu.', 422);
            }

            $data = $request->validate([
                'schema' => 'required|array',
                'catatan' => 'nullable|string',
            ]);
            $valid = $this->validasiSchema($data['schema']);
            if (! $valid['ok']) {
                return ResponseHelper::error($valid['pesan'], 422);
            }
            $guardKey = $this->guardKeyPublished((int) $realId, $valid['schema']);
            if ($guardKey) {
                return ResponseHelper::error($guardKey, 422);
            }

            DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
                ->where('Master_Formulir_Id', $realId)
                ->where('Status', 'PUBLISHED')
                ->update([
                    'Status' => 'ARCHIVED',
                    'Updated_At' => now(),
                    'Updated_By' => session('career_auth.nama', 'ADMIN'),
                    'Updated_By_Id' => session('career_auth.id'),
                ]);

            $versiId = $this->simpanVersi((int) $realId, $valid['schema'], 'PUBLISHED', $data['catatan'] ?? 'Dipublish dari builder');

            return ResponseHelper::success(['versiId' => Hashids::encode($versiId)], 'Formulir dipublish');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseHelper::error(collect($e->errors())->flatten()->first() ?? 'Data tidak valid', 422);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal publish formulir #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mempublish formulir', 500);
        }
    }

    public function duplicate(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $data = $request->validate(['nama' => 'nullable|string|max:120']);
            $nama = $data['nama'] ?? ($row->Nama . ' - Salinan');
            $now = now();
            $userId = session('career_auth.id');
            $userName = session('career_auth.nama', 'ADMIN');

            $baruId = DB::table('N_WEB_CAREERS_Master_Formulir')->insertGetId([
                'Kode' => $this->kodeUnik($nama),
                'Nama' => $nama,
                'Kategori' => $row->Kategori,
                'Deskripsi' => $row->Deskripsi,
                'Komponen_Kode' => $row->Komponen_Kode,
                'Petunjuk' => $row->Petunjuk,
                'Flag_Aktif' => 'Y',
                'Created_At' => $now, 'Created_By' => $userName, 'Created_By_Id' => $userId,
                'Updated_At' => $now, 'Updated_By' => $userName, 'Updated_By_Id' => $userId,
            ], 'Id_Master_Formulir');

            $schema = $this->schemaAktif((int) $realId);
            if ($schema) {
                $this->simpanVersi((int) $baruId, $schema['schema'], 'DRAFT', 'Salinan dari ' . $row->Kode);
            }

            return ResponseHelper::success(['id' => Hashids::encode($baruId)], 'Formulir disalin', 201);
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal duplikasi formulir #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menyalin formulir', 500);
        }
    }

    public function toggle(Request $request, $id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $aktif = $request->boolean('aktif');

            $terpengaruh = DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->update([
                'Flag_Aktif' => $aktif ? 'Y' : 'T',
                'Updated_At' => now(),
                'Updated_By' => session('career_auth.nama', 'ADMIN'),
                'Updated_By_Id' => session('career_auth.id'),
            ]);
            if (! $terpengaruh) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            return ResponseHelper::success(null, 'Status diperbarui');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal toggle formulir #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal mengubah status', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $realId = Hashids::decode($id)[0] ?? null;
            $row = DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->first();
            if (! $row) {
                return ResponseHelper::error('Data tidak ditemukan', 404);
            }

            $dipakai = DB::table('N_WEB_CAREERS_Formulir_Pengisian')
                ->where('Master_Formulir_Id', $realId)->count();
            if ($dipakai) {
                return ResponseHelper::error("Formulir sudah dipakai {$dipakai} pengisian - nonaktifkan saja, jangan dihapus.", 422);
            }

            $dipakaiAlur = DB::table('N_WEB_CAREERS_Master_Alur_Tahap')
                ->where('Formulir_Kode', $row->Kode)->count();
            if ($dipakaiAlur) {
                return ResponseHelper::error("Formulir masih dipakai {$dipakaiAlur} tahap alur seleksi.", 422);
            }

            DB::table('N_WEB_CAREERS_Master_Formulir')->where('Id_Master_Formulir', $realId)->delete();

            return ResponseHelper::success(null, 'Formulir dihapus');
        } catch (\Throwable $e) {
            Log::channel('web_career')->error("Gagal hapus formulir #{$id}: " . $e->getMessage());

            return ResponseHelper::error('Gagal menghapus data', 500);
        }
    }

    private function rules(): array
    {
        return [
            'nama' => 'required|string|max:120',
            'kategori' => 'nullable|string|max:20',
            'deskripsi' => 'nullable|string|max:500',
            'petunjuk' => 'nullable|string|max:500',
            'schema' => 'nullable|array',
            'catatan' => 'nullable|string',
        ];
    }

    private function guardSchema(array $data)
    {
        if (empty($data['schema'])) {
            return ResponseHelper::error('Master formulir wajib memiliki schema formulir.', 422);
        }
        if (! $this->punyaTabelVersi()) {
            return ResponseHelper::error('Tabel versi formulir belum tersedia. Jalankan DDL master formulir dinamis dulu.', 422);
        }
        return null;
    }

    private function kodeUnik(string $nama): string
    {
        return KodeUnik::buat('N_WEB_CAREERS_Master_Formulir', 'Kode', $nama, 30, 'FORMULIR');
    }

    private function punyaTabelVersi(): bool
    {
        try {
            return Schema::hasTable('N_WEB_CAREERS_Master_Formulir_Versi');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function versiPerFormulir(array $ids): array
    {
        if (! $ids || ! $this->punyaTabelVersi()) {
            return [];
        }

        return DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
            ->whereIn('Master_Formulir_Id', $ids)
            ->whereIn('Status', ['DRAFT', 'PUBLISHED'])
            ->orderByDesc('Versi')
            ->get()
            ->reduce(function ($carry, $v) {
                $slot = strtolower($v->Status);
                if (! isset($carry[$v->Master_Formulir_Id][$slot])) {
                    $carry[$v->Master_Formulir_Id][$slot] = [
                        'id' => Hashids::encode($v->Id_Master_Formulir_Versi),
                        'versi' => (int) $v->Versi,
                        'status' => $v->Status,
                        'schema' => json_decode($v->Schema_Json ?: '{}', true) ?: [],
                        'catatan' => $v->Catatan,
                        'publishedAt' => $v->Published_At,
                        'updatedAt' => $v->Updated_At,
                    ];
                }
                return $carry;
            }, []);
    }

    private function schemaAktif(int $formulirId): ?array
    {
        if (! $this->punyaTabelVersi()) {
            return null;
        }

        $row = DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
            ->where('Master_Formulir_Id', $formulirId)
            ->whereIn('Status', ['DRAFT', 'PUBLISHED'])
            ->orderByRaw("CASE WHEN Status = 'DRAFT' THEN 0 ELSE 1 END")
            ->orderByDesc('Versi')
            ->first();

        return $row ? [
            'id' => (int) $row->Id_Master_Formulir_Versi,
            'versi' => (int) $row->Versi,
            'schema' => json_decode($row->Schema_Json ?: '{}', true) ?: [],
        ] : null;
    }

    private function simpanVersi(int $formulirId, array $schema, string $status, ?string $catatan): int
    {
        if (! $this->punyaTabelVersi()) {
            return 0;
        }

        $last = (int) DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
            ->where('Master_Formulir_Id', $formulirId)
            ->max('Versi');
        $draft = $status === 'DRAFT'
            ? DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
                ->where('Master_Formulir_Id', $formulirId)
                ->where('Status', 'DRAFT')
                ->orderByDesc('Versi')
                ->first()
            : null;

        $payload = [
            'Schema_Json' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'Catatan' => $catatan,
            'Status' => $status,
            'Published_At' => $status === 'PUBLISHED' ? now() : null,
            'Updated_At' => now(),
            'Updated_By' => session('career_auth.nama', 'ADMIN'),
            'Updated_By_Id' => session('career_auth.id'),
        ];

        if ($draft) {
            DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
                ->where('Id_Master_Formulir_Versi', $draft->Id_Master_Formulir_Versi)
                ->update($payload);

            return (int) $draft->Id_Master_Formulir_Versi;
        }

        return (int) DB::table('N_WEB_CAREERS_Master_Formulir_Versi')->insertGetId($payload + [
            'Master_Formulir_Id' => $formulirId,
            'Versi' => $last + 1,
            'Created_At' => now(),
            'Created_By' => session('career_auth.nama', 'ADMIN'),
            'Created_By_Id' => session('career_auth.id'),
        ], 'Id_Master_Formulir_Versi');
    }

    /**
     * Penegakan aturan skema di sisi server.
     *
     * Editor sudah memvalidasi sebelum mengirim, tapi endpoint ini bisa dipanggil
     * tanpa lewat editor sama sekali — tanpa penegakan di sini, validasi browser
     * cuma saran. Aturan dan kalimat galatnya sengaja sama persis dengan versi
     * JS-nya (inti/schema.js), supaya kesalahan yang sama tidak dijelaskan dengan
     * dua cara berbeda.
     */
    private function validasiSchema(array $schema): array
    {
        $steps = $schema['langkah'] ?? [];
        if (! is_array($steps) || count($steps) < 1) {
            return ['ok' => false, 'pesan' => 'Schema minimal harus memiliki satu langkah.'];
        }

        // Konteks pemakaian formulir ikut tersimpan ke Schema_Json. Skema lama
        // tanpa konteks dianggap KEDUANYA, yang hanya mengizinkan kunci
        // isi-otomatis yang tersedia di kedua konteks.
        $konteks = strtoupper((string) ($schema['konteks'] ?? 'KEDUANYA'));
        if (! in_array($konteks, ['PENDAFTARAN', 'TAHAP', 'KEDUANYA'], true)) {
            $konteks = 'KEDUANYA';
        }
        $schema['konteks'] = $konteks;
        $kunciPrefill = KatalogPrefill::kunciUntuk($konteks);

        // Dua lintasan: yang pertama membangun peta posisi key, yang kedua
        // memeriksa rujukan antar-field. Tanpa lintasan pertama, rujukan ke
        // field yang letaknya di belakang tidak bisa dibedakan dari rujukan ke
        // key yang memang tidak ada.
        $posisi = [];
        $urut = 0;
        foreach ($steps as $step) {
            foreach (($step['bagian'] ?? []) as $section) {
                foreach (($section['field'] ?? []) as $field) {
                    $key = $this->slugKey((string) ($field['key'] ?? $field['label'] ?? 'field'));
                    if ($key !== '' && ! isset($posisi[$key])) {
                        $posisi[$key] = $urut;
                    }
                    $urut++;
                }
            }
        }

        $keys = [];
        $fieldIds = [];
        $urut = 0;
        foreach ($steps as $li => $step) {
            $bagian = $step['bagian'] ?? [];
            if (! is_array($bagian) || count($bagian) < 1) {
                return ['ok' => false, 'pesan' => 'Setiap langkah harus memiliki minimal satu section.'];
            }
            foreach ($bagian as $bi => $section) {
                $fields = $section['field'] ?? [];
                if (! is_array($fields) || count($fields) < 1) {
                    return ['ok' => false, 'pesan' => 'Setiap section harus memiliki minimal satu field.'];
                }
                foreach ($fields as $fi => $field) {
                    $key = $this->slugKey((string) ($field['key'] ?? $field['label'] ?? 'field'));
                    if ($key === '') {
                        return ['ok' => false, 'pesan' => 'Semua field wajib memiliki key.'];
                    }
                    if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                        return ['ok' => false, 'pesan' => "Key field \"{$key}\" hanya boleh huruf kecil, angka, dan underscore; harus diawali huruf."];
                    }
                    if (isset($keys[$key])) {
                        return ['ok' => false, 'pesan' => "Key field \"{$key}\" dipakai lebih dari sekali."];
                    }
                    $fieldId = trim((string) ($field['field_id'] ?? $field['id'] ?? ''));
                    if ($fieldId === '') {
                        $fieldId = $this->fallbackFieldId($key, (int) $li, (int) $bi, (int) $fi);
                    }
                    if (isset($fieldIds[$fieldId])) {
                        return ['ok' => false, 'pesan' => "ID sistem field \"{$field['label']}\" dipakai lebih dari sekali."];
                    }

                    // Tipe tak dikenal tidak lolos — ia dikembalikan ke text.
                    $tipe = strtolower((string) ($field['tipe'] ?? 'text'));
                    if (! in_array($tipe, KatalogField::tipeValid(), true)) {
                        $tipe = 'text';
                    }
                    $label = (string) ($field['label'] ?? $key);

                    $bersih = $this->bersihkanField($field, $tipe);

                    if ($galat = KatalogField::galat($bersih)) {
                        return ['ok' => false, 'pesan' => "Field \"{$label}\": {$galat}"];
                    }

                    if ($galat = $this->galatRujukan($bersih, $label, $key, $posisi, $urut)) {
                        return ['ok' => false, 'pesan' => $galat];
                    }

                    if (! empty($bersih['prefill']) && ! in_array($bersih['prefill'], $kunciPrefill, true)) {
                        return [
                            'ok' => false,
                            'pesan' => "Field \"{$label}\" mengisi otomatis dari \"{$bersih['prefill']}\", yang tidak tersedia untuk konteks formulir ini.",
                        ];
                    }

                    $lebarPersen = $this->normalisasiLebarPersen($bersih);
                    $bersih['field_id'] = $fieldId;
                    $bersih['key'] = $key;
                    $bersih['tipe'] = $tipe;
                    $bersih['label'] = $label;
                    $bersih['lebar_persen'] = $lebarPersen;
                    $bersih['penuh'] = $lebarPersen >= 100;

                    $schema['langkah'][$li]['bagian'][$bi]['field'][$fi] = $bersih;
                    $keys[$key] = true;
                    $fieldIds[$fieldId] = true;
                    $urut++;
                }
            }
        }

        $schema['schema_version'] = (int) ($schema['schema_version'] ?? 1);
        $schema['template'] = $schema['template'] ?? 'TEMPLATE_1';
        $layout = strtoupper((string) ($schema['layout'] ?? 'SATU_HALAMAN'));
        $schema['layout'] = in_array($layout, ['SATU_HALAMAN', 'BERTAHAP'], true) ? $layout : 'SATU_HALAMAN';

        return ['ok' => true, 'schema' => $schema];
    }

    /** Buang properti yang bukan milik tipe field ini. Lihat KatalogField. */
    private function bersihkanField(array $field, string $tipe): array
    {
        $out = [];
        foreach ($field as $prop => $nilai) {
            if (KatalogField::bolehPunya($tipe, (string) $prop)) {
                $out[$prop] = $nilai;
            }
        }

        return $out;
    }

    /**
     * Rujukan ke field lain. `tampil_jika` juga diperiksa ARAHNYA: acuan yang
     * letaknya sesudah field ini tidak akan pernah terisi saat field ini dinilai
     * pada layout bertahap, jadi syaratnya mustahil terpenuhi.
     */
    private function galatRujukan(array $field, string $label, string $key, array $posisi, int $urut): ?string
    {
        $acuan = $field['tampil_jika']['field'] ?? null;
        if ($acuan) {
            if (! isset($posisi[$acuan])) {
                return "Field \"{$label}\" (tampil jika) menunjuk key \"{$acuan}\" yang tidak ada di formulir ini.";
            }
            if ($posisi[$acuan] >= $urut) {
                return "Field \"{$label}\" bersyarat pada \"{$acuan}\", yang letaknya sesudah field ini — syaratnya tidak akan pernah terpenuhi.";
            }
        }

        $rujukan = [];
        if (! empty($field['beda_dengan'])) {
            $rujukan['beda dengan'] = [$field['beda_dengan']];
        }
        if (! empty($field['reset_anak']) && is_array($field['reset_anak'])) {
            $rujukan['reset anak'] = $field['reset_anak'];
        }
        foreach (['bergantung', 'saring'] as $nama) {
            if (! empty($field[$nama]) && is_array($field[$nama])) {
                $rujukan[$nama] = array_values($field[$nama]);
            }
        }

        foreach ($rujukan as $nama => $daftar) {
            foreach ($daftar as $target) {
                if ($target && ! isset($posisi[$target])) {
                    return "Field \"{$label}\" ({$nama}) menunjuk key \"{$target}\" yang tidak ada di formulir ini.";
                }
            }
        }

        return null;
    }

    private function guardKeyPublished(int $formulirId, array $schemaBaru): ?string
    {
        $published = $this->schemaPublished($formulirId);
        if (! $published) {
            return null;
        }

        $publishedById = [];
        foreach ($this->fieldsDariSchema($published) as $field) {
            $fieldId = trim((string) ($field['field_id'] ?? ''));
            if ($fieldId !== '') {
                $publishedById[$fieldId] = $field;
            }
        }
        if (! $publishedById) {
            return null;
        }

        foreach ($this->fieldsDariSchema($schemaBaru) as $field) {
            $fieldId = trim((string) ($field['field_id'] ?? ''));
            if ($fieldId === '' || ! isset($publishedById[$fieldId])) {
                continue;
            }

            $keyLama = (string) ($publishedById[$fieldId]['key'] ?? '');
            $keyBaru = (string) ($field['key'] ?? '');
            if ($keyLama !== $keyBaru) {
                $label = (string) ($field['label'] ?? $keyLama);
                return "Key field \"{$label}\" sudah dipublish sebagai \"{$keyLama}\" dan tidak boleh diubah.";
            }
        }

        return null;
    }

    private function schemaPublished(int $formulirId): ?array
    {
        if (! $this->punyaTabelVersi()) {
            return null;
        }

        $json = DB::table('N_WEB_CAREERS_Master_Formulir_Versi')
            ->where('Master_Formulir_Id', $formulirId)
            ->where('Status', 'PUBLISHED')
            ->orderByDesc('Versi')
            ->value('Schema_Json');

        return $json ? (json_decode($json, true) ?: null) : null;
    }

    private function fieldsDariSchema(array $schema): array
    {
        $fields = [];
        foreach (($schema['langkah'] ?? []) as $step) {
            foreach (($step['bagian'] ?? []) as $section) {
                foreach (($section['field'] ?? []) as $field) {
                    if (is_array($field)) {
                        $fields[] = $field;
                    }
                }
            }
        }

        return $fields;
    }

    private function slugKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?: 'field';
        $key = preg_replace('/^[0-9]+/', '', $key) ?: 'field';
        $key = trim($key, '_');

        return $key !== '' ? $key : 'field';
    }

    private function fallbackFieldId(string $key, int $langkahIndex, int $bagianIndex, int $fieldIndex): string
    {
        return sprintf('fld_legacy_%d_%d_%d_%s', $langkahIndex + 1, $bagianIndex + 1, $fieldIndex + 1, $this->slugKey($key));
    }

    /**
     * Lebar kolom dalam persen. Properti lama (lebar/width_percent/lebar_span/
     * kolom) tidak lagi dibaca di sini — bersihkanField sudah membuangnya sebelum
     * fungsi ini dipanggil.
     */
    private function normalisasiLebarPersen(array $field): int
    {
        if (($field['penuh'] ?? false) === true) {
            return 100;
        }

        $persen = (int) round((float) ($field['lebar_persen'] ?? 0));
        if ($persen > 0) {
            return min(100, max(33, $persen));
        }

        return 33;
    }
}
