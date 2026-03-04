import { Component, OnInit, OnDestroy, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';

Chart.register(...registerables);

interface Operator {
  id: number;
  name: string;
}

interface TrendVecka {
  vecka: string;
  ibc_vecka: number;
}

interface OperatorData {
  id: number;
  name: string;
  total_ibc_ok: number;
  total_ibc_ej_ok: number;
  total_ibc: number;
  total_runtime_h: number;
  antal_skift: number;
  snitt_ibc_per_h: number;
  kvalitet_pct: number;
  trend_veckor: TrendVecka[];
}

interface CompareResponse {
  success: boolean;
  op_a: OperatorData;
  op_b: OperatorData;
  error?: string;
}

@Component({
  selector: 'app-operator-compare',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="compare-page">

      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">
          <i class="fas fa-balance-scale me-2"></i>Operatörsjämförelse
        </h1>
        <p class="page-subtitle">Jämför två operatörers prestanda sida vid sida</p>
      </div>

      <!-- Period + väljare -->
      <div class="controls-card">
        <!-- Periodknappar -->
        <div class="period-row">
          <span class="control-label">Period:</span>
          <div class="period-buttons">
            <button class="period-btn" [class.active]="days === 14" (click)="setDays(14)">14 dagar</button>
            <button class="period-btn" [class.active]="days === 30" (click)="setDays(30)">30 dagar</button>
            <button class="period-btn" [class.active]="days === 90" (click)="setDays(90)">90 dagar</button>
          </div>
        </div>

        <!-- Operatörsväljare -->
        <div class="selectors-row">
          <div class="selector-group">
            <label class="selector-label">Operatör A</label>
            <select class="op-select op-a" [(ngModel)]="selectedOpA">
              <option [ngValue]="null" disabled>Välj operatör A…</option>
              <option *ngFor="let op of operators" [ngValue]="op.id">{{ op.name }}</option>
            </select>
          </div>

          <div class="vs-divider">VS</div>

          <div class="selector-group">
            <label class="selector-label">Operatör B</label>
            <select class="op-select op-b" [(ngModel)]="selectedOpB">
              <option [ngValue]="null" disabled>Välj operatör B…</option>
              <option *ngFor="let op of operators" [ngValue]="op.id">{{ op.name }}</option>
            </select>
          </div>
        </div>

        <!-- Jämför-knapp -->
        <div class="compare-btn-row">
          <button
            class="btn-compare"
            [disabled]="!selectedOpA || !selectedOpB || isLoading || selectedOpA === selectedOpB"
            (click)="compare()">
            <span *ngIf="!isLoading">
              <i class="fas fa-chart-bar me-2"></i>Jämför
            </span>
            <span *ngIf="isLoading">
              <i class="fas fa-spinner fa-spin me-2"></i>Hämtar…
            </span>
          </button>
          <p *ngIf="selectedOpA && selectedOpB && selectedOpA === selectedOpB" class="same-op-warning">
            Välj två olika operatörer
          </p>
        </div>
      </div>

      <!-- Felmeddelande -->
      <div *ngIf="errorMsg" class="error-banner">
        <i class="fas fa-exclamation-circle me-2"></i>{{ errorMsg }}
      </div>

      <!-- Resultat -->
      <ng-container *ngIf="compareData && !isLoading">

        <!-- Operatörsnamn-header -->
        <div class="op-names-row">
          <div class="op-name-badge op-a-badge">
            <div class="op-avatar op-a-avatar">{{ getInitials(compareData.op_a.name) }}</div>
            <span>{{ compareData.op_a.name }}</span>
          </div>
          <div class="op-name-badge op-b-badge">
            <div class="op-avatar op-b-avatar">{{ getInitials(compareData.op_b.name) }}</div>
            <span>{{ compareData.op_b.name }}</span>
          </div>
        </div>

        <!-- KPI-jämförelsetabell -->
        <div class="kpi-table-card">
          <h3 class="section-title"><i class="fas fa-table me-2"></i>KPI-jämförelse — senaste {{ days }} dagar</h3>
          <div class="kpi-table">

            <!-- Header -->
            <div class="kpi-row kpi-header-row">
              <div class="kpi-col kpi-label-col">Mätetal</div>
              <div class="kpi-col kpi-a-col op-a-color">{{ compareData.op_a.name }}</div>
              <div class="kpi-col kpi-b-col op-b-color">{{ compareData.op_b.name }}</div>
            </div>

            <!-- Total IBC -->
            <div class="kpi-row" [class.winner-a]="wins('total_ibc_ok', 'a')" [class.winner-b]="wins('total_ibc_ok', 'b')">
              <div class="kpi-col kpi-label-col">
                <i class="fas fa-box me-2 kpi-icon"></i>Total IBC (OK)
              </div>
              <div class="kpi-col kpi-a-col" [class.winner-cell]="wins('total_ibc_ok', 'a')">
                <span class="kpi-value">{{ compareData.op_a.total_ibc_ok }}</span>
                <span *ngIf="wins('total_ibc_ok', 'a')" class="winner-badge">Bäst</span>
              </div>
              <div class="kpi-col kpi-b-col" [class.winner-cell]="wins('total_ibc_ok', 'b')">
                <span class="kpi-value">{{ compareData.op_b.total_ibc_ok }}</span>
                <span *ngIf="wins('total_ibc_ok', 'b')" class="winner-badge">Bäst</span>
              </div>
            </div>

            <!-- Kvalitet% -->
            <div class="kpi-row" [class.winner-a]="wins('kvalitet_pct', 'a')" [class.winner-b]="wins('kvalitet_pct', 'b')">
              <div class="kpi-col kpi-label-col">
                <i class="fas fa-check-circle me-2 kpi-icon"></i>Kvalitet %
              </div>
              <div class="kpi-col kpi-a-col" [class.winner-cell]="wins('kvalitet_pct', 'a')">
                <span class="kpi-value" [class.kpi-good]="compareData.op_a.kvalitet_pct >= 97" [class.kpi-warn]="compareData.op_a.kvalitet_pct >= 90 && compareData.op_a.kvalitet_pct < 97" [class.kpi-bad]="compareData.op_a.kvalitet_pct < 90">
                  {{ compareData.op_a.kvalitet_pct | number:'1.1-1' }}%
                </span>
                <span *ngIf="wins('kvalitet_pct', 'a')" class="winner-badge">Bäst</span>
              </div>
              <div class="kpi-col kpi-b-col" [class.winner-cell]="wins('kvalitet_pct', 'b')">
                <span class="kpi-value" [class.kpi-good]="compareData.op_b.kvalitet_pct >= 97" [class.kpi-warn]="compareData.op_b.kvalitet_pct >= 90 && compareData.op_b.kvalitet_pct < 97" [class.kpi-bad]="compareData.op_b.kvalitet_pct < 90">
                  {{ compareData.op_b.kvalitet_pct | number:'1.1-1' }}%
                </span>
                <span *ngIf="wins('kvalitet_pct', 'b')" class="winner-badge">Bäst</span>
              </div>
            </div>

            <!-- IBC/h -->
            <div class="kpi-row" [class.winner-a]="wins('snitt_ibc_per_h', 'a')" [class.winner-b]="wins('snitt_ibc_per_h', 'b')">
              <div class="kpi-col kpi-label-col">
                <i class="fas fa-tachometer-alt me-2 kpi-icon"></i>IBC / timme
              </div>
              <div class="kpi-col kpi-a-col" [class.winner-cell]="wins('snitt_ibc_per_h', 'a')">
                <span class="kpi-value">{{ compareData.op_a.snitt_ibc_per_h | number:'1.1-1' }}</span>
                <span *ngIf="wins('snitt_ibc_per_h', 'a')" class="winner-badge">Bäst</span>
              </div>
              <div class="kpi-col kpi-b-col" [class.winner-cell]="wins('snitt_ibc_per_h', 'b')">
                <span class="kpi-value">{{ compareData.op_b.snitt_ibc_per_h | number:'1.1-1' }}</span>
                <span *ngIf="wins('snitt_ibc_per_h', 'b')" class="winner-badge">Bäst</span>
              </div>
            </div>

            <!-- Antal skift -->
            <div class="kpi-row">
              <div class="kpi-col kpi-label-col">
                <i class="fas fa-calendar-check me-2 kpi-icon"></i>Antal skift
              </div>
              <div class="kpi-col kpi-a-col">
                <span class="kpi-value">{{ compareData.op_a.antal_skift }}</span>
              </div>
              <div class="kpi-col kpi-b-col">
                <span class="kpi-value">{{ compareData.op_b.antal_skift }}</span>
              </div>
            </div>

            <!-- Total drifttid -->
            <div class="kpi-row" [class.winner-a]="wins('total_runtime_h', 'a')" [class.winner-b]="wins('total_runtime_h', 'b')">
              <div class="kpi-col kpi-label-col">
                <i class="fas fa-clock me-2 kpi-icon"></i>Total drifttid
              </div>
              <div class="kpi-col kpi-a-col" [class.winner-cell]="wins('total_runtime_h', 'a')">
                <span class="kpi-value">{{ compareData.op_a.total_runtime_h | number:'1.1-1' }} h</span>
                <span *ngIf="wins('total_runtime_h', 'a')" class="winner-badge">Bäst</span>
              </div>
              <div class="kpi-col kpi-b-col" [class.winner-cell]="wins('total_runtime_h', 'b')">
                <span class="kpi-value">{{ compareData.op_b.total_runtime_h | number:'1.1-1' }} h</span>
                <span *ngIf="wins('total_runtime_h', 'b')" class="winner-badge">Bäst</span>
              </div>
            </div>

            <!-- Totalt IBC (inkl. ej ok) -->
            <div class="kpi-row">
              <div class="kpi-col kpi-label-col">
                <i class="fas fa-boxes me-2 kpi-icon"></i>Totalt IBC (inkl. ej OK)
              </div>
              <div class="kpi-col kpi-a-col">
                <span class="kpi-value">{{ compareData.op_a.total_ibc }}</span>
              </div>
              <div class="kpi-col kpi-b-col">
                <span class="kpi-value">{{ compareData.op_b.total_ibc }}</span>
              </div>
            </div>

          </div><!-- /kpi-table -->

          <!-- Sammanfattning: vinnare -->
          <div class="summary-row" *ngIf="getWinnerSummary() as summary">
            <i class="fas fa-trophy me-2" style="color:#ffd700"></i>
            <span [innerHTML]="summary"></span>
          </div>
        </div>

        <!-- Trendgraf -->
        <div class="trend-card" *ngIf="hasTrendData()">
          <h3 class="section-title"><i class="fas fa-chart-line me-2"></i>Veckovis IBC-trend — senaste 8 veckor</h3>
          <div class="chart-wrapper">
            <canvas #trendChartCanvas></canvas>
          </div>
          <div class="chart-legend">
            <span class="legend-item legend-a"><span class="legend-dot dot-a"></span>{{ compareData.op_a.name }}</span>
            <span class="legend-item legend-b"><span class="legend-dot dot-b"></span>{{ compareData.op_b.name }}</span>
          </div>
        </div>

        <!-- Ingen trenddata -->
        <div class="trend-card no-data-card" *ngIf="!hasTrendData()">
          <i class="fas fa-chart-line fa-2x mb-2" style="color:#4a5568"></i>
          <p>Ingen trenddata tillgänglig för vald period.</p>
        </div>

      </ng-container>

      <!-- Tom tillstånd (ingen sökning gjord ännu) -->
      <div class="empty-state" *ngIf="!compareData && !isLoading">
        <i class="fas fa-users fa-3x mb-3" style="color:#4a5568"></i>
        <p>Välj två operatörer ovan och klicka <strong>Jämför</strong> för att se statistiken.</p>
      </div>

    </div>
  `,
  styles: [`
    .compare-page {
      min-height: 100vh;
      background: #1a202c;
      padding: 24px;
      color: #e2e8f0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Header */
    .page-header {
      margin-bottom: 24px;
    }
    .page-title {
      font-size: 1.75rem;
      font-weight: 700;
      color: #e2e8f0;
      margin: 0 0 4px 0;
    }
    .page-subtitle {
      color: #a0aec0;
      margin: 0;
      font-size: 0.9rem;
    }

    /* Controls card */
    .controls-card {
      background: #2d3748;
      border-radius: 12px;
      padding: 20px 24px;
      margin-bottom: 20px;
    }
    .period-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }
    .control-label {
      color: #a0aec0;
      font-size: 0.85rem;
      font-weight: 600;
      white-space: nowrap;
    }
    .period-buttons {
      display: flex;
      gap: 8px;
    }
    .period-btn {
      background: #1a202c;
      border: 1px solid #4a5568;
      color: #a0aec0;
      border-radius: 20px;
      padding: 4px 16px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.15s;
    }
    .period-btn:hover {
      border-color: #4299e1;
      color: #e2e8f0;
    }
    .period-btn.active {
      background: #4299e1;
      border-color: #4299e1;
      color: #fff;
      font-weight: 600;
    }

    /* Selectors */
    .selectors-row {
      display: flex;
      align-items: flex-end;
      gap: 16px;
      flex-wrap: wrap;
    }
    .selector-group {
      flex: 1;
      min-width: 180px;
    }
    .selector-label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: #a0aec0;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .op-select {
      width: 100%;
      background: #1a202c;
      border: 1px solid #4a5568;
      color: #e2e8f0;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 0.95rem;
      cursor: pointer;
      outline: none;
      transition: border-color 0.15s;
    }
    .op-select:focus {
      border-color: #4299e1;
    }
    .op-a { border-left: 3px solid #4299e1; }
    .op-b { border-left: 3px solid #ed8936; }

    .vs-divider {
      font-size: 1.1rem;
      font-weight: 800;
      color: #718096;
      padding-bottom: 8px;
      align-self: flex-end;
    }

    .compare-btn-row {
      margin-top: 18px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .btn-compare {
      background: linear-gradient(135deg, #4299e1, #3182ce);
      border: none;
      color: #fff;
      border-radius: 8px;
      padding: 10px 28px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s;
    }
    .btn-compare:hover:not(:disabled) {
      background: linear-gradient(135deg, #63b3ed, #4299e1);
      transform: translateY(-1px);
    }
    .btn-compare:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }
    .same-op-warning {
      color: #fc8181;
      font-size: 0.85rem;
      margin: 0;
    }

    /* Error */
    .error-banner {
      background: rgba(252, 129, 74, 0.15);
      border: 1px solid #fc8181;
      border-radius: 8px;
      padding: 12px 16px;
      color: #fc8181;
      margin-bottom: 16px;
    }

    /* Op names row */
    .op-names-row {
      display: flex;
      gap: 16px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .op-name-badge {
      display: flex;
      align-items: center;
      gap: 10px;
      background: #2d3748;
      border-radius: 10px;
      padding: 10px 18px;
      flex: 1;
      min-width: 160px;
      font-weight: 600;
      font-size: 1rem;
    }
    .op-a-badge { border-left: 4px solid #4299e1; }
    .op-b-badge { border-left: 4px solid #ed8936; }
    .op-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.85rem;
      color: #fff;
      flex-shrink: 0;
    }
    .op-a-avatar { background: #2b6cb0; }
    .op-b-avatar { background: #c05621; }

    /* KPI table card */
    .kpi-table-card {
      background: #2d3748;
      border-radius: 12px;
      padding: 20px 24px;
      margin-bottom: 20px;
    }
    .section-title {
      font-size: 1rem;
      font-weight: 600;
      color: #e2e8f0;
      margin: 0 0 16px 0;
    }

    .kpi-table {
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid #4a5568;
    }
    .kpi-row {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      border-bottom: 1px solid #4a5568;
      transition: background 0.15s;
    }
    .kpi-row:last-child { border-bottom: none; }
    .kpi-header-row {
      background: #1a202c;
    }
    .kpi-col {
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .kpi-label-col {
      font-size: 0.9rem;
      color: #a0aec0;
    }
    .kpi-icon { font-size: 0.8rem; opacity: 0.7; }
    .kpi-a-col { justify-content: center; border-left: 1px solid #4a5568; }
    .kpi-b-col { justify-content: center; border-left: 1px solid #4a5568; }
    .op-a-color { color: #63b3ed; font-weight: 600; font-size: 0.85rem; }
    .op-b-color { color: #f6ad55; font-weight: 600; font-size: 0.85rem; }

    .kpi-value {
      font-size: 1.05rem;
      font-weight: 600;
      color: #e2e8f0;
    }
    .winner-cell {
      background: rgba(72, 187, 120, 0.12);
    }
    .winner-badge {
      font-size: 0.65rem;
      background: #48bb78;
      color: #fff;
      border-radius: 10px;
      padding: 2px 7px;
      margin-left: 6px;
      font-weight: 700;
      text-transform: uppercase;
    }
    .kpi-good { color: #68d391; }
    .kpi-warn { color: #f6ad55; }
    .kpi-bad  { color: #fc8181; }

    .winner-a { background: rgba(66, 153, 225, 0.05); }
    .winner-b { background: rgba(237, 137, 54, 0.05); }

    /* Summary row */
    .summary-row {
      margin-top: 16px;
      padding: 10px 14px;
      background: #1a202c;
      border-radius: 8px;
      font-size: 0.9rem;
      color: #e2e8f0;
    }

    /* Trend chart */
    .trend-card {
      background: #2d3748;
      border-radius: 12px;
      padding: 20px 24px;
      margin-bottom: 20px;
    }
    .no-data-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #718096;
      padding: 40px 24px;
    }
    .chart-wrapper {
      position: relative;
      height: 300px;
      margin-top: 8px;
    }
    .chart-legend {
      display: flex;
      gap: 20px;
      margin-top: 12px;
      justify-content: center;
    }
    .legend-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.85rem;
      color: #a0aec0;
    }
    .legend-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
    }
    .dot-a { background: #4299e1; }
    .dot-b { background: #ed8936; }

    /* Empty state */
    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 60px 20px;
      color: #718096;
      text-align: center;
    }
    .empty-state p {
      font-size: 1rem;
      max-width: 400px;
    }

    /* Responsivt */
    @media (max-width: 600px) {
      .compare-page { padding: 12px; }
      .selectors-row { flex-direction: column; }
      .vs-divider { align-self: center; }
      .kpi-row { grid-template-columns: 1.5fr 1fr 1fr; }
      .kpi-col { padding: 10px 8px; }
      .kpi-value { font-size: 0.9rem; }
    }
  `]
})
export class OperatorComparePage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  Math = Math;

  operators: Operator[] = [];
  selectedOpA: number | null = null;
  selectedOpB: number | null = null;
  days = 30;
  compareData: { op_a: OperatorData; op_b: OperatorData } | null = null;
  isLoading = false;
  errorMsg = '';

  private trendChart: Chart | null = null;
  private chartTimer: any = null;

  @ViewChild('trendChartCanvas') trendChartCanvas!: ElementRef<HTMLCanvasElement>;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadOperators();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) {
      clearTimeout(this.chartTimer);
      this.chartTimer = null;
    }
    this.trendChart?.destroy();
    this.trendChart = null;
  }

  // -------------------------------------------------------------------------
  // Period
  // -------------------------------------------------------------------------
  setDays(d: number): void {
    this.days = d;
    // Re-compare om data redan är laddad
    if (this.compareData && this.selectedOpA && this.selectedOpB) {
      this.compare();
    }
  }

  // -------------------------------------------------------------------------
  // Hämta operatörslista
  // -------------------------------------------------------------------------
  loadOperators(): void {
    this.http
      .get<Operator[]>(
        `${this.apiBase}?action=operator-compare&run=operators-list`,
        { withCredentials: true }
      )
      .pipe(
        timeout(8000),
        catchError(() => of([])),
        takeUntil(this.destroy$)
      )
      .subscribe((data) => {
        this.operators = data || [];
      });
  }

  // -------------------------------------------------------------------------
  // Jämför
  // -------------------------------------------------------------------------
  compare(): void {
    if (!this.selectedOpA || !this.selectedOpB || this.isLoading) return;
    if (this.selectedOpA === this.selectedOpB) return;

    this.isLoading = true;
    this.errorMsg = '';

    const url =
      `${this.apiBase}?action=operator-compare&run=compare` +
      `&op_a=${this.selectedOpA}&op_b=${this.selectedOpB}&days=${this.days}`;

    this.http
      .get<CompareResponse>(url, { withCredentials: true })
      .pipe(
        timeout(10000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe((data) => {
        this.isLoading = false;
        if (data?.success) {
          this.compareData = { op_a: data.op_a, op_b: data.op_b };
          // Vänta en tick så att *ngIf renderar canvas
          if (this.chartTimer) {
            clearTimeout(this.chartTimer);
          }
          this.chartTimer = setTimeout(() => this.buildTrendChart(), 120);
        } else {
          this.errorMsg = data?.error || 'Kunde inte hämta jämförelsedata. Försök igen.';
          this.compareData = null;
        }
      });
  }

  // -------------------------------------------------------------------------
  // Trendgraf
  // -------------------------------------------------------------------------
  buildTrendChart(): void {
    if (!this.trendChartCanvas || !this.compareData) return;

    this.trendChart?.destroy();
    this.trendChart = null;

    const opA = this.compareData.op_a;
    const opB = this.compareData.op_b;

    // Samla alla unika veckor
    const allWeeks = Array.from(
      new Set([
        ...opA.trend_veckor.map((t) => t.vecka),
        ...opB.trend_veckor.map((t) => t.vecka),
      ])
    ).sort();

    if (allWeeks.length === 0) return;

    // Mappa vecka → IBC
    const mapA = new Map(opA.trend_veckor.map((t) => [t.vecka, t.ibc_vecka]));
    const mapB = new Map(opB.trend_veckor.map((t) => [t.vecka, t.ibc_vecka]));

    const dataA = allWeeks.map((w) => mapA.get(w) ?? 0);
    const dataB = allWeeks.map((w) => mapB.get(w) ?? 0);

    // Formattera veckoetikett: "202406" → "V06"
    const labels = allWeeks.map((w) => {
      if (w.length === 6) {
        return `V${w.slice(4)}`;
      }
      return w;
    });

    const ctx = this.trendChartCanvas.nativeElement.getContext('2d');
    if (!ctx) return;

    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: opA.name,
            data: dataA,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.12)',
            borderWidth: 2.5,
            pointBackgroundColor: '#4299e1',
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.3,
          },
          {
            label: opB.name,
            data: dataB,
            borderColor: '#ed8936',
            backgroundColor: 'rgba(237,137,54,0.10)',
            borderWidth: 2.5,
            pointBackgroundColor: '#ed8936',
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.3,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#2d3748',
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            borderColor: '#4a5568',
            borderWidth: 1,
            callbacks: {
              label: (ctx) => {
                return ` ${ctx.dataset.label}: ${ctx.parsed.y} IBC`;
              },
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid: { color: 'rgba(255,255,255,0.07)' },
            beginAtZero: true,
            title: {
              display: true,
              text: 'IBC per vecka',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // -------------------------------------------------------------------------
  // Hjälpmetoder
  // -------------------------------------------------------------------------

  /** Returnerar true om operatör op vinner på metricen (högre = bättre) */
  wins(
    metric: 'total_ibc_ok' | 'kvalitet_pct' | 'snitt_ibc_per_h' | 'total_runtime_h',
    op: 'a' | 'b'
  ): boolean {
    if (!this.compareData) return false;
    const a = this.compareData.op_a;
    const b = this.compareData.op_b;

    const valA = a[metric] as number;
    const valB = b[metric] as number;

    if (valA === valB) return false;
    return op === 'a' ? valA > valB : valB > valA;
  }

  /** Räknar hur många KPI:er varje operatör vinner och returnerar en summeringstext */
  getWinnerSummary(): string {
    if (!this.compareData) return '';
    const metrics: Array<'total_ibc_ok' | 'kvalitet_pct' | 'snitt_ibc_per_h' | 'total_runtime_h'> =
      ['total_ibc_ok', 'kvalitet_pct', 'snitt_ibc_per_h', 'total_runtime_h'];

    let scoreA = 0;
    let scoreB = 0;
    for (const m of metrics) {
      if (this.wins(m, 'a')) scoreA++;
      else if (this.wins(m, 'b')) scoreB++;
    }

    const nameA = this.compareData.op_a.name;
    const nameB = this.compareData.op_b.name;

    if (scoreA > scoreB) {
      return `<strong>${nameA}</strong> vinner ${scoreA} av ${metrics.length} mätetal.`;
    } else if (scoreB > scoreA) {
      return `<strong>${nameB}</strong> vinner ${scoreB} av ${metrics.length} mätetal.`;
    } else {
      return `Jämnt resultat — vardera operatör vinner ${scoreA} mätetal.`;
    }
  }

  hasTrendData(): boolean {
    if (!this.compareData) return false;
    return (
      this.compareData.op_a.trend_veckor.length > 0 ||
      this.compareData.op_b.trend_veckor.length > 0
    );
  }

  getInitials(name: string): string {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  }
}
