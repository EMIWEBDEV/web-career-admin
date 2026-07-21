<!-- WEB CAREER — Admin: Master Jadwal Kegiatan = timeline lengkap (non-seleksi + tahap seleksi dari alur). -->
<template>
    <Head><title>Master Jadwal Kegiatan - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Master Jadwal Kegiatan</h1>
                <p>Timeline <b>lengkap</b> satu gelombang: aktivitas pendukung (rapat, campaign, evaluasi) <b>+ tahapan seleksi</b> dari alur. Sistem menghitung durasi & menjaga sinkron dengan alur.</p>
            </div>
            <div class="wca-phead__actions">
                <button class="wca-btn wca-btn--primary" @click="openCreate"><i class="bi bi-plus-lg"></i> Jadwal Baru</button>
            </div>
        </div>

        <div class="wca-note wca-note--info">
            <i class="bi bi-calendar3-range"></i>
            <span><b>Superset alur:</b> jadwal boleh punya lebih banyak baris daripada tahap seleksi. Baris <b>Seleksi</b> (dari alur) wajib lengkap, urut & tak tumpang tindih; baris <b>non-seleksi</b> (rapat/campaign/…) bebas & boleh paralel. Semua bertanggal — sistem hitung lama hari.</span>
        </div>

        <div class="wca-acc">
            <div v-for="j in list" :key="j.id" class="wca-acc__item" :class="{ open: open === j.id }">
                <button class="wca-acc__head" @click="toggle(j.id)">
                    <span class="wca-acc__chev"><i class="bi bi-chevron-right"></i></span>
                    <span class="wca-acc__title">
                        <strong>{{ j.kegiatan }}</strong>
                        <small>{{ j.id }} · Alur {{ j.alur }} · Siklus {{ siklusLabel(j.siklus) }}</small>
                    </span>
                    <span class="wca-acc__tags">
                        <span class="wca-badge" :class="katBadge(j.kategori)">{{ j.kategori }}</span>
                        <span class="wca-badge wca-b--slate">{{ seleksiCount(j) }} seleksi · {{ j.agenda.length }} total</span>
                        <span class="wca-badge" :class="statusBadge(j.status)">{{ j.status }}</span>
                    </span>
                    <span class="wca-acc__kpi"><b>{{ jTotal(j) }}</b><small>hari</small></span>
                </button>

                <div class="wca-acc__body">
                    <div class="wca-acc__inner">
                        <div class="wca-fsection__label" style="margin-bottom:.4rem"><i class="bi bi-bar-chart-steps"></i> Agenda ({{ j.agenda.length }} baris)</div>
                        <div class="wca-jrange"><i class="bi bi-calendar-event"></i> {{ fmtFull(jBase(j)) }} — {{ fmtFull(jEnd(j)) }} <span class="wca-badge wca-b--slate">{{ jTotal(j) }} hari</span></div>
                        <div class="wca-gantt">
                            <div v-for="(s, i) in j.agenda" :key="i" class="wca-gantt__row">
                                <div class="wca-gantt__lbl">
                                    <span class="wca-jtag" :class="jenisKelas(s.jenis)"><i class="bi" :class="jenisIkon(s.jenis)"></i> {{ jenisLabel(s.jenis) }}</span>
                                    {{ s.label }}
                                </div>
                                <div class="wca-gantt__track">
                                    <div class="wca-gantt__bar" :class="jenisKelas(s.jenis)" :style="barStyle(s, j)">
                                        <span>{{ fmt(s.mulai) }}–{{ fmt(s.selesai) }}</span>
                                    </div>
                                </div>
                                <div class="wca-gantt__dur">{{ lamaHari(s.mulai, s.selesai) }} hari</div>
                            </div>
                        </div>

                        <div class="wca-acc__foot">
                            <Link href="/karir/program-kegiatan" class="wca-btn wca-btn--ghost wca-btn--sm"><i class="bi bi-diagram-3"></i> Pakai di Program</Link>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="!list.length" class="wca-empty"><i class="bi bi-calendar3-range"></i><h4>Belum ada jadwal</h4></div>
        </div>

        <!-- Modal buat jadwal — builder inline (agenda: seleksi terkunci + non-seleksi bebas) -->
        <AdminModal :show="showCreate" title="Buat Jadwal Kegiatan" subtitle="Pilih alur (tahap seleksi terisi otomatis), tambah aktivitas lain, lalu isi tanggal" icon="bi-calendar3-range" lg save-label="Simpan Jadwal" @close="showCreate = false" @save="saveCreate">
            <div class="wca-fsection">
                <div class="wca-fsection__label"><i class="bi bi-calendar3-range"></i> Detail Jadwal</div>
                <div class="wca-form">
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Jenis Kegiatan</label>
                            <el-select filterable v-model="form.kegiatanId" placeholder="Pilih jenis kegiatan" @change="onKegiatanChange">
                                <el-option v-for="k in kegiatanOptions" :key="k.id" :label="`${k.nama} (${k.kategori})`" :value="k.id" />
                            </el-select>
                        </div>
                        <div><label class="wca-field-lbl">Alur Seleksi (sumber tahap seleksi)</label>
                            <el-select filterable v-model="form.alurId" placeholder="Pilih alur seleksi" @change="onAlurChange">
                                <el-option v-for="a in alurByKat" :key="a.id" :label="a.nama" :value="a.id" />
                            </el-select>
                        </div>
                    </div>
                    <div><label class="wca-field-lbl">Siklus <small style="font-weight:600;color:var(--muted)">(penanda, opsional)</small></label>
                        <el-select filterable v-model="form.siklus" placeholder="Seberapa sering berulang" style="max-width:280px">
                            <el-option v-for="s in siklusOptions" :key="s.kode" :label="s.nama" :value="s.kode" />
                        </el-select>
                    </div>
                    <div class="wca-flow__note" style="margin:0"><i class="bi bi-lock"></i> Baris <b>Seleksi</b> otomatis dari alur — terkunci (tak bisa hapus/rename) agar sinkron. Anda hanya isi tanggalnya & boleh menambah aktivitas lain.</div>
                </div>
            </div>

            <div class="wca-fsection">
                <div class="wca-fsection__label">
                    <i class="bi bi-list-ol"></i> Agenda ({{ form.agenda.length }})
                    <span v-if="formTotal" class="wca-fsection__hint">· {{ formTotal }} hari</span>
                </div>
                <div class="wca-form">
                    <div v-for="(s, i) in form.agenda" :key="i" class="wca-stagecard" :class="{ 'is-locked': s.jenis === 'SELEKSI' }">
                        <div class="wca-stagecard__num" :class="jenisKelas(s.jenis)">{{ i + 1 }}</div>
                        <div class="wca-stagecard__body">
                            <div class="wca-frow">
                                <div>
                                    <label class="wca-field-lbl">Nama <span class="wca-jtag" :class="jenisKelas(s.jenis)" style="margin-left:.3rem"><i class="bi" :class="jenisIkon(s.jenis)"></i> {{ jenisLabel(s.jenis) }}</span></label>
                                    <el-input v-if="s.jenis !== 'SELEKSI'" v-model="s.label" placeholder="mis. Rapat Persiapan" />
                                    <div v-else class="wca-lockedname"><i class="bi bi-lock-fill"></i> {{ s.label }}</div>
                                </div>
                                <div v-if="s.jenis !== 'SELEKSI'"><label class="wca-field-lbl">Jenis Aktivitas</label>
                                    <el-select filterable v-model="s.jenis" placeholder="Pilih jenis">
                                        <el-option v-for="jn in jenisNonSeleksi" :key="jn.kode" :label="jn.nama" :value="jn.kode" />
                                    </el-select>
                                </div>
                            </div>
                            <div class="wca-frow">
                                <div><label class="wca-field-lbl">Tanggal Mulai</label>
                                    <el-date-picker v-model="s.mulai" type="date" value-format="YYYY-MM-DD" placeholder="Mulai" style="width:100%" @change="() => onDates(i)" />
                                </div>
                                <div><label class="wca-field-lbl">Tanggal Selesai</label>
                                    <el-date-picker v-model="s.selesai" type="date" value-format="YYYY-MM-DD" placeholder="Selesai" style="width:100%" @change="() => onDates(i)" />
                                </div>
                            </div>
                            <div class="wca-stagedur" :class="{ bad: rowErr(s) }">
                                <i class="bi" :class="rowErr(s) ? 'bi-exclamation-triangle' : 'bi-hourglass-split'"></i>
                                <template v-if="rowErr(s)">{{ rowErr(s) }}</template>
                                <template v-else-if="s.mulai && s.selesai"><b>{{ lamaHari(s.mulai, s.selesai) }} hari</b> · {{ fmt(s.mulai) }} – {{ fmt(s.selesai) }}</template>
                                <template v-else>Tetapkan tanggal — sistem hitung otomatis</template>
                            </div>
                        </div>
                        <div class="wca-stagecard__actions">
                            <button class="wca-iconbtn" title="Naik" :disabled="i === 0" @click="move(i, -1)"><i class="bi bi-chevron-up"></i></button>
                            <button class="wca-iconbtn" title="Turun" :disabled="i === form.agenda.length - 1" @click="move(i, 1)"><i class="bi bi-chevron-down"></i></button>
                            <button v-if="s.jenis !== 'SELEKSI'" class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="removeRow(i)"><i class="bi bi-trash"></i></button>
                            <span v-else class="wca-iconbtn" style="opacity:.4;cursor:not-allowed" title="Tahap seleksi terkunci"><i class="bi bi-lock"></i></span>
                        </div>
                    </div>

                    <button class="wca-addrow" type="button" @click="addRow"><i class="bi bi-plus-circle"></i> Tambah Aktivitas (non-seleksi)</button>

                    <div v-if="syncErrors.length" class="wca-note wca-note--danger" style="margin:.7rem 0 0">
                        <i class="bi bi-shield-exclamation"></i>
                        <span><b>Belum sinkron:</b><br /><span v-for="(e, i) in syncErrors" :key="i">• {{ e }}<br /></span></span>
                    </div>
                </div>
            </div>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AdminModal from '@career/AdminModal.vue';
