{{-- FOOTER email EVO Career — dipakai bersama via @include dari template/base.
     Isi: banner keamanan (penipuan & phishing + tanpa pungutan biaya),
     catatan kecil (bisa dioverride via @section('footer_note')),
     lalu footer kontak (logo grup + alamat + telepon + copyright).
     Logo di-embed sebagai CID agar selalu tampil di semua klien email. --}}

{{-- BANNER 1 : PENIPUAN & PHISHING --}}
<tr><td style="padding:24px 40px 0">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:linear-gradient(135deg,#fef2f2,#fff5f5);border:1px solid #f6c9c9;border-radius:16px">
        <tr>
            <td width="52" style="vertical-align:top;padding:16px 0 16px 16px">
                <div style="width:36px;height:36px;border-radius:10px;background:#ef4444;text-align:center;line-height:36px;font-size:19px;font-weight:800;color:#ffffff;font-family:'Inter',Arial,sans-serif">!</div>
            </td>
            <td style="vertical-align:top;padding:16px 16px 16px 12px">
                <div style="font-size:13.5px;font-weight:800;color:#b42318;font-family:'Inter',Arial,sans-serif">Waspada penipuan &amp; phishing</div>
                <p style="margin:5px 0 0;font-size:12.5px;line-height:1.6;color:#a4453a;font-family:'Inter',Arial,sans-serif">Jangan bagikan tautan verifikasi, kata sandi, atau data pribadimu kepada siapa pun. EVO Career hanya mengirim email dari alamat resmi berdomain <b>@evonusabersaudara.co.id</b> &mdash; abaikan pesan mencurigakan yang mengatasnamakan kami.</p>
            </td>
        </tr>
    </table>
</td></tr>

{{-- BANNER 2 : PUNGUTAN BIAYA --}}
<tr><td style="padding:12px 40px 4px">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:linear-gradient(135deg,#fff8ec,#fffaf0);border:1px solid #f6e2b8;border-radius:16px">
        <tr>
            <td width="52" style="vertical-align:top;padding:16px 0 16px 16px">
                <div style="width:36px;height:36px;border-radius:10px;background:#f59e0b;text-align:center;line-height:36px;font-size:18px;font-weight:800;color:#ffffff;font-family:'Inter',Arial,sans-serif">Rp</div>
            </td>
            <td style="vertical-align:top;padding:16px 16px 16px 12px">
                <div style="font-size:13.5px;font-weight:800;color:#92660a;font-family:'Inter',Arial,sans-serif">Rekrutmen tanpa pungutan biaya</div>
                <p style="margin:5px 0 0;font-size:12.5px;line-height:1.6;color:#8a6d29;font-family:'Inter',Arial,sans-serif"><b>EVO Group tidak pernah memungut biaya apa pun</b> selama proses rekrutmen &mdash; tidak ada biaya pendaftaran, tes, seragam, maupun transportasi. Abaikan pihak yang meminta uang mengatasnamakan kami.</p>
            </td>
        </tr>
    </table>
    <p style="margin:12px 2px 0;font-size:12px;line-height:1.6;color:#94a3b8;font-family:'Inter',Arial,sans-serif">@yield('footer_note', 'Bukan kamu yang membuat akun ini? Abaikan email ini dengan aman &mdash; tidak ada tindakan yang diperlukan.')</p>
</td></tr>

{{-- FOOTER : LOGOS + KONTAK --}}
<tr><td style="padding:26px 40px 30px">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:#f8f9fc;border:1px solid #eef0f7;border-radius:16px">
        <tr><td style="padding:20px 22px 6px" align="center">
            <div style="font-size:10px;font-weight:800;letter-spacing:.2em;color:#a2a9ba;font-family:'Inter',Arial,sans-serif">DIDUKUNG OLEH</div>
        </td></tr>
        <tr><td style="padding:12px 22px 16px" align="center">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td style="vertical-align:middle;padding:0 14px"><img src="{{ isset($message) ? $message->embed(public_path('logo/EMI.png')) : rtrim(config('app.url'), '/') . '/logo/EMI.png' }}" alt="PT EVO Manufacturing Indonesia" height="30" style="display:block;height:30px;width:auto;object-fit:contain"></td>
                <td style="vertical-align:middle;padding:0"><div style="width:1px;height:26px;background:#e2e8f0"></div></td>
                <td style="vertical-align:middle;padding:0 14px"><img src="{{ isset($message) ? $message->embed(public_path('logo/ENB.png')) : rtrim(config('app.url'), '/') . '/logo/ENB.png' }}" alt="PT EVO Nusa Bersaudara" height="26" style="display:block;height:26px;width:auto;object-fit:contain"></td>
                <td style="vertical-align:middle;padding:0"><div style="width:1px;height:26px;background:#e2e8f0"></div></td>
                <td style="vertical-align:middle;padding:0 14px"><img src="{{ isset($message) ? $message->embed(public_path('logo/GMN.png')) : rtrim(config('app.url'), '/') . '/logo/GMN.png' }}" alt="PT Graha Maju Nusantara" height="30" style="display:block;height:30px;width:auto;object-fit:contain"></td>
            </tr></table>
        </td></tr>
        <tr><td style="padding:0 22px 20px" align="center">
            <div style="border-top:1px solid #eef0f7;padding-top:14px">
                <p style="margin:0;font-size:12px;line-height:1.7;color:#8b93a7;font-family:'Inter',Arial,sans-serif">Jl. Sapta Marga No.83, Bukit Sangkal, Kec. Kalidoni,<br>Kota Palembang, Sumatera Selatan 30114</p>
                <p style="margin:6px 0 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif">Telepon resmi: <a href="tel:+6282287440675" style="color:#4f46e5;font-weight:600;text-decoration:none">0822-8744-0675</a></p>
            </div>
        </td></tr>
    </table>
    <p style="margin:16px 0 0;text-align:center;font-size:11px;line-height:1.6;color:#a2a9ba;font-family:'Inter',Arial,sans-serif">&copy; {{ date('Y') }} EVO Group &middot; EVO Career &mdash; Portal Kandidat<br>Email ini dikirim otomatis, mohon tidak membalas.</p>
</td></tr>
