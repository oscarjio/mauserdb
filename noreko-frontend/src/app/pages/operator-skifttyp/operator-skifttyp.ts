import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface SkifttypData {
  ibc_per_h: number;
  antal_skift: number;
  vs_overall: number;
}

interface OperatorSkifttyp {
  number: number;
  name: string;
  overall_ibc_h: number;
  best_skifttyp: 'dag' | 'kvall' | 'natt' | null;
  skifttyper: {
    dag:   SkifttypData | null;
    kvall: SkifttypData | null;
    natt:  SkifttypData | null;
  };
}

interface SkifttypResponse {
  success: boolean;
  days: number;
  from: string;
  to: string;
  operators: OperatorSkifttyp[];
  team_by_skifttyp: {
    dag?:   { ibc_per_h: number; antal_skift: number };
    kvall?: { ibc_per_h: number; antal_skift: number };
    natt?:  { ibc_per_h: number; antal_skift: number };
  };
}

@Component({
  standalone: true,
  selector: 'app-operator-skifttyp',
  imports: [CommonModule, RouterModule],
  templateUrl: './operator-skifttyp.html',
  styleUrl: './operator-skifttyp.css'
})
export class OperatorSkifttypPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  operators: OperatorSkifttyp[] = [];
  teamBySkifttyp: SkifttypResponse['team_by_skifttyp'] = {};
  days = 90;
  from = '';
  to = '';

  sortBy: 'name' | 'overall' | 'dag' | 'kvall' | 'natt' = 'name';

  Math = Math;

  readonly skiftLabels: Record<string, string> = {
    dag:   'Dagskift (06–14)',
    kvall: 'Kvällsskift (14–22)',
    natt:  'Nattskift (22–06)',
  };

  readonly skiftShort: Record<string, string> = {
    dag:   'Dag',
    kvall: 'Kväll',
    natt:  'Natt',
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    const url = `${environment.apiUrl}?action=rebotling&run=operator-skifttyp&days=${this.days}`;
    this.http.get<SkifttypResponse>(url, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta skifttyp-data.';
        return;
      }
      this.operators      = res.operators;
      this.teamBySkifttyp = res.team_by_skifttyp;
      this.from           = res.from;
      this.to             = res.to;
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setSort(s: typeof this.sortBy): void {
    this.sortBy = s;
  }

  get sorted(): OperatorSkifttyp[] {
    return [...this.operators].sort((a, b) => {
      if (this.sortBy === 'overall') return b.overall_ibc_h - a.overall_ibc_h;
      if (this.sortBy === 'dag')   return (b.skifttyper.dag?.ibc_per_h   ?? -1) - (a.skifttyper.dag?.ibc_per_h   ?? -1);
      if (this.sortBy === 'kvall') return (b.skifttyper.kvall?.ibc_per_h ?? -1) - (a.skifttyper.kvall?.ibc_per_h ?? -1);
      if (this.sortBy === 'natt')  return (b.skifttyper.natt?.ibc_per_h  ?? -1) - (a.skifttyper.natt?.ibc_per_h  ?? -1);
      return a.name.localeCompare(b.name);
    });
  }

  vsColor(vs: number | undefined): string {
    if (vs === undefined || vs === null) return '#4a5568';
    if (vs >= 1.5)  return '#68d391';
    if (vs >= 0)    return '#9ae6b4';
    if (vs >= -1.5) return '#feb2b2';
    return '#fc8181';
  }

  bestLabel(typ: string | null): string {
    if (!typ) return '—';
    return this.skiftShort[typ] ?? typ;
  }

  teamIbcH(typ: string): string {
    const t = (this.teamBySkifttyp as Record<string, { ibc_per_h: number } | undefined>)[typ];
    return t ? t.ibc_per_h.toFixed(1) : '—';
  }

  teamSkifts(typ: string): number {
    const t = (this.teamBySkifttyp as Record<string, { antal_skift: number } | undefined>)[typ];
    return t?.antal_skift ?? 0;
  }

  maxIbcH(): number {
    let max = 0;
    for (const op of this.operators) {
      if (op.overall_ibc_h > max) max = op.overall_ibc_h;
      for (const typ of ['dag', 'kvall', 'natt'] as const) {
        const v = op.skifttyper[typ]?.ibc_per_h ?? 0;
        if (v > max) max = v;
      }
    }
    return max || 1;
  }

  barWidth(val: number | undefined): number {
    if (!val) return 0;
    return Math.round(Math.min(100, (val / this.maxIbcH()) * 100));
  }

  skifttypOf(op: OperatorSkifttyp, typ: string): SkifttypData | null {
    return (op.skifttyper as Record<string, SkifttypData | null>)[typ] ?? null;
  }
}
