import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import * as XLSX from 'xlsx';
import Chart from 'chart.js/auto';

interface CalendarDay {
  date: string;
  ibc: number;
  goal: number;
  pct: number;
}

interface CalendarResponse {
  success: boolean;
  year: number;
  days: CalendarDay[];
}

interface WeekRow {
  weekLabel: string;
  cells: (CalendarDay | null)[];
}

interface MonthBlock {
  month: number;        // 0–11
  monthName: string;
  weeks: WeekRow[];
}

interface HourlyData {
  timme: number;
  ibc: number;
  ibc_per_h: number;
  runtime_min: number;
  ej_ok: number;
  skift: number;
}

interface DaySummary {
  total_ibc: number;
  avg_ibc_per_h: number;
  skift1_ibc: number;
  skift2_ibc: number;
  skift3_ibc: number;
  total_ej_ok: number;
  quality_pct: number;
  active_hours: number;
}

interface Operator {
  id: number;
  name: string;
  initials: string;
}

interface DayDetailResponse {
  success: boolean;
  date: string;
  hourly: HourlyData[];
  summary: DaySummary;
  operators: Operator[];
}

@Component({
  selector: 'app-production-calendar',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './production-calendar.html',
  styleUrls: ['./production-calendar.css']
})
export class ProductionCalendarPage implements OnInit, OnDestroy {
  Math = Math;

  selectedYear: number = new Date().getFullYear();
  availableYears: number[] = [];

  loading = false;
  error = '';

  days: CalendarDay[] = [];
  dayMap: Map<string, CalendarDay> = new Map();

  // KPI
  totalIbc = 0;
  avgIbcPerDay = 0;
  bestDayIbc = 0;
  daysOnTarget = 0;
  totalProductionDays = 0;
  daysOnTargetPct = 0;
  bestDate = '';

  // Grid: 12 months each with a grid of weeks
  monthBlocks: MonthBlock[] = [];

  // === Dagdetalj ===
  selectedDay: CalendarDay | null = null;
  dayDetail: DayDetailResponse | null = null;
  dayDetailLoading = false;
  private dayDetailChart: Chart | null = null;
  private dayDetailTimer: ReturnType<typeof setTimeout> | null = null;

  private destroy$ = new Subject<void>();

  private readonly MONTH_NAMES_SV = [
    'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun',
    'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'
  ];

  private readonly MONTH_NAMES_FULL_SV = [
    'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
    'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'
  ];

