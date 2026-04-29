import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface RytmCell {
  dag_namn: string;
  dow: number;
  ibc_per_h: number | null;
  tot_ibc: number;
  antal_skift: number;
  vs_avg_pct: number | null;
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

interface KpiItem {
  label: string;
  ibc_per_h: number;
  antal_skift: number;
}

interface RytmResponse {
  success: boolean;
  days: number;
  from: string;
  to: string;
  period_avg: number;
  grid: RytmRow[];
  col_avgs: ColAvg[];
  best: KpiItem | null;
  worst: KpiItem | null;
}

@Component({
  standalone: true,
  selector: 'app-produktionsrytm',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './produktionsrytm.html',
  styleUrl: './produktionsrytm.css',
})
export class ProduktionsrytmPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  Math = Math;

  days = 90;
  loading = false;
  error = '';

  periodAvg = 0;
  grid: RytmRow[] = [];
  colAvgs: ColAvg[] = [];
  best: KpiItem | null = null;
  worst: KpiItem | null = null;
  from = '';
  to = '';

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=produktionsrytm&days=${this.days}`;
    this.http.get<RytmResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta produktionsrytm.';
        return;
      }
      this.periodAvg = res.period_avg;
      this.grid      = res.grid;
      this.colAvgs   = res.col_avgs;
      this.best      = res.best;
      this.worst     = res.worst;
      this.from      = res.from;
      this.to        = res.to;
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  /** Returns a CSS background color for the heatmap cell based on vs_avg_pct */
  cellBg(vs: number | null): string {
    if (vs === null) return 'transparent';
    // ±20% maps to full green/red; clamp to ±30 for very extreme cells
    const clamped = Math.max(-30, Math.min(30, vs));
    if (clamped >= 0) {
      // green intensity: 0% = neutral, +20%+ = full green
      const t = Math.min(1, clamped / 20);
      const g = Math.round(52 + t * (211 - 52));   // 52→211
      const r = Math.round(26 + t * (56 - 26));
      const b = Math.round(26 + t * (56 - 26));
      return `rgb(${r},${g},${b})`;
    } else {
      // red intensity
      const t = Math.min(1, -clamped / 20);
      const r = Math.round(45 + t * (252 - 45));
      const g = Math.round(55 + t * (129 - 55));
      const b = Math.round(72 + t * (74 - 72));
      return `rgb(${r},${g},${b})`;
    }
  }

  cellTextColor(vs: number | null): string {
    if (vs === null) return '#718096';
    return Math.abs(vs) > 8 ? '#fff' : '#e2e8f0';
  }

  vsLabel(vs: number | null): string {
    if (vs === null) return '';
    const sign = vs >= 0 ? '+' : '';
    return `${sign}${vs.toFixed(1)}%`;
  }

  vsClass(vs: number | null): string {
    if (vs === null) return '';
    if (vs >= 5)  return 'vs-green';
    if (vs <= -5) return 'vs-red';
    return 'vs-neutral';
  }

  colAvgVs(avg: number): number | null {
    if (!this.periodAvg) return null;
    return parseFloat(((avg / this.periodAvg - 1) * 100).toFixed(1));
  }
}
