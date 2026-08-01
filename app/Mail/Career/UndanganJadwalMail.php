<?php

namespace App\Mail\Career;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * WEB CAREER — undangan jadwal wawancara / tes tatap muka.
 *
 * Isinya menjawab tiga pertanyaan kandidat: KAPAN, DI MANA (atau lewat tautan
 * apa), dan APA yang perlu disiapkan. Nilai maupun penilaian tidak pernah ikut
 * — ini undangan, bukan pengumuman hasil.
 */
class UndanganJadwalMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $d;

    public string $waktuTeks;

    public bool $daring;

    public function __construct(array $data)
    {
        $this->d = $data;
        $this->daring = strtoupper((string) ($data['mode'] ?? '')) === 'DARING';
        $this->waktuTeks = $this->susunWaktu($data['mulai'] ?? null, $data['selesai'] ?? null);
    }

    /**
     * "Sabtu, 02 Agustus 2026 · 09.00 – 10.30 WIB".
     *
     * Hari ikut disebut karena itu yang paling cepat ditangkap orang saat
     * membaca undangan; tanggal saja menuntut membuka kalender.
     */
    private function susunWaktu(?string $mulai, ?string $selesai): string
    {
        if (! $mulai) {
            return '—';
        }

        try {
            $m = Carbon::parse($mulai)->locale('id');
            $teks = $m->translatedFormat('l, d F Y') . ' · ' . $m->format('H.i');

            if ($selesai) {
                $teks .= ' – ' . Carbon::parse($selesai)->format('H.i');
            }

            return $teks . ' WIB';
        } catch (\Throwable $e) {
            return (string) $mulai;
        }
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from.address', 'developer@evonusabersaudara.co.id');
        $akt = $this->d['aktivitas'] ?? 'Wawancara';

        return new Envelope(
            subject: "Undangan {$akt} — EVO Career",
            replyTo: [new Address($from, 'EVO Career')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.career.undangan-jadwal',
            text: 'emails.career.undangan-jadwal-text',
            with: [
                'portalUrl' => rtrim(config('app.url'), '/') . '/kandidat/portal',
                'preheaderTxt' => 'Jadwal ' . ($this->d['aktivitas'] ?? 'wawancara') . ': ' . $this->waktuTeks,
            ],
        );
    }
}
