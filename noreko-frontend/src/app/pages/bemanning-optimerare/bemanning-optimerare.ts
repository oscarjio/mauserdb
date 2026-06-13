import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OpStat {
  op_id: number;
  operator_namn: string;
  linje: string;
  position: number;
  position_namn: string;
  skift_count: number;
  total_ibc: number;
  avg_ibc_per_h: number;
}

interface PosSuggestion {
  op_id: number;
  namn: string;
  position_namn: string;
  avg_ibc_per_h: number;
  skift_count: number;
  confidence: 'high' | 'medium' | 'low' | 'none';
}

interface ForeslagResult {
  pos1: PosSuggestion | null;
  pos2: PosSuggestion | null;
  pos3: PosSuggestion | null;
}

interface Operator {
  op_id: number;
  name: string;
  selected: boolean;
}

@Component({
  standalone: true,
  selector: 'app-bemanning-optimerare',
  imports: [CommonModule, FormsModule],
  templateUrl: './bemanning-optimerare.html',
  styleUrl: './bemanning-optimerare.css',
})
export class BemanningOptimerarePage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  Math = Math;

  linje: 'tvattlinje' | 'rebotling' = 'rebotling';
  dagar = 30;

  loading = false;
  loadingForeslag = false;
  error = '';
  errorForeslag = '';

  stats: OpStat[] = [];
  operators: Operator[] = [];

  foreslag: ForeslagResult | null = null;
  totalEstimated: number | null = null;

  readonly linjeOptions = [
    { value: 'rebotling',  label: 'Rebotling' },
    { value: 'tvattlinje', label: 'Tvättlinje' },
  ];

  readonly posKeys: Array<keyof ForeslagResult> = ['pos1', 'pos2', 'pos3'];

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadStats();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  onLinjeChange(): void {
    this.foreslag = null;
    this.totalEstimated = null;
    this.error = '';
    this.errorForeslag = '';
    this.operators = [];
    this.stats = [];
    this.loadStats();
  }

  loadStats(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<any>(
      `${environment.apiUrl}?action=bemanning&run=operator-stats&linje=${this.linje}&dagar=${this.dagar}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) {
          this.error = 'Kunde inte ladda operatörsstatistik.';
          return;
        }
        this.stats = res.data ?? [];
        this.buildOperatorList();
      });
  }

  private buildOperatorList(): void {
    const seen = new Map<number, string>();
    for (const s of this.stats) {
      if (!seen.has(s.op_id)) {
        seen.set(s.op_id, s.operator_namn);
      }
    }
    this.operators = Array.from(seen.entries())
      .sort((a, b) => a[1].localeCompare(b[1], 'sv'))
      .map(([id, name]) => ({ op_id: id, name, selected: true }));
  }

  get selectedOpIds(): number[] {
    return this.operators.filter(o => o.selected).map(o => o.op_id);
  }

  toggleAll(val: boolean): void {
    this.operators.forEach(o => o.selected = val);
  }

  berakna(): void {
    if (this.loadingForeslag) return;
    this.loadingForeslag = true;
    this.foreslag = null;
    this.totalEstimated = null;
    this.errorForeslag = '';

    const body = {
      linje: this.linje,
      available_ops: this.selectedOpIds,
      dagar: this.dagar,
    };

    this.http.post<any>(
      `${environment.apiUrl}?action=bemanning&run=foreslag`,
      body,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingForeslag = false;
        if (!res?.success) {
          this.errorForeslag = 'Kunde inte beräkna förslag.';
          return;
        }
        this.foreslag = res.data;
        this.totalEstimated = res.total_estimated_ibc_h ?? null;
      });
  }

  confidenceLabel(c: string): string {
    if (c === 'high')   return 'Hög säkerhet';
    if (c === 'medium') return 'Medel';
    if (c === 'low')    return 'Låg';
    return 'Ingen data';
  }

  confidenceColor(c: string): string {
    if (c === 'high')   return '#68d391';
    if (c === 'medium') return '#f6ad55';
    if (c === 'low')    return '#fc8181';
    return '#718096';
  }

  confidenceDots(c: string): number[] {
    if (c === 'high')   return [1, 1, 1, 1];
    if (c === 'medium') return [1, 1, 1, 0];
    if (c === 'low')    return [1, 0, 0, 0];
    return [0, 0, 0, 0];
  }

  bestPositionFor(opId: number): string {
    let best: OpStat | null = null;
    for (const s of this.stats) {
      if (s.op_id === opId && (!best || s.avg_ibc_per_h > best.avg_ibc_per_h)) {
        best = s;
      }
    }
    return best ? best.position_namn : '-';
  }

  positionStatsFor(opId: number): OpStat[] {
    return this.stats.filter(s => s.op_id === opId).sort((a, b) => a.position - b.position);
  }

  linjeLabel(): string {
    return this.linje === 'tvattlinje' ? 'Tvättlinje' : 'Rebotling';
  }
}
