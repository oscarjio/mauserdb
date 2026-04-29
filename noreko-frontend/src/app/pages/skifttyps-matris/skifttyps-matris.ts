import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { localToday } from '../../utils/date-utils';

interface ShiftCell {
  skiftraknare: number;
  datum: string;
  dag: number;
  shift_type: 'dag' | 'kvall' | 'natt';
  ibc_h: number;
  ibc_ok: number;
  kassation_pct: number;
  stopp_pct: number;
  drifttid: number;
  op1_name: string;
  op2_name: string;
  op3_name: string;
  op1_num: number;
  op2_num: number;
  op3_num: number;
}

interface TypeAvg {
  ibc_h: number;
  count: number;
}

interface DayMeta {
  dag: number;
  datum: string;
  veckodag: string;
  is_weekend: boolean;
}

@Component({
  standalone: true,
  selector: 'app-skifttyps-matris',
  templateUrl: './skifttyps-matris.html',
  styleUrl: './skifttyps-matris.css',
  imports: [CommonModule, FormsModule, RouterModule],
})
export class SkifttypsMatrisPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  Math = Math;

  year = new Date().getFullYear();
  month = new Date().getMonth() + 1;

  monthNames = ['', 'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
                'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];

  yearOptions: number[] = [];
  monthOptions = [1,2,3,4,5,6,7,8,9,10,11,12];

  loading = false;
  error = '';
  isFetching = false;

  days = 0;
  monthName = '';
  periodAvg = 0;
  typeAvg: Record<string, TypeAvg> = {
    dag:   { ibc_h: 0, count: 0 },
    kvall: { ibc_h: 0, count: 0 },
    natt:  { ibc_h: 0, count: 0 },
  };
  shifts: ShiftCell[] = [];
  dayMeta: DayMeta[] = [];

  // matrix[dag][shift_type] = ShiftCell | null
  matrix: Record<number, Record<string, ShiftCell | null>> = {};

  selectedCell: ShiftCell | null = null;

  readonly types: ('dag' | 'kvall' | 'natt')[] = ['dag', 'kvall', 'natt'];
  readonly typeLabels: Record<string, string> = {
    dag:   'Dag 06–14',
    kvall: 'Kväll 14–22',
    natt:  'Natt 22–06',
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    const today = localToday();
    this.year  = parseInt(today.substring(0, 4));
    this.month = parseInt(today.substring(5, 7));
    const curYear = this.year;
    for (let y = curYear - 3; y <= curYear + 1; y++) this.yearOptions.push(y);
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
    this.selectedCell = null;

    const url = `${environment.apiUrl}/api/rebotling?action=skifttyps-matris&year=${this.year}&month=${this.month}`;
    this.http.get<any>(url, { withCredentials: true })
      .pipe(takeUntil(this.destroy$), timeout(15000), catchError(() => of(null)))
      .subscribe(data => {
        this.isFetching = false;
        this.loading = false;
        if (!data?.success) {
          this.error = 'Kunde inte hämta data.';
          return;
        }
        this.days      = data.days;
        this.monthName = data.month_name;
        this.periodAvg = data.period_avg ?? 0;
        this.typeAvg   = data.type_avg ?? { dag: { ibc_h: 0, count: 0 }, kvall: { ibc_h: 0, count: 0 }, natt: { ibc_h: 0, count: 0 } };
        this.shifts    = data.shifts ?? [];
        this.dayMeta   = data.day_meta ?? [];
        this.buildMatrix();
      });
  }

  buildMatrix(): void {
    this.matrix = {};
    for (const dm of this.dayMeta) {
      this.matrix[dm.dag] = { dag: null, kvall: null, natt: null };
    }
    for (const s of this.shifts) {
      if (this.matrix[s.dag]) {
        this.matrix[s.dag][s.shift_type] = s;
      }
    }
  }

  cellBg(s: ShiftCell | null): string {
    if (!s || this.periodAvg === 0) return '';
    const r = s.ibc_h / this.periodAvg;
    if (r >= 1.2)  return '#276749';
    if (r >= 1.05) return '#2f855a';
    if (r >= 0.95) return '#2a4a7f';
    if (r >= 0.80) return '#744210';
    return '#742a2a';
  }

  vsLabel(s: ShiftCell | null): string {
    if (!s || this.periodAvg === 0) return '';
    const p = Math.round((s.ibc_h / this.periodAvg - 1) * 100);
    return (p >= 0 ? '+' : '') + p + '%';
  }

  selectCell(s: ShiftCell | null): void {
    if (!s) return;
    this.selectedCell = this.selectedCell?.skiftraknare === s.skiftraknare ? null : s;
  }

  typeVsAvg(t: string): string {
    const ta = this.typeAvg[t];
    if (!ta || this.periodAvg === 0) return '';
    const p = Math.round((ta.ibc_h / this.periodAvg - 1) * 100);
    return (p >= 0 ? '+' : '') + p + '%';
  }

  typeVsColor(t: string): string {
    const ta = this.typeAvg[t];
    if (!ta || this.periodAvg === 0) return '#a0aec0';
    const r = ta.ibc_h / this.periodAvg;
    if (r >= 1.05) return '#68d391';
    if (r >= 0.95) return '#63b3ed';
    return '#fc8181';
  }

  get bestShift(): ShiftCell | null {
    if (!this.shifts.length) return null;
    return this.shifts.reduce((a, b) => b.ibc_h > a.ibc_h ? b : a);
  }

  get worstShift(): ShiftCell | null {
    if (!this.shifts.length) return null;
    return this.shifts.reduce((a, b) => b.ibc_h < a.ibc_h ? b : a);
  }

  get aboveAvgCount(): number {
    return this.shifts.filter(s => s.ibc_h >= this.periodAvg).length;
  }

  get aboveAvgPct(): number {
    if (!this.shifts.length) return 0;
    return Math.round(this.aboveAvgCount / this.shifts.length * 100);
  }

  formatH(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return `${h}h ${m}m`;
  }

  prevMonth(): void {
    this.month--;
    if (this.month < 1) { this.month = 12; this.year--; }
    this.load();
  }

  nextMonth(): void {
    this.month++;
    if (this.month > 12) { this.month = 1; this.year++; }
    this.load();
  }
}
