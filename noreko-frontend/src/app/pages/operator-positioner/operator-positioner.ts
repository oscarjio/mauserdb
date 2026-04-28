import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PosData {
  ibc_per_h: number;
  antal_skift: number;
  vs_avg_pct: number;
  team_avg: number;
}

interface OperatorRaw {
  number: number;
  name: string;
  ibc_per_h: number;
  antal_skift: number;
  rating: string;
  per_position: { op1?: PosData; op2?: PosData; op3?: PosData };
}

interface PosSpecialty {
  pos: 'op1' | 'op2' | 'op3';
  label: string;
  ibch: number;
  skift: number;
  index: number;
}

interface OperatorSpecialty {
  number: number;
  name: string;
  overall_ibch: number;
  antal_skift: number;
  rating: string;
  positions: PosSpecialty[];
  favoritPos: string;
  spread: number;
  typ: 'Specialist' | 'Allroundare' | 'Otillräcklig data';
}

@Component({
  standalone: true,
  selector: 'app-operator-positioner',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-positioner.html',
  styleUrl: './operator-positioner.css'
})
export class OperatorPositionerPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  Math = Math;

  days = 90;
  loading = false;
  error = '';

  operators: OperatorSpecialty[] = [];
  activeTab: 'alla' | 'op1' | 'op2' | 'op3' = 'alla';
  sortBy: 'spread' | 'overall' | 'name' = 'spread';

  readonly POS_LABELS: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };

  get filteredOperators(): OperatorSpecialty[] {
    let ops = this.operators;
    if (this.activeTab !== 'alla') {
      ops = ops.filter(o => o.positions.some(p => p.pos === this.activeTab));
    }
    if (this.sortBy === 'spread') return [...ops].sort((a, b) => b.spread - a.spread);
    if (this.sortBy === 'overall') return [...ops].sort((a, b) => b.overall_ibch - a.overall_ibch);
    return [...ops].sort((a, b) => a.name.localeCompare(b.name, 'sv'));
  }

  get specialists(): number { return this.operators.filter(o => o.typ === 'Specialist').length; }
  get allroundare(): number { return this.operators.filter(o => o.typ === 'Allroundare').length; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

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
      .pipe(timeout(10000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda data.'; return; }
        this.operators = this.buildSpecialty(res.data?.operatorer ?? []);
      });
  }

  private buildSpecialty(raw: OperatorRaw[]): OperatorSpecialty[] {
    return raw
      .filter(o => o.antal_skift >= 3 && o.ibc_per_h > 0)
      .map(o => {
        const positions: PosSpecialty[] = (['op1', 'op2', 'op3'] as const)
          .filter(p => {
            const pd = o.per_position?.[p];
            return pd && pd.antal_skift >= 2 && pd.ibc_per_h > 0;
          })
          .map(p => {
            const pd = o.per_position[p]!;
            const index = Math.round((pd.ibc_per_h / o.ibc_per_h - 1) * 100);
            return { pos: p, label: this.POS_LABELS[p], ibch: pd.ibc_per_h, skift: pd.antal_skift, index };
          });

        const indices = positions.map(p => p.index);
        const spread = indices.length >= 2 ? Math.max(...indices) - Math.min(...indices) : 0;
        const favoritPos = positions.length > 0
          ? positions.reduce((a, b) => a.index > b.index ? a : b).label
          : '-';

        let typ: OperatorSpecialty['typ'] = 'Otillräcklig data';
        if (positions.length >= 2) {
          typ = spread >= 15 ? 'Specialist' : 'Allroundare';
        }

        return { number: o.number, name: o.name, overall_ibch: o.ibc_per_h, antal_skift: o.antal_skift, rating: o.rating, positions, favoritPos, spread, typ };
      })
      .filter(o => o.positions.length >= 1);
  }

  indexColor(index: number): string {
    if (index >= 15) return '#68d391';
    if (index >= 5) return '#9ae6b4';
    if (index >= -5) return '#a0aec0';
    if (index >= -15) return '#fbd38d';
    return '#fc8181';
  }

  typColor(typ: string): string {
    if (typ === 'Specialist') return '#f6ad55';
    if (typ === 'Allroundare') return '#68d391';
    return '#a0aec0';
  }

  posTabCount(pos: 'op1' | 'op2' | 'op3'): number {
    return this.operators.filter(o => o.positions.some(p => p.pos === pos)).length;
  }
}
