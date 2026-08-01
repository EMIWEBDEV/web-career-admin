import { describe, expect, it } from 'vitest';
import {
    formatRentang, hitungPersentasePeserta, keEventFullCalendar, salinRingkasanEvent,
    saringEvent, tanggalApi, warnaEvent,
} from './calendarHelpers';

const events = [
    { id: '1', jenis: 'TES', judul: 'Psikotes', program: 'MT 2026', mulai: '2026-08-03 08:00:00', akhir: '2026-08-03 10:00:00', kesiapan: 'SIAP', jumlahPeserta: 50, pesertaTerkirim: 40 },
    { id: '2', jenis: 'AGENDA', judul: 'Onboarding', program: 'Magang Batch 4', mulai: '2026-08-05', akhir: '2026-08-06', allDay: true },
    { id: '3', jenis: 'TUTUP', judul: 'Pendaftaran ditutup', program: 'Rekrutmen Q3', mulai: '2026-08-07 23:59:00' },
];

describe('calendarHelpers', () => {
    it('memfilter jenis, program, dan kata secara bersamaan', () => {
        expect(saringEvent(events, { jenis: 'TES', program: 'MT 2026', cari: 'psiko' })).toEqual([events[0]]);
        expect(saringEvent(events, { cari: 'batch 4' })).toEqual([events[1]]);
    });

    it('memetakan kontrak API ke kontrak FullCalendar', () => {
        const event = keEventFullCalendar(events[0]);
        expect(event).toMatchObject({ id: '1', title: 'Psikotes', start: events[0].mulai, allDay: false });
        expect(event.extendedProps).toBe(events[0]);
    });

    it('mendahulukan warna konflik dan perhatian', () => {
        expect(warnaEvent({ jenis: 'TES', konflik: true })).toBe('#b91c1c');
        expect(warnaEvent({ jenis: 'TES', kesiapan: 'PERLU_PERHATIAN' })).toBe('#c2410c');
    });

    it('membuat tanggal lokal stabil dan membaca akhir all-day yang eksklusif', () => {
        expect(tanggalApi(new Date(2026, 7, 1))).toBe('2026-08-01');
        expect(formatRentang(events[1])).toContain('Sepanjang hari');
    });

    it('menghitung persentase peserta dan membuat ringkasan teks', () => {
        expect(hitungPersentasePeserta(events[0])).toBe(80);
        expect(hitungPersentasePeserta(events[1])).toBe(0);

        const ringkasan = salinRingkasanEvent(events[0]);
        expect(ringkasan).toContain('📌 Psikotes');
        expect(ringkasan).toContain('MT 2026');
        expect(ringkasan).toContain('40 Terkirim');
    });
});

