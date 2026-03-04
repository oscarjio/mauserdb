import {
  Component,
  OnInit,
  OnDestroy,
  ViewChild,
  ElementRef,
  AfterViewInit,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

interface WeeklyKpi {
  total_ibc_ok: number;
  total_ibc_ej: number;
  kvalitet_pct: number;
  drifttid_h: number;
  snitt_ibc_per_h: number;
  dagmal: number;
  mal_per_vecka: number;
  mal_uppfylld_pct: number;
  dagar_pa_mal: number;
  totalt_vardagar: number;
}

interface DailyEntry {
  dag: string;
  ibc_ok: number;
  ibc_ej: number;
  ibc_total: number;
  kvalitet_pct: number;
  drifttid_h: number;
  ibc_per_h: number;
}

interface OperatorEntry {
  name: string;
  ibc_ok_vecka: number;
  snitt_ibc_per_h: number;
  kvalitet_pct: number;
  antal_skift: number;
}

interface WeeklyReport {
  success: boolean;
  week: string;
  period: { from: string; to: string };
  kpi: WeeklyKpi;
  daily: DailyEntry[];
  best_day: DailyEntry | null;
  worst_day: DailyEntry | null;
  operators: OperatorEntry[];
}

@Component({
  standalone: true,
  selector: 'app-weekly-report',
  imports: [CommonModule],
  styles: [`
    :host {
      display: block;
      background: #1a202c;
      min-height: 100vh;
      color: #e2e8f0;
      padding: 1.5rem;
    }

    /* ---- Header ---- */
    .page-header {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .page-header h2 {
      margin: 0;
      font-size: 1.6rem;
      font-weight: 700;
      color: #e2e8f0;
      flex: 1;
    }
    .week-selector {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: #2d3748;
      border-radius: 8px;
      padding: 0.4rem 0.8rem;
    }
    .week-selector button {
      background: none;
      border: none;
      color: #63b3ed;
      cursor: pointer;
      font-size: 1rem;
      padding: 0.2rem 0.4rem;
      border-radius: 4px;
      transition: background 0.15s;
    }
    .week-selector button:hover:not(:disabled) {
      background: rgba(99, 179, 237, 0.15);
    }
    .week-selector button:disabled {
      color: #4a5568;
      cursor: not-allowed;
    }
    .week-label {
      font-size: 0.9rem;
      font-weight: 600;
      color: #e2e8f0;
      min-width: 220px;
      text-align: center;
    }
    .btn-pdf {
      background: #c53030;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 0.4rem 1rem;
      font-size: 0.85rem;
      cursor: pointer;
      transition: background 0.15s;
    }
    .btn-pdf:hover {
      background: #9b2c2c;
    }

    /* ---- Loading / Error ---- */
    .loading-state,
    .error-state {
      text-align: center;
      padding: 3rem;
      color: #a0aec0;
      font-size: 1rem;
    }
    .error-state {
      color: #fc8181;
    }

    /* ---- KPI cards ---- */
    .kpi-row {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .kpi-card {
      background: #2d3748;
      border-radius: 10px;
      padding: 1rem;
      text-align: center;
    }
    .kpi-label {
      font-size: 0.72rem;
      color: #a0aec0;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.4rem;
    }
    .kpi-value {
      font-size: 1.6rem;
      font-weight: 700;
      color: #e2e8f0;
      line-height: 1.1;
    }
    .kpi-sub {
      font-size: 0.72rem;
      color: #718096;
      margin-top: 0.3rem;
    }
    .text-green  { color: #48bb78; }
    .text-yellow { color: #ecc94b; }
    .text-red    { color: #fc8181; }
    .text-blue   { color: #63b3ed; }

    /* ---- Chart panel ---- */
    .chart-panel {
      background: #2d3748;
      border-radius: 10px;
      padding: 1.25rem;
      margin-bottom: 1.5rem;
    }
    .chart-panel h4 {
      margin: 0 0 1rem;
      font-size: 1rem;
      font-weight: 600;
      color: #e2e8f0;
    }
    .chart-container {
      position: relative;
      height: 280px;
    }

    /* ---- Best/Worst ---- */
    .best-worst-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    @media (max-width: 600px) {
      .best-worst-row { grid-template-columns: 1fr; }
    }
    .day-card {
      border-radius: 10px;
      padding: 1rem 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }
    .day-card.best  { background: rgba(72, 187, 120, 0.12); border-left: 4px solid #48bb78; }
    .day-card.worst { background: rgba(252, 129, 129, 0.12); border-left: 4px solid #fc8181; }
    .day-card-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #a0aec0;
    }
    .day-card-value {
      font-size: 1.3rem;
      font-weight: 700;
    }
    .day-card.best  .day-card-value { color: #48bb78; }
    .day-card.worst .day-card-value { color: #fc8181; }
    .day-card-date  {
      font-size: 0.82rem;
      color: #718096;
    }

    /* ---- Operators table ---- */
    .section-title {
      font-size: 1rem;
      font-weight: 600;
      color: #e2e8f0;
      margin-bottom: 0.75rem;
    }
    .operators-table-wrap {
      background: #2d3748;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 1.5rem;
    }
    .operators-table {
      width: 100%;
      border-collapse: collapse;
    }
    .operators-table th,
    .operators-table td {
      padding: 0.7rem 1rem;
      text-align: left;
      font-size: 0.85rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .operators-table th {
      background: rgba(0,0,0,0.2);
      color: #a0aec0;
      font-weight: 600;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .operators-table tr:last-child td {
      border-bottom: none;
    }
    .operators-table tr:hover td {
      background: rgba(255,255,255,0.03);
    }
    .rank-badge {
      display: inline-block;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      text-align: center;
      line-height: 26px;
      font-size: 0.8rem;
      font-weight: 700;
    }
    .rank-1 { background: linear-gradient(135deg, #f6e05e, #d69e2e); color: #1a202c; }
    .rank-2 { background: linear-gradient(135deg, #cbd5e0, #a0aec0); color: #1a202c; }
    .rank-3 { background: linear-gradient(135deg, #c05621, #9c4221); color: #fff; }
    .rank-n { background: #4a5568; color: #e2e8f0; }

    /* ---- Empty state ---- */
    .empty-state {
      text-align: center;
      padding: 2rem;
      color: #718096;
      font-size: 0.9rem;
    }

    /* ---- Print ---- */
    @media print {
      :host { padding: 0; }
      body  { background: #fff !important; color: #000 !important; }
      .no-print { display: none !important; }
      .kpi-card, .chart-panel, .operators-table-wrap, .day-card {
        border: 1px solid #ccc;
        break-inside: avoid;
      }
    }
  `],
  template: `
    <!-- Header -->
    <div class="page-header no-print">
      <h2><i class="fas fa-calendar-week me-2" style="color:#63b3ed"></i>Veckorapport</h2>

      <div class="week-selector">
        <button (click)="prevWeek()" title="Föregående vecka">&#9664;</button>
        <span class="week-label">{{ weekLabel }}</span>
        <button (click)="nextWeek()" [disabled]="isCurrentOrFutureWeek" title="Nästa vecka">&#9654;</button>
      </div>

      <button class="btn-pdf no-print" (click)="exportPDF()">
        <i class="fas fa-file-pdf me-1"></i>Exportera PDF
      </button>
    </div>

    <!-- Loading -->
    <div class="loading-state" *ngIf="isLoading">
      <i class="fas fa-spinner fa-spin me-2"></i>Laddar veckorapport...
    </div>

    <!-- Error -->
    <div class="error-state" *ngIf="!isLoading && errorMsg">
      <i class="fas fa-exclamation-triangle me-2"></i>{{ errorMsg }}
    </div>

    <!-- Content -->
    <ng-container *ngIf="!isLoading && data && !errorMsg">

      <!-- 6 KPI-kort -->
      <div class="kpi-row">
        <div class="kpi-card">
          <div class="kpi-label">Total IBC tvättade</div>
          <div class="kpi-value text-blue">{{ data.kpi.total_ibc_ok | number }}</div>
          <div class="kpi-sub">{{ data.kpi.total_ibc_ej }} kasserade</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Kvalitet</div>
          <div class="kpi-value" [class.text-green]="data.kpi.kvalitet_pct >= 98"
               [class.text-yellow]="data.kpi.kvalitet_pct >= 95 && data.kpi.kvalitet_pct < 98"
               [class.text-red]="data.kpi.kvalitet_pct < 95">
            {{ data.kpi.kvalitet_pct | number:'1.1-1' }}%
          </div>
          <div class="kpi-sub">OK / Total</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Snitt IBC/h</div>
          <div class="kpi-value text-blue">{{ data.kpi.snitt_ibc_per_h | number:'1.1-1' }}</div>
          <div class="kpi-sub">IBC per timme</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Drifttid</div>
          <div class="kpi-value">{{ data.kpi.drifttid_h | number:'1.0-0' }}h</div>
          <div class="kpi-sub">produktionstid</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Veckans mål</div>
          <div class="kpi-value"
               [class.text-green]="data.kpi.mal_uppfylld_pct >= 95"
               [class.text-yellow]="data.kpi.mal_uppfylld_pct >= 80 && data.kpi.mal_uppfylld_pct < 95"
               [class.text-red]="data.kpi.mal_uppfylld_pct < 80">
            {{ data.kpi.mal_uppfylld_pct | number:'1.0-0' }}%
          </div>
          <div class="kpi-sub">{{ data.kpi.total_ibc_ok }} / {{ data.kpi.mal_per_vecka }} IBC</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-label">Dagar på mål</div>
          <div class="kpi-value"
               [class.text-green]="data.kpi.dagar_pa_mal >= 4"
               [class.text-yellow]="data.kpi.dagar_pa_mal === 3"
               [class.text-red]="data.kpi.dagar_pa_mal < 3">
            {{ data.kpi.dagar_pa_mal }} / {{ data.kpi.totalt_vardagar }}
          </div>
          <div class="kpi-sub">vardagar ≥ {{ data.kpi.dagmal }} IBC</div>
        </div>
      </div>

      <!-- Daglig stapeldiagram -->
      <div class="chart-panel">
        <h4><i class="fas fa-chart-bar me-2" style="color:#63b3ed"></i>IBC per dag denna vecka</h4>
        <div class="chart-container">
          <canvas #weekChart></canvas>
        </div>
      </div>

      <!-- Bästa / sämsta dag -->
      <div class="best-worst-row" *ngIf="data.best_day && data.worst_day">
        <div class="day-card best">
          <div class="day-card-label">Bästa dag</div>
          <div class="day-card-value">{{ data.best_day.ibc_ok | number }} IBC</div>
          <div class="day-card-date">{{ formatDagLabel(data.best_day.dag) }}</div>
          <div class="day-card-date">Kvalitet: {{ data.best_day.kvalitet_pct | number:'1.1-1' }}%</div>
        </div>
        <div class="day-card worst">
          <div class="day-card-label">Sämsta dag</div>
          <div class="day-card-value">{{ data.worst_day.ibc_ok | number }} IBC</div>
          <div class="day-card-date">{{ formatDagLabel(data.worst_day.dag) }}</div>
          <div class="day-card-date">Kvalitet: {{ data.worst_day.kvalitet_pct | number:'1.1-1' }}%</div>
        </div>
      </div>

      <!-- Operatörsranking -->
      <div class="section-title">
        <i class="fas fa-users me-2" style="color:#63b3ed"></i>Operatörsranking — vecka {{ currentWeek }}
      </div>
      <div class="operators-table-wrap">
        <div class="empty-state" *ngIf="!data.operators || data.operators.length === 0">
          Ingen operatörsdata för denna vecka
        </div>
        <table class="operators-table" *ngIf="data.operators && data.operators.length > 0">
          <thead>
            <tr>
              <th>#</th>
              <th>Operatör</th>
              <th>IBC vecka</th>
              <th>IBC/h</th>
              <th>Kvalitet</th>
              <th>Skift</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let op of data.operators; let i = index">
              <td>
                <span class="rank-badge"
                      [class.rank-1]="i === 0"
                      [class.rank-2]="i === 1"
                      [class.rank-3]="i === 2"
                      [class.rank-n]="i > 2">
                  {{ i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : (i + 1) }}
                </span>
              </td>
              <td style="font-weight:600">{{ op.name }}</td>
              <td>
                <span [class.text-green]="op.ibc_ok_vecka >= data.kpi.dagmal * 3"
                      [class.text-yellow]="op.ibc_ok_vecka >= data.kpi.dagmal * 2 && op.ibc_ok_vecka < data.kpi.dagmal * 3"
                      [class.text-red]="op.ibc_ok_vecka < data.kpi.dagmal * 2">
                  {{ op.ibc_ok_vecka | number }}
                </span>
              </td>
              <td>{{ op.snitt_ibc_per_h | number:'1.1-1' }}</td>
              <td [class.text-green]="op.kvalitet_pct >= 98"
                  [class.text-yellow]="op.kvalitet_pct >= 95 && op.kvalitet_pct < 98"
                  [class.text-red]="op.kvalitet_pct < 95">
                {{ op.kvalitet_pct | number:'1.1-1' }}%
              </td>
              <td>{{ op.antal_skift }}</td>
            </tr>
          </tbody>
        </table>
      </div>

    </ng-container>
  `,
})
export class WeeklyReportPage implements OnInit, OnDestroy, AfterViewInit {
  private destroy$ = new Subject<void>();
  private apiBase  = environment.apiUrl;

  @ViewChild('weekChart') weekChartRef!: ElementRef<HTMLCanvasElement>;
  private chart: Chart | null = null;
  private pendingChart = false;

  // Standardvärde: förra veckan
  currentYear = new Date().getFullYear();
  currentWeek = this.getISOWeek(new Date()) - 1;

  data: WeeklyReport | null = null;
  isLoading = false;
  errorMsg  = '';

  Math = Math;

  constructor(private http: HttpClient) {
    // Om vi är i vecka 1 och går bakåt hamnar vi i föregående år
    if (this.currentWeek < 1) {
      this.currentYear--;
      this.currentWeek = this.weeksInYear(this.currentYear);
    }
  }

  get weekParam(): string {
    return `${this.currentYear}-W${String(this.currentWeek).padStart(2, '0')}`;
  }

  get weekLabel(): string {
    // Beräkna måndag och söndag för veckan
    const mon = new Date(this.currentYear, 0, 1 + (this.currentWeek - 1) * 7);
    // Justera till ISO-måndag
    const dayOfWeek = mon.getDay() || 7; // 0=sön → 7
    mon.setDate(mon.getDate() - dayOfWeek + 1);
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);

    const monStr = `${mon.getDate()} ${this.monthSv(mon.getMonth())}`;
    const sunStr = `${sun.getDate()} ${this.monthSv(sun.getMonth())}`;
    return `Vecka ${this.currentWeek}, ${this.currentYear} (${monStr} – ${sunStr})`;
  }

  get isCurrentOrFutureWeek(): boolean {
    const now  = new Date();
    const thisWeek = this.getISOWeek(now);
    const thisYear = now.getFullYear();
    if (this.currentYear > thisYear) return true;
    if (this.currentYear === thisYear && this.currentWeek >= thisWeek) return true;
    return false;
  }

  ngOnInit(): void {
    this.load();
  }

  ngAfterViewInit(): void {
    if (this.pendingChart && this.data) {
      this.pendingChart = false;
      this.buildChart();
    }
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.chart?.destroy();
  }

  load(): void {
    if (this.isLoading) return;
    this.isLoading = true;
    this.errorMsg  = '';
    this.data      = null;
    this.chart?.destroy();
    this.chart = null;

    this.http
      .get<WeeklyReport>(
        `${this.apiBase}?action=weekly-report&run=summary&week=${this.weekParam}`,
        { withCredentials: true }
      )
      .pipe(
        timeout(12000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.isLoading = false;
        if (res && res.success) {
          this.data = res;
          this.pendingChart = true;
          setTimeout(() => {
            if (this.weekChartRef) {
              this.pendingChart = false;
              this.buildChart();
            }
          }, 150);
        } else {
          this.errorMsg = 'Ingen data hittades för denna vecka.';
        }
      });
  }

  prevWeek(): void {
    this.currentWeek--;
    if (this.currentWeek < 1) {
      this.currentYear--;
      this.currentWeek = this.weeksInYear(this.currentYear);
    }
    this.load();
  }

  nextWeek(): void {
    if (this.isCurrentOrFutureWeek) return;
    this.currentWeek++;
    if (this.currentWeek > this.weeksInYear(this.currentYear)) {
      this.currentWeek = 1;
      this.currentYear++;
    }
    this.load();
  }

  exportPDF(): void {
    window.print();
  }

  formatDagLabel(dateStr: string): string {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T12:00:00');
    const dagNamn = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    return `${dagNamn[d.getDay()]} ${d.getDate()}/${d.getMonth() + 1}`;
  }

  private buildChart(): void {
    if (!this.weekChartRef || !this.data) return;
    const canvas = this.weekChartRef.nativeElement;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    this.chart?.destroy();

    const daily   = this.data.daily;
    const dagmal  = this.data.kpi.dagmal;

    const labels = daily.map(d => this.formatDagLabel(d.dag));
    const ibcData = daily.map(d => d.ibc_ok);

    const colors = ibcData.map(v => {
      const pct = dagmal > 0 ? v / dagmal : 0;
      if (pct >= 0.95) return 'rgba(72, 187, 120, 0.85)';
      if (pct >= 0.80) return 'rgba(236, 201, 75, 0.85)';
      return 'rgba(252, 129, 129, 0.85)';
    });

    const goalLine = daily.map(() => dagmal);

    this.chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC tvättade',
            data: ibcData,
            backgroundColor: colors,
            borderColor: colors.map(c => c.replace('0.85', '1')),
            borderWidth: 1,
            yAxisID: 'y',
            order: 2,
          },
          {
            label: `Dagsmål (${dagmal})`,
            data: goalLine,
            type: 'line',
            borderColor: 'rgba(246, 224, 94, 0.8)',
            borderWidth: 2,
            borderDash: [8, 4],
            pointRadius: 0,
            fill: false,
            tension: 0,
            yAxisID: 'y',
            order: 1,
          } as any,
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              afterBody: (items) => {
                const idx = items[0]?.dataIndex;
                if (idx !== undefined && daily[idx]) {
                  const d = daily[idx];
                  return [
                    `Kvalitet: ${d.kvalitet_pct.toFixed(1)}%`,
                    `IBC/h: ${d.ibc_per_h.toFixed(1)}`,
                    `Drifttid: ${d.drifttid_h.toFixed(1)}h`,
                  ];
                }
                return [];
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'IBC', color: '#a0aec0' },
          },
        },
      },
    });
  }

  // ISO-veckonummer (1–53)
  getISOWeek(d: Date): number {
    const tmp = new Date(d.getTime());
    tmp.setHours(0, 0, 0, 0);
    tmp.setDate(tmp.getDate() + 4 - (tmp.getDay() || 7));
    const yearStart = new Date(tmp.getFullYear(), 0, 1);
    return Math.ceil((((tmp.getTime() - yearStart.getTime()) / 86400000) + 1) / 7);
  }

  // Antal ISO-veckor i ett år (52 eller 53)
  private weeksInYear(year: number): number {
    const dec28 = new Date(year, 11, 28);
    return this.getISOWeek(dec28);
  }

  private monthSv(m: number): string {
    const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun',
                    'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    return months[m] ?? '';
  }
}
