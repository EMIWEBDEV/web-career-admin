<!-- WEB CAREER — Admin: Penjadwalan Tes
     Pilih PROGRAM → centang kandidat (dinamis) → pilih Jenis Tes + Paket (alat tes) → generate.
     Daftar penjadwalan dengan pencarian & pagination. -->
<template>
    <Head><title>Penjadwalan Tes - Web Career</title></Head>
    <div class="wca">
        <div class="wca-phead">
            <div>
                <h1>Penjadwalan Tes (HCLearn)</h1>
                <p>Pilih <b>program</b>, centang kandidat yang akan dijadwalkan, lalu tentukan <b>jenis &amp; paket tes</b>. Rekrutmen &amp; MT dijadwalkan terpisah.</p>
            </div>
        </div>

        <!-- Tab jenis -->
        <div class="wca-segt">
            <button class="wca-segt__it" :class="{ on: jenis === 'REKRUTMEN' }" @click="setJenis('REKRUTMEN')"><i class="bi bi-briefcase"></i> Rekrutmen</button>
            <button class="wca-segt__it" :class="{ on: jenis === 'MT' }" @click="setJenis('MT')"><i class="bi bi-mortarboard"></i> Management Trainee</button>
        </div>

        <div class="wca-grid wca-grid--2">
            <!-- ══ KONFIGURASI ══ -->
            <div class="wca-card">
                <div class="wca-card__head"><h3><i class="bi bi-sliders"></i> Konfigurasi Tes</h3></div>
                <div class="wca-card__body">
                    <div class="wca-form">
                        <div>
                            <label class="wca-field-lbl">Program</label>
                            <el-select v-model="sel.program" placeholder="Pilih program" filterable style="width:100%">
                                <el-option v-for="p in programOptions" :key="p" :label="p" :value="p" />
                            </el-select>
                        </div>

                        <div>
                            <label class="wca-field-lbl">Jenis Tes <small class="wca-lbl-sub">(pihak ke-3)</small></label>
                            <el-select v-model="sel.jenisTesId" placeholder="Pilih jenis tes" style="width:100%">
                                <el-option v-for="t in jenisTes" :key="t.id" :label="t.nama" :value="t.id">
                                    <span style="font-weight:700">{{ t.nama }}</span>
                                    <span style="float:right;color:var(--muted);font-size:.8rem">{{ t.kategori }}</span>
                                </el-option>
                            </el-select>
                        </div>

                        <div>
                            <label class="wca-field-lbl">Nama Ujian / Paket Tes</label>
                            <el-select v-model="sel.paketNama" placeholder="Pilih paket tes" style="width:100%">
                                <el-option v-for="pk in paketOptions" :key="pk.nama" :label="pk.nama" :value="pk.nama">
                                    <span style="font-weight:700">{{ pk.nama }}</span>
                                    <span style="float:right;color:var(--muted);font-size:.78rem">{{ pk.alat.join(' · ') }}</span>
                                </el-option>
                            </el-select>
                        </div>

                        <!-- Ringkasan alat tes -->
                        <div v-if="selectedPaket" class="wca-testsum">
                            <div class="wca-testsum__hd">
                                <span><i class="bi bi-ui-checks-grid"></i> Alat tes</span>
                                <small><i class="bi bi-building"></i> {{ selectedTest.pelaksana }} · <i class="bi bi-clock"></i> {{ selectedPaket.durasi }} menit</small>
                            </div>
                            <div class="wca-alatwrap">
                                <span v-for="a in selectedPaket.alat" :key="a" class="wca-alat">{{ a }}</span>
                            </div>
                        </div>

                        <div class="wca-frow">
                            <div><label class="wca-field-lbl">Tanggal</label><el-date-picker v-model="sel.tanggal" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" style="width:100%" /></div>
                            <div><label class="wca-field-lbl">&nbsp;</label>
                                <div style="display:flex;gap:.4rem">
                                    <el-time-picker v-model="sel.mulai" format="HH:mm" value-format="HH:mm" placeholder="Mulai" style="width:100%" />
                                    <el-time-picker v-model="sel.selesai" format="HH:mm" value-format="HH:mm" placeholder="Selesai" style="width:100%" />
                                </div>
                            </div>
                        </div>

                        <div class="wca-hint"><i class="bi bi-shield-lock"></i> Kandidat terpilih akan menerima token + OTP unik untuk mengakses tes pada jendela waktu ini.</div>
                        <button class="wca-btn wca-btn--primary" :disabled="!canGenerate" @click="provision">
                            <i class="bi bi-magic"></i> Generate {{ checkedList.length }} Sesi Tes
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══ PILIH KANDIDAT ══ -->
            <div class="wca-card">
                <div class="wca-card__head">
                    <h3><i class="bi bi-people"></i> Pilih Kandidat</h3>
                    <span class="wca-badge wca-b--indigo">{{ checkedList.length }} / {{ programCandidates.length }}</span>
                </div>
                <div class="wca-card__body">
                    <div class="wca-ckbar">
                        <el-checkbox :model-value="allChecked" :indeterminate="someChecked" @change="toggleAll">Pilih semua</el-checkbox>
                        <div class="wca-cksearch">
                            <el-input v-model="search" placeholder="Cari nama / kampus…" clearable size="small" style="width:180px">
                                <template #prefix><i class="bi bi-search"></i></template>
                            </el-input>
                        </div>
                    </div>
                    <div class="wca-cklist">
                        <label v-for="k in programCandidates" :key="k.id" class="wca-ckrow" :class="{ on: checked[k.id] }">
                            <el-checkbox v-model="checked[k.id]" />
                            <span class="wca-avatar wca-avatar--sm">{{ initials(k.nama) }}</span>
                            <div class="wca-ckrow__main">
                                <strong>{{ k.nama }}</strong>
                                <small>{{ k.kampus || k.posisi }} · <span class="wca-ckrow__tahap">{{ k.tahap }}</span></small>
                            </div>
                            <span class="wca-badge" :class="k.jenis === 'MT' ? 'wca-b--gold' : 'wca-b--sky'">{{ k.jenis }}</span>
                        </label>
                        <div v-if="!programCandidates.length" class="wca-empty"><i class="bi bi-inbox"></i><h4>Tidak ada kandidat</h4><p>Pilih program lain atau ubah pencarian.</p></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ DAFTAR PENJADWALAN (filter + accordion) ══ -->
        <div class="wca-card" style="margin-top:1.25rem">
            <div class="wca-card__head">
                <h3><i class="bi bi-calendar2-check"></i> Daftar Penjadwalan</h3>
                <span class="wca-badge wca-b--slate">{{ filteredSessions.length }} sesi · {{ groups.length }} jadwal</span>
            </div>
            <div class="wca-card__body">
                <!-- Filter bar -->
                <div class="wca-filterbar">
                    <div class="wca-segt wca-segt--sm">
                        <button class="wca-segt__it" :class="{ on: fJenis === 'ALL' }" @click="fJenis = 'ALL'">Semua</button>
                        <button class="wca-segt__it" :class="{ on: fJenis === 'REKRUTMEN' }" @click="fJenis = 'REKRUTMEN'">Rekrutmen</button>
                        <button class="wca-segt__it" :class="{ on: fJenis === 'MT' }" @click="fJenis = 'MT'">MT</button>
                    </div>
                    <el-select v-model="fProgram" placeholder="Semua program" clearable filterable size="small" style="width:210px">
                        <el-option v-for="p in fProgramOptions" :key="p" :label="p" :value="p" />
                    </el-select>
                    <el-select v-model="fStatus" placeholder="Semua status" clearable size="small" style="width:150px">
                        <el-option label="Terjadwal" value="PROVISIONED" />
                        <el-option label="Selesai" value="COMPLETED" />
                        <el-option label="Kedaluwarsa" value="EXPIRED" />
                    </el-select>
                    <el-input v-model="qSes" placeholder="Cari kandidat / tes / token…" clearable size="small" style="flex:1;min-width:170px">
                        <template #prefix><i class="bi bi-search"></i></template>
                    </el-input>
                    <button v-if="hasFilter" class="wca-btn wca-btn--ghost wca-btn--sm" @click="resetFilter"><i class="bi bi-x-circle"></i> Reset</button>
                </div>

                <!-- Accordion -->
                <div class="wca-acc">
                    <div v-for="g in pagedGroups" :key="g.key" class="wca-accg" :class="{ open: open[g.key] }">
                        <button type="button" class="wca-accg__hd" @click="toggle(g.key)">
                            <i class="bi wca-accg__chev" :class="open[g.key] ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                            <span class="wca-badge" :class="g.jenis === 'MT' ? 'wca-b--gold' : 'wca-b--sky'">{{ g.jenis }}</span>
                            <div class="wca-accg__title">
                                <strong>{{ g.tes }} <span class="wca-accg__pkt">{{ g.paket }}</span></strong>
                                <small><i class="bi bi-diagram-3"></i> {{ g.program }} <span class="wca-accg__dot">·</span> <i class="bi bi-calendar-event"></i> {{ winOf(g) }}</small>
                            </div>
                            <div class="wca-accg__meta">
                                <span v-for="d in g.statList" :key="d.st" class="wca-statdot" :class="statusBadge(d.st)">{{ d.n }}</span>
                                <span class="wca-accg__count"><i class="bi bi-people"></i> {{ g.items.length }}</span>
                            </div>
                        </button>
                        <div v-show="open[g.key]" class="wca-accg__body">
                            <div v-for="s in g.items" :key="s.id" class="wca-srow">
                                <span class="wca-avatar wca-avatar--sm">{{ initials(s.nama) }}</span>
                                <div class="wca-srow__main"><strong>{{ s.nama }}</strong><small>{{ s.posisi }}</small></div>
                                <div class="wca-srow__cred">
                                    <div class="wca-credbox"><small>Token</small><code :class="{ masked: !reveal[s.id] }">{{ reveal[s.id] ? s.token : mask(s.token) }}</code></div>
                                    <div class="wca-credbox"><small>OTP</small><code :class="{ masked: !reveal[s.id] }">{{ reveal[s.id] ? s.otp : mask(s.otp) }}</code></div>
                                    <button type="button" class="wca-icbtn2" :title="reveal[s.id] ? 'Sembunyikan' : 'Tampilkan'" @click="reveal[s.id] = !reveal[s.id]"><i class="bi" :class="reveal[s.id] ? 'bi-eye-slash' : 'bi-eye'"></i></button>
                                    <button type="button" class="wca-icbtn2" title="Salin token" @click="copy(s.token)"><i class="bi bi-clipboard"></i></button>
                                </div>
                                <span class="wca-badge" :class="statusBadge(s.status)">{{ statusText(s.status) }}</span>
                                <button type="button" class="wca-icbtn2 wca-icbtn2--edit" title="Edit jadwal" @click="openEdit(s)"><i class="bi bi-pencil"></i></button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!groups.length" class="wca-empty"><i class="bi bi-inbox"></i><h4>Tidak ada penjadwalan</h4><p>Ubah filter atau buat sesi baru di atas.</p></div>
                </div>

                <!-- Pager (per grup jadwal) -->
                <div v-if="totalPages > 1" class="wca-pager">
                    <button class="wca-pager__btn" :disabled="page === 1" @click="page--"><i class="bi bi-chevron-left"></i></button>
                    <button v-for="n in totalPages" :key="n" class="wca-pager__num" :class="{ on: n === page }" @click="page = n">{{ n }}</button>
                    <button class="wca-pager__btn" :disabled="page === totalPages" @click="page++"><i class="bi bi-chevron-right"></i></button>
                    <span class="wca-pager__info">Hal. {{ page }} / {{ totalPages }}</span>
                </div>
            </div>
        </div>

        <!-- Modal edit jadwal -->
        <AdminModal :show="!!editing" title="Edit Jadwal Tes" icon="bi-pencil-square" @close="editing = null">
            <template v-if="editing">
                <div class="wca-hint" style="margin-top:0"><i class="bi bi-person-badge"></i> <b>{{ editing.nama }}</b> — {{ editing.tes }} · {{ editing.paket }}</div>
                <div class="wca-form" style="margin-top:1rem">
                    <div><label class="wca-field-lbl">Tanggal</label><el-date-picker v-model="ed.tanggal" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" style="width:100%" /></div>
                    <div class="wca-frow">
                        <div><label class="wca-field-lbl">Jam Mulai</label><el-time-picker v-model="ed.mulai" format="HH:mm" value-format="HH:mm" style="width:100%" /></div>
                        <div><label class="wca-field-lbl">Jam Selesai</label><el-time-picker v-model="ed.selesai" format="HH:mm" value-format="HH:mm" style="width:100%" /></div>
                    </div>
                    <div><label class="wca-field-lbl">Status</label>
                        <el-select v-model="ed.status" style="width:100%">
                            <el-option label="Terjadwal" value="PROVISIONED" />
                            <el-option label="Selesai" value="COMPLETED" />
                            <el-option label="Kedaluwarsa" value="EXPIRED" />
                        </el-select>
                    </div>
                    <div class="wca-edcred">
                        <div><small>Token</small><code>{{ ed.token }}</code></div>
                        <div><small>OTP</small><code>{{ ed.otp }}</code></div>
                        <button class="wca-btn wca-btn--soft wca-btn--sm" @click="regen"><i class="bi bi-arrow-repeat"></i> Regenerate</button>
                    </div>
                </div>
            </template>
            <template #footer>
                <button class="wca-btn wca-btn--ghost" @click="editing = null">Batal</button>
                <button class="wca-btn wca-btn--primary" @click="saveEdit"><i class="bi bi-check-lg"></i> Simpan Perubahan</button>
            </template>
        </AdminModal>

        <!-- Modal hasil generate -->
        <AdminModal :show="showResult" title="Sesi Tes Berhasil Dibuat" icon="bi-check-circle" lg @close="showResult = false">
            <p class="wca-hint" style="margin-top:0;margin-bottom:1rem"><i class="bi bi-info-circle"></i> {{ generated.length }} sesi ter-provision — <b>{{ genTesLabel }}</b> · jendela <b>{{ windowLabel }}</b>. Tautan &amp; kredensial dikirim ke email kandidat (simulasi).</p>
            <div class="wca-mpp">
                <div v-for="g in generated" :key="g.id" class="wca-mpprow" style="cursor:default;align-items:flex-start">
                    <span class="wca-avatar wca-avatar--sm">{{ initials(g.nama) }}</span>
                    <div class="wca-mpprow__main">
                        <strong>{{ g.nama }}</strong><small>{{ g.posisi }}</small>
                        <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.5rem;align-items:center">
                            <span class="wca-cred wca-cred--token">{{ g.token }}</span>
                            <span class="wca-cred wca-cred--otp">OTP {{ g.otp }}</span>
                            <button class="wca-btn wca-btn--soft wca-btn--sm" @click="copy(g.url)"><i class="bi bi-link-45deg"></i> Salin Tautan</button>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <button class="wca-btn wca-btn--ghost" @click="copyAll"><i class="bi bi-clipboard-check"></i> Salin Semua</button>
                <button class="wca-btn wca-btn--primary" @click="showResult = false"><i class="bi bi-check-lg"></i> Selesai</button>
            </template>
        </AdminModal>

        <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AdminModal from '@career/AdminModal.vue';
