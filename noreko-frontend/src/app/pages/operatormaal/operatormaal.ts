import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorGoal {
  number: number;
  name: string;
  ibc_per_h: number;
  team_avg: number;
  vs_avg_pct: number;
  rating: string;
  antal_skift: number;
  trend_weeks: number[];
  target: number | null;
  progress: number;
  status: 'uppnatt' | 'nara' | 'i_fas' | 'bakom' | 'ingen';
}

const STORAGE_KEY = 'operatormaal_targets';

@Component({
  standalone: true,
  selector: 'app-operatormaal',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operatormaal.html',
  styleUrl: './operatormaal.css'
})
export class OperatormaalPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  Math = Math;

  days = 90;
  loading = false;
  error = '';

  operators: OperatorGoal[] = [];
  sortBy: 'progress' | 'ibch' | 'name' = 'progress';
  sortDir: 'asc' | 'desc' = 'asc';

  private targets: Record<number, number> = {};

  get filteredOperators(): OperatorGoal[] {
    const ops = [...this.operators];
    if (this.sortBy === 'progress') {
      ops.sort((a, b) => {
        const pa = a.target ? a.progress : 999;
        const pb = b.target ? b.progress : 999;
        return this.sortDir === 'asc' ? pa - pb : pb - pa;
      });
    } else if (this.sortBy === 'ibch') {
      ops.sort((a, b) => this.sortDir === 'asc' ? a.ibc_per_h - b.ibc_per_h : b.ibc_per_h - a.ibc_per_h);
    } else {
      ops.sort((a, b) => this.sortDir === 'asc'
        ? a.name.localeCompare(b.name, 'sv')
        : b.name.localeCompare(a.name, 'sv'));
    }
    return ops;
  }

  get opsWithTarget(): number { return this.operators.filter(o => o.target !== null).length; }
  get opsAboveTarget(): number { return this.operators.filter(o => o.status === 'uppnatt').length; }
  get opsOnTrack(): number { return this.operators.filter(o => o.status === 'i_fas' || o.status === 'nara').length; }
  get opsBelow(): number { return this.operators.filter(o => o.status === 'bakom').length; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadTargets();
    this.load();
  }

  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  private loadTargets(): void {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      this.targets = raw ? JSON.parse(raw) : {};
    } catch { this.targets = {}; }
  }

  private saveTargets(): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(this.targets));
  }

  load(): void {
    this.loading = true;
    this.error = '';
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - this.days);
    const fmt = (d: Date) => d.toISOString().slice(0, 10);

    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=operator-scores&from=${fmt(from)}&to=${fmt(to)}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda operatörsdata.'; return; }
        this.operators = (res.data?.operatorer ?? []).map((o: any) => this.buildGoal(o));
      });
  }

  private buildGoal(o: any): OperatorGoal {
    const target = this.targets[o.number] ?? null;
    const progress = target && target > 0 ? Math.round((o.ibc_per_h / target) * 100) : 0;
    let status: OperatorGoal['status'] = 'ingen';
    if (target !== null && target > 0) {
      if (progress >= 100) status = 'uppnatt';
      else if (progress >= 90) status = 'nara';
      else if (progress >= 70) status = 'i_fas';
      else status = 'bakom';
    }
    return { ...o, target, progress, status };
  }

  setTarget(op: OperatorGoal, value: string): void {
    const num = parseFloat(value);
    if (!isNaN(num) && num > 0) {
      this.targets[op.number] = num;
    } else {
      delete this.targets[op.number];
    }
    this.saveTargets();
    const idx = this.operators.findIndex(o => o.number === op.number);
    if (idx >= 0) {
      this.operators[idx] = this.buildGoal({ ...this.operators[idx], target: undefined });
    }
  }

  clearTarget(op: OperatorGoal): void {
    delete this.targets[op.number];
    this.saveTargets();
    const idx = this.operators.findIndex(o => o.number === op.number);
    if (idx >= 0) {
      this.operators[idx] = this.buildGoal({ ...this.operators[idx] });
    }
  }

  clearAllTargets(): void {
    this.targets = {};
    this.saveTargets();
    this.operators = this.operators.map(o => this.buildGoal(o));
  }

  toggleSort(col: typeof this.sortBy): void {
    if (this.sortBy === col) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortBy = col;
      this.sortDir = col === 'name' ? 'asc' : 'asc';
    }
  }

  statusLabel(s: OperatorGoal['status']): string {
    switch (s) {
      case 'uppnatt': return 'Uppnått';
      case 'nara':    return 'Nära';
      case 'i_fas':   return 'I fas';
      case 'bakom':   return 'Bakom';
      default:        return 'Inget mål';
    }
  }

  statusColor(s: OperatorGoal['status']): string {
    switch (s) {
      case 'uppnatt': return '#68d391';
      case 'nara':    return '#9ae6b4';
      case 'i_fas':   return '#f6ad55';
      case 'bakom':   return '#fc8181';
      default:        return '#4a5568';
    }
  }

  ratingColor(r: string): string {
    switch (r) {
      case 'Elite':           return '#f6c90e';
      case 'Solid':           return '#68d391';
      case 'Developing':      return '#f6ad55';
      case 'Needs attention': return '#fc8181';
      default:                return '#a0aec0';
    }
  }

  progressBarColor(p: number): string {
    if (p >= 100) return '#68d391';
    if (p >= 90)  return '#9ae6b4';
    if (p >= 70)  return '#f6ad55';
    return '#fc8181';
  }

  sparklinePath(vals: number[]): string {
    if (!vals || vals.length < 2) return '';
    const max = Math.max(...vals, 0.1);
    const min = Math.min(...vals);
    const range = max - min || 1;
    const w = 80, h = 24;
    const pts = vals.map((v, i) => {
      const x = (i / (vals.length - 1)) * w;
      const y = h - ((v - min) / range) * h;
      return `${x},${y}`;
    });
    return `M${pts.join('L')}`;
  }
}
