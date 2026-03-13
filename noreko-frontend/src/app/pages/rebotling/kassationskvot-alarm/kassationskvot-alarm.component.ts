import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, interval } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KassationskvotAlarmService,
  AktuellKvotData,
  AlarmHistorikData,
  TimvisTrendData,
  PerSkiftData,
  TopOrsakerData,
  Troskel,
} from '../../../services/kassationskvot-alarm.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kassationskvot-alarm',
  templateUrl: './kassationskvot-alarm.component.html',
  styleUrls: ['./kassationskvot-alarm.component.scss'],
  imports: [CommonModule, FormsModule],
})
export class KassationskvotAlarmPage implements OnInit, OnDestroy {

  // Laddning / Fel
  loadingAktuell  = false;
  loadingHistorik = false;
  loadingTrend    = false;
  loadingPerSkift = false;
  loadingOrsaker  = false;
  errorAktuell    = false;
  errorHistorik   = false;
  errorTrend      = false;
  errorPerSkift   = false;
  errorOrsaker    = false;

  // Data
  aktuellData: AktuellKvotData | null = null;
  historikData: AlarmHistorikData | null = null;
  trendData: TimvisTrendData | null = null;
  perSkiftData: PerSkiftData | null = null;
  orsakerData: TopOrsakerData | null = null;

  // Troskelinst
  sparaTroskelLoading  = false;
  sparaTroskelMeddelande = '';
  sparaTroskelFel      = '';
  troskelForm = { varning: 3, alarm: 5 };

  // Polling guard
  private isFetchingAktuell  = false;
  private isFetchingHistorik = false;
  private isFetchingTrend    = false;
  private isFetchingPerSkift = false;

  // Chart
  private trendChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  // Skiftnamn
  readonly skiftNamn: Record<string, string> = {
    dag: 'Dag (06-14)',
    kvall: 'Kvall (14-22)',
    natt: 'Natt (22-06)',
  };

  constructor(private svc: KassationskvotAlarmService) {}

  ngOnInit(): void {
    this.laddaAll();

    // Auto-polling var 60:e sekund
    interval(60000)
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.laddaAktuell();
        this.laddaTrend();
        this.laddaHistorik();
        this.laddaPerSkift();
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;
  }

  laddaAll(): void {
    this.laddaAktuell();
    this.laddaHistorik();
    this.laddaTrend();
    this.laddaPerSkift();
    this.laddaOrsaker();
  }

