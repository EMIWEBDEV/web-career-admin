<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f4f5f7;font-family:Segoe UI,Arial,sans-serif;color:#1e293b">
    <span style="display:none;max-height:0;overflow:hidden">{{ $jenis === 'DITARIK' ? 'Ada kesempatan baru untukmu di EVO Career.' : 'Profilmu kami simpan di Talent Pool EVO Career.' }}</span>
    <div style="max-width:540px;margin:24px auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0">
        <div style="background:linear-gradient(135deg,#6366f1,#4f46e5);padding:24px 28px;color:#fff">
            <div style="font-size:19px;font-weight:800;letter-spacing:.02em">EVO Career</div>
            <div style="font-size:12.5px;opacity:.9;margin-top:2px">{{ $jenis === 'DITARIK' ? 'Kesempatan Baru' : 'Talent Pool' }}</div>
        </div>
        <div style="padding:26px 28px">
            <p style="margin:0 0 14px">Halo <b>{{ $nama }}</b>,</p>
            @if($jenis === 'DITARIK')
                <p style="margin:0 0 14px;line-height:1.6">Kabar baik! Berdasarkan rekam jejak seleksimu sebelumnya, tim rekrutmen kami mempertimbangkanmu untuk kesempatan baru:</p>
                <p style="margin:0 0 16px;background:#eef2ff;border-radius:10px;padding:13px 16px;font-weight:700;color:#4338ca">{{ $posisiTujuan ?: 'Posisi baru' }}</p>
                <p style="margin:0 0 14px;line-height:1.6">Proses seleksimu akan berlanjut dari tahap yang ditentukan tim kami. Silakan pantau perkembangannya melalui portal.</p>
            @else
                <p style="margin:0 0 14px;line-height:1.6">Terima kasih atas partisipasimu dalam proses seleksi{{ $posisi ? ' untuk posisi ' . $posisi : '' }} di EVO Group. Untuk kesempatan kali ini kamu belum kami pilih — namun profilmu kami nilai <b>potensial</b>.</p>
                <p style="margin:0 0 14px;line-height:1.6">Karena itu, kami menyimpan datamu di <b>Talent Pool</b> kami. Artinya, saat ada lowongan yang cocok, kamu bisa kami pertimbangkan lebih awal — <b>tanpa perlu mendaftar dari awal</b>.</p>
            @endif
            <p style="margin:22px 0 0"><a href="{{ $portalUrl }}" style="background:#4f46e5;color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:700;display:inline-block">Buka Portal Saya</a></p>
            <p style="color:#94a3b8;font-size:12px;line-height:1.6;margin:26px 0 0">Email ini dikirim otomatis oleh sistem EVO Career. Mohon tidak membalas email ini.</p>
        </div>
    </div>
</body>
</html>
