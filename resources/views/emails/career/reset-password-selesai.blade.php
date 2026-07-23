{{-- Email PEMBERITAHUAN kata sandi berhasil diubah. Mengikuti sistem desain email
     EVO Career: accent strip + hero badge + kartu status + CTA. Tanpa rahasia.
     Header, banner keamanan, dan footer diwarisi dari emails/template/base. --}}
@extends('emails.template.base')

@section('title', 'Kata Sandi Telah Diubah — EVO Career')
@section('preheader', 'Kata sandi akun EVO Career kamu baru saja berhasil diubah. Bukan kamu? Segera hubungi tim rekrutmen.')
@section('footer_note', 'Bukan kamu yang mengubah kata sandi? Segera hubungi tim rekrutmen EVO Group dan amankan email kamu.')

@section('content')
    {{-- ACCENT STRIP --}}
    <tr><td height="5" style="height:5px;line-height:5px;font-size:0;padding:0;background:linear-gradient(90deg,#34d399,#10b981)">&nbsp;</td></tr>

    {{-- HERO ICON — badge PNG (di-embed CID, tajam di semua klien termasuk Gmail) --}}
    <tr><td style="padding:34px 40px 0" align="center">
        <img src="{{ isset($message) ? $message->embed(public_path('logo/reset-success-badge.png')) : rtrim(config('app.url'), '/') . '/logo/reset-success-badge.png' }}" alt="Kata sandi diubah" width="78" height="78" style="display:block;width:78px;height:78px;border-radius:24px;box-shadow:0 14px 34px rgba(16,185,129,.34);margin:0 auto">
    </td></tr>

    {{-- HEADING + BODY --}}
    <tr><td style="padding:20px 44px 0" align="center">
        <div style="font-size:12px;font-weight:800;letter-spacing:.16em;color:#059669;font-family:'Inter',Arial,sans-serif">KEAMANAN AKUN</div>
        <h1 style="margin:8px 0 0;font-size:27px;line-height:1.24;font-weight:800;color:#1e293b;letter-spacing:-.02em;font-family:'Inter',Arial,sans-serif">Kata sandi kamu telah diubah</h1>
        <p style="margin:14px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Hai <b style="color:#334155">{{ $nama }}</b>, kata sandi akun <b style="color:#4f46e5">EVO Career</b> kamu baru saja berhasil diperbarui.</p>
    </td></tr>

    {{-- KARTU STATUS (sesi dikeluarkan) --}}
    <tr><td style="padding:18px 44px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:linear-gradient(135deg,#ecfdf5,#f0fdf4);border:1px solid #b7ebd2;border-radius:14px"><tr>
            <td style="padding:14px 18px" align="left">
                <div style="font-size:10.5px;font-weight:800;letter-spacing:.14em;color:#15803d;font-family:'Inter',Arial,sans-serif">KEAMANAN</div>
                <div style="margin-top:3px;font-size:15px;font-weight:800;color:#166534;font-family:'Inter',Arial,sans-serif">Semua sesi login telah dikeluarkan</div>
                <div style="margin-top:2px;font-size:12.5px;color:#3f9163;font-family:'Inter',Arial,sans-serif">Masuk kembali menggunakan kata sandi baru untuk melanjutkan.</div>
            </td>
        </tr></table>
    </td></tr>

    {{-- CTA --}}
    <tr><td align="center" style="padding:22px 44px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
            <td style="border-radius:14px;background:linear-gradient(135deg,#34d399,#10b981)">
                <a href="{{ rtrim(config('app.url'), '/') . '/login' }}" style="display:block;padding:15px 42px;font-size:15px;font-weight:700;color:#ffffff;font-family:'Inter',Arial,sans-serif;text-decoration:none;letter-spacing:.01em">Masuk ke Akun &nbsp;&rarr;</a>
            </td>
        </tr></table>
    </td></tr>
@endsection
