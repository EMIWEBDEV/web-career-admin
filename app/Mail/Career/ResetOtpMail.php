<?php

namespace App\Mail\Career;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * WEB CAREER — email OTP reset kata sandi (kode 6 digit, bukan magic link).
 * Dikirim lewat WcSyncEmailJob. Menyertakan versi HTML + plaintext dan
 * Reply-To agar penilaian spam lebih baik (email tidak mudah ke folder Spam).
 * OTP hanya dioper transien untuk render — tidak pernah disimpan/di-log.
 */
class ResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nama;

    public string $otp;

    /** Masa berlaku OTP dalam MENIT — selaras Reset_Otp_Expired_At (desain: 10 menit, sekali pakai). */
    public int $berlakuMenit;

    public function __construct(string $nama, string $otp, int $berlakuMenit = 10)
    {
        $this->nama = $nama;
        $this->otp = $otp;
        $this->berlakuMenit = $berlakuMenit;
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from.address', 'developer@evonusabersaudara.co.id');

        return new Envelope(
            subject: 'Kode Reset Kata Sandi — EVO Career',
            replyTo: [new Address($from, 'EVO Career')],
        );
    }

    public function content(): Content
    {
        // Logo di-embed sebagai CID di partial header/footer (reusable). Sertakan
        // versi teks (multipart/alternative) — email HTML-only lebih gampang Spam.
        return new Content(
            view: 'emails.career.reset-password',
            text: 'emails.career.reset-password-text',
        );
    }
}
