import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

const API = `${environment.apiUrl}?action=rebotling&run=stjarnoperatorer`;

interface Operator {
  op_num: number;
  name: string;
  period_ibc_h: number;
  recent_ibc_h: number | null;
  vs_team_period: number;
  vs_team_recent: number;
  trend_pct: number;
  trend_label: 'foerbattras' | 'forsamras' | 'stabil';
  kassation_pct: number;
  team_kass_pct: number;
  p_shifts: number;
  r_shifts: number;
  pos1: number;
  pos2: number;
  pos3: number;
  pos_used: number;
  score: number;
  level: 'stjarna' | 'nyckel' | 'solid' | 'potential' | 'stod';
  strengths: string[];
  speed_score: number;
  trend_score: number;
  vers_score: number;
  exp_score: number;
  kass_score: number;
}

interface ApiResponse {
  success: boolean;
  period: number;
  from_date: string;
  to_date: string;
  team_ibc_h: number;
  team_kass_pct: number;
  counts: Record<string, number>;
  improving: number;
  operators: Operator[];
}

type SortKey = 'score' | 'ibc_h' | 'trend' | 'name';

@Component({
  standalone: true,
  selector: 'app-stjarnoperatorer',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './stjarnoperatorer.html',
  styleUrl: './stjarnoperatorer.css',
})
export class StjarnoperatororPage implements OnInit, OnDestroy {
  Math = Math;

  private destroy$ = new Subject<void>();
  isFetching = false;

  period = 90;
  readonly periodOptions = [
    { val: 30,  label: '30 dagar' },
    { val: 60,  label: '60 dagar' },
    { val: 90,  label: '90 dagar' },
    { val: 180, label: '6 månader' },
    { val: 365, label: '12 månader' },
  ];

  data: ApiResponse | null = null;
  error = false;

  filterLevel: string = 'alla';
  sortKey: SortKey = 'score';
  expandedOp: number | null = null;

  readonly levelOptions = [
    { val: 'alla',      label: 'Alla nivåer' },
    { val: 'stjarna',   label: '⭐⭐⭐⭐⭐ Stjärnoperatör' },
    { val: 'nyckel',    label: '⭐⭐⭐⭐ Nyckelspelare' },
    { val: 'solid',     label: '⭐⭐⭐ Solid' },
    { val: 'potential', label: '⭐⭐ Potential' },
    { val: 'stod',      label: '⭐ Behöver stöd' },
  ];

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
    this.error = false;
    this.data = null;
    this.expandedOp = null;

    this.http
      .get<ApiResponse>(`${API}&period=${this.period}`, { withCredentials: true })
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        if (res?.success) {
          this.data = res;
        } else {
          this.error = true;
        }
      });
  }

  get filteredOperators(): Operator[] {
    if (!this.data) return [];
    let ops = this.data.operators;
    if (this.filterLevel !== 'alla') {
      ops = ops.filter(o => o.level === this.filterLevel);
    }
    return this.sortOperators(ops);
  }

  private sortOperators(ops: Operator[]): Operator[] {
    return [...ops].sort((a, b) => {
      switch (this.sortKey) {
        case 'ibc_h': return b.period_ibc_h - a.period_ibc_h;
        case 'trend': return b.trend_pct - a.trend_pct;
        case 'name':  return a.name.localeCompare(b.name, 'sv');
        default:      return b.score - a.score;
      }
    });
  }

  toggleExpand(opNum: number): void {
    this.expandedOp = this.expandedOp === opNum ? null : opNum;
  }

  stars(level: string): string {
    const map: Record<string, string> = {
      stjarna:   '⭐⭐⭐⭐⭐',
      nyckel:    '⭐⭐⭐⭐',
      solid:     '⭐⭐⭐',
      potential: '⭐⭐',
      stod:      '⭐',
    };
    return map[level] ?? '⭐';
  }

  levelLabel(level: string): string {
    const map: Record<string, string> = {
      stjarna:   'Stjärnoperatör',
      nyckel:    'Nyckelspelare',
      solid:     'Solid',
      potential: 'Potential',
      stod:      'Behöver stöd',
    };
    return map[level] ?? level;
  }

  levelColor(level: string): string {
    const map: Record<string, string> = {
      stjarna:   '#f6c90e',
      nyckel:    '#68d391',
      solid:     '#63b3ed',
      potential: '#f6ad55',
      stod:      '#fc8181',
    };
    return map[level] ?? '#e2e8f0';
  }

  trendIcon(label: string): string {
    if (label === 'foerbattras') return '↑';
    if (label === 'forsamras')   return '↓';
    return '→';
  }

  trendColor(label: string): string {
    if (label === 'foerbattras') return '#68d391';
    if (label === 'forsamras')   return '#fc8181';
    return '#a0aec0';
  }

  vsTeamClass(pct: number): string {
    if (pct >= 15) return 'badge-elite';
    if (pct >= 0)  return 'badge-solid';
    if (pct >= -15) return 'badge-warn';
    return 'badge-low';
  }

  kassClass(op: Operator): string {
    if (op.kassation_pct <= op.team_kass_pct) return 'kass-good';
    if (op.kassation_pct <= op.team_kass_pct * 1.5) return 'kass-ok';
    return 'kass-bad';
  }

  scoreBarWidth(score: number): string {
    return Math.min(100, score) + '%';
  }

  // KPI helpers
  get topScore(): number {
    return this.data?.operators[0]?.score ?? 0;
  }

  get totalCount(): number {
    return this.data?.operators.length ?? 0;
  }

  get stjarnCount(): number {
    return (this.data?.counts['stjarna'] ?? 0) + (this.data?.counts['nyckel'] ?? 0);
  }
}
