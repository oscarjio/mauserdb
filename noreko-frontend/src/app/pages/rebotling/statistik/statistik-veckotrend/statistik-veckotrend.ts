import {
  Component,
  OnInit,
  OnDestroy,
  AfterViewInit,
  ViewChildren,
  QueryList,
  ElementRef,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { RebotlingService, WeeklyKpiCard, WeeklyKpisResponse } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-veckotrend',
  templateUrl: './statistik-veckotrend.html',
  imports: [CommonModule],
})
export class StatistikVeckotrendComponent implements OnInit, AfterViewInit, OnDestroy {
  loading = false;
  error: string | null = null;
  kpis: WeeklyKpiCard[] = [];

  @ViewChildren('sparklineCanvas') canvasRefs!: QueryList<ElementRef<HTMLCanvasElement>>;

  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private animationFrames: number[] = [];

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    this.load();
    this.refreshInterval = setInterval(() => this.load(), 300_000); // 5 min
  }

  ngAfterViewInit(): void {
    // Prenumerera på ändringar i canvas-listan (ritas om när data laddas)
    this.canvasRefs.changes
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(() => this.drawAllSparklines());
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
    if (this.refreshInterval !== null) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    this.animationFrames.forEach((id) => cancelAnimationFrame(id));
    this.animationFrames = [];
  }

  load(): void {
    if (this.loading) return;
    this.loading = true;
    this.error = null;

    this.rebotlingService
      .getWeeklyKpis()
      .pipe(
        catchError(() => {
          this.error = 'Kunde inte hämta veckotrend-data.';
          this.loading = false;
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe((resp: WeeklyKpisResponse | null) => {
        this.loading = false;
        if (!resp || !resp.success) {
          if (!this.error) this.error = 'Okänt fel';
          return;
        }
        this.kpis = resp.kpis;
        // setTimeout ger Angular en cykel att rendera canvases
        this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.drawAllSparklines(); }, 50));
      });
  }

  private drawAllSparklines(): void {
    const canvases = this.canvasRefs.toArray();
    canvases.forEach((ref, i) => {
      if (this.kpis[i]) {
        this.drawSparkline(ref.nativeElement, this.kpis[i]);
      }
    });
  }

  private drawSparkline(canvas: HTMLCanvasElement, kpi: WeeklyKpiCard): void {
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // Storleksanpassning till kortets bredd
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const W = rect.width  || canvas.offsetWidth  || 200;
    const H = rect.height || canvas.offsetHeight || 50;
    canvas.width  = W * dpr;
    canvas.height = H * dpr;
    ctx.scale(dpr, dpr);

    // Välj färg baserat på trend
    const color = this.trendColor(kpi.trend);

    // Filtrera bort null-värden och mappa till koordinater
    const rawValues = kpi.values;
    const nonNullValues = rawValues.filter((v) => v !== null) as number[];
    if (nonNullValues.length < 2) {
      ctx.clearRect(0, 0, W, H);
      return;
    }

    const minV = Math.min(...nonNullValues);
    const maxV = Math.max(...nonNullValues);
    const range = maxV - minV || 1;

    const pad = { top: 4, bottom: 4, left: 2, right: 2 };
    const plotW = W - pad.left - pad.right;
    const plotH = H - pad.top - pad.bottom;

    // Bygg punkter — hoppa över null
    const points: { x: number; y: number }[] = [];
    const step = rawValues.length > 1 ? plotW / (rawValues.length - 1) : plotW;
    rawValues.forEach((v, i) => {
      if (v !== null) {
        const x = pad.left + i * step;
        const y = pad.top + plotH - ((v - minV) / range) * plotH;
        points.push({ x, y });
      }
    });

    // Animera linjen från vänster till höger
    this.animateLine(ctx, points, W, H, plotH, pad, color, kpi);
  }

  private animateLine(
    ctx: CanvasRenderingContext2D,
    points: { x: number; y: number }[],
    W: number,
    H: number,
    plotH: number,
    pad: { top: number; bottom: number; left: number; right: number },
    color: string,
    kpi: WeeklyKpiCard
  ): void {
    const duration = 500; // ms
    const start = performance.now();

    const draw = (now: number) => {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);

      ctx.clearRect(0, 0, W, H);

      // Beräkna hur långt vi kommit längs linjen
      const totalWidth = points[points.length - 1].x - points[0].x;
      const cutX = points[0].x + totalWidth * this.easeInOut(progress);

      // Filtrera synliga punkter
      const visible = points.filter((p) => p.x <= cutX);
      if (visible.length < 2) {
        if (progress < 1) {
          const id = requestAnimationFrame(draw);
          this.animationFrames.push(id);
        }
        return;
      }

      // Rita gradient fill
      const grad = ctx.createLinearGradient(0, pad.top, 0, H - pad.bottom);
      grad.addColorStop(0, color + '55');
      grad.addColorStop(1, color + '05');

      ctx.beginPath();
      ctx.moveTo(visible[0].x, visible[0].y);
      for (let i = 1; i < visible.length; i++) {
        const cp = {
          x: (visible[i - 1].x + visible[i].x) / 2,
          y: (visible[i - 1].y + visible[i].y) / 2,
        };
        ctx.quadraticCurveTo(visible[i - 1].x, visible[i - 1].y, cp.x, cp.y);
      }
      const last = visible[visible.length - 1];
      ctx.lineTo(last.x, last.y);

      // Fyll under linjen
      ctx.lineTo(last.x, H - pad.bottom);
      ctx.lineTo(visible[0].x, H - pad.bottom);
      ctx.closePath();
      ctx.fillStyle = grad;
      ctx.fill();

      // Rita linje
      ctx.beginPath();
      ctx.moveTo(visible[0].x, visible[0].y);
      for (let i = 1; i < visible.length; i++) {
        const cp = {
          x: (visible[i - 1].x + visible[i].x) / 2,
          y: (visible[i - 1].y + visible[i].y) / 2,
        };
        ctx.quadraticCurveTo(visible[i - 1].x, visible[i - 1].y, cp.x, cp.y);
      }
      ctx.lineTo(last.x, last.y);
      ctx.strokeStyle = color;
      ctx.lineWidth = 2;
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';
      ctx.stroke();

      // Rita slutpunkt (dot)
      if (progress === 1) {
        ctx.beginPath();
        ctx.arc(last.x, last.y, 3, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
      }

      if (progress < 1) {
        const id = requestAnimationFrame(draw);
        this.animationFrames.push(id);
      }
    };

    const id = requestAnimationFrame(draw);
    this.animationFrames.push(id);
  }

  private easeInOut(t: number): number {
    return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
  }

  private trendColor(trend: 'up' | 'down' | 'stable'): string {
    if (trend === 'up')   return '#48bb78'; // grön
    if (trend === 'down') return '#fc8181'; // röd
    return '#a0aec0'; // grå
  }

  trendIcon(trend: 'up' | 'down' | 'stable'): string {
    if (trend === 'up')   return 'fa-arrow-trend-up';
    if (trend === 'down') return 'fa-arrow-trend-down';
    return 'fa-minus';
  }

  trendClass(trend: 'up' | 'down' | 'stable'): string {
    if (trend === 'up')   return 'trend-up';
    if (trend === 'down') return 'trend-down';
    return 'trend-stable';
  }

  formatValue(value: number | null, unit: string): string {
    if (value === null) return '–';
    if (unit === '%') return value.toFixed(1) + ' %';
    if (unit === 'min') return value.toFixed(1) + ' min';
    return value.toString();
  }

  formatChangePct(pct: number | null): string {
    if (pct === null) return '';
    const sign = pct >= 0 ? '+' : '';
    return sign + pct.toFixed(1) + ' %';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
