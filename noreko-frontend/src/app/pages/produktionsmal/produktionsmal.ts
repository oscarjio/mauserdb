import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  ProduktionsmalService,
  ProgressData,
  MalHistorikRad,
} from '../../services/produktionsmal.service';
import { PdfExportButtonComponent } from '../../components/pdf-export-button/pdf-export-button.component';
import { localToday, parseLocalDate } from '../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-produktionsmal',
  templateUrl: './produktionsmal.html',
  styleUrls: ['./produktionsmal.css'],
  imports: [CommonModule, FormsModule, PdfExportButtonComponent],
})
export class ProduktionsmalComponent implements OnInit, OnDestroy {
  Math = Math;

  // ---- Laddningsstate ----
  progressLoading = false;
  progressError = false;
  historikLoading = false;
  historikError = false;
  sparLoading = false;
  sparMeddelande = '';
  sparFel = '';

  // ---- Data ----
  progress: ProgressData | null = null;
  historik: MalHistorikRad[] = [];

  // ---- Formularet ----
  formTyp: 'vecka' | 'manad' = 'vecka';
  formAntal: number | null = null;
  formStartdatum = '';

  // ---- Charts ----
  private doughnutChart: Chart | null = null;
  private barChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private chartTimer: ReturnType<typeof setTimeout> | null = null;
  private refreshTimer: ReturnType<typeof setInterval> | null = null;

  constructor(private service: ProduktionsmalService) {}