import { initials } from './careerAdmin';

const props = defineProps({
    kandidat: { type: Array, default: () => [] },
    sesi: { type: Array, default: () => [] },
    jenisTes: { type: Array, default: () => [] },
});

const sessions = reactive(props.sesi.map((s) => ({ ...s })));
const jenis = ref('REKRUTMEN');
const sel = reactive({
    program: '',
    jenisTesId: props.jenisTes[0]?.id ?? '',
    paketNama: props.jenisTes[0]?.paket?.[0]?.nama ?? '',
    tanggal: '', mulai: '09:00', selesai: '11:00',
});
const search = ref('');
const checked = reactive({});

const byJenis = computed(() => props.kandidat.filter((k) => k.jenis === jenis.value));
const programOptions = computed(() => [...new Set(byJenis.value.map((k) => k.program))]);
const programCandidates = computed(() => byJenis.value.filter((k) => {
    if (k.program !== sel.program) return false;
    if (!search.value) return true;
    const q = search.value.toLowerCase();
    return k.nama.toLowerCase().includes(q) || (k.kampus || '').toLowerCase().includes(q) || (k.posisi || '').toLowerCase().includes(q);
}));

const selectedTest = computed(() => props.jenisTes.find((t) => t.id === sel.jenisTesId) || null);
const paketOptions = computed(() => selectedTest.value?.paket || []);
const selectedPaket = computed(() => paketOptions.value.find((p) => p.nama === sel.paketNama) || null);

