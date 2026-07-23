<?php

namespace App\Mail\Career;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * WEB CAREER — email pemberitahuan bahwa kata sandi baru saja diubah.
 * Dikirim lewat WcSyncEmailJob setelah reset berhasil. Tidak memuat rahasia
 * apa pun — hanya konfirmasi + imbauan bila perubahan bukan dilakukan user.
 */
class ResetSelesaiMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nama;

    public function __construct(string $nama)
    {
        $this->nama = $nama;
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from.address', 'developer@evonusabersaudara.co.id');

        return new Envelope(
            subject: 'Kata Sandi Kamu Telah Diubah — EVO Career',
            replyTo: [new Address($from, 'EVO Career')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.career.reset-password-selesai',
            text: 'emails.career.reset-password-selesai-text',
        );
    }
}
