<?php

namespace App\Mail\Career;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * WEB CAREER — email verifikasi akun kandidat (magic link, bukan OTP).
 * Dikirim lewat WcSyncEmailJob (queue 'wc-syncemailjob'), bukan langsung.
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
        return new Envelope(
            subject: 'Verifikasi Email Kamu — EVO Career',
        );
    }

    public function content(): Content
    {
        // Logo di-embed sebagai CID di dalam partial header/footer (reusable),
        // jadi Mailable tak perlu mengoper URL logo lagi.
        return new Content(
            view: 'emails.career.verifikasi-email',
        );
    }
}
