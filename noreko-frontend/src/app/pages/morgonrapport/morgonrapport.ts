import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  MorgonrapportService,
  MorgonrapportData,
  ProduktionData,
  EffektivitetData,
  StoppData,
  KvalitetData,
  TrenderData,
  Highlight,
  Varning,
} from '../../services/morgonrapport.service';

@Component({
  standalone: true,
  selector: 'app-morgonrapport',
  templateUrl: './morgonrapport.html',
  styleUrls: ['./morgonrapport.css'],
  imports: [CommonModule, FormsModule],
})
export class MorgonrapportPage implements OnInit, OnDestroy {
  // -- Datum --
  valtDatum: string = '';

  // -- Laddning / fel --
  loading = false;
  error = false;
  errorMessage = '';

  // -- Data --
  rapportData: MorgonrapportData | null = null;

  private destroy$ = new Subject<void>();

  constructor(private svc: MorgonrapportService) {}

  ngOnInit(): void {
    // Default: gårdagen
    this.valtDatum = this.getYesterday();
    this.loadRapport();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // =================================================================
  // Datum
  // =================================================================

  private getYesterday(): string {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    return this.formatDateISO(d);
  }

  private formatDateISO(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  onDatumChange(): void {
    if (this.valtDatum) {
      this.loadRapport();
    }
  }

  // =================================================================
  // Data
  // =================================================================

  loadRapport(): void {
    this.loading = true;
    this.error = false;
    this.rapportData = null;

    this.svc.getRapport(this.valtDatum)
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (res?.success) {
          this.rapportData = res.data;
        } else {
          this.error = true;
          this.errorMessage = 'Kunde inte ladda morgonrapporten. Kontrollera att du är inloggad.';
        }
      });
  }

  skrivUt(): void {
    window.print();
  }

  // =================================================================
  // Hjälpmetoder — formatering
  // =================================================================

  formatNumber(n: number | null | undefined): string {
    if (n === null || n === undefined) return '-';
    return n.toLocaleString('sv-SE');
  }

  formatDecimal(n: number | null | undefined, decimaler = 1): string {
    if (n === null || n === undefined) return '-';
    return n.toFixed(decimaler);
  }

  formatDatum(d: string): string {
    if (!d) return '-';
    const parts = d.split('-');
    if (parts.length === 3) {
      return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return d;
  }

  formatVeckodag(d: string): string {
    if (!d) return '-';
    const dagar = ['Söndag', 'Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag'];
    // Append T00:00:00 sa att datumet tolkas i lokal tid (undviker UTC-midnatt DST-bugg)
    const dt = new Date(d.length === 10 ? d + 'T00:00:00' : d);
    return dagar[dt.getDay()] ?? d;
  }

  // =================================================================
  // Hjälpmetoder — trendpilar
  // =================================================================

  getTrendPil(pct: number): string {
    if (pct > 5) return '▲';
    if (pct < -5) return '▼';
    return '→';
  }

  getTrendKlass(pct: number, hogreArBattre = true): string {
    if (hogreArBattre) {
      if (pct > 5) return 'trend-up';
      if (pct < -5) return 'trend-down';
    } else {
      if (pct > 5) return 'trend-down';
      if (pct < -5) return 'trend-up';
    }
    return 'trend-neutral';
  }

  // =================================================================
  // Hjälpmetoder — varningsikoner
  // =================================================================

  getSeverityKlass(severity: string): string {
    switch (severity) {
      case 'rod':  return 'varning-rod';
      case 'gul':  return 'varning-gul';
      case 'gron': return 'varning-gron';
      default:     return 'varning-gul';
    }
  }

  getSeverityIkon(severity: string): string {
    switch (severity) {
      case 'rod':  return '✕';
      case 'gul':  return '⚠';
      case 'gron': return '✓';
      default:     return '⚠';
    }
  }

  // =================================================================
  // Getter-genvägar
  // =================================================================

  get produktion(): ProduktionData | null {
    return this.rapportData?.produktion ?? null;
  }

  get effektivitet(): EffektivitetData | null {
    return this.rapportData?.effektivitet ?? null;
  }

  get stopp(): StoppData | null {
    return this.rapportData?.stopp ?? null;
  }

  get kvalitet(): KvalitetData | null {
    return this.rapportData?.kvalitet ?? null;
  }

  get trender(): TrenderData | null {
    return this.rapportData?.trender ?? null;
  }

  get highlights(): Highlight | null {
    return this.rapportData?.highlights ?? null;
  }

  get varningar(): Varning[] {
    return this.rapportData?.varningar ?? [];
  }

  get harVarningar(): boolean {
    return this.varningar.some(v => v.severity === 'rod' || v.severity === 'gul');
  }

  get uppfyllnadFarg(): string {
    const pct = this.produktion?.uppfyllnad_pct ?? 0;
    if (pct >= 100) return '#48bb78';
    if (pct >= 80)  return '#ecc94b';
    return '#fc8181';
  }

  get trendDagar(): string[] {
    const data = this.trender?.daglig_ibc;
    if (!data) return [];
    return Object.keys(data).sort();
  }

  get trendIbcVarden(): number[] {
    const data = this.trender?.daglig_ibc;
    if (!data) return [];
    return this.trendDagar.map(d => data[d] ?? 0);
  }

  get maxTrendIbc(): number {
    const vals = this.trendIbcVarden;
    return vals.length > 0 ? Math.max(...vals) : 1;
  }

  getStapelHojd(antal: number): number {
    const max = this.maxTrendIbc;
    return max > 0 ? Math.round((antal / max) * 100) : 0;
  }

  getTrendIbcForDag(dag: string): number {
    const data = this.trender?.daglig_ibc;
    if (!data) return 0;
    return data[dag] ?? 0;
  }

  getStapelFarg(dag: string): string {
    if (dag === this.valtDatum) return '#4299e1';
    return '#2d6a9f';
  }

  formatDagEtikett(d: string): string {
    if (!d) return '';
    const parts = d.split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}` : d;
  }

  visaDagEtikett(index: number, total: number): boolean {
    if (total <= 10) return true;
    if (total <= 20) return index % 2 === 0;
    return index % 5 === 0 || index === total - 1;
  }
  trackByIndex(index: number): number { return index; }
}