import { statusBadge } from './careerAdmin';

const props = defineProps({
    jadwal: { type: Array, default: () => [] },
    kegiatanOptions: { type: Array, default: () => [] },
    alur: { type: Array, default: () => [] },
    siklusOptions: { type: Array, default: () => [] },
});

// Jenis baris agenda
const jenisNonSeleksi = [
    { kode: 'RAPAT', nama: 'Rapat / Persiapan' },
    { kode: 'CAMPAIGN', nama: 'Campaign / Publikasi' },
    { kode: 'SOSIALISASI', nama: 'Sosialisasi / Job Fair' },
    { kode: 'EVALUASI', nama: 'Evaluasi / Laporan' },
    { kode: 'LAINNYA', nama: 'Lainnya' },
];
function jenisLabel(j) { return j === 'SELEKSI' ? 'Seleksi' : (jenisNonSeleksi.find((x) => x.kode === j)?.nama.split(' / ')[0] || j); }
function jenisKelas(j) { return { SELEKSI: 'ag-sel', RAPAT: 'ag-rap', CAMPAIGN: 'ag-cmp', SOSIALISASI: 'ag-sos', EVALUASI: 'ag-eva', LAINNYA: 'ag-oth' }[j] || 'ag-oth'; }
function jenisIkon(j) { return { SELEKSI: 'bi-funnel', RAPAT: 'bi-people', CAMPAIGN: 'bi-megaphone', SOSIALISASI: 'bi-mortarboard', EVALUASI: 'bi-clipboard-check', LAINNYA: 'bi-dot' }[j] || 'bi-dot'; }
function siklusLabel(s) { return props.siklusOptions.find((o) => o.kode === s)?.nama || s; }
function katBadge(k) { return { MT: 'wca-b--gold', INTERNSHIP: 'wca-b--green', REKRUTMEN: 'wca-b--sky' }[k] || 'wca-b--sky'; }

