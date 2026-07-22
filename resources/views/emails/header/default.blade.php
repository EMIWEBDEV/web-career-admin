{{-- HEADER email EVO Career — masthead (logo EVO Group + label + badge).
     Logo di-embed sebagai CID (bukan URL) agar selalu tampil di semua klien. --}}
<tr><td style="padding:0">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:linear-gradient(135deg,#efeafe 0%,#eef2ff 50%,#e9f0ff 100%)">
        <tr><td style="padding:24px 34px 22px">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>
                <td style="vertical-align:middle">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                        <td style="vertical-align:middle;padding-right:12px"><img src="{{ isset($message) ? $message->embed(public_path('logo/EVOGROUP.png')) : rtrim(config('app.url'), '/') . '/logo/EVOGROUP.png' }}" alt="EVO Group" width="42" height="42" style="display:block;width:42px;height:42px;object-fit:contain"></td>
                        <td style="vertical-align:middle">
                            <div style="font-size:18px;font-weight:800;color:#1e1b4b;letter-spacing:-.01em;font-family:'Inter',Arial,sans-serif">EVO Career</div>
                            <div style="font-size:10px;font-weight:700;letter-spacing:.2em;color:#7c74b0;font-family:'Inter',Arial,sans-serif">PORTAL KANDIDAT</div>
                        </td>
                    </tr></table>
                </td>
                <td align="right" style="vertical-align:middle">
                    <span style="display:inline-block;padding:7px 13px;border-radius:999px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.22);font-size:11px;font-weight:700;color:#4f46e5;font-family:'Inter',Arial,sans-serif">EVO Group</span>
                </td>
            </tr></table>
        </td></tr>
    </table>
</td></tr>
