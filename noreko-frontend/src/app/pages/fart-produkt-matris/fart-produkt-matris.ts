import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface Operator { number: number; name: string; }
interface Product  { id: number; name: string; }
interface Cell     { op_num: number; product_id: number; ibc_per_h: number; skift_count: number; total_ibc: number; }
interface ProdAvg  { product_id: number; team_avg_ibc_h: number; }

interface MatrisResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  operators: Operator[];
  products: Product[];
  cells: Cell[];
  prod_team_avg: ProdAvg[];
  team_avg_overall: number;
}

@Component({
  standalone: true,
  selector: 'app-fart-produkt-matris',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './fart-produkt-matris.html',
  styleUrl: './fart-produkt-matris.css'
})
export class FartProduktMatrisPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  Math = Math;

  days = 90;
  loading = false;
  error = '';

  operators: Operator[] = [];
  products: Product[]   = [];
  cells: Cell[]         = [];
  prodTeamAvg: Record<number, number> = {};
  teamAvgOverall = 0;

  from = '';
  to   = '';

  sortBy: 'name' | 'overall' = 'overall';

  get sortedOperators(): Operator[] {
    if (this.sortBy === 'name') {
      return [...this.operators].sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    }
    return [...this.operators].sort((a, b) => this.opOverallIbch(b.number) - this.opOverallIbch(a.number));
  }

  opOverallIbch(opNum: number): number {
    const opCells = this.cells.filter(c => c.op_num === opNum);
    if (opCells.length === 0) return 0;
    const totalIbc = opCells.reduce((s, c) => s + c.total_ibc, 0);
    const totalMin = opCells.reduce((s, c) => {
      const avg = this.prodTeamAvg[c.product_id] ?? this.teamAvgOverall;
      return s + (avg > 0 ? (c.total_ibc / avg) * 60 : 0);
    }, 0);
    return totalMin > 0 ? Math.round((totalIbc / (totalMin / 60)) * 10) / 10 : 0;
  }

  cell(opNum: number, prodId: number): Cell | undefined {
    return this.cells.find(c => c.op_num === opNum && c.product_id === prodId);
  }

  vsTeam(ibch: number, prodId: number): number {
    const avg = this.prodTeamAvg[prodId] ?? 0;
    return avg > 0 ? Math.round((ibch / avg - 1) * 100) : 0;
  }

  cellBg(ibch: number, prodId: number): string {
    const vs = this.vsTeam(ibch, prodId);
    if (vs >= 20) return 'rgba(72,187,120,0.55)';
    if (vs >= 10) return 'rgba(72,187,120,0.35)';
    if (vs >= 0)  return 'rgba(72,187,120,0.15)';
    if (vs >= -10) return 'rgba(252,129,129,0.2)';
    if (vs >= -20) return 'rgba(252,129,129,0.38)';
    return 'rgba(252,129,129,0.55)';
  }

  cellColor(ibch: number, prodId: number): string {
    const vs = this.vsTeam(ibch, prodId);
    if (vs >= 10)  return '#68d391';
    if (vs >= 0)   return '#9ae6b4';
    if (vs >= -10) return '#fbd38d';
    return '#fc8181';
  }

  get bestCells(): { opName: string; prodName: string; ibch: number; vs: number }[] {
    return this.cells
      .map(c => ({
        opName: this.operators.find(o => o.number === c.op_num)?.name ?? '',
        prodName: this.products.find(p => p.id === c.product_id)?.name ?? '',
        ibch: c.ibc_per_h,
        vs: this.vsTeam(c.ibc_per_h, c.product_id),
      }))
      .sort((a, b) => b.vs - a.vs)
      .slice(0, 5);
  }

  get worstCells(): { opName: string; prodName: string; ibch: number; vs: number }[] {
    return this.cells
      .map(c => ({
        opName: this.operators.find(o => o.number === c.op_num)?.name ?? '',
        prodName: this.products.find(p => p.id === c.product_id)?.name ?? '',
        ibch: c.ibc_per_h,
        vs: this.vsTeam(c.ibc_per_h, c.product_id),
      }))
      .sort((a, b) => a.vs - b.vs)
      .slice(0, 5);
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error   = '';

    const url = `${environment.apiUrl}?action=rebotling&run=fart-produkt-matris&days=${this.days}`;
    this.http.get<MatrisResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading    = false;
      if (!res?.success) { this.error = 'Kunde inte hämta matrisdata.'; return; }

      this.operators     = res.operators;
      this.products      = res.products;
      this.cells         = res.cells;
      this.teamAvgOverall = res.team_avg_overall;
      this.from          = res.from;
      this.to            = res.to;

      const avgMap: Record<number, number> = {};
      for (const pa of res.prod_team_avg) { avgMap[pa.product_id] = pa.team_avg_ibc_h; }
      this.prodTeamAvg = avgMap;
    });
  }
}