  private readonly DAY_NAMES_FULL_SV = [
    'Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'
  ];

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    const currentYear = new Date().getFullYear();
    // Erbjud år 2023 till aktuellt år
    for (let y = 2023; y <= currentYear; y++) {
      this.availableYears.push(y);
    }
    this.loadCalendar();
  }

  ngOnDestroy(): void {
    if (this.dayDetailTimer !== null) {
      clearTimeout(this.dayDetailTimer);
      this.dayDetailTimer = null;
    }
    try { this.dayDetailChart?.destroy(); } catch (e) {}
    this.dayDetailChart = null;
    this.destroy$.next();
    this.destroy$.complete();
  }

  onYearChange(event: Event): void {
    const val = (event.target as HTMLSelectElement).value;
    this.selectedYear = parseInt(val, 10);
    this.closeDayDetail();
    this.loadCalendar();
  }

  prevYear(): void {
    if (this.selectedYear > 2023) {
      this.selectedYear--;
      this.closeDayDetail();
      this.loadCalendar();
    }
  }

  nextYear(): void {
    if (this.selectedYear < new Date().getFullYear()) {
      this.selectedYear++;
      this.closeDayDetail();
      this.loadCalendar();
    }
  }

  loadCalendar(): void {
    this.loading = true;
    this.error = '';
    this.days = [];
    this.dayMap = new Map();
    this.monthBlocks = [];

    const url = `/noreko-backend/api.php?action=rebotling&run=year-calendar&year=${this.selectedYear}`;

    this.http.get<CalendarResponse>(url, { withCredentials: true })
      .pipe(
        timeout(10000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.loading = false;
          if (res?.success && res.days) {
            this.days = res.days;
            res.days.forEach(d => this.dayMap.set(d.date, d));
            this.computeKpis();
            this.buildGrid();
          } else {
            this.error = 'Ingen kalenderdata tillgänglig.';
            this.buildGrid(); // visa tom grid
          }
        },
        error: () => {
          this.loading = false;
          this.error = 'Kunde inte hämta data från servern.';
          this.buildGrid();
        }
      });
  }

  // =============================================
  // DAGDETALJ — DRILL-DOWN
  // =============================================

  selectDay(day: CalendarDay | null): void {
    if (!day) return;

    // Toggle: klick på samma dag stänger panelen
    if (this.selectedDay?.date === day.date) {
      this.closeDayDetail();
      return;
    }

    this.selectedDay = day;
    this.dayDetail = null;
    this.loadDayDetail(day.date);
  }

  closeDayDetail(): void {
    this.selectedDay = null;
    this.dayDetail = null;
    this.dayDetailLoading = false;
    try { this.dayDetailChart?.destroy(); } catch (e) {}
    this.dayDetailChart = null;
  }

  loadDayDetail(date: string): void {
    this.dayDetailLoading = true;
    try { this.dayDetailChart?.destroy(); } catch (e) {}
    this.dayDetailChart = null;

    const url = `/noreko-backend/api.php?action=rebotling&run=day-detail&date=${date}`;

    this.http.get<DayDetailResponse>(url, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.dayDetailLoading = false;
          if (res?.success) {
            this.dayDetail = res;
            // Ge Angular tid att rendera canvas-elementet
            this.dayDetailTimer = setTimeout(() => { if (!this.destroy$.closed) this.buildDayDetailChart(); }, 50);
          }
        }
      });
  }

  buildDayDetailChart(): void {
    try { this.dayDetailChart?.destroy(); } catch (e) {}
    this.dayDetailChart = null;

    const canvas = document.getElementById('dayDetailChart') as HTMLCanvasElement | null;
    if (!canvas || !this.dayDetail) return;

    const hourly = this.dayDetail.hourly;
    const avgIbc = this.dayDetail.summary.avg_ibc_per_h;

    const labels = hourly.map(h => `${String(h.timme).padStart(2, '0')}:00`);
    const data   = hourly.map(h => h.ibc_per_h);

    // Färg per IBC/h vs genomsnitt
    const colors = hourly.map(h => {
      if (h.ibc === 0) return 'rgba(74, 85, 104, 0.6)';      // grå — ingen produktion
      if (h.ibc_per_h >= avgIbc * 1.1) return 'rgba(72, 187, 120, 0.85)';  // grön — bra
      if (h.ibc_per_h >= avgIbc * 0.8) return 'rgba(214, 158, 46, 0.85)';  // gul — nära
      return 'rgba(229, 62, 62, 0.85)';                        // röd — lågt
    });

    // Skift-bakgrundsfärger som annotations (via plugin-fri metod: alternativa dataset)
    const skiftColors = hourly.map(h => {
      switch (h.skift) {
        case 1: return 'rgba(66, 153, 225, 0.08)';   // blå — skift 1
        case 2: return 'rgba(72, 187, 120, 0.08)';   // grön — skift 2
        default: return 'rgba(159, 122, 234, 0.08)'; // lila — skift 3
      }
    });

    this.dayDetailChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h',
            data,
            backgroundColor: colors,
            borderColor: colors.map(c => c.replace('0.85', '1').replace('0.6', '1')),
            borderWidth: 1,
            borderRadius: 4,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: (items) => {
                const idx = items[0].dataIndex;
                const h = hourly[idx];
                const dateLabel = this.selectedDay ? this.formatDayDetailTitle() : '';
                return [
                  dateLabel,
                  `${String(h.timme).padStart(2, '0')}:00 — Skift ${h.skift}`
                ].filter(s => s !== '');
              },
              label: (item) => {
                const idx = item.dataIndex;
                const h = hourly[idx];
                const goal = this.selectedDay?.goal ?? 0;
                const lines = [
                  `  IBC producerade: ${h.ibc}`,
                  `  IBC per timme: ${h.ibc_per_h}`,
                  `  Drifttid: ${h.runtime_min} min`,
                ];
                if (goal > 0) {
                  lines.push(`  Dagsmål: ${goal} IBC`);
                }
                if (h.ej_ok > 0) {
                  lines.push(`  Ej OK: ${h.ej_ok}`);
                }
                return lines;
              }
            },
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
          }
        },
        scales: {
          x: {
            ticks: { color: '#718096', font: { size: 11 } },
            grid: { color: 'rgba(74,85,104,0.3)' }
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#718096', font: { size: 11 } },
            grid: { color: 'rgba(74,85,104,0.3)' },
            title: {
              display: true,
              text: 'IBC / timme',
              color: '#718096',
              font: { size: 11 }
            }
          }
        }
      }
    });
  }

  formatDayDetailTitle(): string {
    if (!this.selectedDay) return '';
    const d = new Date(this.selectedDay.date + 'T00:00:00');
    const dayName = this.DAY_NAMES_FULL_SV[d.getDay()];
    const monthName = this.MONTH_NAMES_FULL_SV[d.getMonth()];
    return `${dayName} ${d.getDate()} ${monthName} ${d.getFullYear()}`;
  }

  // =============================================
  // KALENDER-GRID
  // =============================================

  private computeKpis(): void {
    if (this.days.length === 0) {
      this.totalIbc = 0;
      this.avgIbcPerDay = 0;
      this.bestDayIbc = 0;
      this.daysOnTarget = 0;
      this.totalProductionDays = 0;
      this.daysOnTargetPct = 0;
      this.bestDate = '';
      return;
    }

    this.totalIbc = this.days.reduce((sum, d) => sum + d.ibc, 0);
    this.totalProductionDays = this.days.length;
    this.avgIbcPerDay = Math.round(this.totalIbc / this.totalProductionDays);

    const bestDay = this.days.reduce((best, d) => d.ibc > best.ibc ? d : best, this.days[0]);
    this.bestDayIbc = bestDay.ibc;
    this.bestDate = bestDay.date;

    this.daysOnTarget = this.days.filter(d => d.pct >= 95).length;
    this.daysOnTargetPct = Math.round((this.daysOnTarget / this.totalProductionDays) * 100);
  }

  private buildGrid(): void {
    this.monthBlocks = [];

    // Bygg en fullständig lista av alla dagar i valt år, organiserat per månad → per vecka (mån–sön)
    for (let m = 0; m < 12; m++) {
      const firstDay = new Date(this.selectedYear, m, 1);
      const lastDay  = new Date(this.selectedYear, m + 1, 0);

      // ISO-veckodag: 0=sön, 1=mån, ... 6=lör → vi vill ha mån som first col
      // getDay() returns 0=Sun, 1=Mon, ..., 6=Sat
      // Vi vill: 0=Mån,1=Tis,...,6=Sön
      const firstDow = (firstDay.getDay() + 6) % 7; // Mån=0
      const totalDays = lastDay.getDate();

      const cells: (CalendarDay | null)[] = [];

      // Fyll ledande tomma celler (förra månadens dagar)
      for (let i = 0; i < firstDow; i++) {
        cells.push(null);
      }

      // Fyll faktiska dagar
      for (let d = 1; d <= totalDays; d++) {
        const dateStr = this.formatDateStr(this.selectedYear, m + 1, d);
        const dayData = this.dayMap.get(dateStr) ?? null;
        if (dayData) {
          cells.push(dayData);
        } else {
          // Tom cell med bara datum
          cells.push(null);
        }
      }

      // Fyll avslutande tomma celler så varje månad har hela veckor
      while (cells.length % 7 !== 0) {
        cells.push(null);
      }

      // Dela upp i veckor
      const weeks: WeekRow[] = [];
      for (let wi = 0; wi < cells.length; wi += 7) {
        const weekCells = cells.slice(wi, wi + 7);
        // Hitta första icke-null cellen för att beräkna veckonummer
        weeks.push({
          weekLabel: '',
          cells: weekCells
        });
      }

      this.monthBlocks.push({
        month: m,
        monthName: this.MONTH_NAMES_SV[m],
        weeks
      });
    }
  }

  private formatDateStr(year: number, month: number, day: number): string {
    const mm = String(month).padStart(2, '0');
    const dd = String(day).padStart(2, '0');
    return `${year}-${mm}-${dd}`;
  }

  // Returnera dag-nummer från datumsträngen "YYYY-MM-DD"
  getDayNum(dateStr: string | null): number {
    if (!dateStr) return 0;
    return parseInt(dateStr.split('-')[2], 10);
  }

  // Returnera datumsträngen för en given cell-position
  getCellDate(month: number, weekIndex: number, dayIndex: number): string | null {
    const block = this.monthBlocks[month];
    if (!block) return null;
    const week = block.weeks[weekIndex];
    if (!week) return null;
    // Vi lagrar CalendarDay|null, men vi behöver datum för null-celler också.
    // Vi beräknar datumet från position istället.
    const firstDay  = new Date(this.selectedYear, month, 1);
    const firstDow  = (firstDay.getDay() + 6) % 7;
    const cellIdx   = weekIndex * 7 + dayIndex - firstDow;
    if (cellIdx < 0 || cellIdx >= new Date(this.selectedYear, month + 1, 0).getDate()) return null;
    return this.formatDateStr(this.selectedYear, month + 1, cellIdx + 1);
  }

  getCellColorClass(cell: CalendarDay | null, selected: boolean = false): string {
    const base = (() => {
      if (!cell) return 'cell-empty';
      if (cell.pct >= 110) return 'cell-superday';
      if (cell.pct >= 95)  return 'cell-green';
      if (cell.pct >= 80)  return 'cell-yellow';
      if (cell.pct >= 60)  return 'cell-orange';
      return 'cell-red';
    })();
    return selected ? base + ' cell-selected' : base;
  }

  getTooltip(cell: CalendarDay | null, month: number, weekIndex: number, dayIndex: number): string {
    const dateStr = this.getCellDate(month, weekIndex, dayIndex);
    if (!cell) {
      return dateStr ? this.formatSwedishDate(dateStr) + ' — Ingen produktion' : '';
    }
    return `${this.formatSwedishDate(cell.date)}\n${cell.ibc} IBC av ${cell.goal} planerade\n${cell.pct.toFixed(0)}% av dagsmål\nKlicka för timvis detalj`;
  }

  private formatSwedishDate(dateStr: string): string {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T00:00:00');
    const days = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    return `${days[d.getDay()]} ${d.getDate()} ${this.MONTH_NAMES_SV[d.getMonth()]}`;
  }

  formatBestDate(): string {
    return this.bestDate ? this.formatSwedishDate(this.bestDate) : '—';
  }

  // =============================================
  // EXPORT-FUNKTIONER
  // =============================================

  private getDayStatus(day: CalendarDay): string {
    if (day.pct >= 110) return 'Superdag';
    if (day.pct >= 95)  return 'Bra';
    if (day.pct >= 80)  return 'OK';
    if (day.pct >= 60)  return 'Under mål';
    return 'Låg';
  }

  private getDayName(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    const names = ['Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'];
    return names[d.getDay()];
  }

  exportToExcel(): void {
    if (this.days.length === 0) return;

    // Rubrikrad
    const headers = ['Datum', 'Dag', 'IBC', 'Mål', '% av mål', 'Status'];

    // Datarows — en per produktionsdag
    const dataRows = this.days.map(d => [
      d.date,
      this.getDayName(d.date),
      d.ibc,
      d.goal,
      d.goal > 0 ? Math.round(d.ibc / d.goal * 100) : 0,
      this.getDayStatus(d)
    ]);

    // Tom rad + summering
    const emptyRow: (string | number)[] = [];
    const summaryHeader = ['SUMMERING', '', '', '', '', ''];
    const totalRow = ['Totalt IBC', '', this.totalIbc, '', '', ''];
    const avgRow = ['Snitt per dag', '', this.avgIbcPerDay, '', '', ''];
    const bestRow = ['Bästa dag', this.formatBestDate(), this.bestDayIbc, '', '', 'Superdag'];
    const targetRow = ['Dagar på mål (≥95%)', '', this.daysOnTarget, `av ${this.totalProductionDays}`, `${this.daysOnTargetPct}%`, ''];

    const allRows = [
      headers,
      ...dataRows,
      emptyRow,
      summaryHeader,
      totalRow,
      avgRow,
      bestRow,
      targetRow
    ];

    const ws = XLSX.utils.aoa_to_sheet(allRows);

    // Kolumnbredder
    ws['!cols'] = [
      { wch: 12 }, // Datum
      { wch: 12 }, // Dag
      { wch: 8 },  // IBC
      { wch: 8 },  // Mål
      { wch: 10 }, // % av mål
      { wch: 12 }  // Status
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, `Produktionskalender ${this.selectedYear}`);
    XLSX.writeFile(wb, `Produktionskalender_${this.selectedYear}.xlsx`);
  }

  exportToPDF(): void {
    window.print();
  }
  trackByIndex(index: number): number { return index; }
}