const list = reactive(props.jadwal.map((j) => ({ ...j, agenda: j.agenda.map((s) => ({ ...s })) })));
const open = ref(list[0]?.id ?? null);
function toggle(id) { open.value = open.value === id ? null : id; }
function seleksiCount(j) { return j.agenda.filter((s) => s.jenis === 'SELEKSI').length; }

// ── Tanggal helpers ──
function toDate(s) { return s ? new Date(s + 'T00:00:00') : null; }
function lamaHari(a, b) { const d1 = toDate(a), d2 = toDate(b); if (!d1 || !d2) return 0; return Math.round((d2 - d1) / 86400000) + 1; }
function offsetHari(base, a) { const d1 = toDate(base), d2 = toDate(a); if (!d1 || !d2) return 0; return Math.round((d2 - d1) / 86400000); }
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmt(s) { if (!s) return '—'; const [, m, d] = s.split('-'); return `${parseInt(d)} ${BULAN[parseInt(m) - 1]}`; }
function fmtFull(s) { if (!s) return '—'; const [y, m, d] = s.split('-'); return `${parseInt(d)} ${BULAN[parseInt(m) - 1]} ${y}`; }

// ── Gantt ──
function jBase(j) { return j.agenda.map((s) => s.mulai).filter(Boolean).sort()[0] || null; }
function jEnd(j) { return j.agenda.map((s) => s.selesai).filter(Boolean).sort().slice(-1)[0] || null; }
function jTotal(j) { const b = jBase(j), e = jEnd(j); return b && e ? lamaHari(b, e) : 0; }
function barStyle(s, j) {
    const base = jBase(j), total = jTotal(j) || 1;
    const left = (offsetHari(base, s.mulai) / total) * 100;
    const width = (lamaHari(s.mulai, s.selesai) / total) * 100;
    return { left: left + '%', width: Math.max(width, 4) + '%' };
}