  ngOnInit(): void {
    this.formStartdatum = this.todayStr();
    this.laddaAllt();
    // Auto-refresh var 5:e minut
    this.refreshTimer = setInterval(() => {
      if (!this.destroy$.closed) this.laddaAllt();
    }, 300000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    if (this.refreshTimer) { clearInterval(this.refreshTimer); this.refreshTimer = null; }
    this.destroyCharts();
  }

  private destroyCharts(): void {
    try { this.doughnutChart?.destroy(); } catch (_) {}
    try { this.barChart?.destroy(); } catch (_) {}
    this.doughnutChart = null;
    this.barChart = null;
  }

  // ================================================================
  // DATA
  // ================================================================

  private laddaAllt(): void {
    this.laddaProgress();
    this.laddaHistorik();
  }

  laddaProgress(): void {
    this.progressLoading = true;
    this.progressError = false;
    this.service.getProgress()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.progressLoading = false;
        if (res?.success) {
          this.progress = res.data;
          if (this.chartTimer) clearTimeout(this.chartTimer);
          this.chartTimer = setTimeout(() => {
            if (!this.destroy$.closed) {
              this.renderDoughnut();
              this.renderBarChart();
            }
          }, 100);
        } else {
          this.progress = null;
          this.progressError = true;
        }
      });
  }

  laddaHistorik(): void {
    this.historikLoading = true;
    this.historikError = false;
    this.service.getMalHistorik(12)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.historikLoading = false;
        if (res?.success) {
          this.historik = res.data.historik;
        } else {
          this.historik = [];
          this.historikError = true;
        }
      });
  }

  sparaMal(): void {
    if (!this.formAntal || this.formAntal <= 0 || !this.formStartdatum) {
      this.sparFel = 'Fyll i alla falt korrekt.';
      return;
    }
    this.sparLoading = true;
    this.sparFel = '';
    this.sparMeddelande = '';

    this.service.sattMal(this.formTyp, this.formAntal, this.formStartdatum)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.sparLoading = false;
        if (res?.success) {
          this.sparMeddelande = 'Malet sparades!';
          this.sparFel = '';
          this.laddaAllt();
        } else {
          this.sparFel = res?.error || 'Kunde inte spara malet.';
        }
      });
  }

  // ================================================================
  // CHARTS
  // ================================================================

  private renderDoughnut(): void {
    try { this.doughnutChart?.destroy(); } catch (_) {}
    this.doughnutChart = null;

    const canvas = document.getElementById('progressDoughnut') as HTMLCanvasElement;
    if (!canvas || !this.progress?.har_mal) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const producerat = this.progress.producerat;
    const mal = this.progress.mal?.mal_antal || 1;
    const aterstaar = Math.max(0, mal - producerat);
    const procent = this.progress.procent;

    const mainColor = procent >= 90 ? '#48bb78' : procent >= 70 ? '#ecc94b' : '#fc5c65';

    if (this.doughnutChart) { (this.doughnutChart as any).destroy(); }
    this.doughnutChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Producerat', 'Aterstar'],
        datasets: [{
          data: [Math.min(producerat, mal), aterstaar],
          backgroundColor: [mainColor, '#4a5568'],
          borderColor: ['transparent', 'transparent'],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            callbacks: {
              label: (item: any) => {
                const val = item.raw as number;
                return ` ${item.label}: ${val.toLocaleString('sv-SE')} IBC`;
              },
            },
          },
        },
      } as any,
      plugins: [{
        id: 'centerText',
        afterDraw(chart: Chart) {
          const { ctx: c, width, height } = chart;
          if (!c) return;
          c.save();
          c.textAlign = 'center';
          c.textBaseline = 'middle';
          const cx = width / 2;
          const cy = height / 2;
          c.fillStyle = mainColor;
          c.font = 'bold 2.5rem system-ui';
          c.fillText(`${procent}%`, cx, cy - 10);
          c.fillStyle = '#a0aec0';
          c.font = '0.85rem system-ui';
          c.fillText(`${producerat.toLocaleString('sv-SE')} / ${mal.toLocaleString('sv-SE')}`, cx, cy + 20);
          c.restore();
        },
      }],
    });
  }

  private renderBarChart(): void {
    try { this.barChart?.destroy(); } catch (_) {}
    this.barChart = null;

    const canvas = document.getElementById('dagligBarChart') as HTMLCanvasElement;
    if (!canvas || !this.progress?.har_mal || !this.progress.daglig_produktion?.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const daglig = this.progress.daglig_produktion;
    const malPerDag = this.progress.mal_per_dag;

    const labels = daglig.map(d => {
      const dt = new Date(d.datum);
      return dt.toLocaleDateString('sv-SE', { day: 'numeric', month: 'numeric' });
    });
    const values = daglig.map(d => d.antal);
    const malLine = daglig.map(() => malPerDag);

    const barColors = values.map(v => v >= malPerDag ? '#48bb78' : '#fc8181');

    if (this.barChart) { (this.barChart as any).destroy(); }
    this.barChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Produktion',
            data: values,
            backgroundColor: barColors,
            borderRadius: 4,
            barPercentage: 0.7,
          },
          {
            label: `Mal/dag (${malPerDag})`,
            data: malLine,
            type: 'line',
            borderColor: '#f6ad55',
            borderWidth: 2,
            borderDash: [6, 3],
            pointRadius: 0,
            fill: false,
            tension: 0,
          } as any,
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 11 }, usePointStyle: true },
          },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (item) => {
                const v = item.raw as number;
                return ` ${item.dataset.label}: ${v.toLocaleString('sv-SE')} IBC`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 10 }, maxRotation: 45 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#a0aec0',
              font: { size: 11 },
              callback: (val: any) => val.toLocaleString('sv-SE'),
            },
            grid: { color: 'rgba(255,255,255,0.07)' },
            title: {
              display: true,
              text: 'IBC',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ================================================================
  // HJALPMETODER
  // ================================================================

  todayStr(): string {
    return localToday();
  }

  formatDatum(datum: string): string {
    if (!datum) return '-';
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  formatNumber(n: number | null | undefined): string {
    if (n === null || n === undefined) return '-';
    return n.toLocaleString('sv-SE');
  }

  typLabel(typ: string): string {
    return typ === 'vecka' ? 'Vecka' : 'Manad';
  }

  prognosFargKlass(): string {
    if (!this.progress) return '';
    if (this.progress.prognos_farg === 'gron') return 'prognos-gron';
    if (this.progress.prognos_farg === 'rod') return 'prognos-rod';
    return '';
  }

  progressColor(): string {
    if (!this.progress) return '#a0aec0';
    const p = this.progress.procent;
    if (p >= 90) return '#48bb78';
    if (p >= 70) return '#ecc94b';
    return '#fc5c65';
  }

  historikRadFarg(rad: MalHistorikRad): string {
    if (!rad.avslutad) return '';
    return rad.uppnadd ? 'row-green' : 'row-red';
  }
  trackByIndex(index: number): number { return index; }
}
