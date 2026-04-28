import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OpItem  { number: number; name: string; }
interface ProdItem { id: number; name: string; }

interface CellData {
  kassation_pct: number;
  skift_count:   number;
  total_ibc:     number;
  total_ibc_ej:  number;
}

interface KartaResponse {
  success:        boolean;
  from:           string;
  to:             string;
  days:           number;
  operators:      OpItem[];
  products:       ProdItem[];
  cells:          { op_num: number; product_id: number; kassation_pct: number; skift_count: number; total_ibc: number; total_ibc_ej: number; }[];
  team_kassgrad:  number;
}

@Component({
  standalone: true,
  selector: 'app-kassations-karta',
  imports: [CommonModule, RouterModule],
  templateUrl: './kassations-karta.html',
  styleUrl: './kassations-karta.css'
})
export class KassationsKartaPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error   = '';
  days    = 90;

  from         = '';
  to           = '';
  operators:   OpItem[]  = [];
  products:    ProdItem[] = [];
  teamKassgrad = 0;

  // matrix[opNum][productId] = CellData
  matrix: Record<number, Record<number, CellData>> = {};

  Math = Math;

  constructor(private http: HttpClient) {}

  ngOnInit():    void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading    = true;
    this.error      = '';

    const url = `${environment.apiUrl}?action=rebotling&run=kassations-karta&days=${this.days}`;
    this.http.get<KartaResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading    = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta kassationsdata.';
        return;
      }
      this.from         = res.from;
      this.to           = res.to;
      this.operators    = res.operators;
      this.products     = res.products;
      this.teamKassgrad = res.team_kassgrad;
      this.matrix       = {};
      for (const c of res.cells) {
        if (!this.matrix[c.op_num]) this.matrix[c.op_num] = {};
        this.matrix[c.op_num][c.product_id] = {
          kassation_pct: c.kassation_pct,
          skift_count:   c.skift_count,
          total_ibc:     c.total_ibc,
          total_ibc_ej:  c.total_ibc_ej,
        };
      }
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  cell(opNum: number, prodId: number): CellData | null {
    return this.matrix[opNum]?.[prodId] ?? null;
  }

  cellColor(kass: number): string {
    const t = this.teamKassgrad;
    const ref = t > 0 ? t : 5;
    if (kass <= ref * 0.5)      return '#22543d'; // deep green
    if (kass <= ref * 0.85)     return '#276749';
    if (kass <= ref * 1.0)      return '#2f855a';
    if (kass <= ref * 1.3)      return '#b7791f'; // yellow-orange
    if (kass <= ref * 1.7)      return '#c05621';
    return '#9b2c2c';                              // deep red
  }

  cellTextColor(kass: number): string {
    const t = this.teamKassgrad;
    const ref = t > 0 ? t : 5;
    if (kass <= ref * 1.0) return '#9ae6b4';
    if (kass <= ref * 1.3) return '#fbd38d';
    return '#fc8181';
  }

  opRowTotal(opNum: number): { kassation_pct: number; skift_count: number } | null {
    const row = this.matrix[opNum];
    if (!row) return null;
    let sumEj = 0, sumTotal = 0, sumSkift = 0;
    for (const cell of Object.values(row)) {
      sumEj    += cell.total_ibc_ej;
      sumTotal += cell.total_ibc;
      sumSkift += cell.skift_count;
    }
    if (sumTotal === 0) return null;
    return { kassation_pct: Math.round(sumEj / sumTotal * 1000) / 10, skift_count: sumSkift };
  }

  prodColTotal(prodId: number): { kassation_pct: number; skift_count: number } | null {
    let sumEj = 0, sumTotal = 0, sumSkift = 0;
    for (const opNum of this.operators.map(o => o.number)) {
      const c = this.cell(opNum, prodId);
      if (c) { sumEj += c.total_ibc_ej; sumTotal += c.total_ibc; sumSkift += c.skift_count; }
    }
    if (sumTotal === 0) return null;
    return { kassation_pct: Math.round(sumEj / sumTotal * 1000) / 10, skift_count: sumSkift };
  }

  get worstCell(): { opName: string; prodName: string; kassation_pct: number } | null {
    let worst: { opName: string; prodName: string; kassation_pct: number } | null = null;
    for (const op of this.operators) {
      for (const prod of this.products) {
        const c = this.cell(op.number, prod.id);
        if (c && c.skift_count >= 3 && (!worst || c.kassation_pct > worst.kassation_pct)) {
          worst = { opName: op.name, prodName: prod.name, kassation_pct: c.kassation_pct };
        }
      }
    }
    return worst;
  }

  get bestCell(): { opName: string; prodName: string; kassation_pct: number } | null {
    let best: { opName: string; prodName: string; kassation_pct: number } | null = null;
    for (const op of this.operators) {
      for (const prod of this.products) {
        const c = this.cell(op.number, prod.id);
        if (c && c.skift_count >= 3 && (!best || c.kassation_pct < best.kassation_pct)) {
          best = { opName: op.name, prodName: prod.name, kassation_pct: c.kassation_pct };
        }
      }
    }
    return best;
  }

  get totalCells(): number {
    return Object.values(this.matrix).reduce((a, row) => a + Object.keys(row).length, 0);
  }
}
