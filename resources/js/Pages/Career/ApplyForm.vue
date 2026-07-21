<!-- WEB CAREER — Kandidat: Formulir Lamaran. Memakai CareerLayout (navbar+footer+bg) seperti landing. -->
<template>
    <Head><title>Lamar — {{ flow.lowongan.posisi }}</title></Head>
    <CareerLayout :has-mt="hasMt" :offices="offices">
        <main class="wc-af">
            <div class="wc-af__inner">
                <a class="wc-af__back" href="/"><i class="bi bi-arrow-left"></i> Kembali ke Karir</a>

                <header class="wc-af__hero">
                    <span class="wc-eyebrow"><span class="wc-dot"></span> {{ flow.form === 2 ? 'Formulir Tahap Lanjut · Form 2' : 'Formulir Lamaran' }}</span>
                    <h1 class="wc-af__title">
                        {{ flow.lowongan.posisi }}
                        <span class="wc-af__cat" :class="flow.lowongan.kategori === 'MT' ? 'is-mt' : 'is-rek'">{{ flow.lowongan.kategori === 'MT' ? 'Management Trainee' : 'Rekrutmen' }}</span>
                    </h1>
                    <p class="wc-af__meta"><i class="bi bi-building"></i> {{ flow.lowongan.program }} <span class="wc-af__sep">·</span> <i class="bi bi-geo-alt"></i> {{ flow.lowongan.lokasi }}</p>
                </header>

                <!-- Sukses -->
                <div v-if="done" class="wca-apply__done" :class="{ 'is-fail': doneKo.length }">
                    <div class="wca-apply__doneic"><i class="bi" :class="doneKo.length ? 'bi-x-circle-fill' : 'bi-check-circle-fill'"></i></div>
                    <template v-if="doneKo.length">
                        <h2>Belum Memenuhi Syarat</h2>
                        <p>Terima kasih telah melamar <b>{{ flow.lowongan.posisi }}</b>. Namun lamaran Anda <b>belum memenuhi syarat wajib</b>: <b>{{ doneKo.join(' · ') }}</b>. Anda tetap dapat melamar posisi lain yang sesuai dengan kualifikasi Anda.</p>
                    </template>
                    <template v-else>
                        <h2>Lamaran Terkirim!</h2>
                        <p>Data Anda telah <b>difinalisasi</b> & terkirim untuk posisi <b>{{ flow.lowongan.posisi }}</b>. Tim rekrutmen akan meninjau di tahap <b>Seleksi Administrasi</b>. Pantau statusnya di menu <b>Lamaran Saya</b>.</p>
                    </template>
                    <a href="/kandidat/portal" class="wca-btn wca-btn--primary"><i class="bi bi-list-check"></i> Ke Lamaran Saya</a>
                </div>

                <template v-else>
                    <!-- Stepper -->
                    <div class="wca-steps2">
                        <div v-for="(s, i) in steps" :key="s.key" class="wca-steps2__it" :class="{ done: i < step, cur: i === step }">
                            <span class="wca-steps2__dot"><i v-if="i < step" class="bi bi-check-lg"></i><i v-else class="bi" :class="s.ikon"></i></span>
                            <span class="wca-steps2__lbl">{{ stepShort(s.key) }}</span>
                        </div>
                    </div>

                    <!-- Kartu langkah -->
                    <div class="wca-apply__card">
                        <div class="wca-apply__head">
                            <span class="wca-apply__stepic"><i class="bi" :class="cur.ikon"></i></span>
                            <div>
                                <span class="wca-apply__stepno">Langkah {{ step + 1 }} / {{ steps.length }}</span>
                                <h3>{{ cur.judul }} <small v-if="cur.opsional" class="wca-apply__opt">· opsional</small></h3>
                                <p v-if="cur.deskripsi">{{ cur.deskripsi }}</p>
                            </div>
                        </div>

                        <!-- MULTI / repeater (mis. Pengalaman — bisa banyak) -->
                        <div v-if="cur.repeat" class="wca-multi">
                            <div v-for="(entry, ei) in multi[cur.key]" :key="ei" class="wca-multi__item">
                                <div class="wca-multi__head">
                                    <span><i class="bi" :class="cur.ikon"></i> {{ cur.itemLabel }} {{ ei + 1 }}</span>
                                    <button v-if="multi[cur.key].length > 1" type="button" class="wca-iconbtn wca-iconbtn--danger" title="Hapus" @click="removeEntry(cur, ei)"><i class="bi bi-trash"></i></button>
                                </div>
                                <div class="wca-aform">
                                    <div v-for="f in cur.fields" :key="f.key" class="wca-aform__fld" :class="{ full: f.full }">
                                        <template v-if="f.tipe === 'switch'">
                                            <label class="wca-switchrow"><el-switch v-model="entry[f.key]" @change="onSekarang(entry)" /> <span>{{ f.label }}</span></label>
                                        </template>
                                        <template v-else>
                                            <label class="wca-field-lbl">{{ f.label }}</label>
                                            <el-date-picker v-if="f.tipe === 'date'" v-model="entry[f.key]" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" style="width:100%" :disabled="f.disableIf ? !!entry[f.disableIf] : false" />
                                            <el-input v-else-if="f.tipe === 'textarea'" v-model="entry[f.key]" type="textarea" :rows="3" :placeholder="f.ph" />
                                            <el-input v-else v-model="entry[f.key]" :placeholder="f.ph" />
                                        </template>
                                    </div>
                                </div>
                                <div v-if="entry.mulai" class="wca-multi__dur"><i class="bi bi-hourglass-split"></i> {{ durasiText(entry) }}</div>
                            </div>
                            <button type="button" class="wca-addrow" @click="addEntry(cur)"><i class="bi bi-plus-circle"></i> Tambah {{ cur.itemLabel }}</button>
                        </div>

                        <!-- FORM (dukungan kondisional showIf + readonly/prefill) -->
                        <div v-else-if="cur.tipe === 'FORM'" class="wca-aform">
                            <template v-for="f in cur.fields" :key="f.key">
                                <div v-if="showField(f)" class="wca-aform__fld" :class="{ full: f.full }">
                                    <label class="wca-field-lbl">{{ f.label }} <span v-if="f.required" class="wca-req">*</span></label>
                                    <el-select v-if="f.tipe === 'select'" v-model="form[f.key]" :placeholder="'Pilih ' + f.label" filterable clearable style="width:100%">
                                        <el-option v-for="o in f.opsi" :key="o" :label="o" :value="o" />
                                    </el-select>
                                    <el-date-picker v-else-if="f.tipe === 'date'" v-model="form[f.key]" type="date" value-format="YYYY-MM-DD" placeholder="Pilih tanggal" style="width:100%" />
                                    <el-input v-else-if="f.tipe === 'textarea'" v-model="form[f.key]" type="textarea" :rows="2" :placeholder="f.ph" :disabled="f.readonly" />
                                    <el-input-number v-else-if="f.tipe === 'number'" v-model="form[f.key]" :min="0" :max="f.key === 'ipk' ? 4 : undefined" :precision="f.key === 'ipk' ? 2 : 0" :step="f.key === 'ipk' ? 0.05 : 1" controls-position="right" :placeholder="f.ph" style="width:100%" />
                                    <el-input v-else v-model="form[f.key]" :placeholder="f.ph" :disabled="f.readonly" />
                                </div>
                            </template>
                        </div>

                        <!-- UPLOAD (batasi tipe file + pratinjau) -->
                        <div v-else-if="cur.tipe === 'UPLOAD'" class="wca-aup">
                            <div v-for="f in cur.files" :key="f.key" class="wca-aup__row" :class="{ ok: files[f.key] }">
                                <span class="wca-aup__ic"><i class="bi" :class="files[f.key] ? 'bi-check-circle-fill' : (f.hint === 'PDF' ? 'bi-file-earmark-pdf' : 'bi-cloud-arrow-up')"></i></span>
                                <span class="wca-aup__main">
                                    <strong>{{ f.label }} <span v-if="f.required" class="wca-req">*</span> <span class="wca-aup__fmt">{{ f.hint }}</span></strong>
                                    <small>{{ files[f.key] ? files[f.key].name : 'Belum ada berkas — hanya ' + f.hint }}</small>
                                </span>
                                <button v-if="files[f.key]" type="button" class="wca-btn wca-btn--soft wca-btn--sm" @click="showFile(files[f.key])"><i class="bi bi-eye"></i> Lihat</button>
                                <el-upload class="wca-aup__ep" :accept="f.accept" :auto-upload="false" :show-file-list="false" :on-change="(uf) => onFileEP(f.key, uf, f)">
                                    <button type="button" class="wca-btn wca-btn--soft wca-btn--sm"><i class="bi" :class="files[f.key] ? 'bi-arrow-repeat' : 'bi-upload'"></i> {{ files[f.key] ? 'Ganti' : 'Unggah' }}</button>
                                </el-upload>
                            </div>
                            <div v-if="uploadErr" class="wca-note wca-note--danger" style="margin:.6rem 0 0"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ uploadErr }}</span></div>
                            <div class="wca-hint" style="margin:.5rem 0 0"><i class="bi bi-info-circle"></i> Tipe file dibatasi sesuai kolom (dokumen wajib <b>PDF</b>). Klik <b>Lihat</b> untuk pratinjau. Maks 2MB (demo).</div>
                        </div>

                        <!-- PERNYATAAN (el-checkbox) -->
                        <div v-else-if="cur.tipe === 'PERNYATAAN'" class="wca-astate">
                            <el-checkbox v-for="(it, i) in cur.items" :key="i" v-model="checks[i]" class="wca-chk">{{ it }}</el-checkbox>
                        </div>

                        <!-- FACE -->
                        <div v-else-if="cur.tipe === 'FACE'" class="wca-face">
                            <div class="wca-face__stage">
                                <img v-if="facePhoto" :src="facePhoto" alt="Foto wajah" />
                                <video v-show="cameraOn && !facePhoto" ref="videoEl" autoplay playsinline muted></video>
                                <div v-if="!cameraOn && !facePhoto" class="wca-face__idle"><i class="bi bi-camera-video"></i><span>Kamera belum aktif</span></div>
                                <canvas ref="canvasEl" style="display:none"></canvas>
                            </div>
                            <div v-if="cameraError" class="wca-note wca-note--danger" style="margin:.6rem 0 0"><i class="bi bi-exclamation-triangle"></i><span>Kamera tak tersedia (butuh HTTPS / izin). Gunakan simulasi untuk demo.</span></div>
                            <div class="wca-face__act">
                                <template v-if="!facePhoto">
                                    <button v-if="!cameraOn" class="wca-btn wca-btn--primary" @click="startCamera"><i class="bi bi-camera-video"></i> Aktifkan Kamera</button>
                                    <button v-else class="wca-btn wca-btn--primary" @click="capture"><i class="bi bi-camera"></i> Ambil Foto</button>
                                </template>
                                <button v-else class="wca-btn wca-btn--ghost" @click="retake"><i class="bi bi-arrow-repeat"></i> Ambil Ulang</button>
                            </div>
                        </div>

                        <!-- REVIEW (tabs + timeline + berkas + consent) -->
                        <div v-else-if="cur.tipe === 'REVIEW'" class="wca-arev">
                            <div class="wca-arev__face">
                                <img v-if="facePhoto" :src="facePhoto" alt="wajah" />
                                <div v-else class="wca-face__sim wca-face__sim--sm"><i class="bi bi-person-badge"></i></div>
                                <div><strong>{{ form.nama || flow.lowongan.posisi }}</strong><small>{{ form.email || '—' }} · {{ form.hp || '—' }}</small></div>
                            </div>

                            <el-tabs class="wca-arev__tabs">
                                <el-tab-pane label="Data Pribadi">
                                    <div v-if="reviewItems.length" class="wca-arev__grid">
                                        <div v-for="r in reviewItems" :key="r.label"><small>{{ r.label }}</small><b>{{ r.value }}</b></div>
                                    </div>
                                    <div v-else class="wca-hint" style="margin:0"><i class="bi bi-info-circle"></i> Belum ada data terisi.</div>
                                </el-tab-pane>
                                <el-tab-pane v-if="experiences.length" :label="`Pengalaman (${experiences.length})`">
                                    <el-timeline class="wca-tl">
                                        <el-timeline-item v-for="(x, i) in experiences" :key="i" type="primary" hollow :timestamp="x.periode" placement="top">
                                            <div class="wca-tl__card">
                                                <strong>{{ x.posisi }}</strong>
                                                <div class="wca-tl__co"><i class="bi bi-building"></i> {{ x.perusahaan }} <span class="wca-tl__dur"><i class="bi bi-hourglass-split"></i> {{ x.durasi }}</span></div>
                                                <p v-if="x.desc">{{ x.desc }}</p>
                                            </div>
                                        </el-timeline-item>
                                    </el-timeline>
                                </el-tab-pane>
                                <el-tab-pane v-if="fileCards.length" :label="`Berkas (${fileCards.length})`">
                                    <div class="wca-fgrid">
                                        <button v-for="fc in fileCards" :key="fc.key" type="button" class="wca-fcard" @click="showFile(fc)">
                                            <span class="wca-fcard__ic" :class="{ pdf: fc.isPdf }"><i class="bi" :class="fc.isPdf ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image'"></i></span>
                                            <span class="wca-fcard__nm">{{ fc.name }}</span>
                                            <span class="wca-fcard__view"><i class="bi bi-eye"></i> Pratinjau</span>
                                        </button>
                                    </div>
                                </el-tab-pane>
                            </el-tabs>

                            <div class="wca-consent">
                                <el-checkbox v-model="consent[0]" class="wca-chk"><b>Kebenaran data.</b> Saya menyatakan seluruh data & dokumen yang saya isi benar dan dapat dipertanggungjawabkan.</el-checkbox>
                                <el-checkbox v-model="consent[1]" class="wca-chk wca-chk--safe"><b><i class="bi bi-shield-lock-fill"></i> Anti-penipuan.</b> Saya memahami bahwa <b>EVO Group TIDAK PERNAH memungut biaya / pungutan dalam bentuk apa pun</b> selama proses rekrutmen. Waspadai penipuan yang mengatasnamakan EVO Group.</el-checkbox>
                            </div>
                            <div v-if="!consentOk" class="wca-note wca-note--danger" style="margin:.5rem 0 0"><i class="bi bi-info-circle"></i><span>Centang <b>kedua</b> pernyataan di atas untuk mengaktifkan tombol Finalisasi.</span></div>
                        </div>

                        <div v-if="err" class="wca-note wca-note--danger" style="margin:.8rem 0 0"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ err }}</span></div>
                    </div>

                    <!-- Footer aksi -->
                    <div class="wca-apply__foot">
                        <button class="wca-btn wca-btn--ghost" :disabled="step === 0" @click="back"><i class="bi bi-arrow-left"></i> Kembali</button>
                        <button v-if="cur.tipe !== 'REVIEW'" class="wca-btn wca-btn--primary" @click="next">Lanjut <i class="bi bi-arrow-right"></i></button>
                        <button v-else class="wca-btn wca-btn--dark" :disabled="!consentOk" @click="finalize"><i class="bi bi-send-check"></i> Finalisasi & Kirim</button>
                    </div>
                </template>
            </div>
        </main>
    </CareerLayout>

    <!-- Pratinjau berkas (viewer asli) -->
    <teleport to="body">
        <transition name="wca-toast">
            <div v-if="preview" class="wca-drawer-mask wca-drawer-mask--center wca" @click.self="preview = null">
                <div class="wca-filepv wca-filepv--lg">
                    <div class="wca-filepv__head">
                        <span><i class="bi" :class="preview.isPdf ? 'bi-file-earmark-pdf' : 'bi-image'"></i> {{ preview.name }}</span>
                        <button class="wca-drawer__close" @click="preview = null"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="wca-filepv__view">
                        <iframe v-if="preview.isPdf" :src="preview.url" title="Pratinjau PDF"></iframe>
                        <img v-else :src="preview.url" alt="Pratinjau" />
                    </div>
                    <div class="wca-filepv__foot">
                        <button class="wca-btn wca-btn--ghost" @click="preview = null"><i class="bi bi-x-circle"></i> Tutup</button>
                        <a class="wca-btn wca-btn--primary" :href="preview.url" :download="preview.name"><i class="bi bi-download"></i> Unduh</a>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>

    <transition name="wca-toast"><div v-if="toast" class="wca-toast"><i class="bi bi-check-circle-fill"></i> {{ toast }}</div></transition>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import CareerLayout from './Layouts/CareerLayout.vue';
