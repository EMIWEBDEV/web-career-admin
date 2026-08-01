<?php

namespace App\Mail\Career;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * WEB CAREER — email HASIL LAMARAN. Tiga varian status:
 *   LOLOS    : lolos seleksi administrasi otomatis → selamat + nomor pendaftaran.
 *   GUGUR    : auto-gugur (syarat tak terpenuhi) → permintaan maaf yang sopan.
 *   MENUNGGU : tanpa syarat/menunggu keputusan admin → terima kasih + silakan pantau.
 * Memakai template email reusable (emails/template/base) — HTML + plaintext + Reply-To.
 */
class HasilLamaranMail extends Mailable
{
    /**
     * Status yang sah. LOLOS/GUGUR = keputusan tahap; MENUNGGU = email saat
     * kandidat baru MELAMAR, bukan hasil keputusan.
     */
    public const STATUS_SAH = ['LOLOS', 'GUGUR', 'MENUNGGU'];

    use Queueable, SerializesModels;

    public string $nama;

    public string $status;

    public ?string $kode;

    public ?string $posisi;

    public ?string $program;

    // Konteks tahap (untuk LOLOS) — membuat email reusable lintas tahap
    // (Seleksi Administrasi, Psikotes, Wawancara, dst.).
    public ?string $tahapLolos;

    public ?string $tahapBerikut;

    public ?int $urutan;

    public ?int $total;

    public bool $diterima;

    // Kartu data kandidat.
    public ?string $email;

    public ?string $tglLahir;   // sudah diformat "21 Jul 2026"

    public ?string $kampus;     // asal kampus/institusi dari formulir pendaftaran

    public ?string $jkel;       // jenis kelamin

    public ?string $hp;         // nomor telepon, sudah dirapikan ke format +62

    public ?string $fotoData;   // bytes foto verifikasi (di-embed di blade), null bila tak ada

    // [feat/feedback] URL halaman feedback (token-based, tanpa login)
    public ?string $feedbackUrl;

    public function __construct(string $nama, string $status, ?string $kode = null, ?string $posisi = null, ?string $program = null, array $tahap = [], array $kandidat = [], ?string $feedbackUrl = null)
    {
        $this->nama = $nama;
        // JANGAN diam-diam jatuh ke 'MENUNGGU'.
        //
        // Dulu status apa pun yang tak dikenali otomatis jadi 'MENUNGGU', dan
        // itu berarti surat "Terima kasih telah mendaftar / Pendaftaranmu sedang
        // ditinjau" terkirim kepada kandidat yang BARU SAJA diloloskan atau
        // ditolak — tanpa satu pun galat yang bisa dilacak. Salah ketik satu
        // huruf saja sudah cukup, dan kesalahannya baru ketahuan dari keluhan
        // kandidat. Sekarang ia gagal keras dan terlihat.
        if (! in_array($status, self::STATUS_SAH, true)) {
            throw new \InvalidArgumentException(
                "Status email hasil tidak dikenali: '{$status}'. Yang sah: " . implode(', ', self::STATUS_SAH) . '.'
            );
        }

        $this->status = $status;
        $this->kode = $kode;
        $this->posisi = $posisi;
        $this->program = $program;
        $this->tahapLolos = $tahap['lolos'] ?? null;
        $this->tahapBerikut = $tahap['berikut'] ?? null;
        $this->urutan = $tahap['urutan'] ?? null;
        $this->total = $tahap['total'] ?? null;
        $this->diterima = (bool) ($tahap['diterima'] ?? false);
        $this->feedbackUrl = $feedbackUrl; // [feat/feedback]

        $this->email = $kandidat['email'] ?? null;
        $this->kampus = $kandidat['kampus'] ?? null;
        $this->jkel = $kandidat['jkel'] ?? null;
        $this->hp = self::rapikanHp($kandidat['hp'] ?? null);
        $this->fotoData = $kandidat['foto'] ?? null;
        // Format tanggal → "21 Jul 2026" (fallback ke nilai asli bila tak bisa di-parse).
        $this->tglLahir = null;
        if (! empty($kandidat['tglLahir'])) {
            try {
                $this->tglLahir = \Illuminate\Support\Carbon::parse($kandidat['tglLahir'])->format('d M Y');
            } catch (\Throwable $e) {
                $this->tglLahir = (string) $kandidat['tglLahir'];
            }
        }
    }

    /**
     * Rapikan nomor telepon jadi "+62 812-3456-7890".
     *
     * Tersimpan apa adanya sesuai ketikan kandidat (mis. "6282269362834"),
     * yang kalau ditampilkan mentah terbaca seperti deretan angka acak.
     * Bentuk yang tak dikenali dibiarkan utuh — lebih baik apa adanya
     * daripada dipotong salah.
     */
    private static function rapikanHp(?string $hp): ?string
    {
        $angka = preg_replace('/\\D+/', '', (string) $hp);
        if (! $angka) {
            return null;
        }

        if (str_starts_with($angka, '0')) {
            $angka = '62' . substr($angka, 1);
        }
        if (! str_starts_with($angka, '62')) {
            return $hp;
        }

        // Pola baca nomor seluler Indonesia: 3 digit kode operator, lalu
        // kelompok 4 — "+62 822-6936-2834", bukan "+62 8226-9362-834".
        $sisa = substr($angka, 2);
        $blok = rtrim(chunk_split(substr($sisa, 3), 4, '-'), '-');

        return trim('+62 ' . substr($sisa, 0, 3) . ($blok ? '-' . $blok : ''));
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from.address', 'developer@evonusabersaudara.co.id');
        $subjek = match (true) {
            $this->status === 'LOLOS' && $this->diterima => 'Selamat! Kamu Diterima di EVO Group — EVO Career',
            $this->status === 'LOLOS' => 'Selamat! Kamu Lolos ' . ($this->tahapLolos ?: 'Tahap Seleksi') . ' — EVO Career',
            $this->status === 'GUGUR' => 'Informasi Hasil Lamaran Kamu — EVO Career',
            default => 'Terima Kasih Telah Mendaftar — EVO Career',
        };

        return new Envelope(
            subject: $subjek,
            replyTo: [new Address($from, 'EVO Career')],
        );
    }

    public function content(): Content
    {
        $portalUrl = rtrim(config('app.url'), '/') . '/kandidat/portal';
        $karirUrl = rtrim(config('app.url'), '/') . '/karir/landing-page';

        $preheader = match ($this->status) {
            'LOLOS' => $this->diterima ? 'Selamat! Kamu diterima di EVO Group.' : ('Selamat! Kamu lolos ' . ($this->tahapLolos ?: 'tahap seleksi') . '.'),
            'GUGUR' => 'Informasi hasil lamaran kamu di EVO Career.',
            default => 'Lamaran kamu sudah kami terima — mohon menunggu proses seleksi.',
        };

        return new Content(
            view: 'emails.career.hasil-lamaran',
            text: 'emails.career.hasil-lamaran-text',
            with: [
                'preheaderTxt' => $preheader,
                'portalUrl' => $portalUrl,
                'karirUrl' => $karirUrl,
                'feedbackUrl' => $this->feedbackUrl, // [feat/feedback]
            ],
        );
    }
}
