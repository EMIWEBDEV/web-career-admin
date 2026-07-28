<?php

namespace App\Mail\Career;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * WEB CAREER — email notifikasi Talent Pool. Dua jenis:
 *   MASUK   : kandidat disimpan ke Talent Pool (belum terpilih, tapi potensial).
 *   DITARIK : kandidat ditarik dari pool ke lowongan baru (kesempatan lanjut).
 * Antrean mengikuti pola email lamaran (async, non-blocking).
 */
class TalentPoolMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $nama,
        public string $jenis,          // MASUK | DITARIK
        public ?string $posisi = null, // posisi asal (untuk MASUK)
        public ?string $posisiTujuan = null // posisi tujuan (untuk DITARIK)
    ) {
        // Non-local → cloudtasks; local → koneksi 'webcareers' (sama seperti email lamaran).
        if (env('QUEUE_CONNECTION') === 'cloudtasks') {
            $this->onConnection('cloudtasks')->onQueue('wc-applymail');
        } else {
            $this->onConnection('webcareers');
        }
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from.address', 'developer@evonusabersaudara.co.id');
        $subjek = $this->jenis === 'DITARIK'
            ? 'Kesempatan Baru Untukmu — EVO Career'
            : 'Profilmu Kami Simpan di Talent Pool — EVO Career';

        return new Envelope(subject: $subjek, replyTo: [new Address($from, 'EVO Career')]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.career.talent-pool',
            text: 'emails.career.talent-pool-text',
            with: [
                'portalUrl' => rtrim(config('app.url'), '/') . '/kandidat/portal',
                'karirUrl' => rtrim(config('app.url'), '/') . '/karir/landing-page',
            ],
        );
    }
}