import { checkKnockout, flowFor, getApp, getSession, isLoggedIn, nextActionFor, requireLogin, upsertApp } from './careerSession';

defineOptions({ layout: null }); // tanpa shell HCIS — pakai CareerLayout (situs karir)

const props = defineProps({
    flow: { type: Object, default: () => ({ lowongan: {}, steps: [] }) },
    hasMt: { type: Boolean, default: false },
    offices: { type: Array, default: () => [] },
});
const steps = props.flow.steps;
const lowongan = props.flow.lowongan || {};
const jenis = lowongan.kategori === 'MT' ? 'MT' : 'REKRUTMEN';
const step = ref(0);
const cur = computed(() => steps[step.value] || {});
const done = ref(false);
const doneKo = ref([]); // alasan knock-out bila lamaran langsung tidak lolos
const err = ref('');
const preview = ref(null);
const uploadErr = ref('');

const form = reactive({});
const files = reactive({});
const checks = reactive([]);
const fileList = computed(() => Object.values(files).filter(Boolean).map((v) => v.name));

// Persetujuan finalisasi (2 centang wajib: kebenaran data + anti-penipuan)
const consent = reactive([false, false]);
const consentOk = computed(() => consent[0] && consent[1]);

// ── Pengalaman: tanggal → durasi ──
const BULAN_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmtId(iso) { if (!iso) return '—'; const [y, m, d] = iso.split('-'); return `${parseInt(d)} ${BULAN_ID[parseInt(m) - 1]} ${y}`; }
function periodeText(e) { return `${fmtId(e.mulai)} – ${e.sekarang ? 'Sekarang' : fmtId(e.selesai)}`; }
function durasiText(e) {
    if (!e.mulai) return '—';
    const start = new Date(e.mulai + 'T00:00:00');
    const end = e.sekarang ? new Date() : (e.selesai ? new Date(e.selesai + 'T00:00:00') : null);
    if (!end) return 'Pilih tanggal selesai atau centang "sampai sekarang"';
    if (end < start) return 'Tanggal tidak valid (selesai sebelum mulai)';
    const days = Math.floor((end - start) / 86400000) + 1;
    let years = end.getFullYear() - start.getFullYear();
    let months = end.getMonth() - start.getMonth();
    if (end.getDate() < start.getDate()) months--;
    if (months < 0) { years--; months += 12; }
    const parts = [];
    if (years > 0) parts.push(years + ' tahun');
    if (months > 0) parts.push(months + ' bulan');
    if (!parts.length) parts.push(days + ' hari');
    return `${parts.join(' ')} · ${days} hari${e.sekarang ? ' (berjalan)' : ''}`;
}
function onSekarang(e) { if (e.sekarang) e.selesai = ''; }

