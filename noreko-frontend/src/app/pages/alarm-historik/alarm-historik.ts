import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  AlarmHistorikService,
  Alarm,
  AlarmSummaryData,
  AlarmTimelineData,
} from '../../services/alarm-historik.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-alarm-historik',
  templateUrl: './alarm-historik.html',
  styleUrls: ['./alarm-historik.css'],
  imports: [CommonModule, FormsModule],
})
export class AlarmHistorikPage implements OnInit, OnDestroy {
  // -- Period & filter --
  days = 30;
  readonly dayOptions = [7, 30, 90];
  filterSeverity = 'all';
  filterTyp      = 'all';
  filterStatus   = 'all';

  // -- Laddning --
  loadingSummary  = false;
  loadingList     = false;
  loadingTimeline = false;

  // -- Fel --
  errorSummary  = false;
  errorList     = false;
  errorTimeline = false;

  // -- Data --
  summary: AlarmSummaryData | null = null;
  alarms: Alarm[] = [];
  timelineData: AlarmTimelineData | null = null;

  // -- Typlista (populeras fran larm) --
  typOptions: string[] = [];

  // -- Chart --
  private timelineChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private svc: AlarmHistorikService) {}

  ngOnInit(): void {
    this.loadAll();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyChart();
  }

  private destroyChart(): void {
    try { this.timelineChart?.destroy(); } catch (_) {}
    this.timelineChart = null;
  }

  // =================================================================
  // Period / Filter
  // =================================================================

  onDaysChange(d: number): void {
    this.days = d;
    this.filterSeverity = 'all';
    this.filterTyp      = 'all';
    this.filterStatus   = 'all';
    this.loadAll();
  }

  onFilterChange(): void {
    this.loadList();
  }

  // =================================================================
  // Data
  // =================================================================

  loadAll(): void {
    this.loadSummary();
    this.loadList();
    this.loadTimeline();
  }

  loadSummary(): void {
    this.loadingSummary = true;
    this.errorSummary   = false;
    this.svc.getSummary(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingSummary = false;
        if (res?.success) {
          this.summary = res.data;
        } else {
          this.errorSummary = true;
        }
      });
  }

  loadList(): void {
    this.loadingList = true;
    this.errorList   = false;
    this.svc.getList(this.days, this.filterStatus, this.filterSeverity, this.filterTyp)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingList = false;
        if (res?.success) {
          this.alarms = res.data.alarms ?? [];
          // Bygg typlista fran aktuella larm (utan filter)
          if (this.filterSeverity === 'all' && this.filterTyp === 'all' && this.filterStatus === 'all') {
            const typer = [...new Set(this.alarms.map(a => a.typ))].sort();
            this.typOptions = typer;
          }
        } else {
          this.errorList = true;
          this.alarms    = [];
        }
      });
  }

  loadTimeline(): void {
    this.loadingTimeline = true;
    this.errorTimeline   = false;
    this.svc.getTimeline(this.days)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTimeline = false;
        if (res?.success) {
          this.timelineData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.buildTimelineChart(); }, 0);
        } else {
          this.errorTimeline = true;
          this.timelineData  = null;
        }
      });
  }

  // =================================================================
  // Chart.js — Staplat stapeldiagram (larm per dag per severity)
  // =================================================================

  private buildTimelineChart(): void {
    this.destroyChart();
    const canvas = document.getElementById('alarmTimelineChart') as HTMLCanvasElement;
    if (!canvas || !this.timelineData?.har_data) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { labels, datasets } = this.timelineData;

    this.timelineChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: datasets.map(ds => ({
          label: ds.label,
          data: ds.data,
          backgroundColor: ds.backgroundColor,
          borderColor: ds.borderColor,
          borderWidth: ds.borderWidth,
          stack: ds.stack,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              title: (items) => {
                // Visa fullt datum om tillgangligt
                const idx = items[0]?.dataIndex ?? 0;
                const fullDate = this.timelineData?.dates?.[idx];
                return fullDate ? this.formatDatum(fullDate) : items[0]?.label ?? '';
              },
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal larm', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =================================================================
  // Hjalpmetoder
  // =================================================================

  severityLabel(s: string): string {
    const labels: Record<string, string> = {
      critical: 'Kritisk',
      warning:  'Varning',
      info:     'Info',
    };
    return labels[s] ?? s;
  }

  severityBadgeClass(s: string): string {
    const map: Record<string, string> = {
      critical: 'badge-critical',
      warning:  'badge-warning',
      info:     'badge-info',
    };
    return 'alarm-badge ' + (map[s] ?? '');
  }

  statusLabel(s: string): string {
    return s === 'active' ? 'Aktiv' : 'Atgardad';
  }

  statusClass(s: string): string {
    return s === 'active' ? 'text-danger' : 'text-success';
  }

  formatDatum(d: string): string {
    if (!d) return '-';
    const parts = d.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    return d;
  }

  formatTid(t: string): string {
    if (!t) return '-';
    return t.substring(0, 5); // HH:MM
  }

  formatVaraktighet(min: number | null): string {
    if (min === null || min === undefined) return '-';
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }

  get typOptionsForFilter(): string[] {
    // Anvand summary.per_typ om tillganglig, annars typOptions fran larm
    if (this.summary?.per_typ) {
      return Object.keys(this.summary.per_typ).sort();
    }
    return this.typOptions;
  }
  trackByIndex(index: number): number { return index; }
}
