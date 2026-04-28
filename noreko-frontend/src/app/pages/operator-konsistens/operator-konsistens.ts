import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorKonsistens {
  number: number;
  name: string;
  avg_ibc_h: number;
  stddev: number;
  cv: number;
  min_ibc_h: number;
  max_ibc_h: number;
  range: number;
  shifts: number;
  badge: 'mycket_konsekvent' | 'konsekvent' | 'variabel' | 'oforutsagbar';
  vs_team: number;
}

interface KonsistensResponse {
  success: boolean;
  from: string;
  to: string;
  days: number;
  team_avg_ibch: number;
  team_avg_cv: number;
  operators: OperatorKonsistens[];
}

@Component({
  standalone: true,
  selector: 'app-operator-konsistens',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './operator-konsistens.html',
  styleUrl: './operator-konsistens.css'
})
export class OperatorKonsistensPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;

  Math = Math;
  loading = false;
  error = '';

  operators: OperatorKonsistens[] = [];
  teamAvgIbch = 0;
  teamAvgCv = 0;
  from = '';
  to = '';

  days = 90;
  sortBy: 'cv' | 'avg_ibc_h' | 'name' | 'shifts' = 'cv';
  sortAsc = true;
  badgeFilter: 'alla' | 'mycket_konsekvent' | 'konsekvent' | 'variabel' | 'oforutsagbar' = 'alla';

  get filtered(): OperatorKonsistens[] {
    let list = this.badgeFilter === 'alla'
      ? [...this.operators]
      : this.operators.filter(o => o.badge === this.badgeFilter);

    list.sort((a, b) => {
      let cmp = 0;
      if (this.sortBy === 'cv')        cmp = a.cv - b.cv;
      else if (this.sortBy === 'avg_ibc_h') cmp = b.avg_ibc_h - a.avg_ibc_h;
      else if (this.sortBy === 'shifts')    cmp = b.shifts - a.shifts;
      else cmp = a.name.localeCompare(b.name, 'sv');
      return this.sortAsc ? cmp : -cmp;
    });

    return list;
  }

  get countPerBadge() {
    return {
      mycket_konsekvent: this.operators.filter(o => o.badge === 'mycket_konsekvent').length,
      konsekvent:        this.operators.filter(o => o.badge === 'konsekvent').length,
      variabel:          this.operators.filter(o => o.badge === 'variabel').length,
      oforutsagbar:      this.operators.filter(o => o.badge === 'oforutsagbar').length,
    };
  }

  get mostConsistent(): OperatorKonsistens | null {
    if (!this.operators.length) return null;
    return [...this.operators].sort((a, b) => a.cv - b.cv)[0];
  }

  get mostVariable(): OperatorKonsistens | null {
    if (!this.operators.length) return null;
    return [...this.operators].sort((a, b) => b.cv - a.cv)[0];
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';

    this.http.get<KonsistensResponse>(
      `${environment.apiUrl}?action=rebotling&run=operator-konsistens&days=${this.days}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      this.loading = false;
      if (!res?.success) {
        this.error = 'Kunde inte hämta konsistensdata.';
        return;
      }
      this.operators    = res.operators;
      this.teamAvgIbch  = res.team_avg_ibch;
      this.teamAvgCv    = res.team_avg_cv;
      this.from         = res.from;
      this.to           = res.to;
    });
  }

  setDays(d: number): void {
    this.days = d;
    this.load();
  }

  setSort(s: typeof this.sortBy): void {
    if (this.sortBy === s) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortBy = s;
      this.sortAsc = true;
    }
  }

  setBadgeFilter(b: typeof this.badgeFilter): void {
    this.badgeFilter = b;
  }

  badgeLabel(b: string): string {
    if (b === 'mycket_konsekvent') return 'Mycket konsekvent';
    if (b === 'konsekvent')        return 'Konsekvent';
    if (b === 'variabel')          return 'Variabel';
    return 'Oförutsägbar';
  }

  badgeClass(b: string): string {
    if (b === 'mycket_konsekvent') return 'badge-mk';
    if (b === 'konsekvent')        return 'badge-k';
    if (b === 'variabel')          return 'badge-v';
    return 'badge-o';
  }

  cvColor(cv: number): string {
    if (cv <= 15) return '#68d391';
    if (cv <= 25) return '#9ae6b4';
    if (cv <= 35) return '#f6ad55';
    return '#fc8181';
  }

  cvBarWidth(cv: number): number {
    const max = Math.max(...this.operators.map(o => o.cv), 1);
    return Math.min(100, (cv / max) * 100);
  }

  vsTeamClass(vs: number): string {
    if (vs >= 10)  return 'vs-great';
    if (vs >= 0)   return 'vs-good';
    if (vs >= -10) return 'vs-low';
    return 'vs-bad';
  }

  sortIndicator(col: string): string {
    if (this.sortBy !== col) return '';
    return this.sortAsc ? ' ↑' : ' ↓';
  }
}