const experiences = computed(() => {
    const arr = multi.KERJA || [];
    return arr.filter((e) => e.perusahaan || e.posisiKerja || e.deskripsiKerja).map((e) => ({
        posisi: e.posisiKerja || '(Posisi belum diisi)',
        perusahaan: e.perusahaan || '—',
        periode: periodeText(e),
        durasi: durasiText(e),
        desc: e.deskripsiKerja,
    }));
});
const fileCards = computed(() => Object.entries(files).filter(([, v]) => v).map(([k, v]) => ({ key: k, ...v })));

// Step repeater (mis. Pengalaman) → array entri per step.key
const multi = reactive({});
function emptyEntry(s) { return Object.fromEntries((s.fields || []).map((f) => [f.key, ''])); }
steps.forEach((s) => { if (s.repeat) multi[s.key] = [emptyEntry(s)]; });
function addEntry(s) { multi[s.key].push(emptyEntry(s)); }
function removeEntry(s, i) { if (multi[s.key].length > 1) multi[s.key].splice(i, 1); }

function stepShort(k) {
    return {
        DIRI: 'Data Diri', DIDIK: 'Pendidikan', KERJA: 'Pengalaman', BERKAS: 'Berkas', SEDIA: 'Pernyataan',
        VALIDASI: 'Validasi', IDENTITAS: 'Identitas', DARURAT: 'Kontak', KESIAPAN: 'Kesiapan', DOKUMEN: 'Dokumen', PERSETUJUAN: 'Persetujuan',
        FACE: 'Verifikasi', REVIEW: 'Finalisasi',
    }[k] || k;
}
// Kondisional field (showIf) — hanya tampil bila syarat terpenuhi.
function showField(f) { return !f.showIf || form[f.showIf.key] === f.showIf.value; }

