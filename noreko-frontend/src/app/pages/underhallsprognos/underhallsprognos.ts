import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  UnderhallsprognosService,
  OverviewData,
  ScheduleData,
  SchemaRad,
  HistoryData,
} from '../../services/underhallsprognos.service';
import { parseLocalDate } from '../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-underhallsprognos',
  templateUrl: './underhallsprognos.html',
  styleUrls: ['./underhallsprognos.css'],
  imports: [CommonModule],
})
export class UnderhallsprognosComponent implements OnInit, OnDestroy {

  // Math-referens för template
  Math = Math;

  // ---- Laddningsstatus ----
  overviewLoading  = false;
  overviewLoaded   = false;
  scheduleLoading  = false;
  scheduleLoaded   = false;
  historyLoading   = false;
  historyLoaded    = false;
  hasError         = false;

  // ---- Data ----
  overviewData: OverviewData | null = null;
  scheduleData: ScheduleData | null = null;
  historyData: HistoryData | null = null;

  // ---- UI-state ----
  lastRefreshed: Date | null = null;
  historyDays: number = 90;

  private destroy$ = new Subject<void>();
  private timelineChart: Chart | null = null;
  private chartBuildTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(private service: UnderhallsprognosService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartBuildTimer) {
      clearTimeout(this.chartBuildTimer);
      this.chartBuildTimer = null;
    }
    this.destroyChart();
  }

  private destroyChart(): void {
    try { this.timelineChart?.destroy(); } catch (_) {}
    this.timelineChart = null;
  }

  private loadAll(): void {
    this.loadOverview();
    this.loadSchedule();
    this.loadHistory();
  }

  private loadOverview(): void {
    if (this.overviewLoading) return;
    this.overviewLoading = true;

    this.service.getOverview()
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.overviewLoading = false;
        this.overviewLoaded = true;
        if (res?.success) {
          this.overviewData = res.data;
          this.hasError = false;
        } else {
          this.overviewData = null;
          this.hasError = true;
        }
        this.lastRefreshed = new Date();
      });
  }

  private loadSchedule(): void {
    if (this.scheduleLoading) return;
    this.scheduleLoading = true;

    this.service.getSchedule()
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.scheduleLoading = false;
        this.scheduleLoaded = true;
        if (res?.success) {
          this.scheduleData = res.data;
          // Bygg tidslinje-diagram efter att data laddats
          if (this.chartBuildTimer) clearTimeout(this.chartBuildTimer);
          this.chartBuildTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.renderTimelineChart();
          }, 150);
        } else {
          this.scheduleData = null;
        }
      });
  }

  private loadHistory(): void {
    if (this.historyLoading) return;
    this.historyLoading = true;

    this.service.getHistory(this.historyDays, 50)
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.historyLoading = false;
        this.historyLoaded = true;
        if (res?.success) {
          this.historyData = res.data;
        } else {
          this.historyData = null;
        }
      });
  }

  setHistoryDays(days: number): void {
    if (this.historyDays === days) return;
    this.historyDays = days;
    this.historyLoaded = false;
    this.historyData = null;
    this.loadHistory();
  }

  // ================================================================
  // CHART-RENDERING — Tidslinje-diagram
  // ================================================================

  private renderTimelineChart(): void {
    this.destroyChart();

    const canvas = document.getElementById('timelineChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.scheduleData?.schema?.length) return;

    // Ta de 10 närmaste underhållspunkterna (sorterade efter nasta_datum)
    const sorted = [...this.scheduleData.schema]
      .filter(r => r.nasta_datum !== null)
      .sort((a, b) => (a.nasta_datum ?? '').localeCompare(b.nasta_datum ?? ''))
      .slice(0, 10);

    const labels = sorted.map(r => r.komponent);

    // Dagar kvar (kan vara negativt)
    const dagarKvar = sorted.map(r => r.dagar_kvar ?? 0);

    // Bakgrundsfärger baserat på status
    const bgColors = sorted.map(r => {
      if (r.status === 'forsenat') return 'rgba(252, 92, 101, 0.8)';
      if (r.status === 'snart')    return 'rgba(254, 211, 48, 0.8)';
      return 'rgba(38, 222, 129, 0.8)';
    });

    const borderColors = sorted.map(r => {
      if (r.status === 'forsenat') return '#fc5c65';
      if (r.status === 'snart')    return '#fed330';
      return '#26de81';
    });

    if (this.timelineChart) { (this.timelineChart as any).destroy(); }
    this.timelineChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Dagar kvar till underhåll',
          data: dagarKvar,
          backgroundColor: bgColors,
          borderColor: borderColors,
          borderWidth: 1,
          borderRadius: 4,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: ctx => {
                const val = ctx.parsed.x ?? 0;
                if (val < 0) return `Försenat med ${Math.abs(val)} dagar`;
                if (val === 0) return 'Förfaller idag';
                return `${val} dagar kvar`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#e2e8f0' },
            grid:  { color: 'rgba(255,255,255,0.05)' },
            title: {
              display: true,
              text: 'Dagar kvar (negativa = försenat)',
              color: '#a0aec0',
            },
          },
          y: {
            ticks: { color: '#e2e8f0', font: { size: 12 } },
            grid:  { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  // ================================================================
  // HJÄLPMETODER FÖR TEMPLATE
  // ================================================================

  getStatusLabel(status: string): string {
    if (status === 'forsenat') return 'Försenat';
    if (status === 'snart')    return 'Snart';
    return 'OK';
  }

  getStatusClass(status: string): string {
    if (status === 'forsenat') return 'badge-danger';
    if (status === 'snart')    return 'badge-warning';
    return 'badge-ok';
  }

  getProgressClass(status: string): string {
    if (status === 'forsenat') return 'progress-danger';
    if (status === 'snart')    return 'progress-warning';
    return 'progress-ok';
  }

  getRowClass(status: string): string {
    if (status === 'forsenat') return 'row-danger';
    if (status === 'snart')    return 'row-warning';
    return '';
  }

  getDagarText(rad: SchemaRad): string {
    if (rad.aldrig_underhalls) return 'Aldrig underhållit';
    if (rad.dagar_kvar === null) return '—';
    if (rad.dagar_kvar < 0) return `Försenat ${Math.abs(rad.dagar_kvar)} dagar`;
    if (rad.dagar_kvar === 0) return 'Förfaller idag';
    return `${rad.dagar_kvar} dagar kvar`;
  }

  getDagarClass(rad: SchemaRad): string {
    if (rad.aldrig_underhalls) return 'text-danger';
    if (rad.dagar_kvar === null) return 'text-muted';
    if (rad.dagar_kvar < 0) return 'text-danger fw-bold';
    if (rad.dagar_kvar <= 7) return 'text-warning fw-bold';
    return 'text-success';
  }

  formatIntervall(dagar: number): string {
    if (dagar < 7)   return `${dagar} dag${dagar !== 1 ? 'ar' : ''}`;
    if (dagar < 30)  return `${Math.round(dagar / 7)} vecka${Math.round(dagar / 7) !== 1 ? 'r' : ''}`;
    if (dagar < 365) return `${Math.round(dagar / 30)} månad${Math.round(dagar / 30) !== 1 ? 'er' : ''}`;
    return `${Math.round(dagar / 365)} år`;
  }

  formatDatum(datum: string | null): string {
    if (!datum) return '—';
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  formatVaraktighet(min: number | null): string {
    if (min === null || min === undefined) return '—';
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }

  getKallaLabel(kalla: string): string {
    if (kalla === 'maintenance_log') return 'Underhållslogg';
    if (kalla === 'underhallslogg') return 'Underhållslogg';
    return kalla;
  }

  getTypLabel(typ: string): string {
    const map: Record<string, string> = {
      'planerat':    'Planerat',
      'akut':        'Akut',
      'inspektion':  'Inspektion',
      'kalibrering': 'Kalibrering',
      'rengoring':   'Rengöring',
      'ovrigt':      'Övrigt',
      'oplanerat':   'Oplanerat',
    };
    return map[typ] ?? typ;
  }

  getMaskinLabel(maskin: string): string {
    const map: Record<string, string> = {
      'rebotling':            'Rebotling',
      'tvattlinje':           'Tvättlinje',
      'saglinje':             'Såglinje',
      'klassificeringslinje': 'Klassificeringslinje',
      'allmant':              'Allmänt',
    };
    return map[maskin] ?? maskin;
  }

  isLoading(): boolean {
    return this.overviewLoading || this.scheduleLoading;
  }

  get forsenadePoster(): SchemaRad[] {
    return this.scheduleData?.schema.filter(r => r.status === 'forsenat') ?? [];
  }

  get snartPoster(): SchemaRad[] {
    return this.scheduleData?.schema.filter(r => r.status === 'snart') ?? [];
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
