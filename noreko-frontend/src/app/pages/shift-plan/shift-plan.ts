import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { ToastService } from '../../services/toast.service';
import { parseLocalDate } from '../../utils/date-utils';
import { environment } from '../../../environments/environment';

const API = `${environment.apiUrl}?action=shift-plan`;

interface ShiftEntry {
  op_number: number;
  op_name: string;
  note: string | null;
}

interface Operator {
  op_number: number;
  op_name: string;
  initialer?: string;
}

interface ActiveCell {
  datum: string;
  skift: number;
}

// --- Week-view types ---
interface WeekViewOp {
  op_number: number;
  op_name: string;
  initialer: string;
  planerad?: boolean;
}

interface WeekViewSlot {
  datum: string;
  skift_nr: number;
  dag_namn: string;
  skift_label: string;
  skift_tid: string;
  planerade_ops: WeekViewOp[];
  faktiska_ops: WeekViewOp[];
  uteblev_ops: WeekViewOp[];
}

@Component({
  standalone: true,
  selector: 'app-shift-plan',
  imports: [CommonModule, FormsModule],
  templateUrl: './shift-plan.html',
  styleUrl: './shift-plan.css'
})
export class ShiftPlanPage implements OnInit, OnDestroy {

  // --- Tab state ---
  activeTab: 'veckoplan' | 'veckoöversikt' = 'veckoplan';

  // ===== Veckoplan (befintlig) =====
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

  // Cachat totalt antal ops denna vecka — beräknas när weekData eller weekDays ändras
  cachedTotalOpsThisWeek = 0;

  // ===== Veckoöversikt (ny) =====
  weekViewData: WeekViewSlot[] = [];
  weekViewLoading = false;
  weekViewError = '';
  weekViewStart: Date = new Date();
  operatorsList: Operator[] = [];

  showAssignModal = false;
  assigningSlot: WeekViewSlot | null = null;
  assigningOpNumber: number | null = null;
  assignLoading = false;

  // Bemanningsvarning
  staffingWarnings: { datum: string; dag_namn: string; underbemanning: { skift_nr: number; antal_ops: number }[] }[] = [];
  minOperators: number = 2;
  staffingWarningLoading = false;

  // Kopiera schema
  copyLoading = false;

  private destroy$ = new Subject<void>();

  constructor(
    private http: HttpClient,
    private toast: ToastService
  ) {}