// ── Builder ──
const showCreate = ref(false);
const form = reactive({ kegiatanId: '', alurId: '', siklus: 'TAHUNAN', agenda: [] });
const currentKat = computed(() => props.kegiatanOptions.find((k) => k.id === form.kegiatanId)?.kategori ?? 'REKRUTMEN');
const alurByKat = computed(() => props.alur.filter((a) => a.kategori === currentKat.value));
const formTotal = computed(() => {
    const ms = form.agenda.map((s) => s.mulai).filter(Boolean).sort();
    const es = form.agenda.map((s) => s.selesai).filter(Boolean).sort();
    return ms.length && es.length ? lamaHari(ms[0], es[es.length - 1]) : 0;
});

function seleksiFromAlur(alurId) {
    const a = props.alur.find((x) => x.id === alurId);
    return (a?.stages ?? []).map((s, idx) => ({ jenis: 'SELEKSI', label: s.label, mulai: null, selesai: null, alurIdx: idx }));
}
function rowErr(s) {
    if (!s.mulai || !s.selesai) return '';
    if (s.selesai < s.mulai) return 'Tanggal selesai sebelum mulai';
    return '';
}
// Validasi sinkron: semua tahap seleksi bertanggal, URUT sesuai alur, tak tumpang tindih.
const syncErrors = computed(() => {
    const e = [];
    form.agenda.forEach((s) => {
        if (!s.mulai || !s.selesai) e.push(`"${s.label || jenisLabel(s.jenis)}": tanggal belum lengkap.`);
        else if (s.selesai < s.mulai) e.push(`"${s.label}": selesai sebelum mulai.`);
    });
    const sel = form.agenda.filter((s) => s.jenis === 'SELEKSI');
    let prevIdx = -1, prevSelesai = null, prevLabel = '';
    for (const s of sel) {
        if (s.alurIdx <= prevIdx) e.push(`Urutan tahap seleksi "${s.label}" tidak sesuai alur.`);
        if (s.mulai && prevSelesai && s.mulai <= prevSelesai) e.push(`Tahap seleksi "${s.label}" tumpang tindih dengan "${prevLabel}".`);
        prevIdx = s.alurIdx; prevSelesai = s.selesai; prevLabel = s.label;
    }
    return e;
});

function onKegiatanChange() {
    const keg = props.kegiatanOptions.find((k) => k.id === form.kegiatanId);
    form.alurId = keg?.alurDefault ?? alurByKat.value[0]?.id ?? '';
    onAlurChange();
}
function onAlurChange() { form.agenda = seleksiFromAlur(form.alurId); }
function onDates(i) {
    const s = form.agenda[i];
    // rantai bantu: untuk baris seleksi, isi mulai baris seleksi berikutnya = selesai+1 bila kosong
    if (s.jenis === 'SELEKSI' && s.mulai && s.selesai && s.selesai >= s.mulai) {
        const next = form.agenda.slice(i + 1).find((x) => x.jenis === 'SELEKSI');
        if (next && !next.mulai) {
            const d = new Date(s.selesai + 'T00:00:00'); d.setDate(d.getDate() + 1);
            next.mulai = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }
    }
}
function addRow() { form.agenda.push({ jenis: 'RAPAT', label: '', mulai: null, selesai: null, alurIdx: null }); }
function removeRow(i) { if (form.agenda[i].jenis !== 'SELEKSI') form.agenda.splice(i, 1); }
function move(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= form.agenda.length) return;
    const arr = form.agenda;
    [arr[i], arr[j]] = [arr[j], arr[i]];
}
function openCreate() {
    const first = props.kegiatanOptions[0];
    Object.assign(form, { kegiatanId: first?.id ?? '', alurId: first?.alurDefault ?? '', siklus: 'TAHUNAN', agenda: [] });
    form.agenda = seleksiFromAlur(form.alurId);
    showCreate.value = true;
}
function saveCreate() {
    if (syncErrors.value.length) { notice('Perbaiki dulu: agenda belum sinkron/lengkap.'); return; }
    const keg = props.kegiatanOptions.find((k) => k.id === form.kegiatanId);
    const alurObj = props.alur.find((a) => a.id === form.alurId);
    list.unshift({
        id: 'JDW-' + (list.length + 1), kegiatan: keg?.nama ?? 'Kegiatan', kegiatanId: form.kegiatanId,
        kategori: keg?.kategori ?? 'REKRUTMEN', alur: alurObj?.nama ?? '—', alurId: form.alurId, siklus: form.siklus, status: 'AKTIF',
        agenda: form.agenda.map((s) => ({ jenis: s.jenis, label: s.label || jenisLabel(s.jenis), mulai: s.mulai, selesai: s.selesai, alurIdx: s.alurIdx })),
    });
    notice('Jadwal kegiatan dibuat (demo dummy).');
    showCreate.value = false;
    open.value = list[0].id;
}

const toast = ref('');
let t = null;
function notice(m) {
    toast.value = m;
    if (t) clearTimeout(t);
    t = setTimeout(() => (toast.value = ''), 3000);
}
</script>
