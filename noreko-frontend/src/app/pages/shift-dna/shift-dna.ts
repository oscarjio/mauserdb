import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface ShiftOperator {
  number: number;
  name: string;
  pos: string;
}

interface ShiftRow {
  skiftraknare: number;
  datum: string;
  ops: ShiftOperator[];
  product_id: number;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  totalt: number;
  ibc_per_h: number;
  vs_avg_pct: number;
  drifttid: number;
  rasttime: number;
  driftstopptime: number;
  rating: string;
  created_at: string;
  expanded?: boolean;
}

interface Operator {
  number: number;
  name: string;
}

interface ApiResponse {
  success: boolean;
  data: {
    shifts: ShiftRow[];
    total: number;
    limit: number;
    offset: number;
    team_avg: number;
    operators: Operator[];
  };
}

@Component({
  standalone: true,
  selector: 'app-shift-dna',
  imports: [CommonModule, FormsModule],
  templateUrl: './shift-dna.html',
  styleUrl: './shift-dna.css'
})
export class ShiftDnaPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  loading = false;
  loadingMore = false;
  error = '';
  shifts: ShiftRow[] = [];
  allOperators: Operator[] = [];
  teamAvg = 0;
  total = 0;
  limit = 50;
  offset = 0;

  filterOperator = 0;
  filterRating = '';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.fetchData();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  fetchData(): void {
    if (this.loading) return;
    this.offset = 0;
    this.error = '';
    this.loading = true;

    const url = this.buildUrl(this.limit, 0);
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => { this.error = 'Kunde inte hämta data'; return of(null); }),
        finalize(() => { this.loading = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) { this.error = 'Serverfel'; return; }
        this.shifts = res.data.shifts;
        this.total = res.data.total;
        this.teamAvg = res.data.team_avg;
        if (res.data.operators?.length) this.allOperators = res.data.operators;
      });
  }

  loadMore(): void {
    if (this.loadingMore) return;
    const nextOffset = this.offset + this.limit;
    this.loadingMore = true;

    const url = this.buildUrl(this.limit, nextOffset);
    this.http.get<ApiResponse>(url, { withCredentials: true })
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        finalize(() => { this.loadingMore = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res?.success) return;
        this.shifts = [...this.shifts, ...res.data.shifts];
        this.total = res.data.total;
        this.offset = nextOffset;
      });
  }

  private buildUrl(limit: number, offset: number): string {
    let url = `${environment.apiUrl}?action=rebotling&run=shift-dna&limit=${limit}&offset=${offset}`;
    if (this.filterOperator > 0) url += `&operator=${this.filterOperator}`;
    return url;
  }

  get filtered(): ShiftRow[] {
    if (!this.filterRating) return this.shifts;
    return this.shifts.filter(s => s.rating === this.filterRating);
  }

  get hasMore(): boolean {
    return this.offset + this.limit < this.total;
  }

  toggleExpand(shift: ShiftRow): void {
    shift.expanded = !shift.expanded;
  }

  ratingLabel(r: string): string {
    const map: Record<string, string> = {
      great: 'Utmärkt', good: 'Bra', avg: 'Genomsnitt', weak: 'Svag', poor: 'Låg'
    };
    return map[r] ?? r;
  }

  ratingClass(r: string): string {
    const map: Record<string, string> = {
      great: 'rating-great', good: 'rating-good', avg: 'rating-avg',
      weak: 'rating-weak', poor: 'rating-poor'
    };
    return map[r] ?? 'rating-avg';
  }

  posLabel(pos: string): string {
    const map: Record<string, string> = { op1: 'Tvätt', op2: 'Kontroll', op3: 'Truck' };
    return map[pos] ?? pos;
  }

  opColor(num: number): string {
    const colors = ['#63b3ed', '#68d391', '#f6ad55', '#b794f4', '#76e4f7', '#fbb6ce', '#9ae6b4', '#fc8181'];
    return colors[num % colors.length];
  }

  formatDuration(min: number): string {
    if (!min) return '—';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  vsClass(pct: number): string {
    if (pct >= 10) return 'text-success';
    if (pct <= -10) return 'text-danger';
    return 'text-warning';
  }

  vsArrow(pct: number): string {
    if (pct >= 5) return '↑';
    if (pct <= -5) return '↓';
    return '→';
  }

  ratingCounts(): Record<string, number> {
    const c: Record<string, number> = { great: 0, good: 0, avg: 0, weak: 0, poor: 0 };
    this.shifts.forEach(s => { if (s.rating in c) c[s.rating]++; });
    return c;
  }
}
