{{-- Email RESET KATA SANDI (kode OTP 6 digit). Mengikuti sistem desain email EVO
     Career: accent strip + hero badge + kartu kode + banner keamanan (footer).
     Header, banner, dan footer diwarisi dari emails/template/base (reusable). --}}
@extends('emails.template.base')

@section('title', 'Kode Reset Kata Sandi — EVO Career')
@section('preheader', 'Kode OTP reset kata sandimu: ' . $otp . ' — berlaku ' . $berlakuMenit . ' menit, sekali pakai. Jangan bagikan ke siapa pun.')
@section('footer_note', 'Tidak meminta reset kata sandi? Abaikan email ini &mdash; kata sandimu tidak akan berubah.')

@section('content')
    {{-- ACCENT STRIP --}}
    <tr><td height="5" style="height:5px;line-height:5px;font-size:0;padding:0;background:linear-gradient(90deg,#8b5cf6,#6366f1)">&nbsp;</td></tr>

    {{-- HERO ICON — badge PNG (di-embed CID, tajam di semua klien termasuk Gmail) --}}
    <tr><td style="padding:34px 40px 0" align="center">
        <img src="{{ isset($message) ? $message->embed(public_path('logo/reset-otp-badge.png')) : rtrim(config('app.url'), '/') . '/logo/reset-otp-badge.png' }}" alt="Reset kata sandi" width="78" height="78" style="display:block;width:78px;height:78px;border-radius:24px;box-shadow:0 14px 34px rgba(99,102,241,.34);margin:0 auto">
    </td></tr>

    {{-- HEADING + BODY --}}
    <tr><td style="padding:20px 44px 0" align="center">
        <div style="font-size:12px;font-weight:800;letter-spacing:.16em;color:#6366f1;font-family:'Inter',Arial,sans-serif">RESET KATA SANDI</div>
        <h1 style="margin:8px 0 0;font-size:27px;line-height:1.24;font-weight:800;color:#1e293b;letter-spacing:-.02em;font-family:'Inter',Arial,sans-serif">Kode reset kata sandi kamu</h1>
        <p style="margin:14px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Hai <b style="color:#334155">{{ $nama }}</b>, kami menerima permintaan untuk mengatur ulang kata sandi akun <b style="color:#4f46e5">EVO Career</b> kamu. Masukkan kode di bawah pada halaman reset untuk melanjutkan.</p>
    </td></tr>

    {{-- KARTU KODE OTP (monospace, garis putus-putus) --}}
    <tr><td style="padding:22px 40px 0" align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:#f6f5ff;border:1.5px dashed #cfc7f7;border-radius:16px"><tr>
            <td style="padding:18px 20px" align="center">
                <div style="font-size:10.5px;font-weight:800;letter-spacing:.16em;color:#8b83c9;font-family:'Inter',Arial,sans-serif">KODE OTP</div>
                <div style="margin-top:8px;font-size:36px;font-weight:700;letter-spacing:.3em;color:#5b52e0;font-family:'JetBrains Mono','Courier New',monospace">{{ $otp }}</div>
                <div style="margin-top:6px;font-size:11.5px;color:#9a93bf;font-family:'Inter',Arial,sans-serif">Berlaku <b style="color:#7a72b8">{{ $berlakuMenit }} menit</b> &middot; sekali pakai &middot; jangan dibagikan</div>
            </td>
        </tr></table>
    </td></tr>

    {{-- CATATAN KEAMANAN --}}
    <tr><td style="padding:18px 44px 0" align="center">
        <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7488;font-family:'Inter',Arial,sans-serif">Demi keamanan, <b style="color:#334155">jangan pernah membagikan kode ini</b> kepada siapa pun &mdash; termasuk yang mengaku dari EVO Group.</p>
    </td></tr>
@endsection
