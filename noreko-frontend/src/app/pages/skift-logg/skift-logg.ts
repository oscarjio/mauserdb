import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface ShiftRow {
  skiftraknare: number;
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  drifttid: number;
  driftstopptime: number;
  product_id: number | null;
  product_name: string;
  ibc_per_h: number;
  kassation_pct: number;
  stopp_pct: number;
  op1: number | null;
  op2: number | null;
  op3: number | null;
  op1_name: string;
  op2_name: string;
  op3_name: string;
}

interface Kpi {
  total_shifts: number;
  total_ibc: number;
  avg_ibch: number;
  avg_kass_pct: number;
  avg_stopp_pct: number;
}

interface OpOption { number: number; name: string; }
interface ProdOption { id: number; name: string; }

interface ApiResponse {
  success: boolean;
  from: string;
  to: string;
  shifts: ShiftRow[];
  kpi: Kpi | null;
  operators: OpOption[];
  products: ProdOption[];
}

@Component({
  standalone: true,
  selector: 'app-skift-logg',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './skift-logg.html',
  styleUrl: './skift-logg.css',
})
export class SkiftLoggPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  from = '';
  to = '';
  filterOp = 0;
  filterProduct = 0;
  days = 90;

  sort = 'datum';
  dir: 'asc' | 'desc' = 'desc';

  shifts: ShiftRow[] = [];
  kpi: Kpi | null = null;
  operators: OpOption[] = [];
  products: ProdOption[] = [];

  Math = Math;

  readonly daysOptions = [
    { value: 30,  label: '30 dagar' },
    { value: 60,  label: '60 dagar' },
    { value: 90,  label: '90 dagar' },
    { value: 180, label: '6 månader' },
    { value: 365, label: '1 år' },
    { value: 730, label: '2 år' },
  ];

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.applyPreset(90);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  applyPreset(d: number): void {
    this.days = d;
    const today = new Date();
    const past  = new Date(today);
    past.setDate(today.getDate() - d);
    this.to   = today.toISOString().slice(0, 10);
    this.from = past.toISOString().slice(0, 10);
    this.fetchData();
  }

  fetchData(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const params: Record<string, string> = {
      action: 'rebotling',
      run: 'skift-logg',
      from: this.from,
      to: this.to,
      sort: this.sort,
      dir: this.dir,
    };
    if (this.filterOp > 0)      params['op']      = String(this.filterOp);
    if (this.filterProduct > 0) params['product']  = String(this.filterProduct);

    const qs = new URLSearchParams(params).toString();
    const url = `${environment.apiUrl}?${qs}`;

    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$),
      )
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res || !res.success) {
          this.error = 'Kunde inte hämta skiftlogg.';
          return;
        }
        this.shifts   = res.shifts;
        this.kpi      = res.kpi;
        if (res.operators.length) this.operators = res.operators;
        if (res.products.length)  this.products  = res.products;
      });
  }

  sortBy(col: string): void {
    if (this.sort === col) {
      this.dir = this.dir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sort = col;
      this.dir  = col === 'datum' ? 'desc' : 'desc';
    }
    this.fetchData();
  }

  sortIcon(col: string): string {
    if (this.sort !== col) return 'fas fa-sort';
    return this.dir === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }

  exportCsv(): void {
    const header = ['Skiftnr', 'Datum', 'Op1', 'Op2', 'Op3', 'Produkt', 'IBC ok', 'IBC ej ok', 'IBC/h', 'Kassation%', 'Stoppgrad%', 'Drifttid (min)'];
    const rows = this.shifts.map(s => [
      s.skiftraknare, s.datum,
      s.op1_name, s.op2_name, s.op3_name,
      s.product_name, s.ibc_ok, s.ibc_ej_ok,
      s.ibc_per_h, s.kassation_pct, s.stopp_pct, s.drifttid,
    ]);
    const csv = [header, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    const link  = document.createElement('a');
    link.href   = URL.createObjectURL(blob);
    link.download = `skiftlogg_${this.from}_${this.to}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  resetFilters(): void {
    this.filterOp      = 0;
    this.filterProduct = 0;
    this.fetchData();
  }
}
