import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import {
  NarvarotrackerService,
  NarvaroOperator,
  NarvaroSummary,
  NarvaroDayEntry,
  NarvaroMonthlyResponse
} from '../../../services/narvarotracker.service';

@Component({
  standalone: true,
  selector: 'app-narvarotracker',
  templateUrl: './narvarotracker.html',
  styleUrls: ['./narvarotracker.css'],
  imports: [CommonModule]
})
export class NarvarotrackerPage implements OnInit, OnDestroy {
  year: number = new Date().getFullYear();
  month: number = new Date().getMonth() + 1;
  daysInMonth: number = 31;
  dayNumbers: number[] = [];

  operators: NarvaroOperator[] = [];
  summary: NarvaroSummary | null = null;
  loading = false;
  loaded = false;

  expandedOp: number | null = null;

  tooltipVisible = false;
  tooltipX = 0;
  tooltipY = 0;
  tooltipData: { operator: string; dag: string; ibc: number; skift: number[]; snittCykel: number } | null = null;

  private destroy$ = new Subject<void>();

  // Lookup for quick cell access
  private opDayMap: Map<string, NarvaroDayEntry> = new Map();

  readonly monthNames = [
    'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
    'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'
  ];

  constructor(private narvaroService: NarvarotrackerService) {}

  ngOnInit() {
    this.loadData();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  get monthLabel(): string {
    return this.monthNames[this.month - 1] + ' ' + this.year;
  }

  prevMonth(): void {
    this.month--;
    if (this.month < 1) {
      this.month = 12;
      this.year--;
    }
    this.loadData();
  }

  nextMonth(): void {
    this.month++;
    if (this.month > 12) {
      this.month = 1;
      this.year++;
    }
    this.loadData();
  }

  loadData(): void {
    if (this.loading) return;
    this.loading = true;
    this.loaded = false;
    this.expandedOp = null;

    this.narvaroService.getMonthlyOverview(this.year, this.month).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: NarvaroMonthlyResponse | null) => {
      this.loading = false;
      this.loaded = true;
      if (res?.success && res.data) {
        this.daysInMonth = res.data.days_in_month;
        this.dayNumbers = Array.from({ length: this.daysInMonth }, (_, i) => i + 1);
        this.operators = res.data.operators;
        this.summary = res.data.summary;
        this.buildOpDayMap();
      } else {
        this.operators = [];
        this.summary = null;
        this.dayNumbers = [];
      }
    });
  }

  private buildOpDayMap(): void {
    this.opDayMap.clear();
    for (const op of this.operators) {
      for (const d of op.days) {
        const dayNum = parseInt(d.dag.split('-')[2], 10);
        this.opDayMap.set(`${op.operator_id}_${dayNum}`, d);
      }
    }
  }

  getDayEntry(opId: number, day: number): NarvaroDayEntry | undefined {
    return this.opDayMap.get(`${opId}_${day}`);
  }

  getCellIbc(opId: number, day: number): number {
    return this.getDayEntry(opId, day)?.ibc ?? 0;
  }

  getCellClass(opId: number, day: number): string {
    const ibc = this.getCellIbc(opId, day);
    if (ibc === 0) return 'cell-empty';
    if (ibc <= 10) return 'cell-low';
    if (ibc <= 30) return 'cell-mid';
    return 'cell-high';
  }

  toggleExpand(opId: number): void {
    this.expandedOp = this.expandedOp === opId ? null : opId;
  }

  isExpanded(opId: number): boolean {
    return this.expandedOp === opId;
  }

  getExpandedDays(opId: number): NarvaroDayEntry[] {
    const op = this.operators.find(o => o.operator_id === opId);
    return op?.days ?? [];
  }

  formatSkift(skift: number[]): string {
    return skift.map(s => 'Skift ' + s).join(', ');
  }

  showTooltip(event: MouseEvent, opId: number, day: number, opName: string): void {
    const entry = this.getDayEntry(opId, day);
    if (!entry) {
      this.tooltipVisible = false;
      return;
    }
    this.tooltipData = {
      operator: opName,
      dag: entry.dag,
      ibc: entry.ibc,
      skift: entry.skift,
      snittCykel: entry.snitt_cykel
    };
    this.tooltipX = event.clientX + 12;
    this.tooltipY = event.clientY - 10;
    this.tooltipVisible = true;
  }

  hideTooltip(): void {
    this.tooltipVisible = false;
    this.tooltipData = null;
  }
}
