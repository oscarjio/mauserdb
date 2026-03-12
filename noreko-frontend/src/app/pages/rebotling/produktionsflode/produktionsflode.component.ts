import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  ProduktionsflodeService,
  FlodeOverview,
  FlodeData,
  StationDetaljerData,
} from '../../../services/produktionsflode.service';
import { PdfExportButtonComponent } from '../../../components/pdf-export-button/pdf-export-button.component';

@Component({
  standalone: true,
  selector: 'app-produktionsflode',
  templateUrl: './produktionsflode.component.html',
  styleUrls: ['./produktionsflode.component.css'],
  imports: [CommonModule, FormsModule, PdfExportButtonComponent],
})
export class ProduktionsflodePage implements OnInit, OnDestroy {

  // Loading
  loadingOverview  = false;
  loadingFlode     = false;
  loadingStationer = false;

  // Data
  overview: FlodeOverview | null          = null;
  flodeData: FlodeData | null             = null;
  stationerData: StationDetaljerData | null = null;

  // Filter
  selectedDays = 7;

  // SVG Sankey dimensions
  svgWidth  = 900;
  svgHeight = 500;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;

  constructor(private svc: ProduktionsflodeService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 120000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
  }

  // ---- Period ----

  selectPeriod(days: number): void {
    this.selectedDays = days;
    this.loadAll();
  }

  // ---- Load ----

  loadAll(): void {
    this.loadOverview();
    this.loadFlode();
    this.loadStationer();
  }

  loadOverview(): void {
    this.loadingOverview = true;
    this.svc.getOverview(this.selectedDays)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingOverview = false;
        if (res?.success) this.overview = res.data;
      });
  }

  loadFlode(): void {
    this.loadingFlode = true;
    this.svc.getFlodeData(this.selectedDays)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingFlode = false;
        if (res?.success) this.flodeData = res.data;
      });
  }

  loadStationer(): void {
    this.loadingStationer = true;
    this.svc.getStationDetaljer(this.selectedDays)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loadingStationer = false;
        if (res?.success) this.stationerData = res.data;
      });
  }

  // ---- SVG Sankey helpers ----

  get sankeyNodes(): Array<{id: string; label: string; x: number; y: number; w: number; h: number; color: string; value: number}> {
    if (!this.flodeData) return [];

    const nodes = this.flodeData.nodes;
    const stations = this.flodeData.stations;
    const summary = this.flodeData.summary;
    const total = summary.total || 1;

    const padding = 60;
    const usableW = this.svgWidth - padding * 2;
    const usableH = this.svgHeight - padding * 2;
    const nodeW = 28;

    // Columns: inkommande, station1..N, godkand+kassation
    const colCount = nodes.length > 0 ? 2 + stations.length : 0;
    if (colCount === 0) return [];

    const colSpacing = usableW / (colCount - 1);

    const result: Array<{id: string; label: string; x: number; y: number; w: number; h: number; color: string; value: number}> = [];

    // Inkommande
    const inH = Math.max(20, (total / total) * (usableH * 0.7));
    result.push({
      id: 'inkommande', label: 'Inkommande', x: padding, y: padding + (usableH - inH) / 2,
      w: nodeW, h: inH, color: '#4299e1', value: total,
    });

    // Stationer
    stations.forEach((s, i) => {
      const val = s.inkommande;
      const h = Math.max(15, (val / total) * (usableH * 0.7));
      result.push({
        id: s.id, label: s.name, x: padding + colSpacing * (i + 1), y: padding + (usableH - h) / 2,
        w: nodeW, h: h,
        color: s.genomstromning_pct < 90 ? '#ed8936' : '#48bb78',
        value: val,
      });
    });

    // Godkand
    const okH = Math.max(15, (summary.godkanda / total) * (usableH * 0.7));
    const kassH = Math.max(15, (summary.kasserade / total) * (usableH * 0.7));
    const lastX = padding + colSpacing * (colCount - 1);

    result.push({
      id: 'godkand', label: 'Godkand', x: lastX, y: padding + 20,
      w: nodeW, h: okH, color: '#48bb78', value: summary.godkanda,
    });

    result.push({
      id: 'kassation', label: 'Kassation', x: lastX, y: padding + 20 + okH + 40,
      w: nodeW, h: kassH, color: '#fc8181', value: summary.kasserade,
    });

    return result;
  }

  get sankeyLinks(): Array<{d: string; color: string; opacity: number; strokeWidth: number; value: number; from: string; to: string}> {
    if (!this.flodeData) return [];

    const nodes = this.sankeyNodes;
    const links = this.flodeData.links;
    const total = this.flodeData.summary.total || 1;

    const nodeMap = new Map<string, typeof nodes[0]>();
    nodes.forEach(n => nodeMap.set(n.id, n));

    // Track vertical offsets for each node (source-side and target-side)
    const sourceOffsets = new Map<string, number>();
    const targetOffsets = new Map<string, number>();

    return links.map(link => {
      const fromNode = nodeMap.get(link.from);
      const toNode   = nodeMap.get(link.to);
      if (!fromNode || !toNode) return null;

      const sw = Math.max(3, (link.value / total) * (this.svgHeight * 0.5));

      // Calculate source Y position
      const srcOff = sourceOffsets.get(link.from) || 0;
      const srcY = fromNode.y + srcOff + sw / 2;
      sourceOffsets.set(link.from, srcOff + sw);

      // Calculate target Y position
      const tgtOff = targetOffsets.get(link.to) || 0;
      const tgtY = toNode.y + tgtOff + sw / 2;
      targetOffsets.set(link.to, tgtOff + sw);

      const x1 = fromNode.x + fromNode.w;
      const x2 = toNode.x;
      const cx = (x1 + x2) / 2;

      const d = `M ${x1} ${srcY} C ${cx} ${srcY}, ${cx} ${tgtY}, ${x2} ${tgtY}`;
      const color = link.type === 'kassation' ? '#fc8181' : '#4299e1';

      return { d, color, opacity: 0.35, strokeWidth: sw, value: link.value, from: link.from, to: link.to };
    }).filter(Boolean) as any[];
  }

  // ---- Helpers ----

  formatPct(val: number | null | undefined): string {
    if (val == null) return '-';
    return val.toFixed(1) + '%';
  }

  periodLabel(days: number): string {
    switch (days) {
      case 1:  return 'Idag';
      case 7:  return '7 dagar';
      case 30: return '30 dagar';
      case 90: return '90 dagar';
      default: return `${days} dagar`;
    }
  }

  stationStatusClass(pct: number): string {
    if (pct >= 98) return 'text-success';
    if (pct >= 95) return 'text-info';
    if (pct >= 90) return 'text-warning';
    return 'text-danger';
  }
}
