@php
    $posisiTxt = $posisi ? (' untuk posisi ' . $posisi) : '';
    $programTxt = $program ? (' pada program ' . $program) : '';
@endphp
@if ($status === 'LOLOS')
@if ($diterima)
Selamat, {{ $nama }}! Kamu Diterima.

Kamu telah menyelesaikan seluruh tahap seleksi{{ $posisiTxt }}{{ $programTxt }} dan dinyatakan DITERIMA di EVO Group. Tim kami akan segera menghubungimu.
@else
Selamat, {{ $nama }}!

Kamu dinyatakan LOLOS {{ $tahapLolos ?: 'tahap seleksi' }}@if ($urutan && $total) (tahap {{ $urutan }} dari {{ $total }})@endif{{ $posisiTxt }}{{ $programTxt }}. Selamat melangkah ke tahap berikutnya!
@if ($tahapBerikut)
Tahap selanjutnya: {{ $tahapBerikut }}. Pantau & kerjakan di Portal Kandidat.
@endif
@endif

@if ($kode)NOMOR PENDAFTARAN: {{ $kode }}
Simpan nomor ini sebagai referensi untuk memantau status lamaranmu.
@endif
Buka Portal Kandidat: {{ $portalUrl }}
@elseif ($status === 'GUGUR')
Halo, {{ $nama }},

Terima kasih sudah mendaftar{{ $posisiTxt }} di EVO Career. Setelah kami tinjau, untuk kesempatan kali ini kamu belum memenuhi kualifikasi yang dibutuhkan. Mohon maaf, dan kami sangat menghargai minat serta waktumu.

Jangan berkecil hati — masih banyak peluang lain menantimu. Kami berharap dapat bertemu kembali di kesempatan berikutnya.

Lihat lowongan lain: {{ $karirUrl }}
@else
Terima Kasih, {{ $nama }}!

Lamaranmu{{ $posisiTxt }}{{ $programTxt }} sudah kami terima dan sedang dalam proses peninjauan oleh tim rekrutmen kami.

@if ($kode)NOMOR PENDAFTARAN: {{ $kode }}
Simpan nomor ini sebagai referensi untuk memantau status lamaranmu.
@endif
Mohon menunggu — kami akan menginformasikan tahap selanjutnya. Selalu kunjungi web kami secara berkala untuk informasi & pengumuman terbaru.

Buka Portal Kandidat: {{ $portalUrl }}
@endif

------------------------------------------------------------
WASPADA PENIPUAN: EVO Group tidak pernah memungut biaya apa pun dalam proses rekrutmen. Email resmi hanya dari domain @evonusabersaudara.co.id.

© {{ date('Y') }} EVO Group · EVO Career — Portal Kandidat
Email otomatis, mohon tidak membalas.
