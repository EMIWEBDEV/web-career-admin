{{--
    LAPORAN KANDIDAT — dokumen internal HC EVO Group.

    DIRENDER OLEH DOMPDF, dan itu menentukan seluruh cara berkas ini ditulis:
    tidak ada flexbox, tidak ada grid, tidak ada CSS variable. Tata letak
    memakai TABEL — bukan karena kuno, tapi karena hanya itu yang dihitung
    dompdf dengan benar pada dokumen bertingkat seperti ini.

    Gambar (logo & foto verifikasi) DITANAM sebagai data URI oleh
    App\Support\Career\LaporanKandidat: dompdf mengambil gambar jarak jauh lewat
    permintaan HTTP-nya sendiri, dan berkas kita ada di bucket berwenang —
    permintaannya akan ditolak lalu gambarnya hilang tanpa satu pun galat.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kandidat — {{ $d['kandidat']['nama'] }}</title>
    <style>
        @page { margin: 92px 38px 74px; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5px;
            color: #1e293b;
            line-height: 1.5;
        }

        /* ── KOP & KAKI: diulang di SETIAP halaman ──────────────────────────
           Laporan ini beredar sebagai lembaran tercetak; halaman ke-3 yang
           terlepas dari tumpukannya harus tetap bisa dikenali milik siapa dan
           bahwa isinya rahasia. */
        .kop { position: fixed; top: -74px; left: 0; right: 0; height: 62px; }
        .kaki { position: fixed; bottom: -56px; left: 0; right: 0; height: 40px; }

        .kop-tbl, .kaki-tbl { width: 100%; border-collapse: collapse; }
        .kop-logo { width: 96px; vertical-align: middle; }
        .kop-logo img { height: 46px; }
        .kop-teks { text-align: right; vertical-align: middle; font-size: 8px; color: #64748b; line-height: 1.55; }
        .kop-teks b { display: block; font-size: 11px; color: #0f172a; letter-spacing: .02em; }
        .kop-garis { height: 3px; background: #1e40af; margin-top: 8px; }

        .kaki-rahasia {
            background: #1e40af; color: #fff; text-align: center;
            font-size: 7.5px; letter-spacing: .06em; padding: 6px 0; font-weight: bold;
        }
        .kaki-meta { font-size: 7px; color: #94a3b8; padding-top: 5px; }
        .kaki-meta td { padding: 0; }

        /* ── JUDUL DOKUMEN ─────────────────────────────────────────────── */
        .judul {
            background: #1e40af; color: #fff; text-align: center;
            padding: 9px; font-size: 13px; font-weight: bold; letter-spacing: .04em;
            border-radius: 3px;
        }
        .subjudul { text-align: center; font-size: 8px; color: #64748b; margin: 5px 0 14px; letter-spacing: .08em; }

        /* ── IDENTITAS: biodata kiri, FOTO KANAN ────────────────────────── */
        .id-tbl { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .id-tbl > tbody > tr > td { vertical-align: top; }
        .id-kiri { padding-right: 12px; }
        .id-kanan { width: 132px; }

        .bio { width: 100%; border-collapse: collapse; border: 1px solid #dbe1ea; }
        .bio td { padding: 5px 9px; border-bottom: 1px solid #eef1f6; font-size: 9px; }
        .bio tr:last-child td { border-bottom: 0; }
        .bio .k { width: 108px; color: #64748b; background: #f8fafc; }
        .bio .v { color: #0f172a; font-weight: bold; }

        /* Kartu foto — dibingkai supaya terbaca sebagai dokumen identitas,
           bukan hiasan yang kebetulan ditempel di pojok. */
        .foto-kartu { border: 1px solid #dbe1ea; border-radius: 3px; overflow: hidden; }
        .foto-kartu .cap {
            background: #1e40af; color: #fff; font-size: 6.5px; letter-spacing: .1em;
            text-align: center; padding: 4px 0; font-weight: bold;
        }
        .foto-kartu .isi { padding: 7px; text-align: center; background: #fff; }
        .foto-kartu img { width: 112px; height: 140px; object-fit: cover; }
        .foto-kosong {
            width: 112px; height: 140px; background: #f1f5f9; color: #94a3b8;
            font-size: 7.5px; text-align: center; line-height: 140px;
        }

        /* ── LENCANA HASIL ─────────────────────────────────────────────── */
        .hasil-kotak { border: 1px solid #dbe1ea; border-radius: 3px; margin-top: 8px; overflow: hidden; }
        .hasil-kotak .cap {
            background: #f1f5f9; color: #475569; font-size: 6.5px; letter-spacing: .1em;
            text-align: center; padding: 4px 0; font-weight: bold; border-bottom: 1px solid #dbe1ea;
        }
        .lencana {
            display: block; text-align: center; color: #fff; font-weight: bold;
            font-size: 11px; letter-spacing: .05em; padding: 8px 4px;
        }
        .l-lolos   { background: #15803d; }
        .l-gugur   { background: #b91c1c; }
        .l-jalan   { background: #b45309; }
        .l-netral  { background: #475569; }
        .l-talent  { background: #a16207; }

        /* ── SEKSI ─────────────────────────────────────────────────────── */
        .seksi { margin-top: 16px; }
        .seksi-judul {
            background: #1e40af; color: #fff; font-size: 9.5px; font-weight: bold;
            letter-spacing: .05em; padding: 6px 10px; border-radius: 3px 3px 0 0;
        }
        .seksi-isi { border: 1px solid #dbe1ea; border-top: 0; border-radius: 0 0 3px 3px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: #f1f5f9; color: #334155; font-size: 7.5px; letter-spacing: .07em;
            text-align: left; padding: 6px 9px; border-bottom: 1px solid #dbe1ea;
        }
        table.data td { padding: 6px 9px; border-bottom: 1px solid #eef1f6; font-size: 8.5px; vertical-align: top; }
        table.data tr:last-child td { border-bottom: 0; }
        .num { width: 26px; text-align: center; color: #94a3b8; font-weight: bold; }

        .pil {
            display: inline-block; padding: 2px 7px; border-radius: 8px;
            font-size: 7px; font-weight: bold; letter-spacing: .04em;
        }
        .p-lolos  { background: #dcfce7; color: #15803d; }
        .p-gugur  { background: #fee2e2; color: #b91c1c; }
        .p-jalan  { background: #fef3c7; color: #b45309; }
        .p-diam   { background: #f1f5f9; color: #64748b; }
        .p-internal { background: #ede9fe; color: #6d28d9; }

        .akt { color: #475569; font-size: 8px; }
        .akt-nilai { font-weight: bold; color: #1e40af; }
        .catatan { color: #64748b; font-size: 7.5px; font-style: italic; }

        /* Isian formulir: dua kolom sejajar, label di atas nilai. */
        .isian { width: 100%; border-collapse: collapse; }
        .isian td { width: 50%; padding: 6px 9px; border-bottom: 1px solid #eef1f6; vertical-align: top; }
        .isian .lbl { display: block; font-size: 6.8px; letter-spacing: .07em; color: #94a3b8; text-transform: uppercase; }
        .isian .val { display: block; font-size: 9px; color: #0f172a; font-weight: bold; margin-top: 1px; word-wrap: break-word; }

        .kosong { padding: 12px; text-align: center; color: #94a3b8; font-size: 8px; }
        /* Judul seksi tidak boleh tertinggal sendirian di dasar halaman. */
        .seksi, tr { page-break-inside: avoid; }
    </style>
</head>
<body>

{{-- ══ KOP (berulang tiap halaman) ══ --}}
<div class="kop">
    <table class="kop-tbl">
        <tr>
            <td class="kop-logo">
                @if ($logo)<img src="{{ $logo }}" alt="EVO Group">@endif
            </td>
            <td class="kop-teks">
                <b>EVO GROUP</b>
                Jl. Sapta Marga No.21, Bukit Sangkal, Kalidoni, Palembang, Sumatera Selatan 30114<br>
                www.evonusabersaudara.co.id &nbsp;|&nbsp; evonusabersaudara@co.id
            </td>
        </tr>
    </table>
    <div class="kop-garis"></div>
</div>

{{-- ══ KAKI (berulang tiap halaman) ══ --}}
<div class="kaki">
    <div class="kaki-rahasia">
        DOKUMEN RAHASIA — DILARANG MENCETAK ATAU MENYEBARLUASKAN TANPA SEIZIN HC EVO GROUP
    </div>
    <table class="kaki-meta">
        <tr>
            <td>{{ $d['kandidat']['kodeLamaran'] }} · Dicetak {{ $d['dicetak'] }}</td>
            <td style="text-align:right">Halaman <span class="pagenum"></span></td>
        </tr>
    </table>
</div>

{{-- ══ JUDUL ══ --}}
<div class="judul">LAPORAN KANDIDAT SELEKSI</div>
<div class="subjudul">{{ strtoupper($d['lamaran']['program'] ?? '—') }}</div>

{{-- ══ IDENTITAS + FOTO ══ --}}
<table class="id-tbl">
    <tr>
        <td class="id-kiri">
            <table class="bio">
                <tr><td class="k">Nama Kandidat</td><td class="v">{{ $d['kandidat']['nama'] ?: '—' }}</td></tr>
                <tr><td class="k">Kode Lamaran</td><td class="v">{{ $d['kandidat']['kodeLamaran'] ?: '—' }}</td></tr>
                <tr><td class="k">Posisi Dilamar</td><td class="v">{{ $d['lamaran']['posisi'] ?: ($d['lamaran']['program'] ?: '—') }}</td></tr>
                <tr><td class="k">Level / Departemen</td><td class="v">{{ trim(($d['lamaran']['level'] ?: '—') . ' / ' . ($d['lamaran']['departemen'] ?: '—')) }}</td></tr>
                <tr><td class="k">Lokasi</td><td class="v">{{ $d['lamaran']['lokasi'] ?: '—' }}</td></tr>
                <tr><td class="k">Email</td><td class="v">{{ $d['kandidat']['email'] ?: '—' }}</td></tr>
                <tr><td class="k">No. Handphone</td><td class="v">{{ $d['kandidat']['hp'] ?: '—' }}</td></tr>
                <tr><td class="k">Institusi</td><td class="v">{{ $d['kandidat']['kampus'] ?: '—' }}</td></tr>
                <tr><td class="k">Tahun Lulus</td><td class="v">{{ $d['kandidat']['tahunLulus'] ?: '—' }}</td></tr>
                <tr><td class="k">Tanggal Melamar</td><td class="v">{{ $tglLamar ?: '—' }}</td></tr>
            </table>
        </td>

        {{-- FOTO VERIFIKASI di ujung kanan — permintaan eksplisit, dan memang
             tempatnya: pembaca laporan mencocokkan wajah dengan identitas di
             sebelahnya tanpa perlu membalik halaman. --}}
        <td class="id-kanan">
            <div class="foto-kartu">
                <div class="cap">FOTO VERIFIKASI</div>
                <div class="isi">
                    @if ($d['kandidat']['foto'])
                        <img src="{{ $d['kandidat']['foto'] }}" alt="Foto verifikasi">
                    @else
                        <div class="foto-kosong">Tidak ada foto</div>
                    @endif
                </div>
            </div>

            <div class="hasil-kotak">
                <div class="cap">STATUS AKHIR</div>
                <span class="lencana {{ $nadaHasil }}">{{ $labelHasil }}</span>
            </div>
        </td>
    </tr>
</table>

{{-- ══ PERJALANAN SELEKSI ══ --}}
<div class="seksi">
    <div class="seksi-judul">PERJALANAN SELEKSI</div>
    <div class="seksi-isi">
        <table class="data">
            <thead>
                <tr>
                    <th class="num">#</th>
                    <th>TAHAP</th>
                    <th style="width:78px">HASIL</th>
                    <th style="width:200px">AKTIVITAS &amp; NILAI</th>
                    <th style="width:120px">DIPUTUS</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($d['tahap'] as $t)
                <tr>
                    <td class="num">{{ $t['urutan'] }}</td>
                    <td>
                        <b>{{ $t['label'] }}</b>
                        @if ($t['ditahan'])<br><span class="pil p-diam">DITAHAN</span>@endif
                        @if ($t['catatan'])<br><span class="catatan">{{ \Illuminate\Support\Str::limit($t['catatan'], 150) }}</span>@endif
                    </td>
                    <td>
                        @php
                            $ph = $t['hasil'] ?: $t['status'];
                            $pk = match (true) {
                                $t['hasil'] === 'LULUS' => 'p-lolos',
                                $t['hasil'] === 'GUGUR' => 'p-gugur',
                                $t['status'] === 'BERJALAN' => 'p-jalan',
                                default => 'p-diam',
                            };
                        @endphp
                        <span class="pil {{ $pk }}">{{ $ph ?: '—' }}</span>
                    </td>
                    <td>
                        @forelse ($t['aktivitas'] as $a)
                            <div class="akt">
                                • {{ $a['label'] }}
                                @if ($a['nilai'] !== null)<span class="akt-nilai">{{ rtrim(rtrim(number_format($a['nilai'], 2, ',', '.'), '0'), ',') }}</span>@endif
                                @if ($a['hasil']) — {{ $a['hasil'] }}@elseif ($a['status']) — {{ strtolower($a['status']) }}@endif
                                @if ($a['mcuStatus']) ({{ $a['mcuStatus'] }})@endif
                                @if ($a['internal'])<span class="pil p-internal">INTERNAL</span>@endif
                            </div>
                        @empty
                            <span class="catatan">—</span>
                        @endforelse
                    </td>
                    <td>
                        @if ($t['diputusAt'])
                            {{ \Illuminate\Support\Carbon::parse($t['diputusAt'])->format('d M Y H:i') }}<br>
                            <span class="catatan">{{ $t['diputusOleh'] ?: '—' }}</span>
                        @else
                            <span class="catatan">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="kosong">Belum ada tahap yang tercatat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ══ DATA FORMULIR ══ --}}
@foreach ($d['formulir'] as $f)
    <div class="seksi">
        <div class="seksi-judul">
            DATA {{ strtoupper($f['label']) }}
            <span style="float:right;font-weight:normal;font-size:8px">
                Dikirim {{ $f['waktuKirim'] ? \Illuminate\Support\Carbon::parse($f['waktuKirim'])->format('d M Y H:i') : '—' }}
            </span>
        </div>
        <div class="seksi-isi">
            @if (count($f['isian']))
                <table class="isian">
                    @foreach (array_chunk($f['isian'], 2) as $baris)
                        <tr>
                            @foreach ($baris as $j)
                                <td>
                                    <span class="lbl">{{ $j['label'] }}</span>
                                    @if ($j['berkas'] || \Illuminate\Support\Str::startsWith($j['key'], ['dok_', 'file_', 'berkas_', 'upload_']))
                                        {{-- Berkas dicetak sebagai ADA/TIDAK: nama file tidak
                                             berarti apa pun di atas kertas, dan berkasnya
                                             sendiri tidak ikut tercetak. --}}
                                        <span class="val">
                                            <span class="pil {{ $j['berkas'] ? 'p-lolos' : 'p-diam' }}">
                                                {{ $j['berkas'] ? 'TERLAMPIR' : 'BELUM ADA' }}
                                            </span>
                                        </span>
                                    @else
                                        <span class="val">{{ $j['nilai'] !== '' ? $j['nilai'] : '—' }}</span>
                                    @endif
                                </td>
                            @endforeach
                            @if (count($baris) === 1)<td></td>@endif
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="kosong">Formulir ini tidak berisi jawaban.</div>
            @endif
        </div>
    </div>

    @if (count($f['dokumen']))
        <div class="seksi">
            <div class="seksi-judul">DOKUMEN — {{ strtoupper($f['label']) }}</div>
            <div class="seksi-isi">
                <table class="data">
                    <thead>
                        <tr>
                            <th class="num">#</th>
                            <th>JENIS DOKUMEN</th>
                            <th>NAMA BERKAS</th>
                            <th style="width:96px">VERIFIKASI</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($f['dokumen'] as $i => $b)
                        <tr>
                            <td class="num">{{ $i + 1 }}</td>
                            <td>{{ ucwords(str_replace(['_', '-'], ' ', $b['field'])) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($b['nama'], 58) }}</td>
                            <td>
                                <span class="pil {{ $b['status'] === 'VALID' ? 'p-lolos' : 'p-diam' }}">
                                    {{ $b['status'] ?: 'BELUM' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach

{{-- Nomor halaman: dompdf mengisinya saat render, bukan Blade. --}}
<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            $canvas->text(508, 786, "$pageNumber / $pageCount", $font, 7, [0.58, 0.64, 0.72]);
        });
    }
</script>

</body>
</html>
