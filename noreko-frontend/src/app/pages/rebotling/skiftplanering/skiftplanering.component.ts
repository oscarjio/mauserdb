import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { localToday } from '../../../utils/date-utils';
import {
  SkiftplaneringService,
  SkiftOverview,
  SkiftRad,
  DagInfo,
  ShiftDetailResponse,
  OperatorItem,
  DagKapacitet,
} from '../../../services/skiftplanering.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-skiftplanering',
  templateUrl: './skiftplanering.component.html',
  styleUrls: ['./skiftplanering.component.css'],
  imports: [CommonModule, FormsModule],
})
export class SkiftplaneringPage implements OnInit, OnDestroy {

  // Loading
  loadingOverview = false;
  loadingSchedule = false;
  loadingDetail = false;
  loadingCapacity = false;
  loadingOperators = false;

  // Errors
  errorOverview = false;
  errorSchedule = false;
  errorDetail = false;
  errorCapacity = false;
  errorOperators = false;
  removeError = '';

  // Data
  overview: SkiftOverview | null = null;
  schema: SkiftRad[] = [];
  dagar: DagInfo[] = [];
  currentWeek = '';
  monday = '';
  sunday = '';
  shiftDetail: ShiftDetailResponse | null = null;
  allOperators: OperatorItem[] = [];
  capacityData: DagKapacitet[] = [];
  capacityMinPerDay = 0;

  // Assign modal
  showAssignModal = false;
  assignSkift = '';
  assignDatum = '';
  assignOperatorId = 0;
  assignMessage = '';
  assignError = '';
  savingAssign = false;

  // Chart
  private capacityChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;

  constructor(private svc: SkiftplaneringService) {}

  ngOnInit(): void {
    this.loadAll();
    // Auto-refresh var 5 minuter
    this.refreshInterval = setInterval(() => this.loadAll(), 300000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.capacityChart) {
      this.capacityChart.destroy();
      this.capacityChart = null;
    }
  }

  loadAll(): void {
    this.loadOverview();
    this.loadSchedule();
    this.loadCapacity();
  }

  // ---- Overview ----

  loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOverview = false;
      if (res?.success) {
        this.overview = res.data;
      } else {
        this.errorOverview = true;
      }
    });
  }

  // ---- Schedule ----

  loadSchedule(week?: string): void {
    this.loadingSchedule = true;
    this.errorSchedule = false;
    this.svc.getSchedule(week).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingSchedule = false;
      if (res?.success) {
        this.schema = res.schema;
        this.dagar = res.dagar;
        this.currentWeek = res.vecka;
        this.monday = res.monday;
        this.sunday = res.sunday;
      } else {
        this.errorSchedule = true;
      }
    });
  }

  navigateWeek(delta: number): void {
    // Parse current week and navigate
    const match = this.currentWeek.match(/^(\d{4})-W(\d{2})$/);
    if (match) {
      const year = parseInt(match[1], 10);
      const week = parseInt(match[2], 10);
      const dt = new Date(Date.UTC(year, 0, 4)); // Jan 4 is always in week 1
      // Set to the Monday of the current ISO week
      dt.setUTCDate(dt.getUTCDate() - (dt.getUTCDay() || 7) + 1 + (week - 1) * 7);
      // Add delta weeks
      dt.setUTCDate(dt.getUTCDate() + delta * 7);
      // Get ISO week of new date
      const newWeek = this.getISOWeek(dt);
      this.loadSchedule(newWeek);
      this.loadCapacityForWeek(newWeek);
    }
  }

  private getISOWeek(dt: Date): string {
    const d = new Date(dt.getTime());
    d.setUTCDate(d.getUTCDate() + 3 - ((d.getUTCDay() + 6) % 7));
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
    const weekNo = Math.ceil(((d.getTime() - yearStart.getTime()) / 86400000 + 1) / 7);
    return `${d.getUTCFullYear()}-W${String(weekNo).padStart(2, '0')}`;
  }

  // ---- Shift Detail ----

  openShiftDetail(skiftTyp: string, datum: string): void {
    this.loadingDetail = true;
    this.shiftDetail = null;
    this.errorDetail = false;
    this.removeError = '';
    this.svc.getShiftDetail(skiftTyp, datum).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingDetail = false;
      if (res?.success) {
        this.shiftDetail = res;
      } else {
        this.errorDetail = true;
      }
    });
  }

  closeDetail(): void {
    this.shiftDetail = null;
  }

  removeOperator(schemaId: number): void {
    if (!confirm('Ta bort operatör från detta skift?')) return;
    this.removeError = '';
    this.svc.unassignOperator(schemaId).pipe(takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.loadSchedule(this.currentWeek);
        this.loadOverview();
        this.loadCapacity();
        // Reload detail if open
        if (this.shiftDetail) {
          this.openShiftDetail(this.shiftDetail.skift_typ, this.shiftDetail.datum);
        }
      } else {
        this.removeError = res?.error || 'Kunde inte ta bort operatoren';
      }
    });
  }

  // ---- Assign Modal ----

  openAssignModal(skiftTyp: string, datum: string): void {
    this.assignSkift = skiftTyp;
    this.assignDatum = datum;
    this.assignOperatorId = 0;
    this.assignMessage = '';
    this.assignError = '';
    this.showAssignModal = true;

    // Load operators if not loaded yet
    if (this.allOperators.length === 0) {
      this.loadingOperators = true;
      this.errorOperators = false;
      this.svc.getOperators().pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingOperators = false;
        if (res?.success) {
          this.allOperators = res.operatorer;
        } else {
          this.errorOperators = true;
        }
      });
    }
  }

  closeAssignModal(): void {
    this.showAssignModal = false;
  }

  submitAssign(): void {
    if (!this.assignOperatorId) {
      this.assignError = 'Välj en operatör';
      return;
    }

    this.savingAssign = true;
    this.assignError = '';
    this.assignMessage = '';
    this.svc.assignOperator(this.assignOperatorId, this.assignSkift, this.assignDatum)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.savingAssign = false;
        if (res?.success) {
          this.assignMessage = 'Operatör tilldelad!';
          this.loadSchedule(this.currentWeek);
          this.loadOverview();
          this.loadCapacity();
          setTimeout(() => this.closeAssignModal(), 800);
        } else {
          this.assignError = res?.error || 'Kunde inte tilldela operatör';
        }
      });
  }

  // ---- Capacity / Chart ----

  loadCapacity(): void {
    this.loadingCapacity = true;
    this.errorCapacity = false;
    this.svc.getCapacity().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingCapacity = false;
      if (res?.success) {
        this.capacityData = res.dag_data;
        this.capacityMinPerDay = res.min_per_dag;
        setTimeout(() => { if (!this.destroy$.closed) this.renderCapacityChart(); }, 100);
      } else {
        this.errorCapacity = true;
      }
    });
  }

  loadCapacityForWeek(_week: string): void {
    // Capacity endpoint always returns current week; reload
    this.loadCapacity();
  }

  renderCapacityChart(): void {
    if (this.capacityChart) {
      this.capacityChart.destroy();
      this.capacityChart = null;
    }

    const canvas = document.getElementById('capacityChart') as HTMLCanvasElement | null;
    if (!canvas || this.capacityData.length === 0) return;

    const labels = this.capacityData.map(d => d.dag_namn);
    const grades = this.capacityData.map(d => d.bemanningsgrad);
    const colors = grades.map(g => g >= 100 ? '#48bb78' : g >= 75 ? '#ecc94b' : '#e53e3e');

    this.capacityChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Bemanningsgrad %',
            data: grades,
            backgroundColor: colors,
            borderRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
          y: {
            min: 0,
            max: 150,
            ticks: {
              color: '#a0aec0',
              callback: (val: any) => val + '%',
            },
            grid: { color: '#4a556833' },
          },
        },
      },
      plugins: [{
        id: 'targetLine',
        afterDraw: (chart: any) => {
          const yScale = chart.scales['y'];
          const ctx = chart.ctx;
          const y = yScale.getPixelForValue(100);
          ctx.save();
          ctx.beginPath();
          ctx.moveTo(chart.chartArea.left, y);
          ctx.lineTo(chart.chartArea.right, y);
          ctx.lineWidth = 2;
          ctx.strokeStyle = '#fc8181';
          ctx.setLineDash([6, 4]);
          ctx.stroke();
          ctx.restore();
          // Label
          ctx.save();
          ctx.font = '11px sans-serif';
          ctx.fillStyle = '#fc8181';
          ctx.textAlign = 'right';
          ctx.fillText('Minimum', chart.chartArea.right - 4, y - 4);
          ctx.restore();
        }
      }],
    });
  }

  // ---- Helpers ----

  cellStatusClass(status: string): string {
    switch (status) {
      case 'gron': return 'cell-gron';
      case 'gul':  return 'cell-gul';
      case 'rod':  return 'cell-rod';
      default:     return '';
    }
  }

  skiftLabel(typ: string): string {
    switch (typ) {
      case 'FM':   return 'FM (06-14)';
      case 'EM':   return 'EM (14-22)';
      case 'NATT': return 'Natt (22-06)';
      default:     return typ;
    }
  }

  isToday(datum: string): boolean {
    return datum === localToday();
  }

  getAvailableOperators(): OperatorItem[] {
    if (!this.showAssignModal) return [];
    // Filter out operators already assigned on this day
    const assignedOnDay = this.getAssignedOperatorIds(this.assignDatum);
    return this.allOperators.filter(op => !assignedOnDay.includes(op.id));
  }

  private getAssignedOperatorIds(datum: string): number[] {
    const ids: number[] = [];
    for (const row of this.schema) {
      for (const dag of row.dagar) {
        if (dag.datum === datum) {
          for (const op of dag.operatorer) {
            ids.push(op.operator_id);
          }
        }
      }
    }
    return ids;
  }
  trackByIndex(index: number): number { return index; }
}