const checkedList = computed(() => programCandidates.value.filter((k) => checked[k.id]));
const allChecked = computed(() => programCandidates.value.length > 0 && programCandidates.value.every((k) => checked[k.id]));
const someChecked = computed(() => programCandidates.value.some((k) => checked[k.id]) && !allChecked.value);
const canGenerate = computed(() => checkedList.value.length > 0 && sel.program && selectedTest.value && selectedPaket.value && sel.tanggal);

function setJenis(j) { jenis.value = j; }
function toggleAll() { const v = !allChecked.value; programCandidates.value.forEach((k) => { checked[k.id] = v; }); }
function autoCheckAll() { byJenis.value.filter((k) => k.program === sel.program).forEach((k) => { checked[k.id] = true; }); }

// Program mengikuti jenis; ganti program → centang semua default
watch(programOptions, (opts) => { if (!opts.includes(sel.program)) sel.program = opts[0] ?? ''; }, { immediate: true });
watch(() => sel.program, () => { search.value = ''; autoCheckAll(); }, { immediate: true });
watch(() => sel.jenisTesId, () => { sel.paketNama = paketOptions.value[0]?.nama ?? ''; });

/* ── Generate ── */
const generated = ref([]);
const showResult = ref(false);
const windowLabel = ref('');
const genTesLabel = ref('');

