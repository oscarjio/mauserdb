import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { ToastService } from '../../services/toast.service';

const API = '/noreko-backend/api.php?action=shift-plan';

interface ShiftEntry {
  op_number: number;
  op_name: string;
  note: string | null;
}

interface Operator {
  op_number: number;
  op_name: string;
}

interface ActiveCell {
  datum: string;
  skift: number;
}

@Component({
  standalone: true,
  selector: 'app-shift-plan',
  imports: [CommonModule, FormsModule],
  templateUrl: './shift-plan.html',
  styleUrl: './shift-plan.css'
})
export class ShiftPlanPage implements OnInit, OnDestroy {

  currentWeekStart!: Date;
  weekDays: Date[] = [];
  weekData: { [datum: string]: { [skift: string]: ShiftEntry[] } } = {};
  operators: Operator[] = [];

  activeCell: ActiveCell | null = null;
  loading = false;
  error = '';

  readonly skifts = [
    { nr: 1, label: 'Morgon', time: '06–14' },
    { nr: 2, label: 'Eftermiddag', time: '14–22' },
    { nr: 3, label: 'Natt', time: '22–06' },
  ];

  readonly dayNames = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];

  private destroy$ = new Subject<void>();

  constructor(
    private http: HttpClient,
    private toast: ToastService
  ) {}

  ngOnInit() {
    this.currentWeekStart = this.getMondayOf(new Date());
    this.buildWeekDays();
    this.loadOperators();
    this.loadWeek();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // -----------------------------------------------------------------------
  // Veckonavigation
  // -----------------------------------------------------------------------

  getMondayOf(date: Date): Date {
    const d = new Date(date);
    const dow = d.getDay(); // 0=sön, 1=mån, ..., 6=lör
    const diff = dow === 0 ? -6 : 1 - dow;
    d.setDate(d.getDate() + diff);
    d.setHours(0, 0, 0, 0);
    return d;
  }

  buildWeekDays() {
    this.weekDays = [];
    for (let i = 0; i < 7; i++) {
      const d = new Date(this.currentWeekStart);
      d.setDate(d.getDate() + i);
      this.weekDays.push(d);
    }
  }

  prevWeek() {
    this.currentWeekStart = new Date(this.currentWeekStart);
    this.currentWeekStart.setDate(this.currentWeekStart.getDate() - 7);
    this.buildWeekDays();
    this.activeCell = null;
    this.loadWeek();
  }

  nextWeek() {
    this.currentWeekStart = new Date(this.currentWeekStart);
    this.currentWeekStart.setDate(this.currentWeekStart.getDate() + 7);
    this.buildWeekDays();
    this.activeCell = null;
    this.loadWeek();
  }

  goToToday() {
    this.currentWeekStart = this.getMondayOf(new Date());
    this.buildWeekDays();
    this.activeCell = null;
    this.loadWeek();
  }

  getWeekNumber(date: Date): number {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil((((d as any) - (yearStart as any)) / 86400000 + 1) / 7);
  }

  get weekLabel(): string {
    const w = this.getWeekNumber(this.currentWeekStart);
    const y = this.currentWeekStart.getFullYear();
    return `Vecka ${w}, ${y}`;
  }

  formatDate(d: Date): string {
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${mm}-${dd}`;
  }

  isToday(d: Date): boolean {
    const today = new Date();
    return d.getFullYear() === today.getFullYear() &&
           d.getMonth() === today.getMonth() &&
           d.getDate() === today.getDate();
  }

  isWeekend(idx: number): boolean {
    return idx >= 5; // Lör (5) och Sön (6)
  }

  // -----------------------------------------------------------------------
  // Data loading
  // -----------------------------------------------------------------------

  loadOperators() {
    this.http.get<any>(`${API}&run=operators`, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) this.operators = res.operators || [];
          else if (res === null) this.toast.error('Kunde inte hämta operatörslista');
        },
        error: () => {
          this.toast.error('Kunde inte hämta operatörslista');
        }
      });
  }

  loadWeek() {
    this.loading = true;
    this.error = '';
    const dateParam = this.formatDate(this.currentWeekStart);
    this.http.get<any>(`${API}&run=week&date=${dateParam}`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.loading = false;
          if (res === null) {
            this.error = 'Kunde inte hämta skiftplan';
            return;
          }
          if (res.success) {
            this.weekData = res.days || {};
          } else {
            this.error = res.error || 'Okänt fel';
          }
        },
        error: () => {
          this.loading = false;
          this.error = 'Kunde inte hämta skiftplan';
        }
      });
  }

  getShiftEntries(datum: string, skiftNr: number): ShiftEntry[] {
    return this.weekData[datum]?.[String(skiftNr)] ?? [];
  }

  // -----------------------------------------------------------------------
  // Cell-interaktion
  // -----------------------------------------------------------------------

  toggleCell(datum: string, skiftNr: number) {
    if (this.activeCell?.datum === datum && this.activeCell?.skift === skiftNr) {
      this.activeCell = null;
    } else {
      this.activeCell = { datum, skift: skiftNr };
    }
  }

  isCellActive(datum: string, skiftNr: number): boolean {
    return this.activeCell?.datum === datum && this.activeCell?.skift === skiftNr;
  }

  closePanel() {
    this.activeCell = null;
  }

  // Vilka operatörer är INTE redan tilldelade i aktiv cell?
  get availableOperators(): Operator[] {
    if (!this.activeCell) return this.operators;
    const entries = this.getShiftEntries(this.activeCell.datum, this.activeCell.skift);
    const assigned = new Set(entries.map(e => e.op_number));
    return this.operators.filter(op => !assigned.has(op.op_number));
  }

  assignOperator(op: Operator) {
    if (!this.activeCell) return;
    const body = {
      datum: this.activeCell.datum,
      skift_nr: this.activeCell.skift,
      op_number: op.op_number,
    };
    this.http.post<any>(`${API}&run=assign`, body, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res === null) { this.toast.error('Nätverksfel — kunde inte lägga till operatör'); return; }
          if (res.success) {
            // Lägg till lokalt utan att ladda om hela veckan
            const d = this.activeCell!.datum;
            const s = String(this.activeCell!.skift);
            if (!this.weekData[d]) this.weekData[d] = { '1': [], '2': [], '3': [] };
            if (!this.weekData[d][s]) this.weekData[d][s] = [];
            this.weekData[d][s].push({ op_number: op.op_number, op_name: op.op_name, note: null });
            this.toast.success(`${op.op_name} inlagd i skiftet`);
          } else {
            this.toast.error(res.error || 'Kunde inte lägga till operatör');
          }
        },
        error: () => {
          this.toast.error('Kunde inte lägga till operatör');
        }
      });
  }

  removeOperator(datum: string, skiftNr: number, entry: ShiftEntry) {
    const skiftLabel = this.skifts.find(s => s.nr === skiftNr)?.label ?? `Skift ${skiftNr}`;
    if (!confirm(`Ta bort ${entry.op_name} från ${skiftLabel} den ${datum}?`)) return;

    const body = {
      datum,
      skift_nr: skiftNr,
      op_number: entry.op_number,
    };
    this.http.post<any>(`${API}&run=remove`, body, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res === null) { this.toast.error('Nätverksfel — kunde inte ta bort operatör'); return; }
          if (res.success) {
            // Ta bort lokalt
            const s = String(skiftNr);
            if (this.weekData[datum]?.[s]) {
              this.weekData[datum][s] = this.weekData[datum][s].filter(
                e => e.op_number !== entry.op_number
              );
            }
            this.toast.success(`${entry.op_name} borttagen`);
          } else {
            this.toast.error(res.error || 'Kunde inte ta bort operatör');
          }
        },
        error: () => {
          this.toast.error('Kunde inte ta bort operatör');
        }
      });
  }

  // -----------------------------------------------------------------------
  // Avatarfärg (hash-baserad, samma som operators-sidan)
  // -----------------------------------------------------------------------

  getAvatarColor(name: string): string {
    const colors = [
      '#4299e1', '#48bb78', '#ed8936', '#e53e3e', '#9f7aea',
      '#00b5d8', '#d69e2e', '#38a169', '#667eea', '#f687b3'
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
  }

  getInitials(name: string): string {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
  }
}
