<?php

namespace App\Support\Sinkron\Penangan;

use Closure;

/**
 * Hasil satu penangan peristiwa masuk.
 *
 *   ref         penanda hasil di Admin DB (mis. "lamaran:123") — Hasil_Ref
 *   keterangan  kalimat untuk kandidat (Outbox publik → Hasil_Keterangan)
 *   lamaran     id lamaran ADMIN yang potret portalnya perlu disegarkan
 *   sesudah     pekerjaan sesudah COMMIT (surel, HCLearn) — gagalnya tidak
 *               membatalkan peristiwa yang sudah tersimpan
 */
final class HasilPenanganan
{
    /** @param  list<int>  $lamaran  @param  list<Closure>  $sesudah */
    public function __construct(
        public ?string $ref = null,
        public ?string $keterangan = null,
        public array $lamaran = [],
        public array $sesudah = [],
    ) {}

    public static function ok(?string $ref = null, ?string $keterangan = null): self
    {
        return new self($ref, $keterangan);
    }

    public function segarkan(?int $lamaranId): self
    {
        if ($lamaranId) {
            $this->lamaran[] = $lamaranId;
        }

        return $this;
    }

    public function lalu(Closure $kerja): self
    {
        $this->sesudah[] = $kerja;

        return $this;
    }
}
