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

    public ?string $fotoData;   // bytes foto verifikasi (di-embed di blade), null bila tak ada

    public function __construct(string $nama, string $status, ?string $kode = null, ?string $posisi = null, ?string $program = null, array $tahap = [], array $kandidat = [])
    {
        $this->nama = $nama;
        $this->status = in_array($status, ['LOLOS', 'GUGUR', 'MENUNGGU'], true) ? $status : 'MENUNGGU';
        $this->kode = $kode;
        $this->posisi = $posisi;
        $this->program = $program;
        $this->tahapLolos = $tahap['lolos'] ?? null;
        $this->tahapBerikut = $tahap['berikut'] ?? null;
        $this->urutan = $tahap['urutan'] ?? null;
        $this->total = $tahap['total'] ?? null;
        $this->diterima = (bool) ($tahap['diterima'] ?? false);

        $this->email = $kandidat['email'] ?? null;
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
            'LOLOS' => 'Selamat! Kamu lolos seleksi administrasi. Simpan nomor pendaftaranmu.',
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
            ],
        );
    }
}
