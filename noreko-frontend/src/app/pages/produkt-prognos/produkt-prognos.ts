import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorOption {
  number: number;
  name: string;
}

interface ProductOption {
  id: number;
  name: string;
  cycle_time_minutes: number | null;
}

interface OpPositionData {
  number: number;
  name: string;
  antal_skift_overall: number;
  antal_skift_product: number;
  overall_ibc_h: number | null;
  product_ibc_h: number | null;
  team_avg_pos: number | null;
  team_avg_pos_prod: number | null;
  trend: 'up' | 'down' | 'stable' | null;
  trend_pct: number | null;
  recent_ibc_h: number | null;
  baseline_ibc_h: number | null;
  vs_team_overall: number | null;
  vs_team_product: number | null;
}

interface SimilarShift {
  datum: string;
  ibc_ok: number;
  ibc_h: number;
  kassation: number;
  drifttid_min: number;
}

interface TeamChemistry {
  team_shifts: number;
  team_ibc_h: number | null;
  product_shifts: number;
  product_ibc_h: number | null;
  similar_shifts: SimilarShift[];
}

interface Forecast {
  op1: OpPositionData | null;
  op2: OpPositionData | null;
  op3: OpPositionData | null;
  product_id: number;
  product_name: string;
  cycle_time_minutes: number | null;
  prognosed_ibc_h: number | null;
  prognosis_source: string;
  prognosed_8h_ibc: number | null;
  period_team_avg: number | null;
  product_team_avg: number | null;
  vs_team_period: number | null;
  vs_product_avg: number | null;
  team_chemistry: TeamChemistry;
}

interface ApiResponse {
  success: boolean;
  days: number;
  from: string;
  to: string;
  operators: OperatorOption[];
  products: ProductOption[];
  forecast: Forecast | null;
}

@Component({
  standalone: true,
  selector: 'app-produkt-prognos',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './produkt-prognos.html',
  styleUrl: './produkt-prognos.css',
})
export class ProduktPrognosPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  Math = Math;

  days = 90;
  loading = false;
  error = '';

  operators: OperatorOption[] = [];
  products: ProductOption[] = [];

  op1 = 0;
  op2 = 0;
  op3 = 0;
  productId = 0;

  forecast: Forecast | null = null;
  from = '';
  to = '';

  readonly daysOptions = [
    { value: 30, label: '30d' },
    { value: 60, label: '60d' },
    { value: 90, label: '90d' },
    { value: 180, label: '6 mån' },
    { value: 365, label: '1 år' },
  ];

  readonly posLabels: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadBase();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private apiUrl(extra = ''): string {
    return `${environment.apiUrl}?action=rebotling&run=produkt-prognos&days=${this.days}${extra}`;
  }

  loadBase(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.forecast = null;

    this.http.get<ApiResponse>(this.apiUrl(), { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta data.';
        return;
      }
      this.operators = res.operators;
      this.products = res.products;
    });
  }

  calculate(): void {
    if (!this.hasAllInputs() || this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.forecast = null;

    const extra = `&op1=${this.op1}&op2=${this.op2}&op3=${this.op3}&product_id=${this.productId}`;
    this.http.get<ApiResponse>(this.apiUrl(extra), { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte beräkna prognos.';
        return;
      }
      this.operators = res.operators;
      this.products = res.products;
      this.from = res.from;
      this.to = res.to;
      this.forecast = res.forecast;
    });
  }

  setDays(d: number): void {
    this.days = d;
    if (this.hasAllInputs()) {
      this.calculate();
    }
  }

  hasAllInputs(): boolean {
    return this.op1 > 0 && this.op2 > 0 && this.op3 > 0 && this.productId > 0;
  }

  sourceLabel(src: string): string {
    const m: Record<string, string> = {
      'team-product':  'Exakt lag + produkt (bäst)',
      'team-overall':  'Exakt lag, alla produkter',
      'positions':     'Per-positions snitt',
      'none':          'Otillräcklig data',
    };
    return m[src] ?? src;
  }

  sourceIcon(src: string): string {
    const m: Record<string, string> = {
      'team-product': 'fas fa-star',
      'team-overall': 'fas fa-users',
      'positions':    'fas fa-chart-bar',
      'none':         'fas fa-question-circle',
    };
    return m[src] ?? 'fas fa-info-circle';
  }

  vsClass(pct: number | null): string {
    if (pct === null) return '';
    if (pct >= 10) return 'positive-strong';
    if (pct >= 0)  return 'positive';
    if (pct >= -10) return 'negative';
    return 'negative-strong';
  }

  trendIcon(t: string | null): string {
    if (t === 'up')     return 'fas fa-arrow-up trend-up';
    if (t === 'down')   return 'fas fa-arrow-down trend-down';
    if (t === 'stable') return 'fas fa-minus trend-stable';
    return '';
  }

  trendLabel(t: string | null, pct: number | null): string {
    const sign = pct !== null ? (pct >= 0 ? '+' : '') + pct.toFixed(1) + '%' : '';
    if (t === 'up')     return `Förbättring ${sign}`;
    if (t === 'down')   return `Försämring ${sign}`;
    if (t === 'stable') return `Stabil ${sign}`;
    return 'Ingen trenddata';
  }

  bannerClass(vs: number | null): string {
    if (vs === null) return 'banner-neutral';
    if (vs >= 5)   return 'banner-positive';
    if (vs >= -5)  return 'banner-neutral';
    return 'banner-negative';
  }

  reliabilityBadge(nShifts: number): { label: string; cls: string } {
    if (nShifts >= 10) return { label: 'Hög', cls: 'rel-high' };
    if (nShifts >= 3)  return { label: 'OK', cls: 'rel-mid' };
    return { label: 'Låg', cls: 'rel-low' };
  }

  get warningOps(): { pos: string; name: string; reason: string }[] {
    if (!this.forecast) return [];
    const out: { pos: string; name: string; reason: string }[] = [];
    for (const pk of ['op1', 'op2', 'op3'] as const) {
      const op = this.forecast[pk];
      if (!op) continue;
      if (op.trend === 'down' && op.trend_pct !== null && op.trend_pct <= -10) {
        out.push({ pos: this.posLabels[pk], name: op.name, reason: `Prestanda fallit ${op.trend_pct.toFixed(1)}% senaste 30 dagarna` });
      }
      if (op.antal_skift_product < 3) {
        out.push({ pos: this.posLabels[pk], name: op.name, reason: `Bara ${op.antal_skift_product} skift med denna produkt — prognosen är osäker` });
      }
    }
    return out;
  }
}
