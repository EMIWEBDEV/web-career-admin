<?php

namespace App\Mail\Career;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * WEB CAREER — email verifikasi akun kandidat (magic link, bukan OTP).
 * Dikirim lewat WcSyncEmailJob. Menyertakan versi HTML + plaintext dan
 * Reply-To agar penilaian spam lebih baik (email tidak mudah ke folder Spam).
 */
class VerifikasiEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nama;

    public string $verifUrl;

    /** Masa berlaku tautan dalam MENIT — selaras Email_Verif_Expired_At (desain: 30 menit, sekali pakai). */
    public int $berlakuMenit;

    public function __construct(string $nama, string $verifUrl, int $berlakuMenit = 30)
    {
        $this->nama = $nama;
        $this->verifUrl = $verifUrl;
        $this->berlakuMenit = $berlakuMenit;
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from.address', 'developer@evonusabersaudara.co.id');

        return new Envelope(
            subject: 'Verifikasi Email Kamu — EVO Career',
            replyTo: [new Address($from, 'EVO Career')],
        );
    }

    public function content(): Content
    {
        // Logo di-embed sebagai CID di dalam partial header/footer (reusable),
        // jadi Mailable tak perlu mengoper URL logo lagi. Sertakan versi teks
        // (multipart/alternative) — email HTML-only lebih gampang kena Spam.
        return new Content(
            view: 'emails.career.verifikasi-email',
            text: 'emails.career.verifikasi-email-text',
        );
    }
}
