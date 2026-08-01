UNDANGAN {{ mb_strtoupper($d['aktivitas'] ?? 'WAWANCARA') }} — EVO CAREER

Hai {{ $d['nama'] ?? 'Kandidat' }},

Kamu diundang mengikuti {{ $d['aktivitas'] ?? 'wawancara' }}@if (!empty($d['posisi'])) untuk posisi {{ $d['posisi'] }}@endif.
Tahap: {{ $d['tahap'] ?? '-' }}

------------------------------------------------------------
RINCIAN JADWAL
Waktu   : {{ $waktuTeks }}
Metode  : {{ $daring ? 'Daring (online)' : 'Luring (tatap muka)' }}
@if ($daring && !empty($d['link']))Tautan  : {{ $d['link'] }}
@endif
@if (!$daring && !empty($d['lokasi']))Tempat  : {{ $d['lokasi'] }}
@endif
@if (!$daring && !empty($d['alamat']))Alamat  : {{ $d['alamat'] }}
@endif
@if (!$daring && !empty($d['patokan']))Patokan : {{ $d['patokan'] }}
@endif
@if (!$daring && !empty($d['kontak']))Kontak  : {{ $d['kontak'] }}
@endif
{{-- {!! !!}: surat teks biasa, "&amp;" di URL membuat tautannya gagal dibuka. --}}
@if (!$daring && !empty($d['mapsUrl']))Peta    : {!! $d['mapsUrl'] !!}
@endif
@if (!empty($d['catatan']))Catatan : {{ $d['catatan'] }}
@endif
@if (!empty($d['kode']))
Nomor pendaftaran: {{ $d['kode'] }}
@endif

Mohon hadir tepat waktu.

Buka Portal Kandidat: {{ $portalUrl }}

------------------------------------------------------------
WASPADA PENIPUAN: EVO Group tidak pernah memungut biaya apa pun dalam proses rekrutmen. Email resmi hanya dari domain @evonusabersaudara.co.id.

© {{ date('Y') }} EVO Group · EVO Career — Portal Kandidat
Email otomatis, mohon tidak membalas.
