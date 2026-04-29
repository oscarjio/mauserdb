import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface ShiftRow {
  skiftraknare: number;
  datum: string;
  ibc_h: number;
  ibc_ok: number;
  kass_pct: number;
  stopp_pct: number;
  drifttid: number;
  op1: number | null;
  op2: number | null;
  op3: number | null;
  op1_name: string | null;
  op2_name: string | null;
  op3_name: string | null;
  product_id: number | null;
  product_name: string | null;
  z_ibc: number;
  z_kass: number;
  z_stopp: number;
}

interface OpCorr {
  op_number: number;
  name: string;
  total: number;
  low_prod: number;
  high_kass: number;
  high_stopp: number;
  low_prod_pct: number;
  high_kass_pct: number;
  high_stopp_pct: number;
}

interface ProdCorr {
  product_id: number;
  name: string;
  total: number;
  low_prod: number;
  high_kass: number;
  low_prod_pct: number;
  high_kass_pct: number;
}

interface MonthTrend {
  month: string;
  total: number;
  anomaly: number;
  anomaly_rate: number;
}

interface AvvikelserResponse {
  success: boolean;
  period: {
    from: string;
    to: string;
    days: number;
    total_shifts: number;
    anomaly_count: number;
    anomaly_rate: number;
  };
  stats: {
    avg_ibc_h: number;
    std_ibc_h: number;
    avg_kass: number;
    std_kass: number;
    avg_stopp: number;
    std_stopp: number;
    threshold_sigma: number;
  };
  anomalies: {
    low_prod: ShiftRow[];
    high_prod: ShiftRow[];
    high_kass: ShiftRow[];
    high_stopp: ShiftRow[];
  };
  operator_correlation: OpCorr[];
  product_correlation: ProdCorr[];
  monthly_trend: MonthTrend[];
}

@Component({
  standalone: true,
  selector: 'app-skift-avvikelser',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './skift-avvikelser.html',
  styleUrl: './skift-avvikelser.css',
})
export class SkiftAvvikelserPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  days = 180;
  tab: 'low_prod' | 'high_kass' | 'high_stopp' | 'op_corr' | 'prod_corr' | 'trend' = 'low_prod';

  period: AvvikelserResponse['period'] | null = null;
  stats: AvvikelserResponse['stats'] | null = null;
  anomalies: AvvikelserResponse['anomalies'] = { low_prod: [], high_prod: [], high_kass: [], high_stopp: [] };
  opCorr: OpCorr[] = [];
  prodCorr: ProdCorr[] = [];
  monthTrend: MonthTrend[] = [];

  Math = Math;

  readonly daysOptions = [
    { value: 90,  label: '90 dagar' },
    { value: 180, label: '180 dagar' },
    { value: 365, label: '1 år' },
    { value: 730, label: '2 år' },
  ];

  get anomalyRateClass(): string {
    const r = this.period?.anomaly_rate ?? 0;
    if (r > 25) return 'kpi-danger';
    if (r > 15) return 'kpi-warn';
    return 'kpi-ok';
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setTab(t: typeof this.tab): void { this.tab = t; }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=skift-avvikelser&days=${this.days}`;
    this.http.get<AvvikelserResponse>(url, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) {
          this.error = 'Kunde inte hämta avvikelsedata.';
          return;
        }
        this.period    = res.period;
        this.stats     = res.stats;
        this.anomalies = res.anomalies;
        this.opCorr    = res.operator_correlation;
        this.prodCorr  = res.product_correlation;
        this.monthTrend = res.monthly_trend;
      });
  }

  ops(row: ShiftRow): string {
    return [row.op1_name, row.op2_name, row.op3_name].filter(Boolean).join(', ');
  }

  zLabel(z: number): string {
    const sign = z >= 0 ? '+' : '';
    return `${sign}${z.toFixed(1)}σ`;
  }

  zClass(z: number, inverted = false): string {
    const bad = inverted ? z > 0 : z < 0;
    const abs = Math.abs(z);
    if (abs > 2.5) return bad ? 'z-extreme' : 'z-great';
    if (abs > 1.5) return bad ? 'z-bad' : 'z-good';
    return 'z-neutral';
  }

  pctClass(pct: number): string {
    if (pct > 30) return 'corr-high';
    if (pct > 15) return 'corr-mid';
    return 'corr-low';
  }

  formatDate(d: string): string {
    if (!d) return '';
    const dt = new Date(d + 'T00:00:00');
    const days = ['Sön', 'Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör'];
    return `${days[dt.getDay()]} ${d}`;
  }

  monthLabel(m: string): string {
    const [y, mo] = m.split('-');
    const months = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
    return `${months[parseInt(mo) - 1]} ${y}`;
  }

  trendBarWidth(rate: number): number {
    return Math.min(100, Math.round(rate * 2));
  }

  trendBarClass(rate: number): string {
    if (rate > 30) return 'bar-danger';
    if (rate > 15) return 'bar-warn';
    return 'bar-ok';
  }

  opRiskBadge(op: OpCorr): string {
    const score = op.low_prod_pct + op.high_kass_pct;
    if (score > 50) return 'Riskfaktor';
    if (score > 25) return 'Bevaka';
    return 'Stabil';
  }

  opRiskClass(op: OpCorr): string {
    const score = op.low_prod_pct + op.high_kass_pct;
    if (score > 50) return 'risk-high';
    if (score > 25) return 'risk-mid';
    return 'risk-low';
  }
}
