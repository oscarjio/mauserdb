import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface ShiftRecord {
  skiftraknare: number;
  op1_num: number; op2_num: number; op3_num: number;
  op1_name: string; op2_name: string; op3_name: string;
  ibc_ok: number; ibc_ej_ok: number;
  drifttid: number; driftstopptime: number;
  product_id: number; product_name: string;
  ibc_h: number; kassation: number; stoppgrad: number;
}

interface DayData {
  date: string;
  day_name: string;
  day_num: number;
  is_today: boolean;
  is_past: boolean;
  is_future: boolean;
  shifts: ShiftRecord[];
  ibc_ok: number;
  ibc_h: number | null;
  kassation: number | null;
}

interface OperatorData {
  number: number;
  name: string;
  ibc_h_30d: number | null;
  pos_stats: Record<string, { ibc_h: number | null; shifts: number }>;
}

interface WeekKpi {
  total_ibc: number;
  ibc_h: number;
  kassation: number;
  days_done: number;
}

interface VeckoResponse {
  success: boolean;
  week_start: string;
  week_end: string;
  today: string;
  days: DayData[];
  operators: OperatorData[];
  week_kpi: WeekKpi;
}

// Plans are stored in localStorage keyed by week_start date
interface DayPlan { op1: number; op2: number; op3: number; }
type WeekPlan = Record<string, DayPlan>;

const STORAGE_KEY = 'veckoplanering_plans';

@Component({
  standalone: true,
  selector: 'app-veckoplanering',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './veckoplanering.html',
  styleUrl: './veckoplanering.css'
})
export class VeckoplaneringPage implements OnInit, OnDestroy {
  Math = Math;

  isLoading = false;
  days: DayData[] = [];
  operators: OperatorData[] = [];
  weekKpi: WeekKpi = { total_ibc: 0, ibc_h: 0, kassation: 0, days_done: 0 };
  weekStart = '';
  weekEnd   = '';
  today     = '';

  // Plans: date → {op1, op2, op3}
  plans: WeekPlan = {};

  private destroy$ = new Subject<void>();

  constructor(private http: HttpClient) {}

  ngOnInit() { this.load(); }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  load() {
    if (this.isLoading) return;
    this.isLoading = true;
    this.http.get<VeckoResponse>(
      `${environment.apiUrl}?action=veckoplanering`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isLoading = false;
      if (!res?.success) return;
      this.days      = res.days;
      this.operators = res.operators;
      this.weekKpi   = res.week_kpi;
      this.weekStart = res.week_start;
      this.weekEnd   = res.week_end;
      this.today     = res.today;
      this.loadPlans();
      // Seed future days that have no plan yet
      for (const day of this.days) {
        if ((day.is_future || day.is_today) && !this.plans[day.date]) {
          this.plans[day.date] = { op1: 0, op2: 0, op3: 0 };
        }
      }
    });
  }

  // ── localStorage plan persistence ──────────────────────────────────
  private loadPlans() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const all: Record<string, WeekPlan> = raw ? JSON.parse(raw) : {};
      this.plans = all[this.weekStart] ?? {};
    } catch { this.plans = {}; }
  }

  savePlans() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const all: Record<string, WeekPlan> = raw ? JSON.parse(raw) : {};
      all[this.weekStart] = this.plans;
      localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
    } catch {}
  }

  onPlanChange(date: string) {
    this.savePlans();
  }

  clearPlan(date: string) {
    this.plans[date] = { op1: 0, op2: 0, op3: 0 };
    this.savePlans();
  }

  // ── Predictions ────────────────────────────────────────────────────
  // Predicted IBC/h for a planned team: average of each operator's
  // position-specific 30d IBC/h (falls back to overall if no data at pos).
  predictedIbcH(date: string): number | null {
    const plan = this.plans[date];
    if (!plan) return null;
    const ops = [
      { num: plan.op1, pos: '1' },
      { num: plan.op2, pos: '2' },
      { num: plan.op3, pos: '3' },
    ];
    let sum = 0, count = 0;
    for (const { num, pos } of ops) {
      if (!num) continue;
      const op = this.operators.find(o => o.number === num);
      if (!op) continue;
      const posH = op.pos_stats[pos]?.ibc_h;
      const val  = posH ?? op.ibc_h_30d;
      if (val !== null && val !== undefined) { sum += val; count++; }
    }
    return count > 0 ? Math.round((sum / count) * 10) / 10 : null;
  }

  // Names for planned operators (used in display)
  opName(num: number): string {
    if (!num) return '—';
    return this.operators.find(o => o.number === num)?.name ?? `Op ${num}`;
  }

  opIbcH(num: number, pos: number): number | null {
    if (!num) return null;
    const op = this.operators.find(o => o.number === num);
    return op?.pos_stats[pos]?.ibc_h ?? op?.ibc_h_30d ?? null;
  }

  // Workload: shifts already in plan + actual shifts this week per operator
  workloadThisWeek(opNum: number): number {
    let count = 0;
    for (const day of this.days) {
      // actual shifts
      for (const s of day.shifts) {
        if (s.op1_num === opNum || s.op2_num === opNum || s.op3_num === opNum) count++;
      }
    }
    // planned future shifts (not yet actual)
    for (const day of this.days) {
      if (!day.is_future && !day.is_today) continue;
      if (day.shifts.length > 0) continue; // already have actual data
      const p = this.plans[day.date];
      if (p && (p.op1 === opNum || p.op2 === opNum || p.op3 === opNum)) count++;
    }
    return count;
  }

  // ── Color helpers ──────────────────────────────────────────────────
  ibcHColor(val: number | null, ref: number): string {
    if (val === null || ref <= 0) return '';
    const pct = val / ref;
    if (pct >= 1.1) return '#68d391';
    if (pct >= 0.9) return '#63b3ed';
    return '#fc8181';
  }

  kassColor(val: number | null): string {
    if (val === null) return '';
    if (val <= 3)  return '#68d391';
    if (val <= 7)  return '#f6ad55';
    return '#fc8181';
  }

  trackByDate(_: number, d: DayData) { return d.date; }
}
