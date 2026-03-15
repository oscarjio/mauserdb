import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  SkiftrapportSammanstallningService,
  DagligSammanstallningData,
  VeckosammanstallningData,
  SkiftjamforelseData,
  SkiftData,
  SkiftSnitt,
} from '../../../services/skiftrapport-sammanstallning.service';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-skiftrapport-sammanstallning',
  templateUrl: './skiftrapport-sammanstallning.html',
  styleUrls: ['./skiftrapport-sammanstallning.css'],
  imports: [CommonModule, FormsModule, PdfExportButtonComponent],
})
export class SkiftrapportSammanstallningPage implements OnInit, OnDestroy {

  // Datum
  valdDatum: string = '';

  // Loading / Error
  loadingDaglig = false;
  loadingVecko = false;
  loadingJamforelse = false;
  errorDaglig = false;
  errorVecko = false;
  errorJamforelse = false;

  // Data
  dagligData: DagligSammanstallningData | null = null;
  veckoData: VeckosammanstallningData | null = null;
  jamforelseData: SkiftjamforelseData | null = null;

  // Charts
  private skiftChart: Chart | null = null;
  private jamforelseChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  // Skiftnamn
  readonly skiftNamn: Record<string, string> = {
    dag: 'Dag (06-14)',
    kvall: 'Kvall (14-22)',
    natt: 'Natt (22-06)',
  };
  readonly skiftFarger: Record<string, string> = {
    dag: '#f6ad55',
    kvall: '#63b3ed',
    natt: '#9f7aea',
  };

  constructor(private svc: SkiftrapportSammanstallningService) {}

  ngOnInit(): void {
    this.valdDatum = this.todayString();
    this.laddaDaglig();
    this.laddaVecko();
    this.laddaJamforelse();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    try { this.skiftChart?.destroy(); } catch (_) {}
    try { this.jamforelseChart?.destroy(); } catch (_) {}
    this.skiftChart = null;
    this.jamforelseChart = null;
  }

  todayString(): string {
    const d = new Date();
    return d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0');
  }

  bytDatum(): void {
    this.laddaDaglig();
  }

  laddaDaglig(): void {
    this.loadingDaglig = true;
    this.errorDaglig = false;
    this.svc.getDagligSammanstallning(this.valdDatum)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDaglig = false;
        if (res?.success) {
          this.dagligData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.byggSkiftChart(); }, 0);
        } else {
          this.errorDaglig = true;
          this.dagligData = null;
        }
      });
  }

  laddaVecko(): void {
    this.loadingVecko = true;
    this.errorVecko = false;
    this.svc.getVeckosammanstallning()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingVecko = false;
        if (res?.success) {
          this.veckoData = res.data;
        } else {
          this.errorVecko = true;
          this.veckoData = null;
        }
      });
  }

  laddaJamforelse(): void {
    this.loadingJamforelse = true;
    this.errorJamforelse = false;
    this.svc.getSkiftjamforelse(30)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingJamforelse = false;
        if (res?.success) {
          this.jamforelseData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.byggJamforelseChart(); }, 0);
        } else {
          this.errorJamforelse = true;
          this.jamforelseData = null;
        }
      });
  }

  // =============================================================
  // Stapeldiagram — jmfr skiften (produktion + kassation)
  // =============================================================

  private byggSkiftChart(): void {
    try { this.skiftChart?.destroy(); } catch (_) {}
    this.skiftChart = null;

    const canvas = document.getElementById('skiftStapelChart') as HTMLCanvasElement;
    if (!canvas || !this.dagligData) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const skiften = this.dagligData.skift;
    const labels = skiften.map(s => this.skiftNamn[s.skift] || s.skift);
    const prodData = skiften.map(s => s.producerade);
    const kassData = skiften.map(s => s.kasserade);
    const bgColors = skiften.map(s => this.skiftFarger[s.skift] || '#4fd1c5');

    this.skiftChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Producerade',
            data: prodData,
            backgroundColor: bgColors,
            borderRadius: 4,
          },
          {
            label: 'Kasserade',
            data: kassData,
            backgroundColor: '#fc8181',
            borderRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Antal IBC', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
    });
  }

  // =============================================================
  // Linjediagram — skiftjamforelse over tid
  // =============================================================

  private byggJamforelseChart(): void {
    try { this.jamforelseChart?.destroy(); } catch (_) {}
    this.jamforelseChart = null;

    const canvas = document.getElementById('skiftJamforelseChart') as HTMLCanvasElement;
    if (!canvas || !this.jamforelseData) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const { dagdata } = this.jamforelseData;
    const labels = dagdata.map(d => d.datum.substring(5)); // MM-DD
    const dagOee = dagdata.map(d => d.dag_oee_pct);
    const kvallOee = dagdata.map(d => d.kvall_oee_pct);
    const nattOee = dagdata.map(d => d.natt_oee_pct);

    this.jamforelseChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Dag (06-14)',
            data: dagOee,
            borderColor: '#f6ad55',
            backgroundColor: 'rgba(246, 173, 85, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 2,
            fill: false,
          },
          {
            label: 'Kvall (14-22)',
            data: kvallOee,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99, 179, 237, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 2,
            fill: false,
          },
          {
            label: 'Natt (22-06)',
            data: nattOee,
            borderColor: '#9f7aea',
            backgroundColor: 'rgba(159, 122, 234, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 2,
            fill: false,
          },
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
            callbacks: {
              label: (item) => ` ${item.dataset.label}: ${item.raw}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, maxTicksLimit: 15 },
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

  oeeFarg(pct: number): string {
    if (pct >= 85) return '#68d391';
    if (pct >= 60) return '#f6ad55';
    return '#fc8181';
  }

  skiftIkon(skift: string): string {
    if (skift === 'dag') return 'fas fa-sun';
    if (skift === 'kvall') return 'fas fa-cloud-moon';
    return 'fas fa-moon';
  }

  skiftFarg(skift: string): string {
    return this.skiftFarger[skift] || '#e2e8f0';
  }

  getSkiftFromVeckodag(dag: any, skiftNamn: string): SkiftData | null {
    return dag?.[skiftNamn] ?? null;
  }

  getSnitt(key: string): SkiftSnitt {
    const snitt = this.jamforelseData?.snitt as any;
    return snitt?.[key] ?? { totalt_producerade: 0, totalt_kasserade: 0, totalt_godkanda: 0, snitt_oee_pct: 0, snitt_producerade_per_dag: 0 };
  }
  trackByIndex(index: number): number { return index; }
}
