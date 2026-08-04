<?php

namespace App\Jobs\Career;

use App\Jobs\Career\Concerns\AntreanWebCareers;
use App\Support\Career\LaporanKandidat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * WEB CAREER — cetak LAPORAN KANDIDAT (PDF / Excel) di latar belakang.
 *
 * KENAPA DIANTREKAN
 * Merender PDF berisi foto tertanam, seluruh perjalanan tahap, dan jawaban
 * formulir bisa memakan beberapa detik — dan itu untuk SATU kandidat. Bila
 * dikerjakan di dalam permintaan HTTP, admin menatap layar membeku lalu
 * menekan tombolnya lagi, dan lahir dua berkas untuk satu permintaan.
 *
 * Statusnya dicatat di N_WEB_CAREERS_Export_Log — tabel yang sudah dipakai
 * ekspor lain — sehingga layar bisa menanyakan "sudah jadi belum" tanpa
 * menunggu, dan kegagalan meninggalkan jejak yang bisa dibaca.
 */
class WcLaporanKandidatJob implements ShouldQueue
{
    use AntreanWebCareers, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'async_export';

    public $timeout = 300;
    public $tries = 2;
    public $backoff = 20;

    /**
     * @param  int[]  $pengisianIds  formulir yang ikut dicetak; kosong = terbaru.
     * @param  string  $format  PDF | XLSX
     */
    public function __construct(
        private int $exportId,
        private int $lamaranId,
        private array $pengisianIds,
        private string $format,
    ) {
        $this->aturAntrean(self::QUEUE);
    }

    public function handle(): void
    {
        $d = LaporanKandidat::rakit($this->lamaranId, $this->pengisianIds);

        if (! $d) {
            throw new \RuntimeException("Lamaran #{$this->lamaranId} tidak ditemukan.");
        }

        $slug = \Illuminate\Support\Str::slug($d['kandidat']['nama'] ?: 'kandidat');
        $ext = $this->format === 'XLSX' ? 'xlsx' : 'pdf';
        $path = "laporan-kandidat/{$d['kandidat']['kodeLamaran']}-{$slug}-{$this->exportId}.{$ext}";

        $isi = $this->format === 'XLSX' ? $this->buatExcel($d) : $this->buatPdf($d);

        Storage::disk('gcs')->put($path, $isi);

        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update([
                'Status_Export' => 'SELESAI',
                'File_Path' => $path,
                'File_Url' => Storage::disk('gcs')->url($path),
                'Progress_Chunk' => 1,
                'Progress_Total' => 1,
                'Completed_At' => now(),
            ]);

        Log::channel('web_career')->info("[LAPORAN] #{$this->exportId} selesai ({$this->format}) — {$path}");
    }

    /** Render PDF dari blade. Kertas A4 potret — laporan ini dicetak & diarsip. */
    private function buatPdf(array $d): string
    {
        $pdf = Pdf::loadView('career.laporan.kandidat', [
            'd' => $d,
            'logo' => LaporanKandidat::logoDataUri(),
            'tglLamar' => $d['lamaran']['waktuLamar']
                ? \Illuminate\Support\Carbon::parse($d['lamaran']['waktuLamar'])->format('d M Y')
                : null,
            ...$this->nadaHasil($d),
        ]);

        $pdf->setPaper('a4', 'portrait');
        // isRemoteEnabled dibiarkan MATI: seluruh gambar sudah ditanam sebagai
        // data URI. Menyalakannya berarti dompdf boleh menembak URL apa pun
        // yang kebetulan ada di dalam HTML — termasuk yang datang dari isian
        // kandidat.
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->output();
    }

    /**
     * Status akhir → label & warna lencana.
     *
     * Dihitung di sini, bukan di dalam blade: keputusan "mundur itu bukan
     * gagal" adalah aturan bisnis, dan aturan bisnis di dalam template akan
     * berbeda dari yang dipakai layar tanpa ada yang menyadarinya.
     */
    private function nadaHasil(array $d): array
    {
        $status = strtoupper((string) ($d['lamaran']['status'] ?? ''));

        return match (true) {
            $status === 'LULUS' => ['labelHasil' => 'DITERIMA', 'nadaHasil' => 'l-lolos'],
            $status === 'GUGUR' => ['labelHasil' => 'TIDAK LOLOS', 'nadaHasil' => 'l-gugur'],
            $status === 'TALENT_POOL' => ['labelHasil' => 'TALENT POOL', 'nadaHasil' => 'l-talent'],
            $status === 'BERJALAN' => ['labelHasil' => 'BERJALAN', 'nadaHasil' => 'l-jalan'],
            // Keputusan yang datang DARI KANDIDAT (mundur / menolak penawaran)
            // tidak diwarnai merah: prosesnya berhenti, tapi bukan karena ia
            // ditolak.
            $status !== '' => ['labelHasil' => str_replace('_', ' ', $status), 'nadaHasil' => 'l-netral'],
            default => ['labelHasil' => '—', 'nadaHasil' => 'l-netral'],
        };
    }

