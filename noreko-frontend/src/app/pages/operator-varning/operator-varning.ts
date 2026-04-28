import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorVarning {
  op_number: number;
  name: string;
  baseline_ibc_h: number;
  recent_ibc_h: number;
  delta_pct: number;
  kategori: 'forsämring' | 'lätt_försämring' | 'stabil' | 'förbättring';
  baseline_skift: number;
  recent_skift: number;
  streak: number;
  streak_dir: 'over' | 'under' | 'ingen';
}

interface VarningResponse {
  success: boolean;
  operators: OperatorVarning[];
  counts: {
    forsämring: number;
    lätt_försämring: number;
    stabil: number;
    förbättring: number;
  };
  from: string;
  to: string;
  recent_from: string;
}

@Component({
  standalone: true,
  selector: 'app-operator-varning',
  imports: [CommonModule, RouterModule],
  templateUrl: './operator-varning.html',
  styleUrl: './operator-varning.css'
})
export class OperatorVarningPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  operators: OperatorVarning[] = [];
  counts = { forsämring: 0, lätt_försämring: 0, stabil: 0, förbättring: 0 };
  from = '';
  to = '';
  recentFrom = '';

  filter: 'alla' | 'forsämring' | 'lätt_försämring' | 'förbättring' = 'alla';

  Math = Math;

  get filtered(): OperatorVarning[] {
    if (this.filter === 'alla') return this.operators;
    return this.operators.filter(o => o.kategori === this.filter);
  }

  get hasAlerts(): boolean {
    return this.counts.forsämring + this.counts['lätt_försämring'] > 0;
  }

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

    const url = `${environment.apiUrl}?action=rebotling&run=operator-varning`;
    this.http.get<VarningResponse>(url, { withCredentials: true }).pipe(
      timeout(5000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta prestandavarning.';
        return;
      }
      this.operators  = res.operators;
      this.counts     = res.counts;
      this.from       = res.from;
      this.to         = res.to;
      this.recentFrom = res.recent_from;
    });
  }

  setFilter(f: typeof this.filter): void {
    this.filter = f;
  }

  kategoriLabel(k: string): string {
    if (k === 'forsämring')      return 'Försämring';
    if (k === 'lätt_försämring') return 'Lätt försämring';
    if (k === 'förbättring')     return 'Förbättring';
    return 'Stabil';
  }

  streakLabel(o: OperatorVarning): string {
    if (o.streak < 2) return '';
    const dir = o.streak_dir === 'over' ? 'över' : 'under';
    return `${o.streak} skift i rad ${dir} snitt`;
  }

  constructor(private http: HttpClient) {}
}
