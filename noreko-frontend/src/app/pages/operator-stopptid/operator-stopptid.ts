import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorStopp {
  op_number: number;
  name: string;
  total_shifts: number;
  stopp_shifts: number;
  stopp_procent: number;
  snitt_stopp_min: number;
  total_stopp_min: number;
  stoppgrad: number;
  vs_snitt: number | null;
  status: 'bra' | 'normal' | 'hog';
}

interface StopptidResponse {
  success: boolean;
  operators: OperatorStopp[];
  team_stoppgrad: number;
  days: number;
  from: string;
  to: string;
}

@Component({
  standalone: true,
  selector: 'app-operator-stopptid',
  imports: [CommonModule, RouterModule],
  templateUrl: './operator-stopptid.html',
  styleUrl: './operator-stopptid.css'
})
export class OperatorStopptidPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  loading = false;
  error = '';

  operators: OperatorStopp[] = [];
  teamStoppgrad = 0;
  days = 90;
  from = '';
  to = '';

  sortBy: 'stoppgrad' | 'stopp_procent' | 'snitt_stopp_min' | 'name' = 'stoppgrad';
  filter: 'alla' | 'bra' | 'normal' | 'hog' = 'alla';

  Math = Math;

  get filtered(): OperatorStopp[] {
    let list = this.filter === 'alla'
      ? [...this.operators]
      : this.operators.filter(o => o.status === this.filter);

    list.sort((a, b) => {
      if (this.sortBy === 'name') return a.name.localeCompare(b.name);
      if (this.sortBy === 'stopp_procent') return a.stopp_procent - b.stopp_procent;
      if (this.sortBy === 'snitt_stopp_min') return a.snitt_stopp_min - b.snitt_stopp_min;
      return a.stoppgrad - b.stoppgrad;
    });

    return list;
  }

  get counts() {
    return {
      bra:    this.operators.filter(o => o.status === 'bra').length,
      normal: this.operators.filter(o => o.status === 'normal').length,
      hog:    this.operators.filter(o => o.status === 'hog').length,
    };
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

    const url = `${environment.apiUrl}?action=rebotling&run=operator-stopptid&days=${this.days}`;
    this.http.get<StopptidResponse>(url).pipe(
      timeout(5000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res || !res.success) {
        this.error = 'Kunde inte hämta stopptidsdata.';
        return;
      }
      this.operators = res.operators;
      this.teamStoppgrad = res.team_stoppgrad;
      this.from = res.from;
      this.to = res.to;
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setSort(s: typeof this.sortBy): void {
    this.sortBy = s;
  }

  setFilter(f: typeof this.filter): void {
    this.filter = f;
  }

  stoppgradBar(op: OperatorStopp): number {
    const max = Math.max(this.teamStoppgrad * 2, 5);
    return Math.min(100, (op.stoppgrad / max) * 100);
  }

  teamBar(): number {
    const max = Math.max(this.teamStoppgrad * 2, 5);
    return Math.min(100, (this.teamStoppgrad / max) * 100);
  }

  constructor(private http: HttpClient) {}
}
