import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import {
  MaskinDrifttidService,
  HeatmapData,
  HeatmapRad,
  HeatmapCell,
  KpiData,
  DagDetaljData,
  StationOption,
} from '../../../services/maskin-drifttid.service';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';

@Component({
  standalone: true,
  selector: 'app-maskin-drifttid',
  templateUrl: './maskin-drifttid.html',
  styleUrls: ['./maskin-drifttid.css'],
  imports: [CommonModule, FormsModule, PdfExportButtonComponent],
})
export class MaskinDrifttidPage implements OnInit, OnDestroy {

  // Periodselektor
  dagar = 14;
  readonly dagarAlternativ = [
    { varde: 7,  etikett: '7 dagar' },
    { varde: 14, etikett: '14 dagar' },
    { varde: 30, etikett: '30 dagar' },
    { varde: 90, etikett: '90 dagar' },
  ];

  // Maskinfilter
  valdStation = 'alla';
  stationer: StationOption[] = [];

  // Loading
  loadingHeatmap = false;
  loadingKpi     = false;
  loadingDetalj  = false;

  // Error
  errorHeatmap = false;
  errorKpi     = false;
  errorDetalj  = false;

  // Data
  heatmapData: HeatmapData | null = null;
  kpiData:     KpiData | null     = null;
  dagDetalj:   DagDetaljData | null = null;

  // Vald dag (for dagsammanfattning)
  valdDag: string | null = null;

  // Tooltip
  tooltipVisible = false;
  tooltipX = 0;
  tooltipY = 0;
  tooltipTimme = '';
  tooltipAntal = 0;
  tooltipStatus = '';

  private destroy$ = new Subject<void>();

  constructor(private svc: MaskinDrifttidService) {}

  ngOnInit(): void {
    this.laddaStationer();
    this.laddaAllt();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // =============================================================
  // Period / Filter
  // =============================================================

  byttPeriod(d: number): void {
    this.dagar = d;
    this.valdDag = null;
    this.dagDetalj = null;
    this.laddaAllt();
  }

  byttStation(): void {
    this.laddaAllt();
  }

  laddaAllt(): void {
    this.laddaHeatmap();
    this.laddaKpi();
  }

  // =============================================================
  // Data
  // =============================================================

  laddaStationer(): void {
    this.svc.getStationer()
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          this.stationer = res.data.stationer ?? [];
        }
      });
  }

  laddaHeatmap(): void {
    this.loadingHeatmap = true;
    this.errorHeatmap   = false;
    this.svc.getHeatmap(this.dagar)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingHeatmap = false;
        if (res?.success) {
          this.heatmapData = res.data;
        } else {
          this.errorHeatmap = true;
          this.heatmapData = null;
        }
      });
  }

  laddaKpi(): void {
    this.loadingKpi = true;
    this.errorKpi   = false;
    this.svc.getKpi(this.dagar)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingKpi = false;
        if (res?.success) {
          this.kpiData = res.data;
        } else {
          this.errorKpi = true;
          this.kpiData = null;
        }
      });
  }

  // =============================================================
  // Dagsammanfattning
  // =============================================================

  valjDag(datum: string): void {
    if (this.valdDag === datum) {
      this.valdDag   = null;
      this.dagDetalj = null;
      return;
    }
    this.valdDag      = datum;
    this.loadingDetalj = true;
    this.errorDetalj   = false;
    this.dagDetalj     = null;

    this.svc.getDagDetalj(datum)
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingDetalj = false;
        if (res?.success) {
          this.dagDetalj = res.data;
        } else {
          this.errorDetalj = true;
        }
      });
  }

  // =============================================================
  // Tooltip
  // =============================================================

  visaTooltip(event: MouseEvent, cell: HeatmapCell, _rad: HeatmapRad): void {
    this.tooltipVisible = true;
    this.tooltipX = event.clientX + 12;
    this.tooltipY = event.clientY - 40;
    this.tooltipTimme = `${String(cell.timme).padStart(2, '0')}:00-${String(cell.timme + 1).padStart(2, '0')}:00`;
    this.tooltipAntal = cell.antal;
    this.tooltipStatus = this.statusText(cell.status);
  }

  flyttaTooltip(event: MouseEvent): void {
    this.tooltipX = event.clientX + 12;
    this.tooltipY = event.clientY - 40;
  }

  gomTooltip(): void {
    this.tooltipVisible = false;
  }

  // =============================================================
  // Hjalp
  // =============================================================

  cellBgFarg(status: string): string {
    switch (status) {
      case 'hog':     return '#48bb78';  // gron
      case 'lag':     return '#ecc94b';  // gul
      case 'stopp':   return '#fc8181';  // rod
      case 'utanfor': return '#4a5568';  // gra
      default:        return '#4a5568';
    }
  }

  cellOpacity(cell: HeatmapCell): number {
    if (cell.status === 'utanfor') return 0.4;
    if (cell.status === 'hog') {
      // Hogre antal = hogre opacitet
      const cap = Math.min(cell.antal, 20);
      return 0.5 + (cap / 20) * 0.5;
    }
    return 0.85;
  }

  statusText(status: string): string {
    switch (status) {
      case 'hog':     return 'Hog produktion';
      case 'lag':     return 'Lag produktion';
      case 'stopp':   return 'Stopp / Inaktiv';
      case 'utanfor': return 'Utanfor arbetstid';
      default:        return '';
    }
  }

  drifttidFarg(pct: number): string {
    if (pct >= 80) return '#48bb78';
    if (pct >= 50) return '#ecc94b';
    return '#fc8181';
  }

  formatDatum(d: string | null): string {
    if (!d) return '--';
    const parts = d.split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return d;
  }

  formatVeckodag(d: string | null): string {
    if (!d) return '';
    const dt = new Date(d + 'T00:00:00');
    const dagar = ['Son', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor'];
    return dagar[dt.getDay()];
  }

  isSelectedRow(datum: string): boolean {
    return this.valdDag === datum;
  }

  trackByDatum(_index: number, rad: HeatmapRad): string {
    return rad.datum;
  }

  trackByTimme(_index: number, cell: HeatmapCell): number {
    return cell.timme;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
