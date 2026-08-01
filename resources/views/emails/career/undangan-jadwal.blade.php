{{-- Email UNDANGAN JADWAL (wawancara / tes tatap muka). Header, banner keamanan
     & footer diwarisi dari emails/template/base. Ikon = glyph email-safe. --}}
@extends('emails.template.base')

@section('title', 'Undangan Jadwal — EVO Career')
@section('preheader', $preheaderTxt)
@section('footer_note', 'Email ini berisi jadwal seleksimu di EVO Career. Simpan sebagai pengingat.')

@section('content')
    <tr><td style="padding:30px 40px 0" align="center">
        <div style="width:66px;height:66px;border-radius:20px;background:linear-gradient(135deg,#818cf8,#6366f1);margin:0 auto;text-align:center;line-height:66px;font-size:28px;color:#fff">&#128197;</div>
        <div style="margin:16px 0 0;font-size:10.5px;font-weight:800;letter-spacing:.14em;color:#a2a9ba;font-family:'Inter',Arial,sans-serif">{{ mb_strtoupper($d['tahap'] ?? 'JADWAL SELEKSI') }}</div>
        <h1 style="margin:8px 0 0;font-size:26px;line-height:1.26;font-weight:800;color:#1e293b;letter-spacing:-.02em;font-family:'Inter',Arial,sans-serif">Undangan {{ $d['aktivitas'] ?? 'Wawancara' }}</h1>
        <p style="margin:14px 0 0;font-size:15px;line-height:1.68;color:#5b6478;font-family:'Inter',Arial,sans-serif">
            Hai <b style="color:#334155">{{ $d['nama'] ?? 'Kandidat' }}</b>, kamu diundang mengikuti
            <b style="color:#334155">{{ $d['aktivitas'] ?? 'wawancara' }}</b>
            @if (!empty($d['posisi'])) untuk posisi <b style="color:#334155">{{ $d['posisi'] }}</b>@endif.
            Mohon hadir tepat waktu sesuai rincian di bawah.
        </p>
    </td></tr>

    {{-- KARTU RINCIAN JADWAL --}}
    <tr><td style="padding:24px 40px 0">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background:#f6f5ff;border:1.5px solid #dcd8fb;border-radius:16px">
            <tr><td style="padding:18px 20px">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%">
                    <tr>
                        <td style="padding:6px 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif;white-space:nowrap;vertical-align:top;width:110px">Waktu</td>
                        <td style="padding:6px 0 6px 12px;font-size:13.5px;font-weight:800;color:#334155;font-family:'Inter',Arial,sans-serif">{{ $waktuTeks }}</td>
                    </tr>
                    <tr><td colspan="2" style="border-top:1px solid #e6e2fb;font-size:0;line-height:0">&nbsp;</td></tr>
                    <tr>
                        <td style="padding:6px 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif;white-space:nowrap;vertical-align:top">Metode</td>
                        <td style="padding:6px 0 6px 12px;font-size:13.5px;font-weight:800;color:#334155;font-family:'Inter',Arial,sans-serif">
                            {{ $daring ? 'Daring (online)' : 'Luring (tatap muka)' }}
                        </td>
                    </tr>

                    @if ($daring && !empty($d['link']))
                        <tr><td colspan="2" style="border-top:1px solid #e6e2fb;font-size:0;line-height:0">&nbsp;</td></tr>
                        <tr>
                            <td style="padding:6px 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif;white-space:nowrap;vertical-align:top">Tautan</td>
                            <td style="padding:6px 0 6px 12px;font-family:'Inter',Arial,sans-serif">
                                <a href="{{ $d['link'] }}" style="font-size:13px;font-weight:700;color:#4f46e5;word-break:break-all">{{ $d['link'] }}</a>
                            </td>
                        </tr>
                    @endif

                    {{-- TEMPAT: nama resmi + alamat + patokan. Kandidat harus bisa
                         berangkat hanya dengan surat ini di tangan — nama gedung
                         tanpa alamat menuntut dia menebak, dan patokan tanpa
                         gedungnya menuntut dia bertanya. --}}
                    @if (!$daring && !empty($d['lokasi']))
                        <tr><td colspan="2" style="border-top:1px solid #e6e2fb;font-size:0;line-height:0">&nbsp;</td></tr>
                        <tr>
                            <td style="padding:6px 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif;white-space:nowrap;vertical-align:top">Tempat</td>
                            <td style="padding:6px 0 6px 12px;font-family:'Inter',Arial,sans-serif">
                                <div style="font-size:13.5px;font-weight:800;color:#334155">{{ $d['lokasi'] }}</div>
                                @if (!empty($d['alamat']))
                                    <div style="font-size:12.5px;line-height:1.55;color:#64748b;margin-top:2px">{{ $d['alamat'] }}</div>
                                @endif
                                @if (!empty($d['patokan']))
                                    <div style="font-size:12.5px;line-height:1.55;color:#64748b;margin-top:2px">Patokan: {{ $d['patokan'] }}</div>
                                @endif
                                @if (!empty($d['kontak']))
                                    <div style="font-size:12.5px;line-height:1.55;color:#64748b;margin-top:2px">Kontak: {{ $d['kontak'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @endif

                    @if (!empty($d['catatan']))
                        <tr><td colspan="2" style="border-top:1px solid #e6e2fb;font-size:0;line-height:0">&nbsp;</td></tr>
                        <tr>
                            <td style="padding:6px 0;font-size:12px;color:#8b93a7;font-family:'Inter',Arial,sans-serif;white-space:nowrap;vertical-align:top">Catatan</td>
                            <td style="padding:6px 0 6px 12px;font-size:13px;line-height:1.6;color:#475569;font-family:'Inter',Arial,sans-serif">{{ $d['catatan'] }}</td>
                        </tr>
                    @endif
                </table>
            </td></tr>
        </table>
    </td></tr>

    @if ($daring && !empty($d['link']))
        <tr><td style="padding:18px 40px 0" align="center">
            <a href="{{ $d['link'] }}" style="display:inline-block;padding:13px 26px;border-radius:12px;background:linear-gradient(135deg,#818cf8,#6366f1);color:#fff;font-size:14px;font-weight:800;text-decoration:none;font-family:'Inter',Arial,sans-serif">Gabung Pertemuan</a>
        </td></tr>
    {{-- Peta tidak bisa disematkan di surel (klien memblokir iframe), jadi yang
         diberikan tautan yang langsung membuka aplikasi peta di ponsel. --}}
    @elseif (!$daring && !empty($d['mapsUrl']))
        <tr><td style="padding:18px 40px 0" align="center">
            <a href="{{ $d['mapsUrl'] }}" style="display:inline-block;padding:13px 26px;border-radius:12px;background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;font-size:14px;font-weight:800;text-decoration:none;font-family:'Inter',Arial,sans-serif">Lihat Lokasi di Peta</a>
        </td></tr>
    @endif

    @if (!empty($d['kode']))
        <tr><td style="padding:16px 40px 0" align="center">
            <div style="font-size:11px;color:#8b93a7;font-family:'Inter',Arial,sans-serif">Nomor pendaftaran: <b style="color:#334155">{{ $d['kode'] }}</b></div>
        </td></tr>
    @endif

    <tr><td style="padding:18px 40px 0" align="center">
        <a href="{{ $portalUrl }}" style="font-size:13px;font-weight:700;color:#4f46e5;text-decoration:none;font-family:'Inter',Arial,sans-serif">Buka Portal Kandidat &rarr;</a>
    </td></tr>
@endsection
