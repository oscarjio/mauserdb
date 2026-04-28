import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface ProductCell {
  ibc_per_h: number;
  antal_skift: number;
  vs_team: number;
}

interface OperatorRow {
  op_number: number;
  name: string;
  products: { [prodId: number]: ProductCell | null };
}

interface Product {
  id: number;
  name: string;
  team_avg: number;
}

interface BestEntry {
  op_number: number;
  name: string;
  ibc_per_h: number;
}

interface ApiResponse {
  success: boolean;
  operators: OperatorRow[];
  products: Product[];
  best_per_product: { [prodId: number]: BestEntry | null };
  from: string;
  to: string;
  days: number;
  error?: string;
}

@Component({
  selector: 'app-operator-produkt',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-produkt.html',
  styleUrls: ['./operator-produkt.css'],
})
export class OperatorProduktPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  days = 90;
  isFetching = false;

  operators: OperatorRow[] = [];
  products: Product[] = [];
  bestPerProduct: { [prodId: number]: BestEntry | null } = {};
  fromDate = '';
  toDate = '';

  sortBy: 'name' | 'best' = 'name';
  sortedOperators: OperatorRow[] = [];

  error = '';

  constructor(private http: HttpClient) {}

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
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=operator-produkt&days=${this.days}`;
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of({ success: false, error: 'Kunde inte hämta data' } as any)),
        takeUntil(this.destroy$),
      )
      .subscribe(res => {
        this.isFetching = false;
        if (!res.success) {
          this.error = res.error ?? 'Okänt fel';
          return;
        }
        this.operators = res.operators;
        this.products  = res.products;
        this.bestPerProduct = res.best_per_product;
        this.fromDate  = res.from;
        this.toDate    = res.to;
        this.applySort();
      });
  }

  applySort(): void {
    if (this.sortBy === 'name') {
      this.sortedOperators = [...this.operators].sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    } else {
      this.sortedOperators = [...this.operators].sort((a, b) => {
        const bestA = this.bestVsTeam(a);
        const bestB = this.bestVsTeam(b);
        return bestB - bestA;
      });
    }
  }

  private bestVsTeam(op: OperatorRow): number {
    let best = -Infinity;
    for (const cell of Object.values(op.products)) {
      if (cell && cell.vs_team > best) best = cell.vs_team;
    }
    return best === -Infinity ? -999 : best;
  }

  onSortChange(): void {
    this.applySort();
  }

  onDaysChange(): void {
    this.load();
  }

  cellColor(cell: ProductCell | null): string {
    if (!cell) return 'cell-none';
    if (cell.vs_team >= 15)  return 'cell-elite';
    if (cell.vs_team >= 0)   return 'cell-solid';
    if (cell.vs_team >= -15) return 'cell-developing';
    return 'cell-low';
  }

  cellLabel(cell: ProductCell | null): string {
    if (!cell) return '–';
    const sign = cell.vs_team >= 0 ? '+' : '';
    return `${cell.ibc_per_h.toFixed(1)} (${sign}${cell.vs_team.toFixed(0)}%)`;
  }

  isBestForProduct(op: OperatorRow, prodId: number): boolean {
    const best = this.bestPerProduct[prodId];
    if (!best || best.op_number !== op.op_number) return false;
    const cell = op.products[prodId];
    return !!cell && cell.vs_team >= 0;
  }
}
