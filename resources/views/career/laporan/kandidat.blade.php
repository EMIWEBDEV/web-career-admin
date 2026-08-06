{{--
    WEB CAREER — PROFIL KANDIDAT (PDF), bentuk CV dua kolom.

    Dibaca HR, user interview, manajer, sampai direksi — kerap dicetak dan
    dibawa ke ruang wawancara. Karena itu halaman pertamanya disusun seperti CV:
    sidebar gelap berisi identitas yang dicari berulang kali (kontak,
    pendidikan, dokumen), kolom kanan berisi ringkasan lamaran. Rincian jawaban
    formulir menyusul di halaman berikutnya.

    ── KENAPA TIDAK ADA "PERJALANAN SELEKSI" ──────────────────────────────────
    Dicabut atas permintaan. Isinya penilaian internal (hasil per aktivitas,
    catatan penilai) sementara berkas ini paling sering diteruskan ke user
    interview dan manajer lini yang justru TIDAK boleh melihatnya — dan yang
    memang perlu, sudah membacanya di worklist. Datanya tetap tersedia di
    ekspor Excel yang memang untuk diolah tim rekrutmen sendiri.

    ── BATASAN MESIN CETAK (dompdf 3.1.5) ─────────────────────────────────────
      • Tidak ada flexbox, grid, gradient, maupun box-shadow.
      • Dua kolom = SATU TABEL dengan dua sel; latar sel tabel render andal,
        latar pada elemen ber-float sering hilang.
      • `position: fixed` hanya untuk kop/kaki halaman.
      • Blok yang tak boleh terbelah WAJIB diberi page-break-inside: avoid.
      • Warna ditulis HEKSA LANGSUNG, bukan var(). dompdf 3.1.5 memang sudah
        mendukung custom property, tetapi kegagalannya senyap: bila satu var
        tak terbaca, yang keluar hitam pekat di atas navy — dan itu baru
        ketahuan setelah dokumen sampai ke tangan orang.

    Palet EVO yang dipakai (padanan variabel tema):
      --gold #d4a93a   --gold-l #f0c84e  --gold-d #b8902a
      --navy #0f172a   --navy-2 #1e293b  --navy-3 #334155
      --bg #f8fafc     --surf #ffffff    --surf-2 #f1f5f9   --bd #e2e8f0
      --t1 #0f172a     --t2 #475569      --t3 #94a3b8

    Seluruh gambar berupa data URI — dompdf tidak diizinkan menembak URL
    (isRemoteEnabled mati), sebab sebagian isian datang dari kandidat.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Profil Kandidat — {{ $d['kandidat']['nama'] }}</title>
    <style>
        @page { margin: 0; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.2px;
            line-height: 1.55;
            color: #0f172a;
            margin: 0;
        }

        /* ── HALAMAN 1: KARTU CV ────────────────────────────────────────── */
        table.cv { width: 100%; border-collapse: collapse; }
        table.cv > tbody > tr > td { vertical-align: top; padding: 0; }

        /* Sidebar gelap. Tingginya mengikuti isi — pada dompdf, memaksa
           setinggi halaman menghasilkan sel yang meluber ke halaman kedua
           sebagai balok kosong. */
        td.sisi {
            width: 33%;
            background: #0f172a;
            padding: 26px 20px 30px;
            color: #cbd5e1;
        }
        td.utama { width: 67%; background: #ffffff; padding: 26px 26px 30px; }

        /* Foto: bingkai emas tipis. Tanpa border-radius — dompdf
           menggambarnya bergerigi pada gambar berukuran kecil. */
        .foto-bingkai { border: 2px solid #d4a93a; padding: 3px; background: #1e293b; }
        .foto-bingkai img { width: 100%; height: auto; display: block; }

        .sisi-judul {
            font-size: 7.4px; font-weight: bold; color: #d4a93a;
            letter-spacing: 1.8px; text-transform: uppercase;
            border-bottom: 1px solid #334155; padding-bottom: 4px;
            margin: 20px 0 8px;
        }
        .sisi-baris { margin-bottom: 7px; }
        .sisi-lbl { font-size: 6.8px; color: #64748b; letter-spacing: .9px; text-transform: uppercase; }
        .sisi-val { font-size: 9px; color: #f1f5f9; font-weight: bold; word-wrap: break-word; }
        .sisi-val a { color: #f0c84e; text-decoration: none; }

        /* Daftar dokumen di sidebar — tiap baris bisa diklik. */
        .dok { margin-bottom: 5px; }
        .dok a { color: #f1f5f9; text-decoration: none; font-size: 8.4px; font-weight: bold; }
        .dok small { display: block; font-size: 6.8px; color: #64748b; letter-spacing: .3px; }
        .dok .buka { color: #d4a93a; font-size: 6.8px; letter-spacing: .5px; }

        /* ── KOLOM UTAMA ────────────────────────────────────────────────── */
        .merek { border-bottom: 2px solid #d4a93a; padding-bottom: 9px; margin-bottom: 14px; }
        .merek img { height: 26px; }
        .merek .ket { font-size: 6.8px; color: #94a3b8; letter-spacing: 1.7px; text-transform: uppercase; }

        .nama { font-size: 25px; font-weight: bold; color: #0f172a; line-height: 1.1; }
        .jabatan {
            font-size: 11.5px; color: #b8902a; font-weight: bold;
            letter-spacing: .4px; margin-top: 4px;
        }
        .sub { font-size: 8.6px; color: #475569; margin-top: 3px; }

        .lencana {
            display: inline-block; padding: 4px 13px; color: #fff;
            font-size: 8.2px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase;
        }
        .l-lolos  { background: #15803d; }
        .l-gugur  { background: #b91c1c; }
        .l-talent { background: #7c3aed; }
        .l-jalan  { background: #b8902a; }
        .l-netral { background: #475569; }

        .judul {
            font-size: 7.8px; font-weight: bold; color: #0f172a;
            letter-spacing: 1.8px; text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;
            margin: 18px 0 9px;
        }
        .judul span { color: #d4a93a; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data td, table.data th { padding: 5px 9px; vertical-align: top; }
        table.data th {
            background: #f1f5f9; color: #334155; font-size: 7.2px;
            letter-spacing: 1px; text-transform: uppercase; text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        table.data td { border-bottom: 1px solid #f1f5f9; }
        table.data tr.zebra td { background: #f8fafc; }
        .k { color: #94a3b8; width: 38%; }
        .v { color: #0f172a; font-weight: bold; }
        .v a { color: #b8902a; }

        .putus {
            margin-top: 12px; padding: 9px 12px;
            background: #fef2f2; border-left: 3px solid #b91c1c; color: #7f1d1d;
            font-size: 8.6px;
        }
        .putus b { display: block; font-size: 7.6px; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 2px; }

        /* ── HALAMAN LANJUTAN ───────────────────────────────────────────── */
        .lanjutan { padding: 26px 26px 40px; }
        .kop-l {
            border-bottom: 2px solid #d4a93a; padding-bottom: 7px; margin-bottom: 14px;
        }
        .kop-l table { width: 100%; border-collapse: collapse; }
        .kop-l td { border: 0; padding: 0; vertical-align: middle; }
        .kop-l .nm { font-size: 12px; font-weight: bold; color: #0f172a; }
        .kop-l .kd { font-size: 7.4px; color: #94a3b8; letter-spacing: 1.2px; text-align: right; }

        .kosong { color: #94a3b8; font-size: 8.4px; font-style: italic; padding: 8px 0; }
        .kaki-doc {
            margin-top: 22px; padding-top: 8px; border-top: 1px solid #e2e8f0;
            font-size: 6.8px; color: #94a3b8; text-align: center; line-height: 1.6;
        }
    </style>
</head>
<body>

{{-- ══════════════════════ HALAMAN 1 — KARTU CV ══════════════════════════ --}}
<table class="cv">
    <tr>
        {{-- ── SIDEBAR ────────────────────────────────────────────────────
             FOTO HANYA BILA ADA. Tanpa foto, sidebar dimulai langsung dari
             blok kontak — bukan bingkai abu-abu bertuliskan "tanpa foto".
             Placeholder semacam itu terbaca seperti berkas yang gagal dimuat,
             dan pada dokumen yang dibaca direksi itu menjatuhkan kepercayaan
             pada seluruh isinya. --}}
        <td class="sisi">
            @if ($d['kandidat']['foto'])
                <div class="foto-bingkai"><img src="{{ $d['kandidat']['foto'] }}" alt=""></div>
            @endif

            <div class="sisi-judul" @if (! $d['kandidat']['foto']) style="margin-top: 0" @endif>Kontak</div>
            @if ($d['kandidat']['email'])
                <div class="sisi-baris">
                    <div class="sisi-lbl">Email</div>
                    <div class="sisi-val">{{ $d['kandidat']['email'] }}</div>
                </div>
            @endif
            @if ($d['kandidat']['hp'])
                <div class="sisi-baris">
                    <div class="sisi-lbl">Telepon</div>
                    <div class="sisi-val">{{ $d['kandidat']['hp'] }}</div>
                </div>
            @endif
            <div class="sisi-baris">
                <div class="sisi-lbl">Kode Lamaran</div>
                <div class="sisi-val">{{ $d['kandidat']['kodeLamaran'] }}</div>
            </div>

            {{-- PENDIDIKAN. Formulir MT tidak menanyakan tahun lulus — hanya
                 status kemahasiswaan & semester berjalan. Keduanya ikut supaya
                 blok ini tidak berbunyi "—" hanya karena pertanyaannya memang
                 tidak pernah diajukan. --}}
            @php
                $didik = collect([
                    'Jenjang' => $d['kandidat']['jenjang'] ?? null,
                    'Institusi' => $d['kandidat']['kampus'] ?? null,
                    'Jurusan' => $d['kandidat']['jurusan'] ?? null,
                    'IPK' => $d['kandidat']['ipk'] ?? null,
                    'Tahun Lulus' => $d['kandidat']['tahunLulus'] ?? null,
                    'Status' => ($d['kandidat']['statusStudi'] ?? null)
                        . (($d['kandidat']['semester'] ?? null) ? ' · Semester ' . $d['kandidat']['semester'] : ''),
                ])->map(fn ($v) => trim((string) $v))->filter();
            @endphp
            @if ($didik->count())
                <div class="sisi-judul">Pendidikan</div>
                @foreach ($didik as $lbl => $isi)
                    <div class="sisi-baris">
                        <div class="sisi-lbl">{{ $lbl }}</div>
                        <div class="sisi-val">{{ $isi }}</div>
                    </div>
                @endforeach
            @endif

            @php
                $pribadi = collect([
                    'Tanggal Lahir' => $d['kandidat']['tglLahir'] ?? null,
                    'Jenis Kelamin' => $d['kandidat']['jkel'] ?? null,
                ])->filter();
            @endphp
            @if ($pribadi->count())
                <div class="sisi-judul">Data Pribadi</div>
                @foreach ($pribadi as $lbl => $isi)
                    <div class="sisi-baris">
                        <div class="sisi-lbl">{{ $lbl }}</div>
                        <div class="sisi-val">{{ $isi }}</div>
                    </div>
                @endforeach
            @endif

            {{-- DOKUMEN — bisa diklik langsung dari dalam PDF.
                 Tautannya BERTANDA TANGAN & berumur (lihat
                 LaporanKandidat::tautanBerkas): pembaca PDF tidak membawa
                 cookie sesi, jadi tautan ke rute admin biasa akan selalu
                 mendarat di halaman login. --}}
            @php
                $dok = collect($d['formulir'])->flatMap(fn ($f) => $f['dokumen'])
                    ->unique(fn ($x) => $x['field'] . '|' . $x['nama'])->values();
            @endphp
            @if ($dok->count())
                <div class="sisi-judul">Dokumen ({{ $dok->count() }})</div>
                @foreach ($dok as $x)
                    <div class="dok">
                        @if ($x['tautan'])
                            <a href="{{ $x['tautan'] }}">{{ ucwords(str_replace(['_', '-'], ' ', $x['field'])) }}</a>
                            <small>{{ \Illuminate\Support\Str::limit($x['nama'], 30) }}</small>
                            <span class="buka">▸ KLIK UNTUK MEMBUKA</span>
                        @else
                            <span style="color:#f1f5f9; font-size: 8.4px; font-weight: bold">{{ ucwords(str_replace(['_', '-'], ' ', $x['field'])) }}</span>
                            <small>{{ \Illuminate\Support\Str::limit($x['nama'], 30) }}</small>
                        @endif
                    </div>
                @endforeach
            @endif
        </td>

        {{-- ── KOLOM UTAMA ────────────────────────────────────────────── --}}
        <td class="utama">
            <div class="merek">
                @if ($logo)<img src="{{ $logo }}" alt="EVO Group">@endif
                <div class="ket">Profil Kandidat &middot; Rahasia / Confidential</div>
            </div>

            <div class="nama">{{ $d['kandidat']['nama'] ?: '—' }}</div>
            <div class="jabatan">{{ $d['lamaran']['posisi'] ?: $d['lamaran']['program'] ?: '—' }}</div>
            <div class="sub">
                {{ collect([$d['lamaran']['departemen'], $d['lamaran']['level'], $d['lamaran']['lokasi']])->filter()->implode(' · ') ?: $d['lamaran']['program'] }}
            </div>

            <div style="margin-top: 13px">
                <span class="lencana {{ $nadaHasil }}">{{ $labelHasil }}</span>
                <span style="font-size: 7.6px; color: #94a3b8; padding-left: 9px">
                    Melamar <b style="color:#475569">{{ $tglLamar ?: '—' }}</b>
                </span>
            </div>

            <div class="judul"><span>//</span> Ringkasan Lamaran</div>
            <table class="data">
                <tr><td class="k">Program</td><td class="v">{{ $d['lamaran']['program'] ?: '—' }}</td></tr>
                <tr class="zebra"><td class="k">Posisi Dilamar</td><td class="v">{{ $d['lamaran']['posisi'] ?: '—' }}</td></tr>
                <tr><td class="k">Departemen</td><td class="v">{{ $d['lamaran']['departemen'] ?: '—' }}</td></tr>
                <tr class="zebra"><td class="k">Level</td><td class="v">{{ $d['lamaran']['level'] ?: '—' }}</td></tr>
                <tr><td class="k">Penempatan</td><td class="v">{{ $d['lamaran']['lokasi'] ?: '—' }}</td></tr>
                <tr class="zebra"><td class="k">Status Lamaran</td><td class="v">{{ str_replace('_', ' ', (string) $d['lamaran']['status']) ?: '—' }}</td></tr>
            </table>

            @if ($d['lamaran']['status'] === 'GUGUR' && ($d['lamaran']['gugurDi'] || $d['lamaran']['alasanGugur']))
                <div class="putus">
                    <b>Berhenti di tahap {{ $d['lamaran']['gugurDi'] ?: '—' }}</b>
                    {{ $d['lamaran']['alasanGugur'] ?: 'Tanpa alasan tercatat.' }}
                </div>
            @endif

            {{-- Cuplikan jawaban formulir TERBARU ditarik ke halaman muka:
                 delapan pertanyaan pertama biasanya identitas & pendidikan,
                 dan itulah yang ditanya lebih dulu di ruang wawancara. --}}
            @php
                $ringkas = collect($d['formulir'])->last()['isian'] ?? [];
                $ringkas = collect($ringkas)->filter(fn ($j) => ! $j['berkas'] && trim((string) $j['nilai']) !== '')->take(8);
            @endphp
            @if ($ringkas->count())
                <div class="judul"><span>//</span> Sekilas Data Diri</div>
                <table class="data">
                    @foreach ($ringkas->values() as $n => $j)
                        <tr class="{{ $n % 2 ? 'zebra' : '' }}">
                            <td class="k">{{ $j['label'] }}</td>
                            <td class="v">{{ \Illuminate\Support\Str::limit($j['nilai'], 90) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </td>
    </tr>
</table>

{{-- ══════════════════ HALAMAN LANJUTAN — JAWABAN FORMULIR ════════════════ --}}
@foreach ($d['formulir'] as $i => $form)
    <div style="page-break-before: always"></div>
    <div class="lanjutan">
        <div class="kop-l">
            <table>
                <tr>
                    <td>
                        <div class="nm">{{ $d['kandidat']['nama'] }}</div>
                        <div style="font-size: 7.4px; color: #94a3b8; letter-spacing: 1.2px; text-transform: uppercase">
                            {{ $form['label'] }}
                        </div>
                    </td>
                    <td class="kd">
                        {{ $d['kandidat']['kodeLamaran'] }}<br>
                        Dikirim {{ \Illuminate\Support\Str::of($form['waktuKirim'])->substr(0, 16) }}
                    </td>
                </tr>
            </table>
        </div>

        @if (count($form['isian']))
            <table class="data">
                @foreach ($form['isian'] as $n => $j)
                    <tr class="{{ $n % 2 ? 'zebra' : '' }}">
                        <td class="k">{{ $j['label'] }}</td>
                        <td class="v">
                            @if ($j['berkas'])
                                @if ($j['berkas']['tautan'])
                                    <a href="{{ $j['berkas']['tautan'] }}">{{ $j['berkas']['nama'] }}</a>
                                @else
                                    {{ $j['berkas']['nama'] }}
                                @endif
                                <span style="color:#94a3b8; font-weight: normal">
                                    &middot; {{ $j['berkas']['status'] ?: 'belum diperiksa' }}
                                </span>
                            @else
                                {{ $j['nilai'] !== '' ? $j['nilai'] : '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @else
            <p class="kosong">Formulir ini terkirim tanpa isian.</p>
        @endif

        @if ($loop->last)
            <p class="kaki-doc">
                Dokumen internal rekrutmen EVO Group — memuat data pribadi kandidat, dilarang disebarkan
                di luar keperluan seleksi.<br>
                Dihasilkan otomatis pada {{ $d['dicetak'] }}; tidak memerlukan tanda tangan.
                Tautan dokumen di dalamnya berlaku 30 hari sejak dicetak.
            </p>
        @endif
    </div>
@endforeach

@if (! count($d['formulir']))
    <div class="lanjutan">
        <p class="kaki-doc">
            Dokumen internal rekrutmen EVO Group — memuat data pribadi kandidat, dilarang disebarkan
            di luar keperluan seleksi.<br>
            Dihasilkan otomatis pada {{ $d['dicetak'] }}; tidak memerlukan tanda tangan.
        </p>
    </div>
@endif

</body>
</html>
