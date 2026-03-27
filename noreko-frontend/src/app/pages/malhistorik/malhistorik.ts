import { Component, OnInit, OnDestroy, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';

import {
  MalhistorikService,
  GoalHistoryData,
  GoalImpactData,
} from '../../services/malhistorik.service';
import { localToday, localDateStr, parseLocalDate } from '../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-malhistorik',
  templateUrl: './malhistorik.html',
  styleUrls: ['./malhistorik.css'],
  imports: [CommonModule, FormsModule],
})
export class MalhistorikComponent implements OnInit, OnDestroy, AfterViewInit {

  // Laddningstillstånd
  historyLoading = false;
  historyLoaded  = false;
  impactLoading  = false;
  impactLoaded   = false;

  // Data
  historyData: GoalHistoryData | null = null;
  impactData:  GoalImpactData  | null = null;

  // Felmeddelanden
  historyError: string | null = null;
  impactError:  string | null = null;

  // Chart
  private tidslinjeChart: Chart | null = null;
  private viewReady = false;
  private historyReadyForChart = false;

  private destroy$ = new Subject<void>();

  constructor(private service: MalhistorikService) {}

  ngOnInit(): void {
    this.loadHistory();
    this.loadImpact();
  }

  ngAfterViewInit(): void {
    this.viewReady = true;
    if (this.historyReadyForChart) {
      this.buildChart();
    }
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.tidslinjeChart) {
      this.tidslinjeChart.destroy();
      this.tidslinjeChart = null;
    }
  }

  // ---- Datahämtning ----

  loadHistory(): void {
    this.historyLoading = true;
    this.historyError   = null;
    this.service.getGoalHistory()
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.historyLoading = false;
        if (res?.success && res.data) {
          this.historyData  = res.data;
          this.historyLoaded = true;
          this.historyReadyForChart = true;
          if (this.viewReady) {
            this.buildChart();
          }
        } else {
          this.historyError = 'Kunde inte hämta målhistorik.';
        }
      });
  }

  loadImpact(): void {
    this.impactLoading = true;
    this.impactError   = null;
    this.service.getGoalImpact()
      .pipe(catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.impactLoading = false;
        if (res?.success && res.data) {
          this.impactData  = res.data;
          this.impactLoaded = true;
        } else {
          this.impactError = 'Kunde inte hämta impact-data.';
        }
      });
  }

  // ---- Chart.js tidslinje ----

  private buildChart(): void {
    const andringar = this.historyData?.andringar ?? [];
    if (andringar.length === 0) return;

    const canvas = document.getElementById('tidslinjeChart') as HTMLCanvasElement | null;
    if (!canvas) return;

    if (this.tidslinjeChart) {
      this.tidslinjeChart.destroy();
      this.tidslinjeChart = null;
    }

    // Steg-linje för målet: varje ändring skapar en steg-punkt
    // Vi lägger till en punkt direkt innan nästa ändring för att få "trappa"-effekten
    const malLabels: string[] = [];
    const malValues: number[] = [];

    andringar.forEach((a, i) => {
      const datum = a.andrad_vid.split(' ')[0];
      malLabels.push(datum);
      malValues.push(a.nytt_mal);

      // Lägg till en punkt precis innan nästa ändring (dagen innan)
      if (i < andringar.length - 1) {
        const naestaDatum = andringar[i + 1].andrad_vid.split(' ')[0];
        const dagenInnan  = parseLocalDate(naestaDatum);
        dagenInnan.setDate(dagenInnan.getDate() - 1);
        malLabels.push(localDateStr(dagenInnan));
        malValues.push(a.nytt_mal);
      }
    });

    // Lägg till "idag" som sista punkt om senaste målet gäller
    const idag = localToday();
    const sistaAndring = andringar[andringar.length - 1];
    if (sistaAndring && sistaAndring.andrad_vid.split(' ')[0] !== idag) {
      malLabels.push(idag);
      malValues.push(sistaAndring.nytt_mal);
    }

    if (this.tidslinjeChart) { (this.tidslinjeChart as any).destroy(); }
    this.tidslinjeChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: malLabels,
        datasets: [
          {
            label: 'Produktionsmål (IBC/h)',
            data: malValues,
            borderColor: 'rgba(246, 173, 85, 0.9)',
            backgroundColor: 'rgba(246, 173, 85, 0.12)',
            borderWidth: 2,
            pointRadius: malLabels.map((_, i) =>
              // Visa punkt bara vid faktiska ändringar (varannan — ändringsdatum)
              i % 2 === 0 ? 5 : 0
            ),
            pointHoverRadius: 7,
            pointBackgroundColor: 'rgba(246, 173, 85, 1)',
            stepped: true,
            fill: true,
            tension: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y} IBC/h`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', font: { size: 11 }, maxRotation: 45 },
            grid:  { color: 'rgba(255,255,255,0.06)' },
          },
          y: {
            ticks: { color: '#a0aec0', font: { size: 11 } },
            grid:  { color: 'rgba(255,255,255,0.06)' },
            title: {
              display: true,
              text: 'IBC/h',
              color: '#718096',
              font: { size: 11 },
            },
          },
        },
      },
    });
  }

  // ---- Hjälpmetoder för template ----

  getRiktningClass(riktning: string): string {
    switch (riktning) {
      case 'upp':       return 'text-success';
      case 'ner':       return 'text-danger';
      case 'oforandrad': return 'text-muted';
      default:          return 'text-info';
    }
  }

  getRiktningIcon(riktning: string): string {
    switch (riktning) {
      case 'upp':       return 'fa-arrow-up';
      case 'ner':       return 'fa-arrow-down';
      case 'oforandrad': return 'fa-minus';
      default:          return 'fa-flag';
    }
  }

  getEffektClass(effekt: string): string {
    switch (effekt) {
      case 'forbattring': return 'impact-green';
      case 'forsämring':  return 'impact-red';
      case 'oforandrad':  return 'impact-neutral';
      case 'ny-start':    return 'impact-blue';
      default:            return 'impact-none';
    }
  }

  getEffektLabel(effekt: string): string {
    switch (effekt) {
      case 'forbattring': return 'Förbättring';
      case 'forsämring':  return 'Försämring';
      case 'oforandrad':  return 'Oförändrad';
      case 'ny-start':    return 'Ny start';
      default:            return 'Ingen data';
    }
  }

  getEffektIcon(effekt: string): string {
    switch (effekt) {
      case 'forbattring': return 'fa-arrow-trend-up';
      case 'forsämring':  return 'fa-arrow-trend-down';
      case 'oforandrad':  return 'fa-grip-lines';
      case 'ny-start':    return 'fa-play';
      default:            return 'fa-question';
    }
  }

  formatDatum(datum: string | null): string {
    if (!datum) return '—';
    return datum.split(' ')[0];
  }

  formatTid(datum: string | null): string {
    if (!datum) return '—';
    const delar = datum.split(' ');
    return delar.length > 1 ? delar[1].substring(0, 5) : delar[0];
  }

  maluppfyllnadClass(pct: number): string {
    if (pct >= 100) return 'text-success';
    if (pct >= 80)  return 'text-warning';
    return 'text-danger';
  }

  // Sammanfattning: genomsnittlig impact av alla ändringar med data
  getSnittImpact(): number | null {
    const items = this.impactData?.impact ?? [];
    const medData = items.filter(i => i.diff_proc !== null && i.fore.antal_dagar > 0 && i.efter.antal_dagar > 0);
    if (medData.length === 0) return null;
    const summa = medData.reduce((acc, i) => acc + (i.diff_proc ?? 0), 0);
    return Math.round((summa / medData.length) * 10) / 10;
  }

  getAntalForbattringar(): number {
    return (this.impactData?.impact ?? []).filter(i => i.effekt === 'forbattring').length;
  }

  getAntalForsamringar(): number {
    return (this.impactData?.impact ?? []).filter(i => i.effekt === 'forsämring').length;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
