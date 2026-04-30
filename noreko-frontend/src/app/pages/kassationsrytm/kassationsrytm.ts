import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface RytmCell {
  dag_namn: string;
  dow: number;
  kass_pct: number | null;
  bur_pct: number | null;
  tot_ibc_ok: number;
  tot_ibc_ej: number;
  tot_bur_ej: number;
  antal_skift: number;
  vs_avg_pp: number | null;
}

interface RytmRow {
  skift_typ: 'dag' | 'kvall' | 'natt';
  label: string;
  cells: RytmCell[];
  row_avg: number;
}

interface ColAvg {
  dag_namn: string;
  avg: number;
}

interface ApiResponse {
  success: boolean;
  days: number;
  from: string;
  to: string;
  period_avg: number;
  period_bur_avg: number;
  grid: RytmRow[];
  col_avgs: ColAvg[];
  best: { label: string; kass_pct: number; antal_skift: number } | null;
  worst: { label: string; kass_pct: number; antal_skift: number } | null;
  totals: { ibc_ok: number; ibc_ej: number; bur_ej: number };
}

@Component({
  standalone: true,
  selector: 'app-kassationsrytm',
  imports: [CommonModule, FormsModule],
  templateUrl: './kassationsrytm.html',
  styleUrl: './kassationsrytm.css',
})
export class KassationsrytmPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  days = 90;
  dayOptions = [90, 180, 365, 730];
  showBur = false;

  from = '';
  to = '';
  periodAvg = 0;
  periodBurAvg = 0;
  grid: RytmRow[] = [];
  colAvgs: ColAvg[] = [];
  best: ApiResponse['best'] = null;
  worst: ApiResponse['worst'] = null;
  totals = { ibc_ok: 0, ibc_ej: 0, bur_ej: 0 };

  ngOnInit(): void {
    this.load();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    const url = `${environment.apiUrl}?action=rebotling&run=kassationsrytm&days=${this.days}`;
    this.http.get<ApiResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) { this.error = 'Kunde inte hämta kassationsrytm.'; return; }
      this.from = res.from;
      this.to = res.to;
      this.periodAvg = res.period_avg;
      this.periodBurAvg = res.period_bur_avg;
      this.grid = res.grid;
      this.colAvgs = res.col_avgs;
      this.best = res.best;
      this.worst = res.worst;
      this.totals = res.totals;
    });
  }

  // Returns HSL color for a kassation% value relative to the period average.
  // Cells at period_avg get yellow; lower = greener, higher = redder.
  // Max color shift at ±5 pp from avg (or ±100% of avg, whichever is smaller at low avg values).
  cellColor(kassP: number | null): string {
    if (kassP === null) return '#2d3748';
    const avg = this.showBur ? this.periodBurAvg : this.periodAvg;
    if (avg <= 0) return '#4a5568';
    const diff = kassP - avg;
    const maxDiff = Math.max(avg * 0.5, 3); // 50% of avg or 3 pp, whichever is larger
    const ratio = Math.max(-1, Math.min(1, diff / maxDiff)); // -1 = much better, +1 = much worse
    // green (120°) to yellow (60°) to red (0°)
    const hue = 120 - ratio * 120;
    const sat = 60 + Math.abs(ratio) * 20;
    const light = 30 + Math.abs(ratio) * 8;
    return `hsl(${hue}, ${sat}%, ${light}%)`;
  }

  activeKass(cell: RytmCell): number | null {
    return this.showBur ? cell.bur_pct : cell.kass_pct;
  }

  activeAvg(): number {
    return this.showBur ? this.periodBurAvg : this.periodAvg;
  }

  activeRowAvg(row: RytmRow): number {
    if (!this.showBur) return row.row_avg;
    // Bur row avg: SUM(bur_ej) / SUM(ok+ej+bur) across all weekdays in this shift type
    const totOk = row.cells.reduce((s, c) => s + c.tot_ibc_ok, 0);
    const totEj = row.cells.reduce((s, c) => s + c.tot_ibc_ej, 0);
    const totBur = row.cells.reduce((s, c) => s + c.tot_bur_ej, 0);
    const denom = totOk + totEj + totBur;
    return denom > 0 ? +(totBur / denom * 100).toFixed(2) : 0;
  }

  constructor(private http: HttpClient) {}
}
