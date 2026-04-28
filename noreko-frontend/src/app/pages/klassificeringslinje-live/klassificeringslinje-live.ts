import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { of } from 'rxjs';
import { catchError, finalize, timeout } from 'rxjs/operators';
import { LineSkiftrapportService, LineName } from '../../services/line-skiftrapport.service';

@Component({
  standalone: true,
  selector: 'app-klassificeringslinje-live',
  imports: [CommonModule, DatePipe],
  templateUrl: './klassificeringslinje-live.html',
  styleUrl: './klassificeringslinje-live.css'
})
export class KlassificeringslinjeLivePage implements OnInit, OnDestroy {
  readonly line: LineName = 'klassificeringslinje';

  now = new Date();
  private intervalId: any;
  private isFetching = false;

  lastDataUpdate: Date | null = null;
  dataAgeSec = 0;

  antalOkIdag = 0;
  antalEjOkIdag = 0;
  totaltIdag = 0;
  kvalitetPct = 0;
  skiftCount = 0;
  needleRotation = -90;

  get statusText(): string {
    if (this.skiftCount === 0) return 'Väntar på data';
    if (this.kvalitetPct >= 95) return 'Bra kvalitet';
    if (this.kvalitetPct >= 80) return 'Godkänd kvalitet';
    return 'Låg kvalitet';
  }

  get statusBadgeClass(): string {
    if (this.skiftCount === 0) return 'bg-secondary';
    if (this.kvalitetPct >= 95) return 'bg-success';
    if (this.kvalitetPct >= 80) return 'bg-warning text-dark';
    return 'bg-danger';
  }

  get freshnessClass(): string {
    if (!this.lastDataUpdate) return 'freshness-unknown';
    if (this.dataAgeSec > 120) return 'freshness-stale';
    if (this.dataAgeSec > 45) return 'freshness-warning';
    return 'freshness-ok';
  }

  get freshnessLabel(): string {
    if (!this.lastDataUpdate) return 'Väntar på data...';
    if (this.dataAgeSec > 120) return `Ingen data på ${this.dataAgeSec}s`;
    if (this.dataAgeSec > 45) return `Uppdaterad ${this.dataAgeSec}s sedan`;
    return 'Live';
  }

  constructor(private service: LineSkiftrapportService) {}

  ngOnInit() {
    this.fetchTodayData();
    this.intervalId = setInterval(() => {
      this.now = new Date();
      if (this.lastDataUpdate) {
        this.dataAgeSec = Math.round((Date.now() - this.lastDataUpdate.getTime()) / 1000);
      }
      if (this.dataAgeSec % 30 === 0) this.fetchTodayData();
    }, 1000);
  }

  ngOnDestroy() {
    clearInterval(this.intervalId);
  }

  private fetchTodayData() {
    if (this.isFetching) return;
    this.isFetching = true;
    this.service.getReports(this.line)
      .pipe(
        timeout(15000),
        catchError(() => of(null)),
        finalize(() => { this.isFetching = false; })
      )
      .subscribe((res: any) => {
        if (res?.success && res.data) {
          const today = new Date().toISOString().split('T')[0];
          const todayReports = (res.data as any[]).filter(r =>
            (r.datum || '').substring(0, 10) === today
          );
          this.skiftCount = todayReports.length;
          this.antalOkIdag = todayReports.reduce((s, r) => s + (r.antal_ok || 0), 0);
          this.antalEjOkIdag = todayReports.reduce((s, r) => s + (r.antal_ej_ok || 0), 0);
          this.totaltIdag = this.antalOkIdag + this.antalEjOkIdag;
          this.kvalitetPct = this.totaltIdag > 0
            ? Math.round((this.antalOkIdag / this.totaltIdag) * 100) : 0;
          this.updateNeedle();
          this.lastDataUpdate = new Date();
          this.dataAgeSec = 0;
        }
      });
  }

  private updateNeedle() {
    const pct = Math.min(Math.max(this.kvalitetPct, 0), 100);
    this.needleRotation = -90 + (pct / 100) * 180;
  }
}