function genToken() {
    const c = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let s = '';
    for (let n = 0; n < 6; n++) s += c[Math.floor(Math.random() * c.length)];
    return 'HCL-' + s;
}
function genOtp() { return String(Math.floor(100000 + Math.random() * 900000)); }

function provision() {
    if (!canGenerate.value) return;
    const win = `${fmtDate(sel.tanggal)}, ${sel.mulai}–${sel.selesai}`;
    windowLabel.value = win;
    genTesLabel.value = `${selectedTest.value.nama} · ${selectedPaket.value.nama}`;
    generated.value = checkedList.value.map((k, i) => {
        const token = genToken();
        return {
            id: 'SES-N' + (sessions.length + i + 1),
            nama: k.nama, posisi: k.posisi, jenis: jenis.value, program: sel.program,
            tes: selectedTest.value.nama, paket: selectedPaket.value.nama,
            token, otp: genOtp(), url: `https://hclearn.evo.id/exam/${token.toLowerCase()}`,
            tanggal: sel.tanggal, mulai: sel.mulai, selesai: sel.selesai, status: 'PROVISIONED',
        };
    });
    sessions.unshift(...generated.value.map((g) => ({ ...g })));
    // Buka grup jadwal yang baru dibuat & tampilkan di halaman awal
    const nk = [sel.program, selectedTest.value.nama, selectedPaket.value.nama, sel.tanggal, sel.mulai, sel.selesai].join('|');
    open[nk] = true;
    page.value = 1;
    showResult.value = true;
    notice(`${generated.value.length} sesi ter-provision (demo dummy).`);
}