// ── Kamera / verifikasi wajah ──
const videoEl = ref(null);
const canvasEl = ref(null);
const cameraOn = ref(false);
const cameraError = ref(false);
const facePhoto = ref(null);
let stream = null;
async function startCamera() {
    cameraError.value = false;
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        cameraOn.value = true;
        await Promise.resolve();
        if (videoEl.value) videoEl.value.srcObject = stream;
    } catch (e) {
        cameraError.value = true;
        cameraOn.value = false;
    }
}
function capture() {
    const v = videoEl.value, c = canvasEl.value;
    if (!v || !c) return;
    c.width = v.videoWidth || 480;
    c.height = v.videoHeight || 360;
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
    facePhoto.value = c.toDataURL('image/jpeg', 0.85);
    stopCamera();
}
function stopCamera() {
    if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null; }
    cameraOn.value = false;
}
function retake() { facePhoto.value = null; startCamera(); }
onBeforeUnmount(stopCamera);

// ── Navigasi & validasi ──
function validateStep() {
    err.value = '';
    const s = cur.value;
    if (s.tipe === 'FACE' && !facePhoto.value) { err.value = 'Ambil foto wajah dulu untuk melanjutkan.'; return false; }
    if (s.tipe === 'FORM' && !s.opsional) {
        const miss = (s.fields || []).filter((f) => f.required && showField(f) && !form[f.key]);
        if (miss.length) { err.value = `Lengkapi: ${miss.map((f) => f.label).join(', ')}.`; return false; }
    }
    if (s.tipe === 'UPLOAD') {
        const miss = (s.files || []).filter((f) => f.required && !files[f.key]);
        if (miss.length) { err.value = `Unggah dulu: ${miss.map((f) => f.label).join(', ')}.`; return false; }
    }
    if (s.tipe === 'PERNYATAAN') {
        const ok = s.items.every((_, i) => checks[i]);
        if (!ok) { err.value = `Centang semua ${s.items.length} pernyataan untuk melanjutkan.`; return false; }
    }
    return true;
}
function next() { if (validateStep() && step.value < steps.length - 1) { step.value++; err.value = ''; persistDraft(); window.scrollTo({ top: 0, behavior: 'smooth' }); } }
function back() { if (step.value > 0) { step.value--; err.value = ''; persistDraft(); } }
function finalize() {
    if (!consentOk.value) return;
    for (let i = 0; i < steps.length; i++) { step.value = i; if (!validateStep()) return; }
    stopCamera();
    done.value = true;
    // Bila Form 2 (tahap lanjut) — tandai form2 selesai & majukan dari tahap biodata lanjutan.
    const isForm2 = props.flow.form === 2;
    const existing = getApp(lowongan.id);
    const pipeline = existing?.pipeline || flowFor(jenis);
    const base = {
        step: steps.length,
        pipeline,
        stageIdx: isForm2 ? Math.min((existing?.stageIdx ?? 2) + 1, pipeline.length - 1) : (existing?.stageIdx ?? 0),
        result: 'BERJALAN', stageStatus: 'BARU',
        appliedAt: existing?.appliedAt || new Date().toISOString().slice(0, 10),
        form2Done: isForm2 ? true : (existing?.form2Done || false),
        bio: isForm2
            ? [...(existing?.bio || []), ...reviewItems.value.map((r) => ({ label: r.label, value: r.value }))]
            : reviewItems.value.map((r) => ({ label: r.label, value: r.value })),
        experiences: experiences.value.length ? experiences.value.map((x) => ({ ...x })) : (existing?.experiences || []),
    };
    if (isForm2) {
        base.facePhoto = existing?.facePhoto || facePhoto.value || null;
        base.files = { ...(existing?.files || {}), ...Object.fromEntries(Object.entries(files).filter(([, v]) => v).map(([k, v]) => [k, { name: v.name, isPdf: v.isPdf }])) };
    }
    // Knock-out syarat wajib (mis. IPK < min) → langsung Tidak Lolos saat finalisasi.
    if (!isForm2) {
        const ko = checkKnockout(jenis, form);
        if (ko.length) { base.result = 'GAGAL'; base.stageStatus = 'GUGUR'; base.knockout = ko; }
    }
    const finalApp = snapshot('FINAL', base);
    finalApp.nextAction = nextActionFor(finalApp);
    upsertApp(finalApp);
    doneKo.value = base.knockout || [];
    notice('Lamaran difinalisasi & terkirim.');
}

