import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { localToday, localDateStr } from '../../utils/date-utils';
import { environment } from '../../../environments/environment';

interface ProductionRate {
  avg_ibc_per_day_7d: number;
  avg_ibc_per_day_30d: number;
  avg_ibc_per_day_90d: number;
  dag_mal: number;
}

interface WeeklyMilestone {
  week: number;
  date: string;
  cumIbc: number;
  pct: number;
}

interface PrognosResult {
  completionDateStr: string;
  workdaysNeeded: number;
  calendarDaysNeeded: number;
  weeklyMilestones: WeeklyMilestone[];
}

@Component({
  standalone: true,
  selector: 'app-rebotling-prognos',
  imports: [CommonModule, FormsModule],
  templateUrl: './rebotling-prognos.html',
  styleUrl: './rebotling-prognos.css'
})
export class RebotlingPrognosPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  // Indata
  targetIbc = 500;
  startDate = localToday();
  selectedPeriod: '7d' | '30d' | '90d' = '30d';
  workdaysPerWeek = 5;

  // Data
  productionRate: ProductionRate | null = null;
  loading = false;
  loadError = false;
  calculated = false;
  result: PrognosResult | null = null;

  Math = Math;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadProductionRate();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadProductionRate(): void {
    this.loading = true;
    this.loadError = false;
    this.http
      .get<any>(`${environment.apiUrl}?action=rebotling&run=production-rate`, { withCredentials: true })
      .pipe(timeout(10000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (res?.success && res.data) {
          this.productionRate = {
            avg_ibc_per_day_7d: res.data.avg_ibc_per_day_7d ?? 0,
            avg_ibc_per_day_30d: res.data.avg_ibc_per_day_30d ?? 0,
            avg_ibc_per_day_90d: res.data.avg_ibc_per_day_90d ?? 0,
            dag_mal: res.data.dag_mal ?? 100
          };
          if (this.currentRate > 0) {
            this.calculate();
          }
        } else {
          this.loadError = true;
        }
      });
  }

  get currentRate(): number {
    if (!this.productionRate) return 0;
    switch (this.selectedPeriod) {
      case '7d':  return this.productionRate.avg_ibc_per_day_7d;
      case '90d': return this.productionRate.avg_ibc_per_day_90d;
      default:    return this.productionRate.avg_ibc_per_day_30d;
    }
  }

  calculate(): void {
    if (!this.currentRate || this.currentRate <= 0 || this.targetIbc <= 0) return;

    const ibcPerWorkday = this.currentRate;
    const workdaysNeeded = Math.ceil(this.targetIbc / ibcPerWorkday);

    // Beräkna faktiskt slutdatum (hoppa över helger om workdaysPerWeek=5 eller 6)
    const start = new Date(this.startDate + 'T00:00:00');
    let currentDate = new Date(start);
    let workdaysCount = 0;

    while (workdaysCount < workdaysNeeded) {
      const dow = currentDate.getDay();
      let isWorkday: boolean;
      if (this.workdaysPerWeek === 7) {
        isWorkday = true;
      } else if (this.workdaysPerWeek === 6) {
        isWorkday = dow !== 0; // Sön = ledig
      } else {
        isWorkday = dow !== 0 && dow !== 6; // Lör+Sön = lediga
      }
      if (isWorkday) workdaysCount++;
      if (workdaysCount < workdaysNeeded) {
        currentDate.setDate(currentDate.getDate() + 1);
      }
    }

    const calendarDays = Math.ceil((currentDate.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));

    // Veckomilepålar
    const milestones: WeeklyMilestone[] = [];
    for (let week = 1; week <= Math.min(Math.ceil(workdaysNeeded / this.workdaysPerWeek) + 1, 52); week++) {
      const weekWorkdays = Math.min(week * this.workdaysPerWeek, workdaysNeeded);
      const ibcAtWeek = Math.min(weekWorkdays * ibcPerWorkday, this.targetIbc);

      // Beräkna datum för denna veckas sista arbetsdag
      let tempDate = new Date(start);
      let wd = 0;
      while (wd < weekWorkdays) {
        const dow = tempDate.getDay();
        let isWorkday: boolean;
        if (this.workdaysPerWeek === 7) {
          isWorkday = true;
        } else if (this.workdaysPerWeek === 6) {
          isWorkday = dow !== 0;
        } else {
          isWorkday = dow !== 0 && dow !== 6;
        }
        if (isWorkday) wd++;
        if (wd < weekWorkdays) tempDate.setDate(tempDate.getDate() + 1);
      }

      milestones.push({
        week,
        date: localDateStr(tempDate),
        cumIbc: Math.round(ibcAtWeek),
        pct: Math.round(Math.min(ibcAtWeek / this.targetIbc * 100, 100))
      });

      if (ibcAtWeek >= this.targetIbc) break;
    }

    this.result = {
      completionDateStr: localDateStr(currentDate),
      workdaysNeeded,
      calendarDaysNeeded: calendarDays,
      weeklyMilestones: milestones
    };
    this.calculated = true;
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '';
    const p = dateStr.split('-');
    return `${p[2]}/${p[1]}/${p[0]}`;
  }

  /** Uppdatera period-knapp och beräkna om */
  setPeriod(period: '7d' | '30d' | '90d'): void {
    this.selectedPeriod = period;
    if (this.currentRate > 0 && this.targetIbc > 0) {
      this.calculate();
    }
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
