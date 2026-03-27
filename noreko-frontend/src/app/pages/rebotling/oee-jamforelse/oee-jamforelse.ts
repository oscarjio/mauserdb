import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  OeeJamforelseService,
  WeeklyOeeData,
} from '../../../services/oee-jamforelse.service';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-oee-jamforelse',
  templateUrl: './oee-jamforelse.html',
  styleUrls: ['./oee-jamforelse.css'],
  imports: [CommonModule, FormsModule, PdfExportButtonComponent],
})
export class OeeJamforelsePage implements OnInit, OnDestroy {

  // Periodselektor
  veckor = 12;
  readonly veckorAlternativ = [
    { varde: 8,  etikett: '8 veckor' },
    { varde: 12, etikett: '12 veckor' },
    { varde: 26, etikett: '26 veckor' },
    { varde: 52, etikett: '52 veckor' },
  ];

  // Loading / Error
  loading = false;
  error = false;

  // Data
  data: WeeklyOeeData | null = null;

  // Chart
  private oeeChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private svc: OeeJamforelseService) {}

  ngOnInit(): void {
    this.laddaData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    try { this.oeeChart?.destroy(); } catch (_) {}
    this.oeeChart = null;
  }

  byttPeriod(v: number): void {
    this.veckor = v;
    this.laddaData();
  }

  laddaData(): void {
    this.loading = true;
    this.error = false;
    this.svc.getWeeklyOee(this.veckor)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (res?.success) {
          this.data = res.data;
          this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.byggOeeChart(); }, 0));
        } else {
          this.error = true;
          this.data = null;
        }
      });
  }

  // =============================================================
  // Chart — Linjediagram OEE% per vecka
  // =============================================================

  private byggOeeChart(): void {
    try { this.oeeChart?.destroy(); } catch (_) {}
    this.oeeChart = null;

    const canvas = document.getElementById('oeeJamforelseChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.data) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { veckodata, mal_oee } = this.data;
    const labels = veckodata.map(v => v.vecko_label);
    const oeeValues = veckodata.map(v => v.oee_pct);
    const tillgValues = veckodata.map(v => v.tillganglighet_pct);
    const prestValues = veckodata.map(v => v.prestanda_pct);
    const kvalValues = veckodata.map(v => v.kvalitet_pct);
    const malArr = veckodata.map(() => mal_oee);

    this.oeeChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'OEE %',
            data: oeeValues,
            borderColor: '#4fd1c5',
            backgroundColor: 'rgba(79, 209, 197, 0.1)',
            borderWidth: 3,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: '#4fd1c5',
            fill: true,
            order: 1,
          },
          {
            label: 'Tillgänglighet %',
            data: tillgValues,
            borderColor: '#63b3ed',
            borderWidth: 1.5,
            tension: 0.3,
            pointRadius: 2,
            borderDash: [4, 2],
            fill: false,
            order: 2,
          },
          {
            label: 'Prestanda %',
            data: prestValues,
            borderColor: '#f6ad55',
            borderWidth: 1.5,
            tension: 0.3,
            pointRadius: 2,
            borderDash: [4, 2],
            fill: false,
            order: 3,
          },
          {
            label: 'Kvalitet %',
            data: kvalValues,
            borderColor: '#68d391',
            borderWidth: 1.5,
            tension: 0.3,
            pointRadius: 2,
            borderDash: [4, 2],
            fill: false,
            order: 4,
          },
          {
            label: `Mal (${mal_oee}%)`,
            data: malArr,
            borderColor: '#fc8181',
            borderWidth: 2,
            borderDash: [8, 4],
            pointRadius: 0,
            fill: false,
            tension: 0,
            order: 5,
          } as any,
        ],
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
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (item) => {
                const v = item.raw as number;
                return ` ${item.dataset.label}: ${v}%`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'OEE (%)', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =============================================================
  // Hjalpmetoder
  // =============================================================

  trendIkon(pil: string): string {
    if (pil === 'up')   return '\u25B2';  // forbattring
    if (pil === 'down') return '\u25BC';  // forsamring
    return '\u2014';
  }

  trendFarg(pil: string): string {
    if (pil === 'up')   return '#68d391';  // gront = forbattring
    if (pil === 'down') return '#fc8181';  // rott = forsamring
    return '#a0aec0';
  }

  oeeFarg(pct: number): string {
    if (pct >= 85) return '#68d391';  // utmarkt
    if (pct >= 60) return '#f6ad55';  // typiskt
    return '#fc8181';                 // lagt
  }

  forandringText(diff: number | null): string {
    if (diff === null) return '';
    const prefix = diff > 0 ? '+' : '';
    return `${prefix}${diff} pp`;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