    /**
     * Excel — untuk yang perlu MENGOLAH datanya, bukan membacanya.
     *
     * Sengaja berbeda bentuk dari PDF: di sini tiap baris satu fakta, tanpa
     * gambar dan tanpa tata letak dua kolom. Meniru tampilan PDF di dalam
     * spreadsheet menghasilkan berkas yang tidak enak dibaca DAN tidak bisa
     * disaring — gagal di dua-duanya.
     */
    private function buatExcel(array $d): string
    {
        $book = new Spreadsheet();
        $book->getProperties()->setTitle('Laporan Kandidat')->setCompany('EVO Group');

        $s = $book->getActiveSheet();
        $s->setTitle('Profil');

        $judul = fn (string $teks, int $baris) => $s->setCellValue("A{$baris}", $teks);

        $judul('LAPORAN KANDIDAT SELEKSI — EVO GROUP', 1);
        $s->mergeCells('A1:D1');
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $s->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E40AF');
        $s->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s->getRowDimension(1)->setRowHeight(26);

        $baris = 3;
        foreach ([
            'Nama Kandidat' => $d['kandidat']['nama'],
            'Kode Lamaran' => $d['kandidat']['kodeLamaran'],
            'Email' => $d['kandidat']['email'],
            'No. Handphone' => $d['kandidat']['hp'],
            'Institusi' => $d['kandidat']['kampus'],
            'Tahun Lulus' => $d['kandidat']['tahunLulus'],
            'Program' => $d['lamaran']['program'],
            'Posisi Dilamar' => $d['lamaran']['posisi'],
            'Departemen' => $d['lamaran']['departemen'],
            'Lokasi' => $d['lamaran']['lokasi'],
            'Status Akhir' => $d['lamaran']['status'],
        ] as $k => $v) {
            $s->setCellValue("A{$baris}", $k);
            $s->setCellValue("B{$baris}", (string) ($v ?: '—'));
            $s->getStyle("A{$baris}")->getFont()->setBold(true);
            $baris++;
        }

        // ── Sheet perjalanan tahap ──────────────────────────────────────
        $t = $book->createSheet();
        $t->setTitle('Perjalanan');
        $t->fromArray(['#', 'Tahap', 'Status', 'Hasil', 'Aktivitas', 'Nilai', 'Diputus', 'Oleh'], null, 'A1');
        $this->kepala($t, 'A1:H1');

        $r = 2;
        foreach ($d['tahap'] as $th) {
            if (! $th['aktivitas']) {
                $t->fromArray([$th['urutan'], $th['label'], $th['status'], $th['hasil'] ?: '—', '—', '—',
                    $th['diputusAt'] ?: '—', $th['diputusOleh'] ?: '—'], null, "A{$r}");
                $r++;

                continue;
            }
            // Satu baris per AKTIVITAS: itulah bentuk yang bisa disaring dan
            // dijumlahkan. Tahapnya diulang di tiap baris — pengulangan itu
            // justru yang membuat pivot table bekerja.
            foreach ($th['aktivitas'] as $a) {
                $t->fromArray([
                    $th['urutan'], $th['label'], $th['status'], $th['hasil'] ?: '—',
                    $a['label'] . ($a['internal'] ? ' (internal)' : ''),
                    $a['nilai'] ?? '—',
                    $th['diputusAt'] ?: '—', $th['diputusOleh'] ?: '—',
                ], null, "A{$r}");
                $r++;
            }
        }
        foreach (range('A', 'H') as $k) {
            $t->getColumnDimension($k)->setAutoSize(true);
        }

        // ── Sheet jawaban formulir ──────────────────────────────────────
        $f = $book->createSheet();
        $f->setTitle('Formulir');
        $f->fromArray(['Formulir', 'Dikirim', 'Pertanyaan', 'Jawaban'], null, 'A1');
        $this->kepala($f, 'A1:D1');

        $r = 2;
        foreach ($d['formulir'] as $form) {
            foreach ($form['isian'] as $j) {
                $nilai = $j['berkas'] ? 'TERLAMPIR: ' . $j['berkas']['nama'] : ($j['nilai'] ?: '—');
                $f->fromArray([$form['label'], $form['waktuKirim'], $j['label'], $nilai], null, "A{$r}");
                $r++;
            }
        }
        foreach (range('A', 'D') as $k) {
            $f->getColumnDimension($k)->setAutoSize(true);
        }
        $f->getStyle('D1:D' . max(2, $r))->getAlignment()->setWrapText(true);

        foreach (range('A', 'B') as $k) {
            $s->getColumnDimension($k)->setAutoSize(true);
        }
        $book->setActiveSheetIndex(0);

        // Ditulis ke memori, bukan ke berkas sementara: laporan satu kandidat
        // kecil, dan menulis ke disk hanya menambah satu titik gagal (izin
        // folder, sisa berkas saat job mati di tengah).
        ob_start();
        (new Xlsx($book))->save('php://output');
        $isi = (string) ob_get_clean();

        $book->disconnectWorksheets();

        return $isi;
    }

    /** Gaya baris kepala tabel — sama di seluruh sheet. */
    private function kepala(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E40AF');
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('A2');
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('web_career')->error("[LAPORAN] #{$this->exportId} gagal: " . $e->getMessage());

        DB::table('N_WEB_CAREERS_Export_Log')
            ->where('Id_Export', $this->exportId)
            ->update([
                'Status_Export' => 'GAGAL',
                'Error_Message' => mb_substr($e->getMessage(), 0, 500),
                'Completed_At' => now(),
            ]);
    }
}
