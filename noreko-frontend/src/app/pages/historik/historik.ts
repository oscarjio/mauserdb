import {
  Component, OnInit, OnDestroy,
  ViewChild, ElementRef, AfterViewInit
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { Chart, registerables } from 'chart.js';
import * as XLSX from 'xlsx';
import { environment } from '../../../environments/environment';
import { localToday } from '../../utils/date-utils';

Chart.register(...registerables);

interface ManadsData {
  period: string;
  ar: number;
  manad: number;
  antal_dagar: number;
  total_ibc: number;
  snitt_per_dag: number;
  basta_dag_ibc: number;
  snitt_oee: number | null;
}

interface VeckoData {
  vecka: number;
  ibc_vecka: number;
}

@Component({
  selector: 'app-historik',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="historik-page">
      <!-- Sidhuvud -->
      <div class="page-header mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <h1 class="page-title mb-1">
              <i class="fas fa-history me-2 text-info"></i>Historisk jämförelse
            </h1>
            <p class="page-subtitle mb-0">Rebotling — produktionsutveckling över tid</p>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <label class="text-muted small me-1 mb-0">Visa månader:</label>
            <select class="form-select form-select-sm period-select"
                    [(ngModel)]="valdaManader"
                    (change)="onPeriodChange()">
              <option [value]="12">12 månader</option>
              <option [value]="18">18 månader</option>
              <option [value]="24">24 månader</option>
              <option [value]="36">36 månader</option>
              <option [value]="48">48 månader</option>
            </select>
            <button class="btn btn-sm btn-outline-success"
                    (click)="exportHistorikCSV()"
                    [disabled]="!monthlyData || monthlyData.length === 0"
                    title="Exportera månadsdata som CSV">
              <i class="fas fa-file-csv me-1"></i>CSV
            </button>
            <button class="btn btn-sm btn-outline-info"
                    (click)="exportHistorikExcel()"
                    [disabled]="!monthlyData || monthlyData.length === 0"
                    title="Exportera månadsdata som Excel">
              <i class="fas fa-file-excel me-1"></i>Excel
            </button>
          </div>
        </div>
      </div>

      <!-- Laddning -->
      <div *ngIf="loading" class="text-center py-5">
        <div class="spinner-border text-info" role="status">
          <span class="visually-hidden">Laddar...</span>
        </div>
        <p class="text-muted mt-3">Hämtar historik...</p>
      </div>

      <!-- Fel -->
      <div *ngIf="error && !loading" class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>{{ error }}
      </div>

      <div *ngIf="!loading && !error">

        <!-- KPI-kort -->
        <div class="row g-3 mb-4">
          <div class="col-12 col-sm-4">
            <div class="kpi-card">
              <div class="kpi-icon text-success">
                <i class="fas fa-boxes"></i>
              </div>
              <div class="kpi-content">
                <div class="kpi-label">Total IBC {{ arNu }}</div>
                <div class="kpi-value text-success">{{ totalIbcAr | number }}</div>
                <div class="kpi-sub">IBC tvättade innevarande år</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-4">
            <div class="kpi-card">
              <div class="kpi-icon text-info">
                <i class="fas fa-chart-bar"></i>
              </div>
              <div class="kpi-content">
                <div class="kpi-label">Snitt per månad</div>
                <div class="kpi-value text-info">{{ snittPerManad | number }}</div>
                <div class="kpi-sub">IBC (senaste {{ valdaManader }} mån)</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-4">
            <div class="kpi-card">
              <div class="kpi-icon text-warning">
                <i class="fas fa-trophy"></i>
              </div>
              <div class="kpi-content">
                <div class="kpi-label">Bästa månaden</div>
                <div class="kpi-value text-warning">{{ bastaManad?.total_ibc | number }}</div>
                <div class="kpi-sub">{{ bastaManad ? getPeriodLabel(bastaManad.period) : '—' }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Månadsöversikt - stapeldiagram -->
        <div class="card chart-card mb-4">
          <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="fas fa-chart-bar me-2 text-info"></i>IBC per månad</span>
            <span class="legend-items">
              <span class="legend-dot bg-success me-1"></span><span class="small text-muted me-3">Över snitt</span>
              <span class="legend-dot bg-danger me-1"></span><span class="small text-muted">Under snitt</span>
            </span>
          </div>
          <div class="card-body">
            <div *ngIf="monthlyData.length === 0" class="text-center py-4 text-muted">
              <i class="fas fa-database me-2"></i>Ingen månadsdata tillgänglig
            </div>
            <canvas #monthlyChartRef *ngIf="monthlyData.length > 0" style="max-height:300px;"></canvas>
          </div>
        </div>

        <!-- År-mot-år jämförelse -->
        <div class="card chart-card mb-4">
          <div class="card-header">
            <i class="fas fa-chart-line me-2 text-warning"></i>År-mot-år jämförelse (veckovis)
          </div>
          <div class="card-body">
            <div *ngIf="arsNyclar.length === 0" class="text-center py-4 text-muted">
              <i class="fas fa-database me-2"></i>Ingen årsdata tillgänglig
            </div>
            <canvas #yearlyChartRef *ngIf="arsNyclar.length > 0" style="max-height:300px;"></canvas>
          </div>
        </div>

        <!-- Info om dataomfång -->
        <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4 px-3 py-2" style="background:#2d3748;border-color:#4a5568;color:#a0aec0;font-size:0.85rem;">
          <i class="fas fa-info-circle text-info"></i>
          <span>Visar aggregerad månadsdata för de senaste <strong class="text-light">{{ valdaManader }} månaderna</strong>. Välj ett annat intervall i menyn ovan för att se mer eller mindre historik.</span>
        </div>

        <!-- Månadsdetaljstabell -->
        <div class="card table-card mb-4">
          <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="fas fa-table me-2 text-info"></i>Månadsdetaljer</span>
            <span class="text-muted small">{{ monthlyData.length }} månader</span>
          </div>
          <div class="card-body p-0">
            <div *ngIf="monthlyData.length === 0" class="text-center py-4 text-muted">
              <i class="fas fa-database me-2"></i>Ingen data att visa
            </div>
            <div *ngIf="monthlyData.length > 0" class="table-responsive">
              <table class="table table-dark table-hover table-sm mb-0">
                <thead>
                  <tr>
                    <th>Månad</th>
                    <th class="text-end">Total IBC</th>
                    <th class="text-end">Snitt/dag</th>
                    <th class="text-end">Bästa dag</th>
                    <th class="text-end">OEE%</th>
                    <th class="text-end">Dagar</th>
                    <th class="text-center">Trend</th>
                    <th class="text-center">vs. föregående</th>
                  </tr>
                </thead>
                <tbody>
                  <tr *ngFor="let m of monthlyDataReversed; let i = index">
                    <td class="fw-semibold">{{ getPeriodLabel(m.period) }}</td>
                    <td class="text-end">
                      {{ m.total_ibc | number }}
                      <div class="progress" style="height: 4px; margin-top: 4px; min-width: 60px;">
                        <div class="progress-bar"
                             [style.width]="getGoalPct(m) + '%'"
                             [class]="getGoalPct(m) >= 95 ? 'bg-success' : getGoalPct(m) >= 80 ? 'bg-warning' : 'bg-danger'">
                        </div>
                      </div>
                    </td>
                    <td class="text-end">{{ m.snitt_per_dag | number:'1.1-1' }}</td>
                    <td class="text-end">{{ m.basta_dag_ibc | number }}</td>
                    <td class="text-end">
                      <span *ngIf="m.snitt_oee !== null" [class]="getOeeClass(m.snitt_oee)">
                        {{ m.snitt_oee | number:'1.1-1' }}%
                      </span>
                      <span *ngIf="m.snitt_oee === null" class="text-muted">—</span>
                    </td>
                    <td class="text-end text-muted">{{ m.antal_dagar }}</td>
                    <td class="text-center">
                      <span [class]="trendClass(m.total_ibc, getPrevIbc(i))" style="font-size:1.1rem; font-weight:700;">
                        {{ trendArrow(m.total_ibc, getPrevIbc(i)) }}
                      </span>
                    </td>
                    <td class="text-center">
                      <span *ngIf="getJamforelse(i) as j">
                        <span *ngIf="j.diff > 0" class="text-success small">
                          <i class="fas fa-arrow-up me-1"></i>+{{ j.diff | number }}
                        </span>
                        <span *ngIf="j.diff < 0" class="text-danger small">
                          <i class="fas fa-arrow-down me-1"></i>{{ j.diff | number }}
                        </span>
                        <span *ngIf="j.diff === 0" class="text-muted small">—</span>
                      </span>
                      <span *ngIf="!getJamforelse(i)" class="text-muted small">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  `,
  styles: [`
    .historik-page {
      padding: 1.5rem;
      background: #1a202c;
      min-height: 100vh;
      color: #e2e8f0;
    }

    .page-title {
      font-size: 1.6rem;
      font-weight: 700;
      color: #e2e8f0;
    }

    .page-subtitle {
      color: #718096;
      font-size: 0.95rem;
    }

    .period-select {
      background: #2d3748;
      border-color: #4a5568;
      color: #e2e8f0;
      min-width: 150px;
    }
    .period-select:focus {
      background: #2d3748;
      color: #e2e8f0;
      border-color: #63b3ed;
      box-shadow: 0 0 0 0.2rem rgba(99,179,237,.25);
    }

    /* KPI-kort */
    .kpi-card {
      background: #2d3748;
      border-radius: 12px;
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      border: 1px solid #4a5568;
      height: 100%;
    }
    .kpi-icon {
      font-size: 2rem;
      opacity: 0.85;
      flex-shrink: 0;
    }
    .kpi-label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #718096;
      font-weight: 600;
    }
    .kpi-value {
      font-size: 1.9rem;
      font-weight: 800;
      line-height: 1.1;
      margin: 0.1rem 0;
    }
    .kpi-sub {
      font-size: 0.75rem;
      color: #718096;
    }

    /* Grafkort */
    .chart-card, .table-card {
      background: #2d3748;
      border: 1px solid #4a5568;
      border-radius: 12px;
    }
    .card-header {
      background: transparent;
      border-bottom: 1px solid #4a5568;
      color: #e2e8f0;
      font-weight: 600;
      padding: 0.85rem 1.25rem;
    }
    .card-body {
      padding: 1.25rem;
    }

    /* Legend */
    .legend-items {
      display: flex;
      align-items: center;
    }
    .legend-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      display: inline-block;
    }

    /* Tabell */
    .table-dark {
      background: transparent;
      color: #e2e8f0;
    }
    .table-dark th {
      background: #1a202c;
      color: #a0aec0;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border-color: #4a5568;
      padding: 0.6rem 1rem;
    }
    .table-dark td {
      border-color: #4a5568;
      padding: 0.55rem 1rem;
      font-size: 0.9rem;
      vertical-align: middle;
    }
    .table-hover tbody tr:hover {
      background: rgba(99,179,237,0.07) !important;
    }

    /* Progressbar */
    .progress {
      background: #1a202c;
      border-radius: 2px;
    }

    .oee-good { color: #48bb78; font-weight: 600; }
    .oee-ok   { color: #ecc94b; font-weight: 600; }
    .oee-bad  { color: #fc8181; font-weight: 600; }

    /* Export-knappar */
    .btn-outline-success:disabled,
    .btn-outline-info:disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }
  `]
})
export class HistorikPage implements OnInit, OnDestroy, AfterViewInit {

  @ViewChild('monthlyChartRef') monthlyChartRef!: ElementRef<HTMLCanvasElement>;
  @ViewChild('yearlyChartRef') yearlyChartRef!: ElementRef<HTMLCanvasElement>;

  private destroy$ = new Subject<void>();

  readonly MANADER = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];

  // Data
  monthlyData: ManadsData[] = [];
  yearlyData: { [year: string]: VeckoData[] } = {};
  arsNyclar: string[] = [];
  totalIbcAr = 0;
  snittPerManad = 0;
  bastaManad: { period: string; total_ibc: number } | null = null;
  arNu = new Date().getFullYear();

  // UI state
  loading = true;
  error = '';
  valdaManader = 24;

  // Charts
  private monthlyChart: Chart | null = null;
  private yearlyChart: Chart | null = null;
  private chartsBuilt = false;
  private chartBuildTimer: ReturnType<typeof setTimeout> | null = null;

  // API-basURL
  private apiBase = environment.apiUrl;

  // Färger per år
  private readonly AR_FARGER: { [key: string]: string } = {
    '2023': '#a78bfa',
    '2024': '#63b3ed',
    '2025': '#48bb78',
    '2026': '#f6ad55',
    '2027': '#fc8181',
    '2028': '#76e4f7',
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadData();
  }

  ngAfterViewInit(): void {
    // Grafer byggs efter att data laddats och vyn är renderad
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartBuildTimer !== null) {
      clearTimeout(this.chartBuildTimer);
      this.chartBuildTimer = null;
    }
    try { this.monthlyChart?.destroy(); } catch (e) {}
    this.monthlyChart = null;
    try { this.yearlyChart?.destroy(); } catch (e) {}
    this.yearlyChart = null;
  }

  get monthlyDataReversed(): ManadsData[] {
    return [...this.monthlyData].reverse();
  }

  onPeriodChange(): void {
    this.destroyCharts();
    this.chartsBuilt = false;
    this.loading = true;
    this.error = '';
    this.loadData();
  }

  loadData(): void {
    let monthlyDone = false;
    let yearlyDone = false;
    let monthlyError = false;
    let yearlyError = false;

    const checkDone = () => {
      if (monthlyDone && yearlyDone) {
        this.loading = false;
        if (monthlyError && yearlyError) {
          this.error = 'Kunde inte hämta historikdata. Kontrollera anslutningen.';
        } else {
          this.error = '';
          this.chartBuildTimer = setTimeout(() => this.buildCharts(), 100);
        }
      }
    };

    const monthlyUrl = `${this.apiBase}?action=historik&run=monthly&manader=${this.valdaManader}`;
    this.http.get<any>(monthlyUrl).pipe(
      timeout(8000),
      catchError(err => {
        console.error('Historik monthly error:', err);
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(resp => {
      if (resp && resp.success) {
        this.monthlyData    = resp.monthly ?? [];
        this.totalIbcAr     = resp.total_ibc_ar ?? 0;
        this.snittPerManad  = resp.snitt_per_manad ?? 0;
        this.bastaManad     = resp.basta_manad ?? null;
      } else {
        monthlyError = true;
      }
      monthlyDone = true;
      checkDone();
    });

    const yearlyUrl = `${this.apiBase}?action=historik&run=yearly`;
    this.http.get<any>(yearlyUrl).pipe(
      timeout(8000),
      catchError(err => {
        console.error('Historik yearly error:', err);
        return of(null);
      }),
      takeUntil(this.destroy$)
    ).subscribe(resp => {
      if (resp && resp.success) {
        this.yearlyData = resp.yearly ?? {};
        this.arsNyclar  = Object.keys(this.yearlyData).sort();
      } else {
        yearlyError = true;
      }
      yearlyDone = true;
      checkDone();
    });
  }

  // ─── Trend-helpers ──────────────────────────────────────────────────────────

  trendArrow(current: number, previous: number | null): string {
    if (previous === null || previous === 0) return '→';
    const diff = ((current - previous) / previous) * 100;
    if (diff > 3) return '↑';
    if (diff < -3) return '↓';
    return '→';
  }

  trendClass(current: number, previous: number | null): string {
    if (previous === null || previous === 0) return 'text-muted';
    return current >= previous ? 'text-success' : 'text-danger';
  }

  /**
   * Returnerar IBC-värdet för månaden efter i (nästa rad i omvänd ordning = föregående månad).
   * reversedIndex i = aktuell; i+1 = föregående.
   */
  getPrevIbc(reversedIndex: number): number | null {
    const data = this.monthlyDataReversed;
    if (reversedIndex >= data.length - 1) return null;
    return data[reversedIndex + 1].total_ibc;
  }

  /**
   * Beräknar uppfyllnadsgrad mot snittet (används för progressbar).
   * Använder snittPerManad som referens eftersom ingen explicit goal finns i interfacet.
   */
  getGoalPct(m: ManadsData): number {
    if (!this.snittPerManad || this.snittPerManad === 0) return 0;
    const pct = (m.total_ibc / this.snittPerManad) * 100;
    return Math.min(pct, 100);
  }

  // ─── CSV-export ─────────────────────────────────────────────────────────────

  exportHistorikCSV(): void {
    if (!this.monthlyData || this.monthlyData.length === 0) return;
    const headers = ['Månad', 'Total IBC', 'Snitt IBC/dag', 'Bästa dag IBC', 'OEE %', 'Antal dagar'];
    const rows = [...this.monthlyData].reverse().map(m => [
      this.getPeriodLabel(m.period),
      m.total_ibc,
      m.snitt_per_dag != null ? m.snitt_per_dag.toFixed(1) : '',
      m.basta_dag_ibc,
      m.snitt_oee != null ? m.snitt_oee.toFixed(1) + '%' : '',
      m.antal_dagar
    ]);
    const csv = [headers, ...rows].map(r => r.join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `historik-${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  // ─── Excel-export (SheetJS) ──────────────────────────────────────────────────

  exportHistorikExcel(): void {
    if (!this.monthlyData || this.monthlyData.length === 0) return;

    const wsData = [
      ['Månad', 'Total IBC', 'Snitt IBC/dag', 'Bästa dag IBC', 'OEE %', 'Antal dagar'],
      ...[...this.monthlyData].reverse().map(m => [
        this.getPeriodLabel(m.period),
        m.total_ibc,
        m.snitt_per_dag != null ? parseFloat(m.snitt_per_dag.toFixed(1)) : '',
        m.basta_dag_ibc,
        m.snitt_oee != null ? parseFloat(m.snitt_oee.toFixed(1)) : '',
        m.antal_dagar
      ])
    ];

    const ws = XLSX.utils.aoa_to_sheet(wsData);

    // Kolumnbredder
    ws['!cols'] = [
      { wch: 14 },  // Månad
      { wch: 12 },  // Total IBC
      { wch: 14 },  // Snitt IBC/dag
      { wch: 14 },  // Bästa dag IBC
      { wch: 8  },  // OEE %
      { wch: 10 },  // Antal dagar
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Historik');
    XLSX.writeFile(wb, `historik-${localToday()}.xlsx`);
  }

  // ─── Charts ─────────────────────────────────────────────────────────────────

  private buildCharts(): void {
    if (this.chartsBuilt) return;
    this.chartsBuilt = true;
    this.buildMonthlyChart();
    this.buildYearlyChart();
  }

  private destroyCharts(): void {
    try { this.monthlyChart?.destroy(); } catch (e) {}
    this.monthlyChart = null;
    try { this.yearlyChart?.destroy(); } catch (e) {}
    this.yearlyChart = null;
  }

  private buildMonthlyChart(): void {
    if (!this.monthlyChartRef?.nativeElement || this.monthlyData.length === 0) return;

    try { this.monthlyChart?.destroy(); } catch (e) {}

    const labels = this.monthlyData.map(m => this.getPeriodLabel(m.period));
    const values = this.monthlyData.map(m => m.total_ibc);
    const snitt  = this.snittPerManad;

    const colors = values.map(v => v >= snitt ? 'rgba(72,187,120,0.85)' : 'rgba(252,129,129,0.85)');
    const borders = values.map(v => v >= snitt ? '#48bb78' : '#fc8181');

    this.monthlyChart = new Chart(this.monthlyChartRef.nativeElement, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC per månad',
            data: values,
            backgroundColor: colors,
            borderColor: borders,
            borderWidth: 1,
            borderRadius: 4,
          },
          {
            label: 'Snitt',
            data: new Array(values.length).fill(snitt),
            type: 'line',
            borderColor: '#a0aec0',
            borderDash: [6, 4],
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
          } as any
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            display: true,
            labels: { color: '#a0aec0', font: { size: 12 } }
          },
          tooltip: {
            callbacks: {
              label: ctx => {
                const v = ctx.raw as number;
                if (v == null) return '';
                const diff = v - snitt;
                const sign = diff >= 0 ? '+' : '';
                return ` ${v.toLocaleString('sv-SE')} IBC  (${sign}${diff.toLocaleString('sv-SE')} vs snitt)`;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#718096', maxRotation: 45, font: { size: 11 } },
            grid: { color: 'rgba(74,85,104,0.4)' }
          },
          y: {
            ticks: { color: '#718096', font: { size: 11 } },
            grid: { color: 'rgba(74,85,104,0.4)' },
            beginAtZero: true
          }
        }
      }
    });
  }

  private buildYearlyChart(): void {
    if (!this.yearlyChartRef?.nativeElement || this.arsNyclar.length === 0) return;

    try { this.yearlyChart?.destroy(); } catch (e) {}

    const veckoEtiketter = Array.from({ length: 52 }, (_, i) => `V${i + 1}`);

    const datasets = this.arsNyclar.map(ar => {
      const arData = this.yearlyData[ar] ?? [];
      const weekMap: { [v: number]: number } = {};
      arData.forEach(d => { weekMap[d.vecka] = d.ibc_vecka; });

      const data: (number | null)[] = Array.from({ length: 52 }, (_, i) => {
        return weekMap[i + 1] !== undefined ? weekMap[i + 1] : null;
      });

      const farg = this.AR_FARGER[ar] ?? '#e2e8f0';
      return {
        label: ar,
        data,
        borderColor: farg,
        backgroundColor: farg + '22',
        borderWidth: 2,
        pointRadius: 2,
        pointHoverRadius: 5,
        fill: false,
        spanGaps: false,
        tension: 0.3,
      };
    });

    this.yearlyChart = new Chart(this.yearlyChartRef.nativeElement, {
      type: 'line',
      data: {
        labels: veckoEtiketter,
        datasets
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            labels: { color: '#a0aec0', font: { size: 12 }, usePointStyle: true }
          },
          tooltip: {
            callbacks: {
              label: ctx => {
                const v = ctx.raw as number | null;
                return v !== null ? ` ${ctx.dataset.label}: ${v.toLocaleString('sv-SE')} IBC` : '';
              }
            }
          }
        },
        scales: {
          x: {
            ticks: {
              color: '#718096',
              maxTicksLimit: 26,
              font: { size: 10 }
            },
            grid: { color: 'rgba(74,85,104,0.4)' }
          },
          y: {
            ticks: { color: '#718096', font: { size: 11 } },
            grid: { color: 'rgba(74,85,104,0.4)' },
            beginAtZero: true
          }
        }
      }
    });
  }

  // ─── Helpers ────────────────────────────────────────────────────────────────

  getPeriodLabel(period: string): string {
    if (!period) return '—';
    const parts = period.split('-');
    if (parts.length < 2) return period;
    const y = parts[0];
    const m = parseInt(parts[1], 10);
    if (isNaN(m) || m < 1 || m > 12) return period;
    return `${this.MANADER[m - 1]} ${y}`;
  }

  getOeeClass(oee: number | null): string {
    if (oee === null) return 'text-muted';
    if (oee >= 75) return 'oee-good';
    if (oee >= 60) return 'oee-ok';
    return 'oee-bad';
  }

  getJamforelse(reversedIndex: number): { diff: number } | null {
    const data = this.monthlyDataReversed;
    if (reversedIndex >= data.length - 1) return null;
    const aktuell = data[reversedIndex].total_ibc;
    const foregaende = data[reversedIndex + 1].total_ibc;
    return { diff: aktuell - foregaende };
  }
}
