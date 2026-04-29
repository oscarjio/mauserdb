import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorRotation {
  number: number;
  name: string;
  total_skift: number;
  rotation_skift: number;
  spec_skift: number;
  rotation_pct: number;
  rot_ibch: number | null;
  spec_ibch: number | null;
  overall_ibch: number;
  delta_ibch: number | null;
  op1_skift: number;
  op2_skift: number;
  op3_skift: number;
}

interface Kpi {
  antal_op: number;
  total_skift: number;
  rotation_rate: number;
  avg_delta: number | null;
}

interface ApiResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  operators: OperatorRotation[];
  kpi: Kpi;
}

type SortKey = 'delta' | 'total' | 'name' | 'rotation_pct';

@Component({
  standalone: true,
  selector: 'app-operator-rotation',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-rotation.html',
  styleUrl: './operator-rotation.css',
})
export class OperatorRotationPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  days = 90;
  readonly dayOptions = [30, 60, 90, 180, 365];

  loading = false;
  error = '';

  operators: OperatorRotation[] = [];
  kpi: Kpi | null = null;
  from = '';
  to = '';

  sortKey: SortKey = 'delta';
  filterRotation: 'all' | 'benefits' | 'hurts' = 'all';

  readonly posLabels: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontroll',
    op3: 'Truck',
  };

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
    this.loading = true;
    this.error = '';

    this.http
      .get<ApiResponse>(
        `${environment.apiUrl}?action=rebotling&run=operator-rotation&days=${this.days}`,
        { withCredentials: true }
      )
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res || !res.success) {
          this.error = 'Kunde inte hämta rotationsdata.';
          return;
        }
        this.operators = res.operators;
        this.kpi = res.kpi;
        this.from = res.from;
        this.to = res.to;
      });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  get filtered(): OperatorRotation[] {
    let list = [...this.operators];
    if (this.filterRotation === 'benefits') {
      list = list.filter(o => (o.delta_ibch ?? 0) > 0);
    } else if (this.filterRotation === 'hurts') {
      list = list.filter(o => (o.delta_ibch ?? 0) < 0);
    }
    return list.sort((a, b) => {
      switch (this.sortKey) {
        case 'delta': {
          const ad = a.delta_ibch ?? 0;
          const bd = b.delta_ibch ?? 0;
          return Math.abs(bd) - Math.abs(ad);
        }
        case 'total': return b.total_skift - a.total_skift;
        case 'name':  return a.name.localeCompare(b.name, 'sv');
        case 'rotation_pct': return b.rotation_pct - a.rotation_pct;
        default: return 0;
      }
    });
  }

  get verdiktText(): string {
    if (!this.kpi || this.kpi.avg_delta === null) return '';
    const d = this.kpi.avg_delta;
    if (d > 0.5) return 'Rotation gynnar teamet';
    if (d < -0.5) return 'Specialisering gynnar teamet';
    return 'Rotation är neutral för teamet';
  }

  get verdiktClass(): string {
    if (!this.kpi || this.kpi.avg_delta === null) return '';
    const d = this.kpi.avg_delta;
    if (d > 0.5) return 'verdict-positive';
    if (d < -0.5) return 'verdict-negative';
    return 'verdict-neutral';
  }

  get benefitsCount(): number {
    return this.operators.filter(o => (o.delta_ibch ?? 0) > 0.3).length;
  }

  get hurtsCount(): number {
    return this.operators.filter(o => (o.delta_ibch ?? 0) < -0.3).length;
  }

  posBarWidth(op: OperatorRotation, pos: 'op1' | 'op2' | 'op3'): number {
    if (op.total_skift === 0) return 0;
    return Math.round((op[`${pos}_skift`] / op.total_skift) * 100);
  }

  favoritePos(op: OperatorRotation): string {
    const counts = [
      { pos: 'Tvättplats', count: op.op1_skift },
      { pos: 'Kontroll', count: op.op2_skift },
      { pos: 'Truck', count: op.op3_skift },
    ];
    counts.sort((a, b) => b.count - a.count);
    return counts[0].count > 0 ? counts[0].pos : '—';
  }

  deltaSign(delta: number | null): string {
    if (delta === null) return '';
    if (delta > 0) return '+';
    return '';
  }
}
