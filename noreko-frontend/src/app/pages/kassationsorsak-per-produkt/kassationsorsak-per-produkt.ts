import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface CauseData {
  orsak_id: number;
  namn: string;
  antal: number;
  pct: number;
}

interface ProductCauses {
  product_id: number;
  name: string;
  total_kassation: number;
  top_cause: string;
  causes: CauseData[];
}

interface CauseMeta {
  id: number;
  namn: string;
}

interface KassationsorsakPerProduktResponse {
  success: boolean;
  products: ProductCauses[];
  all_causes: CauseMeta[];
  total_events: number;
  from: string;
  to: string;
}

@Component({
  standalone: true,
  selector: 'app-kassationsorsak-per-produkt',
  imports: [CommonModule, FormsModule],
  templateUrl: './kassationsorsak-per-produkt.html',
  styleUrl: './kassationsorsak-per-produkt.css'
})
export class KassationsorsakPerProduktPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  products: ProductCauses[] = [];
  allCauses: CauseMeta[] = [];
  totalEvents = 0;
  from = '';
  to = '';

  days = 90;
  sortBy: 'total' | 'name' | 'top_cause' = 'total';
  expandedProduct: number | null = null;

  Math = Math;

  readonly CAUSE_COLORS = [
    '#fc8181', '#f6ad55', '#faf089', '#68d391',
    '#63b3ed', '#a78bfa', '#f687b3', '#4fd1c5',
    '#ed8936', '#9ae6b4', '#76e4f7', '#b794f4',
  ];

  get sorted(): ProductCauses[] {
    const list = [...this.products];
    if (this.sortBy === 'name') return list.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    if (this.sortBy === 'top_cause') return list.sort((a, b) => a.top_cause.localeCompare(b.top_cause, 'sv'));
    return list.sort((a, b) => b.total_kassation - a.total_kassation);
  }

  get topCause(): string {
    if (!this.allCauses.length || !this.products.length) return '–';
    const counts: Record<string, number> = {};
    for (const prod of this.products) {
      for (const c of prod.causes) {
        counts[c.namn] = (counts[c.namn] ?? 0) + c.antal;
      }
    }
    return Object.entries(counts).sort((a, b) => b[1] - a[1])[0]?.[0] ?? '–';
  }

  get mostProblematicProduct(): ProductCauses | null {
    if (!this.products.length) return null;
    return this.products.reduce((a, b) => b.total_kassation > a.total_kassation ? b : a);
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    this.expandedProduct = null;

    const url = `${environment.apiUrl}?action=rebotling&run=kassationsorsak-per-produkt&days=${this.days}`;
    this.http.get<KassationsorsakPerProduktResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta kassationsorsaksdata.';
        return;
      }
      this.products     = res.products;
      this.allCauses    = res.all_causes;
      this.totalEvents  = res.total_events;
      this.from         = res.from;
      this.to           = res.to;
    });
  }

  toggleExpand(productId: number): void {
    this.expandedProduct = this.expandedProduct === productId ? null : productId;
  }

  causeColor(idx: number): string {
    return this.CAUSE_COLORS[idx % this.CAUSE_COLORS.length];
  }

  causeColorByName(namn: string): string {
    const idx = this.allCauses.findIndex(c => c.namn === namn);
    return this.causeColor(idx >= 0 ? idx : 0);
  }

  barWidth(antal: number, total: number): number {
    return total > 0 ? Math.round((antal / total) * 100) : 0;
  }

  dominantCauseLabel(product: ProductCauses): string {
    if (!product.causes.length) return '';
    const top = product.causes[0];
    return `${top.namn} (${top.pct}%)`;
  }
}