// ── Sesi kandidat: draf tersimpan di sessionStorage, bisa dilanjutkan ──
function snapshot(status, extra = {}) {
    return {
        lowonganId: lowongan.id, posisi: lowongan.posisi, program: lowongan.program,
        lokasi: lowongan.lokasi, kategori: lowongan.kategori, jenis, form: props.flow.form,
        status, totalSteps: steps.length, step: step.value,
        form: { ...form }, checks: [...checks], consent: [...consent],
        multi: JSON.parse(JSON.stringify(multi)),
        files: Object.fromEntries(Object.entries(files).filter(([, v]) => v).map(([k, v]) => [k, { name: v.name, isPdf: v.isPdf }])),
        facePhoto: facePhoto.value || null,
        ...extra,
    };
}
function persistDraft() {
    if (done.value || !lowongan.id) return;
    const cur0 = getApp(lowongan.id);
    if (cur0 && cur0.status === 'FINAL') return; // sudah final — jangan turunkan ke draf
    upsertApp(snapshot('DRAFT', { appliedAt: cur0?.appliedAt || null }));
}
function restore(a) {
    Object.assign(form, a.form || {});
    (a.checks || []).forEach((v, i) => { checks[i] = v; });
    (a.consent || []).forEach((v, i) => { consent[i] = v; });
    Object.entries(a.multi || {}).forEach(([k, v]) => { if (Array.isArray(v)) multi[k] = v; });
    Object.entries(a.files || {}).forEach(([k, v]) => { files[k] = { ...v, url: null, stale: true }; });
    if (a.facePhoto) facePhoto.value = a.facePhoto;
    if (typeof a.step === 'number') step.value = Math.min(a.step, steps.length - 1);
}
function showFile(fc) {
    if (!fc) return;
    if (!fc.url) { notice('Berkas dari draf — unggah ulang untuk pratinjau.'); return; }
    preview.value = fc;
}

