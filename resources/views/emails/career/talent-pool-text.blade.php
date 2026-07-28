Halo {{ $nama }},

@if($jenis === 'DITARIK')
Kabar baik! Berdasarkan rekam jejak seleksimu, kamu dipertimbangkan untuk kesempatan baru:
{{ $posisiTujuan ?: 'Posisi baru' }}

Proses seleksimu akan berlanjut. Pantau perkembangannya di portal:
{{ $portalUrl }}
@else
Terima kasih atas partisipasimu dalam proses seleksi{{ $posisi ? ' untuk posisi ' . $posisi : '' }} di EVO Group. Untuk kesempatan kali ini kamu belum kami pilih, namun profilmu kami nilai potensial.

Kami menyimpan datamu di Talent Pool EVO Career, sehingga kamu bisa kami pertimbangkan lebih awal saat ada lowongan yang cocok — tanpa mendaftar dari awal.

Portal Saya: {{ $portalUrl }}
@endif

— EVO Career
(Email otomatis, mohon tidak dibalas.)
