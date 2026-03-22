import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import {
  ProduktionsPrognosService,
  ForecastData,
  ShiftHistoryData,
} from '../../services/produktionsprognos.service';
import { parseLocalDate } from '../../utils/date-utils';

@Component({
  standalone: true,
  selector: 'app-produktionsprognos',
  templateUrl: './produktionsprognos.html',
  styleUrls: ['./produktionsprognos.css'],
  imports: [CommonModule],
})
export class ProduktionsPrognosPage implements OnInit, OnDestroy {
  private destroy$    = new Subject<void>();
  private pollInterval: ReturnType<typeof setInterval> | null = null;

  // Data
  forecast: ForecastData | null = null;
  history: ShiftHistoryData | null = null;

  // UI-tillstand
  loadingForecast = true;
  loadingHistory  = true;
  errorForecast   = false;
  errorHistory    = false;
  isFetchingForecast = false;
  isFetchingHistory  = false;

  // Senast uppdaterad
  lastUpdated: string | null = null;

  // Math för template
  Math = Math;

  constructor(private svc: ProduktionsPrognosService) {}

  ngOnInit(): void {
    this.fetchAll();
    // Auto-refresh var 60:e sekund
    this.pollInterval = setInterval(() => this.fetchAll(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
  }

  fetchAll(): void {
    this.fetchForecast();
    this.fetchHistory();
  }

  fetchForecast(): void {
    if (this.isFetchingForecast) return;
    this.isFetchingForecast = true;
    this.errorForecast = false;

    this.svc.getForecast().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.isFetchingForecast = false;
      this.loadingForecast = false;
      if (res?.success && res.data) {
        this.forecast = res.data;
        this.lastUpdated = new Date().toLocaleTimeString('sv-SE');
      } else {
        this.errorForecast = true;
      }
    });
  }

  fetchHistory(): void {
    if (this.isFetchingHistory) return;
    this.isFetchingHistory = true;
    this.errorHistory = false;

    this.svc.getShiftHistory().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.isFetchingHistory = false;
      this.loadingHistory = false;
      if (res?.success && res.data) {
        this.history = res.data;
      } else {
        this.errorHistory = true;
      }
    });
  }

  // ---- Hjälpmetoder ----

  skiftLabel(namn: string): string {
    switch (namn) {
      case 'dag':   return 'Dagskift (06-14)';
      case 'kväll': return 'Kvällsskift (14-22)';
      case 'natt':  return 'Nattskift (22-06)';
      default:      return namn;
    }
  }

  skiftIcon(namn: string): string {
    switch (namn) {
      case 'dag':   return 'fas fa-sun';
      case 'kväll': return 'fas fa-cloud-sun';
      case 'natt':  return 'fas fa-moon';
      default:      return 'fas fa-clock';
    }
  }

  trendIcon(status: string): string {
    switch (status) {
      case 'bättre': return 'fas fa-arrow-trend-up';
      case 'sämre':  return 'fas fa-arrow-trend-down';
      case 'i snitt': return 'fas fa-minus';
      default:        return 'fas fa-question';
    }
  }

  trendColor(status: string): string {
    switch (status) {
      case 'bättre': return '#68d391';
      case 'sämre':  return '#fc8181';
      case 'i snitt': return '#f6ad55';
      default:        return '#a0aec0';
    }
  }

  prognosDiff(prognos: number, mal: number | null): number | null {
    if (mal === null) return null;
    return prognos - mal;
  }

  formatTime(h: number, m: number): string {
    const hStr = h > 0 ? `${h} h ` : '';
    return `${hStr}${m} min`;
  }

  formatDatum(dateStr: string): string {
    if (!dateStr) return '';
    const d = parseLocalDate(dateStr);
    return d.toLocaleDateString('sv-SE', { weekday: 'short', month: 'short', day: 'numeric' });
  }

  /** Beräkna bar-bredd for historik-skift relativt snittIbc */
  histBarWidth(ibc: number, snitt: number): number {
    if (snitt <= 0) return 0;
    return Math.min(100, Math.round((ibc / snitt) * 70));
  }

  /** Beräkna skiftmål (1/3 av dagsmål, avrundat) */
  skiftMal(dagsMal: number): number {
    return Math.round(dagsMal / 3);
  }

  /** Diff-sträng: prognos vs skiftmål */
  skiftDiffStr(prognos: number, dagsMal: number): string {
    const mal = this.skiftMal(dagsMal);
    const diff = prognos - mal;
    return (diff >= 0 ? '+' : '') + diff + ' IBC';
  }

  /** Färg baserat på takt jämfört med snitt */
  histBarColor(ibc: number, snitt: number): string {
    if (snitt <= 0 || ibc === 0) return '#4a5568';
    const ratio = ibc / snitt;
    if (ratio >= 1.05) return '#68d391';
    if (ratio <= 0.95) return '#fc8181';
    return '#f6ad55';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
