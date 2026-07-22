{{-- ============================================================
     MASTER TEMPLATE email EVO Career — dipakai bersama via @extends.
     Struktur reusable:
       template/  → skeleton ini (skeleton + kartu 600px)
       header/    → masthead (logo + badge)
       footer/    → banner keamanan (penipuan + biaya) s.d. footer kontak
     Email turunan (verifikasi, reset sandi, dll) cukup mengisi:
       @section('content')   → isi tengah (hero, heading, tombol, dll)
       @section('preheader') → teks ringkas di daftar inbox (opsional)
       @section('title')     → judul <title> (opsional)
       @section('footer_note') → catatan kecil di atas footer (opsional)
     Layout table + inline style agar aman di semua klien email.
     ============================================================ --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <title>@yield('title', 'EVO Career')</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing: antialiased; letter-spacing: -0.006em; }
        a { color: #4f46e5; text-decoration: none; }
        @keyframes glow { 0%, 100% { box-shadow: 0 12px 30px rgba(99,102,241,.30); } 50% { box-shadow: 0 18px 44px rgba(139,92,246,.48); } }
    </style>
</head> 
<body style="margin:0;padding:0;background-color:#eef1fb;">

    {{-- Preheader (teks ringkas di daftar inbox, tak terlihat di badan email) --}}
    <span style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">@yield('preheader')</span>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;border-collapse:collapse;background:linear-gradient(180deg,#f3f2fd 0%,#eef1fb 48%,#eaf0fb 100%);padding:0">
        <tr><td align="center" style="padding:40px 16px 60px">

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 28px 70px rgba(30,27,75,.18)">

                @include('emails.header.default')

                @yield('content')

                @include('emails.footer.default')

            </table>
        </td></tr>
    </table>
</body>
</html>
