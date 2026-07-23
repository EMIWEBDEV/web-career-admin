{{-- Email HASIL LAMARAN — 3 status (LOLOS/GUGUR/MENUNGGU), 1:1 dari desain
     "Email Rekrutmen EVO" (Claude Design). Header, banner keamanan & footer
     diwarisi dari emails/template/base. Ikon = glyph email-safe (BUKAN SVG). --}}
@extends('emails.template.base')

@section('title', 'Hasil Lamaran — EVO Career')
@section('preheader', $preheaderTxt)
@section('footer_note', 'Email ini dikirim terkait lamaranmu di EVO Career. Simpan sebagai arsip.')

@section('content')
    @php
        // Peta warna & teks per status (selaras palet desain).
        $map = [
            'LOLOS' => [
                'strip' => 'linear-gradient(90deg,#34d399,#10b981)',
                'iconGrad' => 'linear-gradient(135deg,#34d399,#10b981)',
                'icon' => 'check',
                'eyebrow' => $diterima ? 'HASIL AKHIR SELEKSI' : ($tahapLolos ? mb_strtoupper($tahapLolos) : 'HASIL SELEKSI'),
                'ec' => '#059669',
                'cta' => 'Buka Portal Kandidat', 'ctaGrad' => 'linear-gradient(135deg,#34d399,#10b981)', 'ctaUrl' => $portalUrl,
            ],
            'MENUNGGU' => [
                'strip' => 'linear-gradient(90deg,#fbbf24,#f59e0b)',
                'iconGrad' => 'linear-gradient(135deg,#fbbf24,#f59e0b)',
                'icon' => 'clock',
                'eyebrow' => 'SELEKSI ADMINISTRASI',
                'ec' => '#b45309',
                'cta' => 'Cek Status Pendaftaran', 'ctaGrad' => 'linear-gradient(135deg,#fbbf24,#f59e0b)', 'ctaUrl' => $portalUrl,
            ],
            'GUGUR' => [
                'strip' => 'linear-gradient(90deg,#f87171,#ef4444)',
                'iconGrad' => 'linear-gradient(135deg,#f87171,#ef4444)',
                'icon' => 'x',
                'eyebrow' => 'SELEKSI ADMINISTRASI',
                'ec' => '#dc2626',
                'cta' => 'Lihat Lowongan Lainnya', 'ctaGrad' => 'linear-gradient(135deg,#818cf8,#6366f1)', 'ctaUrl' => $karirUrl,
            ],
        ][$status];

        $iconSrc = isset($message)
            ? $message->embed(public_path('email-icons/' . $map['icon'] . '.png'))
            : rtrim(config('app.url'), '/') . '/email-icons/' . $map['icon'] . '.png';

        $posisiTxt = $posisi ? ('untuk posisi <b style="color:#334155">' . e($posisi) . '</b>') : '';
        $programTxt = $program ? (' pada program <b style="color:#334155">' . e($program) . '</b>') : '';

        // Foto verifikasi → CID (bila ada); jika tidak ada, pakai inisial nama.
        $fotoSrc = (isset($message) && $fotoData) ? $message->embedData($fotoData, 'foto-verifikasi.jpg', 'image/jpeg') : null;
        $inisial = collect(explode(' ', trim($nama)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
        $inisial = mb_strtoupper($inisial ?: 'K');
        // Nama panjang → dipangkas untuk sapaan hero (kartu data pakai ellipsis CSS).
        $namaShort = \Illuminate\Support\Str::limit(trim($nama), 32);
    @endphp

    {{-- STATUS ACCENT STRIP --}}
    <tr><td style="height:5px;padding:0;background:{{ $map['strip'] }};font-size:0;line-height:0">&nbsp;</td></tr>

    {{-- HERO ICON (PNG putih di-embed CID → tampil putih & konsisten di semua klien) --}}
    <tr><td style="padding:34px 40px 0" align="center">
        <div style="width:78px;height:78px;border-radius:24px;background:{{ $map['iconGrad'] }};text-align:center;line-height:78px">
            <img src="{{ $iconSrc }}" width="38" height="38" alt="" style="width:38px;height:38px;vertical-align:middle;border:0">
        </div>
    </td></tr>

    {{-- HEADING + BODY + BOX PER STATUS --}}
    <tr><td style="padding:20px 44px 0" align="center">
        <div style="font-size:12px;font-weight:800;letter-spacing:.16em;color:{{ $map['ec'] }};font-family:'Inter',Arial,sans-serif">{{ $map['eyebrow'] }}</div>

        @if ($status === 'LOLOS')
            @if ($diterima)
                <h1 style="margin:8px 0 0;font-size:26px;line-height:1.26;font-weight:800;color:#1e293b;letter-spacing:-.02em;font-family:'Inter',Arial,sans-serif">Selamat, {{ $namaShort }}! Kamu Diterima &#127881;</h1>
                <p style="margin:14px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Kamu telah menyelesaikan <b style="color:#059669">seluruh tahap seleksi</b> {!! $posisiTxt !!}{!! $programTxt !!} dan dinyatakan <b style="color:#059669">DITERIMA</b> di EVO Group. Tim kami akan segera menghubungimu.</p>
            @else
                <h1 style="margin:8px 0 0;font-size:27px;line-height:1.24;font-weight:800;color:#1e293b;letter-spacing:-.02em;font-family:'Inter',Arial,sans-serif">Selamat, <span style="color:#059669">{{ $namaShort }}</span>! &#127881;</h1>
                <p style="margin:14px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Kamu dinyatakan <b style="color:#059669">LOLOS</b> pada tahap <b style="color:#334155">{{ $tahapLolos ?: 'seleksi' }}</b>@if ($urutan && $total) (tahap {{ $urutan }} dari {{ $total }})@endif {!! $posisiTxt !!}. Selamat melanjutkan ke tahap berikutnya!</p>
                @if ($tahapBerikut)
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin-top:16px;background:linear-gradient(135deg,#ecfdf5,#f0fdf4);border:1px solid #b7ebd2;border-radius:14px"><tr>
                        <td style="padding:14px 18px" align="left">
                            <div style="font-size:10.5px;font-weight:800;letter-spacing:.14em;color:#15803d;font-family:'Inter',Arial,sans-serif">TAHAP SELANJUTNYA</div>
                            <div style="margin-top:3px;font-size:15px;font-weight:800;color:#166534;font-family:'Inter',Arial,sans-serif">{{ $tahapBerikut }}</div>
                            <div style="margin-top:2px;font-size:12.5px;color:#3f9163;font-family:'Inter',Arial,sans-serif">Pantau jadwal &amp; instruksi lengkap di Portal Kandidat.</div>
                        </td>
                    </tr></table>
                @endif
            @endif
        @elseif ($status === 'MENUNGGU')
            <h1 style="margin:8px 0 0;font-size:27px;line-height:1.24;font-weight:800;color:#1e293b;letter-spacing:-.02em;font-family:'Inter',Arial,sans-serif">Pendaftaranmu sedang ditinjau</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Hai <b style="color:#334155">{{ $namaShort }}</b>, terima kasih sudah mendaftar {!! $posisiTxt !!}{!! $programTxt !!}. Berkasmu telah kami terima dan sedang <b style="color:#b45309">dalam proses peninjauan</b> oleh tim rekrutmen kami.</p>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin-top:16px;background:linear-gradient(135deg,#fffbeb,#fefce8);border:1px solid #f5e0a3;border-radius:14px"><tr>
                <td style="padding:14px 18px" align="left">
                    <div style="font-size:10.5px;font-weight:800;letter-spacing:.14em;color:#b45309;font-family:'Inter',Arial,sans-serif">STATUS SAAT INI</div>
                    <div style="margin-top:3px;font-size:15px;font-weight:800;color:#92660a;font-family:'Inter',Arial,sans-serif">Menunggu Peninjauan</div>
                    <div style="margin-top:2px;font-size:12.5px;color:#a1782a;font-family:'Inter',Arial,sans-serif">Selalu kunjungi web kami secara berkala untuk informasi &amp; pengumuman terbaru.</div>
                </td>
            </tr></table>
        @else
            <h1 style="margin:8px 0 0;font-size:26px;line-height:1.26;font-weight:800;color:#1e293b;letter-spacing:-.02em;font-family:'Inter',Arial,sans-serif">Terima kasih atas partisipasimu</h1>
            <p style="margin:14px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Hai <b style="color:#334155">{{ $namaShort }}</b>, terima kasih atas minat dan waktumu mengikuti seleksi {!! $posisiTxt !!}. Setelah pertimbangan yang cermat, saat ini kamu <b style="color:#dc2626">belum dapat kami lanjutkan</b> ke tahap berikutnya.</p>
            <p style="margin:12px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Keputusan ini bukan penilaian atas kemampuanmu secara keseluruhan. Data pelamaranmu tetap kami simpan &mdash; kami dengan senang hati menyambutmu kembali pada lowongan lain yang sesuai.</p>
        @endif
    </td></tr>

    {{-- KARTU DATA KANDIDAT (foto + nama + tanggal lahir + email) --}}
    <tr><td style="padding:24px 40px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:#f8f9fc;border:1px solid #eef0f7;border-radius:16px"><tr>
            <td width="112" style="vertical-align:top;padding:18px 4px 18px 18px" align="center">
                <div style="width:84px;height:84px;border-radius:16px;padding:3px;background:linear-gradient(135deg,#a78bfa,#6366f1);margin:0 auto">
                    <div style="width:78px;height:78px;border-radius:13px;background:#eef0fb;overflow:hidden;text-align:center;line-height:78px">
                        @if ($fotoSrc)
                            <img src="{{ $fotoSrc }}" alt="Foto" width="78" height="78" style="width:78px;height:78px;border-radius:13px;object-fit:cover;display:block">
                        @else
                            <span style="font-size:30px;font-weight:800;color:#6366f1;font-family:'Inter',Arial,sans-serif">{{ $inisial }}</span>
                        @endif
                    </div>
                </div>
                <div style="margin-top:8px;font-size:10px;font-weight:700;letter-spacing:.08em;color:#a2a9ba;font-family:'Inter',Arial,sans-serif">FOTO VERIFIKASI</div>
            </td>
            <td style="vertical-align:top;padding:18px 18px 18px 8px">
                <div style="font-size:10.5px;font-weight:800;letter-spacing:.14em;color:#a2a9ba;font-family:'Inter',Arial,sans-serif;padding-bottom:8px">DATA KANDIDAT</div>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%">
                    <tr>
                        <td style="padding:5px 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif;white-space:nowrap;vertical-align:top">Nama Lengkap</td>
                        <td style="padding:5px 0 5px 12px;font-family:'Inter',Arial,sans-serif;text-align:right">
                            <div title="{{ $nama }}" style="max-width:210px;margin-left:auto;font-size:13px;font-weight:700;color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $nama }}</div>
                        </td>
                    </tr>
                    @if ($tglLahir)
                        <tr><td colspan="2" style="border-top:1px solid #eef0f7;font-size:0;line-height:0">&nbsp;</td></tr>
                        <tr>
                            <td style="padding:5px 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif;white-space:nowrap;vertical-align:top">Tanggal Lahir</td>
                            <td style="padding:5px 0 5px 12px;font-size:13px;font-weight:700;color:#334155;font-family:'Inter',Arial,sans-serif;text-align:right">{{ $tglLahir }}</td>
                        </tr>
                    @endif
                    @if ($email)
                        <tr><td colspan="2" style="border-top:1px solid #eef0f7;font-size:0;line-height:0">&nbsp;</td></tr>
                        <tr>
                            <td style="padding:5px 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif;white-space:nowrap;vertical-align:top">Email</td>
                            <td style="padding:5px 0 5px 12px;font-size:13px;font-weight:700;color:#334155;font-family:'Inter',Arial,sans-serif;text-align:right;word-break:break-all">{{ $email }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr></table>
    </td></tr>

    {{-- NOMOR PENDAFTARAN --}}
    @if ($kode)
        <tr><td style="padding:16px 40px 0" align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:#f6f5ff;border:1.5px dashed #cfc7f7;border-radius:16px"><tr>
                <td style="padding:16px 20px" align="center">
                    <div style="font-size:10.5px;font-weight:800;letter-spacing:.16em;color:#8b83c9;font-family:'Inter',Arial,sans-serif">NOMOR PENDAFTARAN</div>
                    <div style="margin-top:6px;font-size:24px;font-weight:700;letter-spacing:.14em;color:#5b52e0;font-family:'Courier New',monospace">{{ $kode }}</div>
                    <div style="margin-top:5px;font-size:11.5px;color:#9a93bf;font-family:'Inter',Arial,sans-serif">Simpan nomor ini sebagai referensi untuk memantau status lamaran.</div>
                </td>
            </tr></table>
        </td></tr>
    @endif

    {{-- Email hasil = INFORMASI saja, tanpa tombol CTA (permintaan: cukup teks). --}}
    <tr><td style="padding:6px 44px 0" align="center">
        <p style="margin:0;font-size:12.5px;line-height:1.6;color:#8b93a7;font-family:'Inter',Arial,sans-serif">Pantau perkembangan &amp; tahap selanjutnya melalui Portal Kandidat di situs EVO Career.</p>
    </td></tr>
@endsection