onMounted(() => {
    // Apply WAJIB login
    const isForm2 = props.flow.form === 2;
    if (!isLoggedIn()) { requireLogin(`/test/karir/apply/${lowongan.id}${isForm2 ? '?form=2' : ''}`); return; }
    const saved = getApp(lowongan.id);
    const sess = getSession();
    if (isForm2) {
        // Tahap lanjut: jangan tampilkan layar selesai; prefill field readonly (namaPre/emailPre/waPre).
        const f = saved?.form || {};
        form.namaPre = f.nama || sess?.nama || '';
        form.emailPre = f.email || sess?.email || '';
        form.waPre = f.hp || f.phone || '';
        return;
    }
    if (saved && saved.status === 'FINAL') { done.value = true; return; } // sudah dilamar (Form 1)
    if (saved) restore(saved);
    // Prefill identitas dari sesi (isi bila kosong)
    if (sess) {
        if (!form.nama) form.nama = sess.nama;
        if (!form.email) form.email = sess.email;
    }
});

// Review generik: kumpulkan semua field FORM yang terisi (label + nilai), lintas Form 1 / Form 2 / rekrutmen.
const reviewItems = computed(() => {
    const items = [];
    steps.forEach((s) => {
        if (s.tipe === 'FORM') (s.fields || []).forEach((f) => {
            if (!f.readonly && showField(f) && form[f.key] !== undefined && form[f.key] !== '') items.push({ label: f.label, value: form[f.key] });
        });
    });
    return items;
});

function processFile(key, file, f) {
    uploadErr.value = '';
    if (!file) return;
    const exts = (f.accept || '.pdf').split(',').map((s) => s.trim().replace(/^\./, '').toLowerCase());
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!exts.includes(ext)) { uploadErr.value = `${f.label}: hanya ${exts.map((x) => '.' + x).join(' / ')} yang diperbolehkan.`; return; }
    if (files[key]?.url) URL.revokeObjectURL(files[key].url);
    files[key] = { name: file.name, url: URL.createObjectURL(file), isPdf: ext === 'pdf' };
}
// el-upload on-change → ambil File asli dari uploadFile.raw
function onFileEP(key, uf, f) { processFile(key, uf && uf.raw, f); }
onBeforeUnmount(() => Object.values(files).forEach((v) => v && v.url && URL.revokeObjectURL(v.url)));

const toast = ref('');
let tm = null;
function notice(m) { toast.value = m; if (tm) clearTimeout(tm); tm = setTimeout(() => (toast.value = ''), 3000); }
</script>