/* ── Daftar penjadwalan: filter + accordion + pagination ── */
const fJenis = ref('ALL');
const fProgram = ref('');
const fStatus = ref('');
const qSes = ref('');
const hasFilter = computed(() => fJenis.value !== 'ALL' || fProgram.value || fStatus.value || qSes.value);
function resetFilter() { fJenis.value = 'ALL'; fProgram.value = ''; fStatus.value = ''; qSes.value = ''; }

const fProgramOptions = computed(() => [...new Set(sessions.filter((s) => fJenis.value === 'ALL' || s.jenis === fJenis.value).map((s) => s.program))]);
watch(fJenis, () => { if (fProgram.value && !fProgramOptions.value.includes(fProgram.value)) fProgram.value = ''; });

const filteredSessions = computed(() => sessions.filter((s) => {
    if (fJenis.value !== 'ALL' && s.jenis !== fJenis.value) return false;
    if (fProgram.value && s.program !== fProgram.value) return false;
    if (fStatus.value && s.status !== fStatus.value) return false;
    if (qSes.value) {
        const q = qSes.value.toLowerCase();
        return s.nama.toLowerCase().includes(q) || (s.tes || '').toLowerCase().includes(q) || (s.paket || '').toLowerCase().includes(q) || (s.token || '').toLowerCase().includes(q);
    }
    return true;
}));

