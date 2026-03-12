import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  AndonBoardService,
  TodayProduction,
  CurrentRate,
  MachineStatus,
  Quality,
  ShiftInfo
} from '../../services/andon-board.service';

@Component({
  standalone: true,
  selector: 'app-andon-board',
  templateUrl: './andon-board.html',
  styleUrls: ['./andon-board.css'],
  imports: [CommonModule]
})
export class AndonBoardComponent implements OnInit, OnDestroy {
  // Data
  production: TodayProduction | null = null;
  rate: CurrentRate | null = null;
  machine: MachineStatus | null = null;
  quality: Quality | null = null;
  shift: ShiftInfo | null = null;
  timestamp = '';

  // UI state
  loading = true;
  error: string | null = null;
  clock = '';
  clockDate = '';
  isFullscreen = false;

  private destroy$ = new Subject<void>();
  private refreshInterval: any = null;
  private clockInterval: any = null;

  constructor(private andonService: AndonBoardService) {}

  ngOnInit(): void {
    this.updateClock();
    this.fetchData();

    this.clockInterval = setInterval(() => this.updateClock(), 1000);
    this.refreshInterval = setInterval(() => this.fetchData(), 30000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) clearInterval(this.refreshInterval);
    if (this.clockInterval) clearInterval(this.clockInterval);
  }

  fetchData(): void {
    this.andonService.getStatus()
      .pipe(takeUntil(this.destroy$))
      .subscribe(data => {
        if (data && data.success) {
          this.production = data.today_production;
          this.rate = data.current_rate;
          this.machine = data.machine_status;
          this.quality = data.quality;
          this.shift = data.shift;
          this.timestamp = data.timestamp;
          this.loading = false;
          this.error = null;
        } else if (!data) {
          this.error = 'Kunde inte hamta data fran servern.';
          this.loading = false;
        }
      });
  }

  private updateClock(): void {
    const nu = new Date();
    this.clock = nu.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    this.clockDate = nu.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  // ---- Computed helpers ----

  get progressWidth(): number {
    return this.production ? Math.min(100, Math.round(this.production.percentage)) : 0;
  }

  get progressColor(): string {
    if (!this.production) return '#4a5568';
    switch (this.production.status) {
      case 'green':  return '#48bb78';
      case 'yellow': return '#ed8936';
      case 'red':    return '#f56565';
      default:       return '#4a5568';
    }
  }

  get trendIcon(): string {
    if (!this.rate) return '';
    switch (this.rate.trend) {
      case 'up':     return 'fa-arrow-up';
      case 'down':   return 'fa-arrow-down';
      case 'stable': return 'fa-minus';
      default:       return 'fa-minus';
    }
  }

  get trendColor(): string {
    if (!this.rate) return '#718096';
    switch (this.rate.trend) {
      case 'up':     return '#48bb78';
      case 'down':   return '#f56565';
      case 'stable': return '#ed8936';
      default:       return '#718096';
    }
  }

  get machineStatusText(): string {
    if (!this.machine) return 'OKAND';
    switch (this.machine.status) {
      case 'running': return 'KOR';
      case 'stopped': return 'STOPP';
      case 'unknown': return 'OKAND';
      default:        return 'OKAND';
    }
  }

  get machineStatusColor(): string {
    if (!this.machine) return '#718096';
    switch (this.machine.status) {
      case 'running': return '#48bb78';
      case 'stopped': return '#f56565';
      case 'unknown': return '#ed8936';
      default:        return '#718096';
    }
  }

  get machineGlowClass(): string {
    if (!this.machine) return 'glow-unknown';
    switch (this.machine.status) {
      case 'running': return 'glow-running';
      case 'stopped': return 'glow-stopped';
      case 'unknown': return 'glow-unknown';
      default:        return 'glow-unknown';
    }
  }

  get qualityColor(): string {
    if (!this.quality) return '#718096';
    if (this.quality.scrap_rate_percent <= 2)  return '#48bb78';
    if (this.quality.scrap_rate_percent <= 5)  return '#ed8936';
    return '#f56565';
  }

  get lastStopText(): string {
    if (!this.machine || !this.machine.last_stop_reason) return 'Ingen registrerad';
    const reason = this.machine.last_stop_reason;
    const dur = this.machine.last_stop_duration_minutes;
    const ago = this.machine.last_stop_minutes_ago;

    let text = reason;
    if (dur && dur > 0) text += ` (${dur} min)`;
    if (ago !== null) {
      if (ago < 60) {
        text += ` - ${ago} min sedan`;
      } else {
        const h = Math.floor(ago / 60);
        const m = ago % 60;
        text += ` - ${h}h ${m}min sedan`;
      }
    }
    return text;
  }

  toggleFullscreen(): void {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => {});
      this.isFullscreen = true;
    } else {
      document.exitFullscreen().catch(() => {});
      this.isFullscreen = false;
    }
  }
}