  laddaAktuell(): void {
    if (this.isFetchingAktuell) return;
    this.isFetchingAktuell = true;
    this.loadingAktuell = true;
    this.errorAktuell = false;
    this.svc.getAktuellKvot()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingAktuell = false;
        this.isFetchingAktuell = false;
        if (res?.success) {
          this.aktuellData = res.data;
          // Fyll troskelformularet om vi inte sparar
          if (!this.sparaTroskelLoading && res.data.troskel) {
            this.troskelForm.varning = res.data.troskel.varning_procent;
            this.troskelForm.alarm   = res.data.troskel.alarm_procent;
          }
        } else {
          this.errorAktuell = true;
        }
      });
  }

  laddaHistorik(): void {
    if (this.isFetchingHistorik) return;
    this.isFetchingHistorik = true;
    this.loadingHistorik = true;
    this.errorHistorik = false;
    this.svc.getAlarmHistorik(30)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingHistorik = false;
        this.isFetchingHistorik = false;
        if (res?.success) {
          this.historikData = res.data;
        } else {
          this.errorHistorik = true;
        }
      });
  }

  laddaTrend(): void {
    if (this.isFetchingTrend) return;
    this.isFetchingTrend = true;
    this.loadingTrend = true;
    this.errorTrend = false;
    this.svc.getTimvisTrend()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingTrend = false;
        this.isFetchingTrend = false;
        if (res?.success) {
          this.trendData = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.byggTrendChart(); }, 0);
        } else {
          this.errorTrend = true;
        }
      });
  }

  laddaPerSkift(): void {
    if (this.isFetchingPerSkift) return;
    this.isFetchingPerSkift = true;
    this.loadingPerSkift = true;
    this.errorPerSkift = false;
    this.svc.getPerSkift()
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingPerSkift = false;
        this.isFetchingPerSkift = false;
        if (res?.success) {
          this.perSkiftData = res.data;
        } else {
          this.errorPerSkift = true;
        }
      });
  }

  laddaOrsaker(): void {
    this.loadingOrsaker = true;
    this.errorOrsaker = false;
    this.svc.getTopOrsaker(30)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOrsaker = false;
        if (res?.success) {
          this.orsakerData = res.data;
        } else {
          this.errorOrsaker = true;
        }
      });
  }

  sparaTroskel(): void {
    this.sparaTroskelMeddelande = '';
    this.sparaTroskelFel = '';
    const v = Number(this.troskelForm.varning);
    const a = Number(this.troskelForm.alarm);
    if (!v || !a || v <= 0 || a <= 0 || v >= 100 || a >= 100) {
      this.sparaTroskelFel = 'Ange procent mellan 0 och 100.';
      return;
    }
    if (v >= a) {
      this.sparaTroskelFel = 'Varningstroskeln maste vara lagre an alarmtroskeln.';
      return;
    }
    this.sparaTroskelLoading = true;
    this.svc.sparaTroskel(v, a)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.sparaTroskelLoading = false;
        if (res?.success) {
          this.sparaTroskelMeddelande = 'Troskelvarden sparades!';
          setTimeout(() => { this.sparaTroskelMeddelande = ''; }, 3000);
          this.laddaAll();
        } else {
          this.sparaTroskelFel = 'Sparning misslyckades.';
        }
      });
  }

  // =============================================================
  // Chart.js — kassationstrend per timme 24h med trosklar
  // =============================================================

  private byggTrendChart(): void {
    try { this.trendChart?.destroy(); } catch (_) {}
    this.trendChart = null;

    const canvas = document.getElementById('kassationsTrendChart') as HTMLCanvasElement;
    if (!canvas || !this.trendData) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const trend   = this.trendData.trend;
    const labels  = trend.map(t => t.timme.substring(11, 16)); // HH:mm
    const kvotData = trend.map(t => t.kvot_pct);
    const troskel = this.trendData.troskel;

    // Punktfarg baserat pa troskel
    const pointColors = trend.map(t => {
      if (t.kvot_pct >= troskel.alarm_procent)   return '#fc8181';
      if (t.kvot_pct >= troskel.varning_procent) return '#f6ad55';
      return '#68d391';
    });

    this.trendChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Kassationskvot (%)',
            data: kvotData,
            borderColor: '#63b3ed',
            backgroundColor: 'rgba(99, 179, 237, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: pointColors,
            fill: true,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: '#e2e8f0', boxWidth: 12, padding: 12, font: { size: 11 } },
          },
          tooltip: {
            callbacks: {
              label: (item) => ` ${item.dataset.label}: ${item.raw}%`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, autoSkip: true, maxTicksLimit: 12 },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            beginAtZero: true,
            max: Math.max(15, (troskel.alarm_procent + 5)),
            ticks: { color: '#a0aec0', callback: (v) => v + '%' },
            grid: { color: 'rgba(255,255,255,0.08)' },
            title: { display: true, text: 'Kassationskvot (%)', color: '#a0aec0', font: { size: 11 } },
          },
        },
      },
      plugins: [
        {
          id: 'troskelLinjer',
          afterDraw: (chart: Chart) => {
            const ctx2 = chart.ctx;
            const yAxis = chart.scales['y'];
            const xAxis = chart.scales['x'];
            if (!yAxis || !xAxis) return;

            const drawHLine = (val: number, color: string, label: string) => {
              const y = yAxis.getPixelForValue(val);
              ctx2.save();
              ctx2.setLineDash([6, 4]);
              ctx2.strokeStyle = color;
              ctx2.lineWidth = 1.5;
              ctx2.beginPath();
              ctx2.moveTo(xAxis.left, y);
              ctx2.lineTo(xAxis.right, y);
              ctx2.stroke();
              ctx2.fillStyle = color;
              ctx2.font = '11px sans-serif';
              ctx2.fillText(label + ' ' + val + '%', xAxis.left + 4, y - 4);
              ctx2.restore();
            };

            drawHLine(troskel.varning_procent, '#f6ad55', 'Varning');
            drawHLine(troskel.alarm_procent, '#fc8181', 'Alarm');
          },
        },
      ],
    });
  }

  // =============================================================
  // Hjalpmetoder
  // =============================================================

  fargKlass(farg: string | undefined): string {
    if (farg === 'rod') return 'kpi-rod';
    if (farg === 'gul') return 'kpi-gul';
    return 'kpi-gron';
  }

  statusBadge(status: string): string {
    return status === 'alarm' ? 'badge-rod' : 'badge-gul';
  }

  statusText(status: string): string {
    return status === 'alarm' ? 'ALARM' : 'VARNING';
  }

  skiftFarg(skiftNamn: string | undefined): string {
    if (skiftNamn === 'dag')   return '#f6ad55';
    if (skiftNamn === 'kvall') return '#63b3ed';
    return '#9f7aea';
  }

  maxOrsaker(): number {
    if (!this.orsakerData?.orsaker?.length) return 1;
    return Math.max(...this.orsakerData.orsaker.map(o => o.antal));
  }

  orsakBredd(antal: number): number {
    const max = this.maxOrsaker();
    return max > 0 ? Math.round((antal / max) * 100) : 0;
  }
}