  ngOnInit() {
    this.currentWeekStart = this.getMondayOf(new Date());
    this.weekViewStart = this.getMondayOf(new Date());
    this.buildWeekDays();
    this.loadOperators();
    this.loadWeek();
    this.loadOperatorsList();
    this.loadStaffingWarning();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // -----------------------------------------------------------------------
  // Flik-hantering
  // -----------------------------------------------------------------------

  setTab(tab: 'veckoplan' | 'veckoöversikt') {
    this.activeTab = tab;
    if (tab === 'veckoöversikt' && this.weekViewData.length === 0) {
      this.loadWeekView();
    }
  }

  // -----------------------------------------------------------------------
  // Veckonavigation (veckoplan)
  // -----------------------------------------------------------------------

  getMondayOf(date: Date): Date {
    const d = new Date(date);
    const dow = d.getDay();
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

  // -----------------------------------------------------------------------
  // Veckonavigation (veckoöversikt)
  // -----------------------------------------------------------------------

  prevWeekView() {
    const d = new Date(this.weekViewStart);
    d.setDate(d.getDate() - 7);
    this.weekViewStart = d;
    this.loadWeekView();
  }

  nextWeekView() {
    const d = new Date(this.weekViewStart);
    d.setDate(d.getDate() + 7);
    this.weekViewStart = d;
    this.loadWeekView();
  }

  goToTodayView() {
    this.weekViewStart = this.getMondayOf(new Date());
    this.loadWeekView();
  }

  // -----------------------------------------------------------------------
  // Datum/vecka-hjälpare
  // -----------------------------------------------------------------------

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

  get weekViewLabel(): string {
    const w = this.getWeekNumber(this.weekViewStart);
    const endDate = new Date(this.weekViewStart);
    endDate.setDate(endDate.getDate() + 6);
    const startStr = `${this.weekViewStart.getDate()} ${this.getMonthShort(this.weekViewStart)}`;
    const endStr = `${endDate.getDate()} ${this.getMonthShort(endDate)}`;
    return `v.${w}, ${startStr}–${endStr}`;
  }

  getMonthShort(d: Date): string {
    const months = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
    return months[d.getMonth()];
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

  isTodayStr(datum: string): boolean {
    return datum === this.formatDate(new Date());
  }

  isWeekend(idx: number): boolean {
    return idx >= 5;
  }

  isWeekendDatum(datum: string): boolean {
    const dow = parseLocalDate(datum).getDay();
    return dow === 0 || dow === 6;
  }

  getDayIndex(datum: string): number {
    const dow = parseLocalDate(datum).getDay();
    return dow === 0 ? 6 : dow - 1;
  }

  getDayShort(datum: string): string {
    const names = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];
    return names[this.getDayIndex(datum)];
  }

  formatDayDate(datum: string): string {
    const d = parseLocalDate(datum);
    return `${d.getDate()}/${d.getMonth() + 1}`;
  }

  // -----------------------------------------------------------------------
  // Data loading — Veckoplan
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
            this.recomputeTotalOps();
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
  // Data loading — Veckoöversikt
  // -----------------------------------------------------------------------

  loadOperatorsList() {
    this.http.get<any>(`${API}&run=operators-list`, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res?.success) this.operatorsList = res.operators || [];
        }
      });
  }

  loadWeekView() {
    this.weekViewLoading = true;
    this.weekViewError = '';
    const weekStartParam = this.formatDate(this.weekViewStart);
    this.http.get<any>(`${API}&run=week-view&week_start=${weekStartParam}`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.weekViewLoading = false;
          if (res === null) {
            this.weekViewError = 'Kunde inte hämta veckoöversikt';
            return;
          }
          if (res.success) {
            this.weekViewData = res.slots || [];
          } else {
            this.weekViewError = res.error || 'Okänt fel';
          }
        },
        error: () => {
          this.weekViewLoading = false;
          this.weekViewError = 'Kunde inte hämta veckoöversikt';
        }
      });
  }

  getSlot(datum: string, skiftNr: number): WeekViewSlot | undefined {
    return this.weekViewData.find(s => s.datum === datum && s.skift_nr === skiftNr);
  }

  get weekViewDates(): string[] {
    const seen = new Set<string>();
    const result: string[] = [];
    for (const slot of this.weekViewData) {
      if (!seen.has(slot.datum)) {
        seen.add(slot.datum);
        result.push(slot.datum);
      }
    }
    return result;
  }

  // -----------------------------------------------------------------------
  // Veckoöversikt hjälpmetoder
  // -----------------------------------------------------------------------

  isOpFactual(slot: WeekViewSlot, opNumber: number): boolean {
    return slot.faktiska_ops.some(f => f.op_number === opNumber);
  }

  getUnplannedOps(slot: WeekViewSlot): WeekViewOp[] {
    return slot.faktiska_ops.filter(f => !f.planerad);
  }

  hasUnplannedOps(slot: WeekViewSlot): boolean {
    return slot.faktiska_ops.some(f => !f.planerad);
  }

  // -----------------------------------------------------------------------
  // Tilldelning från veckoöversikt
  // -----------------------------------------------------------------------

  openAssignModal(slot: WeekViewSlot, event: Event) {
    event.stopPropagation();
    this.assigningSlot = slot;
    this.assigningOpNumber = null;
    this.showAssignModal = true;
  }

  closeAssignModal() {
    this.showAssignModal = false;
    this.assigningSlot = null;
    this.assigningOpNumber = null;
  }

  getAvailableOpsForSlot(slot: WeekViewSlot): Operator[] {
    const assigned = new Set(slot.planerade_ops.map(o => o.op_number));
    return this.operatorsList.filter(op => !assigned.has(op.op_number));
  }

  confirmAssign() {
    if (!this.assigningSlot || !this.assigningOpNumber) return;
    const slot = this.assigningSlot;
    const opNumber = this.assigningOpNumber;
    const op = this.operatorsList.find(o => o.op_number === opNumber);
    if (!op) return;

    this.assignLoading = true;
    const body = {
      datum: slot.datum,
      skift_nr: slot.skift_nr,
      op_number: opNumber,
    };
    this.http.post<any>(`${API}&run=assign`, body, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.assignLoading = false;
          if (res === null) { this.toast.error('Nätverksfel — kunde inte lägga till operatör'); return; }
          if (res.success) {
            slot.planerade_ops.push({
              op_number: opNumber,
              op_name: op.op_name,
              initialer: op.initialer ?? this.getInitials(op.op_name),
            });
            this.toast.success(`${op.op_name} inlagd i ${slot.skift_label} ${slot.datum}`);
            this.closeAssignModal();
          } else {
            this.toast.error(res.error || 'Kunde inte lägga till operatör');
          }
        },
        error: () => {
          this.assignLoading = false;
          this.toast.error('Kunde inte lägga till operatör');
        }
      });
  }

  removeFromWeekView(slot: WeekViewSlot, op: WeekViewOp, event: Event) {
    event.stopPropagation();
    if (!confirm(`Ta bort ${op.op_name} från ${slot.skift_label} den ${slot.datum}?`)) return;
    const body = { datum: slot.datum, skift_nr: slot.skift_nr, op_number: op.op_number };
    this.http.post<any>(`${API}&run=remove`, body, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          if (res === null) { this.toast.error('Nätverksfel'); return; }
          if (res.success) {
            slot.planerade_ops = slot.planerade_ops.filter(o => o.op_number !== op.op_number);
            slot.uteblev_ops = slot.uteblev_ops.filter(o => o.op_number !== op.op_number);
            this.toast.success(`${op.op_name} borttagen`);
          } else {
            this.toast.error(res.error || 'Kunde inte ta bort');
          }
        }
      });
  }

  // -----------------------------------------------------------------------
  // Cell-interaktion (veckoplan)
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
            const d = this.activeCell!.datum;
            const s = String(this.activeCell!.skift);
            if (!this.weekData[d]) this.weekData[d] = { '1': [], '2': [], '3': [] };
            if (!this.weekData[d][s]) this.weekData[d][s] = [];
            this.weekData[d][s].push({ op_number: op.op_number, op_name: op.op_name, note: null });
            this.recomputeTotalOps();
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
            const s = String(skiftNr);
            if (this.weekData[datum]?.[s]) {
              this.weekData[datum][s] = this.weekData[datum][s].filter(
                e => e.op_number !== entry.op_number
              );
            }
            this.recomputeTotalOps();
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
  // Bemanningsvarning
  // -----------------------------------------------------------------------

  loadStaffingWarning() {
    this.staffingWarningLoading = true;
    this.http.get<any>(`${API}&run=staffing-warning`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.staffingWarningLoading = false;
          if (res?.success) {
            this.staffingWarnings = res.warnings || [];
            this.minOperators     = res.min_operators ?? 2;
          }
        },
        error: () => {
          this.staffingWarningLoading = false;
        }
      });
  }

  // -----------------------------------------------------------------------
  // Veckoöversikt-panel: sammanfattning per skift
  // -----------------------------------------------------------------------

  getShiftCount(skiftNr: number): number {
    let total = 0;
    for (const day of this.weekDays) {
      const datum = this.formatDate(day);
      total += this.getShiftEntries(datum, skiftNr).length;
    }
    return total;
  }

  getShiftCountForDay(datum: string, skiftNr: number): number {
    return this.getShiftEntries(datum, skiftNr).length;
  }

  getStaffingClass(datum: string, skiftNr: number): string {
    const count = this.getShiftCountForDay(datum, skiftNr);
    if (count >= 2) return 'staffing-ok';
    if (count === 1) return 'staffing-warning';
    return 'staffing-danger';
  }

  getStaffingColor(datum: string, skiftNr: number): string {
    const count = this.getShiftCountForDay(datum, skiftNr);
    if (count >= 2) return '#48bb78';
    if (count === 1) return '#ecc94b';
    return '#e53e3e';
  }

  private recomputeTotalOps(): void {
    let total = 0;
    for (const day of this.weekDays) {
      const datum = this.formatDate(day);
      for (const s of this.skifts) {
        total += this.getShiftEntries(datum, s.nr).length;
      }
    }
    this.cachedTotalOpsThisWeek = total;
  }

  /** @deprecated Använd cachedTotalOpsThisWeek direkt i templaten */
  getTotalOpsThisWeek(): number { return this.cachedTotalOpsThisWeek; }

  // -----------------------------------------------------------------------
  // Kopiera förra veckans schema
  // -----------------------------------------------------------------------

  copyLastWeek() {
    if (!confirm('Kopiera förra veckans schema till denna vecka? Befintliga tilldelningar behålls.')) return;
    this.copyLoading = true;
    const body = { target_week_start: this.formatDate(this.currentWeekStart) };
    this.http.post<any>(`${API}&run=copy-week`, body, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.copyLoading = false;
          if (res === null) { this.toast.error('Nätverksfel — kunde inte kopiera schema'); return; }
          if (res.success) {
            this.toast.success(res.message || `Kopierade ${res.copied} tilldelning(ar)`);
            this.loadWeek();
            this.loadStaffingWarning();
          } else {
            this.toast.error(res.error || 'Kunde inte kopiera schema');
          }
        },
        error: () => {
          this.copyLoading = false;
          this.toast.error('Kunde inte kopiera schema');
        }
      });
  }

  // -----------------------------------------------------------------------
  // Avatarfärg (hash-baserad)
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
  trackByIndex(index: number): number { return index; }
}