// Kelompokkan jadi grup jadwal (program + tes + paket + waktu) → accordion
const groups = computed(() => {
    const map = new Map();
    filteredSessions.value.forEach((s) => {
        const key = [s.program, s.tes, s.paket, s.tanggal, s.mulai, s.selesai].join('|');
        if (!map.has(key)) map.set(key, { key, jenis: s.jenis, program: s.program, tes: s.tes, paket: s.paket, tanggal: s.tanggal, mulai: s.mulai, selesai: s.selesai, items: [] });
        map.get(key).items.push(s);
    });
    return [...map.values()].map((g) => {
        const stat = {};
        g.items.forEach((s) => { stat[s.status] = (stat[s.status] || 0) + 1; });
        g.statList = ['PROVISIONED', 'COMPLETED', 'EXPIRED'].filter((st) => stat[st]).map((st) => ({ st, n: stat[st] }));
        return g;
    });
});

const page = ref(1);
const pageSize = 4;
const totalPages = computed(() => Math.max(1, Math.ceil(groups.value.length / pageSize)));
const pagedGroups = computed(() => groups.value.slice((page.value - 1) * pageSize, page.value * pageSize));
watch([groups, totalPages], () => { if (page.value > totalPages.value) page.value = totalPages.value; });

const open = reactive({});
function toggle(key) { open[key] = !open[key]; }
watch(groups, (g) => { if (g.length && !Object.values(open).some(Boolean)) open[g[0].key] = true; }, { immediate: true });

const reveal = reactive({});
function mask(v) { return v ? '•'.repeat(Math.min(v.length, 10)) : ''; }

function winOf(x) { return `${fmtDate(x.tanggal)}, ${x.mulai}–${x.selesai}`; }
function statusBadge(s) { return { PROVISIONED: 'wca-b--sky', COMPLETED: 'wca-b--green', EXPIRED: 'wca-b--red' }[s] || 'wca-b--slate'; }
function statusText(s) { return { PROVISIONED: 'Terjadwal', COMPLETED: 'Selesai', EXPIRED: 'Kedaluwarsa' }[s] || s; }

/* ── Edit jadwal ── */
const editing = ref(null);
const ed = reactive({ tanggal: '', mulai: '', selesai: '', status: '', token: '', otp: '' });
function openEdit(s) {
    editing.value = s;
    ed.tanggal = s.tanggal; ed.mulai = s.mulai; ed.selesai = s.selesai; ed.status = s.status; ed.token = s.token; ed.otp = s.otp;
}
function regen() { ed.token = genToken(); ed.otp = genOtp(); }
function saveEdit() {
    if (!editing.value) return;
    Object.assign(editing.value, { tanggal: ed.tanggal, mulai: ed.mulai, selesai: ed.selesai, status: ed.status, token: ed.token, otp: ed.otp });
    editing.value = null;
    notice('Jadwal tes diperbarui.');
}

function fmtDate(iso) {
    const M = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return `${d.getDate()} ${M[d.getMonth()]} ${d.getFullYear()}`;
}
function copy(text) { try { navigator.clipboard?.writeText(text); } catch (e) { /* ignore */ } notice('Disalin ke clipboard.'); }
function copyAll() { copy(generated.value.map((g) => `${g.nama} | ${g.token} | OTP ${g.otp} | ${g.url}`).join('\n')); }

const toast = ref('');
let t = null;
function notice(m) { toast.value = m; if (t) clearTimeout(t); t = setTimeout(() => (toast.value = ''), 3000); }
</script>
