{{-- Email VERIFIKASI (magic link). Hanya mengisi bagian tengah; header, banner
     keamanan, dan footer diwarisi dari emails/template/base (reusable). --}}
@extends('emails.template.base')

@section('title', 'Verifikasi Email — EVO Career')
@section('preheader', 'Verifikasi email kamu untuk mengaktifkan akun EVO Career — cukup satu klik, tautan aman berlaku ' . $berlakuMenit . ' menit.')

@section('content')
    {{-- HERO ICON --}}
    <tr><td style="padding:34px 40px 0" align="center">
        <div style="width:74px;height:74px;border-radius:22px;background:linear-gradient(135deg,#8b5cf6,#6366f1);text-align:center;line-height:74px;animation:glow 3.4s ease-in-out infinite">
            <img src="{{ isset($message) ? $message->embed(public_path('email-icons/envelope.png')) : rtrim(config('app.url'), '/') . '/email-icons/envelope.png' }}" width="36" height="36" alt="" style="width:36px;height:36px;vertical-align:middle;border:0">
        </div>
    </td></tr> 

    {{-- HEADING + BODY --}}
    <tr><td style="padding:20px 44px 0" align="center">
        <div style="font-size:12px;font-weight:800;letter-spacing:.16em;color:#8b5cf6;font-family:'Inter',Arial,sans-serif">VERIFIKASI AKUN</div>
        <h1 style="margin:8px 0 0;font-size:27px;line-height:1.24;font-weight:800;color:#1e293b;letter-spacing:-.02em;font-family:'Inter',Arial,sans-serif">Konfirmasi alamat email kamu</h1>
        <p style="margin:14px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Hai <b style="color:#334155">{{ $nama }}</b>, terima kasih sudah mendaftar dan mengambil langkah pertama bersama <b style="color:#4f46e5">EVO Career</b> &mdash; kami senang menyambut perjalanan kariermu dimulai di sini.</p>
        <p style="margin:10px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">Tinggal satu klik untuk mengaktifkan akunmu &mdash; tanpa kode, tanpa kata sandi tambahan.</p>
    </td></tr>

    {{-- MAGIC LINK BUTTON --}}
    <tr><td align="center" style="padding:26px 44px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#6366f1" style="border-radius:14px;background:linear-gradient(135deg,#8b5cf6,#6366f1)">
            <a href="{{ $verifUrl }}" style="display:block;padding:16px 46px;font-size:15.5px;font-weight:700;color:#ffffff;font-family:'Inter',Arial,sans-serif;text-decoration:none;letter-spacing:.01em">Verifikasi Email Saya &nbsp;&rarr;</a>
        </td></tr></table>
        <p style="margin:14px 0 0;font-size:12.5px;color:#8b93a7;font-family:'Inter',Arial,sans-serif">Tautan aman ini berlaku selama <b style="color:#4f46e5">{{ $berlakuMenit }} menit</b> dan hanya bisa dipakai sekali.</p>
    </td></tr>

    {{-- ALTERNATIVE LINK --}}
    <tr><td style="padding:24px 44px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%">
            <tr><td style="border-top:1px solid #eef0f7;font-size:0;line-height:0">&nbsp;</td></tr>
        </table>
        <p style="margin:18px 0 8px;font-size:13px;color:#6b7488;font-family:'Inter',Arial,sans-serif">Tombol tidak berfungsi? Salin &amp; tempel tautan berikut di browser kamu:</p>
        <div style="background:#f6f5ff;border:1px solid #e7e3fb;border-radius:12px;padding:13px 15px;word-break:break-all">
            <a href="{{ $verifUrl }}" style="font-size:12.5px;line-height:1.55;color:#5b52e0;font-family:'Courier New',monospace;text-decoration:none">{{ $verifUrl }}</a>
        </div>
    </td></tr>
@endsection
