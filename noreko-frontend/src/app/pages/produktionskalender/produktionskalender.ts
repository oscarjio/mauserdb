import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';

import {
  ProduktionskalenderService,
  MonthData,
  DagData,
  VeckoData,
  DayDetail,
} from '../../services/produktionskalender.service';
import { parseLocalDate } from '../../utils/date-utils';

const MANAD_NAMN = [
  '', 'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
  'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'
];

const DAG_NAMN = ['Mån', 'Tis', 'Ons', 'Tor', 'Fre', 'Lör', 'Sön'];

interface KalenderDag {
  datum: string | null;         // null = tom ruta utanför månaden
  dagNr: number | null;
  veckodag: number;             // 0 = mån, 6 = sön
  helg: boolean;
  dagData: DagData | null;
}

interface KalenderVecka {
  veckoNr: number;
  dagar: KalenderDag[];
  snittIbc: number;
  snittKval: number;
  foregSnitt: number | null;
  trend: 'upp' | 'ner' | 'stabil' | null;
}

@Component({
  standalone: true,
  selector: 'app-produktionskalender',
  templateUrl: './produktionskalender.html',
  styleUrls: ['./produktionskalender.css'],
  imports: [CommonModule, FormsModule],
})
export class ProduktionskalenderComponent implements OnInit, OnDestroy {
  // Navigering
  selectedYear: number  = new Date().getFullYear();
  selectedMonth: number = new Date().getMonth() + 1;

  // Månadsväljare
  readonly years: number[] = this.buildYears();
  readonly months: { nr: number; namn: string }[] = MANAD_NAMN
    .slice(1)
    .map((namn, i) => ({ nr: i + 1, namn }));

  // Laddningstillstånd
  manadLoading = false;
  manadLoaded  = false;
  manadFel     = false;

  detaljLoading = false;
  detaljLoaded  = false;

  // Data
  manadData: MonthData | null = null;
  kalender: KalenderVecka[] = [];
  dagNamn = DAG_NAMN;
  manadNamn = MANAD_NAMN;

  // Vald dag
  valdDag: string | null = null;
  dagDetalj: DayDetail | null = null;
  panelVisible = false;

  private destroy$ = new Subject<void>();

  constructor(private service: ProduktionskalenderService) {}

  ngOnInit(): void {
    this.laddaManad();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ================================================================
  // MÅNADSNAVIGERING
  // ================================================================

  foregaendeManad(): void {
    this.selectedMonth--;
    if (this.selectedMonth < 1) {
      this.selectedMonth = 12;
      this.selectedYear--;
    }
    this.stangPanel();
    this.laddaManad();
  }

  nastaManad(): void {
    this.selectedMonth++;
    if (this.selectedMonth > 12) {
      this.selectedMonth = 1;
      this.selectedYear++;
    }
    this.stangPanel();
    this.laddaManad();
  }

  onManadChange(): void {
    this.stangPanel();
    this.laddaManad();
  }

  // ================================================================
  // DATALADDNING
  // ================================================================

  laddaManad(): void {
    this.manadLoading = true;
    this.manadLoaded  = false;
    this.manadFel     = false;
    this.manadData    = null;
    this.kalender     = [];

    this.service
      .getMonthData(this.selectedYear, this.selectedMonth)
      .pipe(takeUntil(this.destroy$))
      .subscribe(resp => {
        this.manadLoading = false;
        if (resp?.success && resp.data) {
          this.manadData  = resp.data;
          this.manadLoaded = true;
          this.byggKalender();
        } else {
          this.manadFel = true;
        }
      });
  }

  klickaDag(dag: KalenderDag): void {
    if (!dag.datum || !dag.dagData?.har_data) return;

    if (this.valdDag === dag.datum) {
      this.stangPanel();
      return;
    }

    this.valdDag     = dag.datum;
    this.panelVisible = true;
    this.detaljLoading = true;
    this.detaljLoaded  = false;
    this.dagDetalj    = null;

    this.service
      .getDayDetail(dag.datum)
      .pipe(takeUntil(this.destroy$))
      .subscribe(resp => {
        this.detaljLoading = false;
        if (resp?.success && resp.data) {
          this.dagDetalj  = resp.data;
          this.detaljLoaded = true;
        }
      });
  }

  stangPanel(): void {
    this.panelVisible  = false;
    this.valdDag       = null;
    this.dagDetalj     = null;
    this.detaljLoaded  = false;
  }

  // ================================================================
  // KALENDERBYGGE
  // ================================================================

  private byggKalender(): void {
    if (!this.manadData) return;

    const { year, month, dagar } = this.manadData;

    // Första och sista dag i månaden
    const förstaDag = new Date(year, month - 1, 1);
    const sistaDag  = new Date(year, month, 0);
    const antalDagar = sistaDag.getDate();

    // Veckodag för måndens första dag (0=mån, 6=sön)
    let förstVeckodag = förstaDag.getDay() - 1;
    if (förstVeckodag < 0) förstVeckodag = 6;

    // Bygg rad med tomma rutor + alla dagar i månaden
    const allaDagar: KalenderDag[] = [];

    // Fyll tomma rutor i början
    for (let i = 0; i < förstVeckodag; i++) {
      allaDagar.push({ datum: null, dagNr: null, veckodag: i, helg: false, dagData: null });
    }

    for (let d = 1; d <= antalDagar; d++) {
      const datumStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const veckodag = (förstVeckodag + d - 1) % 7;
      allaDagar.push({
        datum: datumStr,
        dagNr: d,
        veckodag,
        helg: veckodag >= 5,
        dagData: dagar[datumStr] ?? null,
      });
    }

    // Dela upp i veckor (7 dagar per rad)
    this.kalender = [];
    const veckoMap: { [v: number]: VeckoData } = {};
    for (const v of this.manadData.veckor) {
      veckoMap[v.vecka] = v;
    }

    for (let i = 0; i < allaDagar.length; i += 7) {
      const veckasDagar = allaDagar.slice(i, i + 7);
      // Fyll upp till 7 om sista veckan är kort
      while (veckasDagar.length < 7) {
        veckasDagar.push({ datum: null, dagNr: null, veckodag: veckasDagar.length, helg: false, dagData: null });
      }

      // Hämta veckonummer från första giltiga dag i veckan
      let veckoNr = 0;
      for (const d of veckasDagar) {
        if (d.datum) {
          const ts = new Date(d.datum);
          const jan4 = new Date(ts.getFullYear(), 0, 4);
          const startOfWeek1 = new Date(jan4);
          startOfWeek1.setDate(jan4.getDate() - ((jan4.getDay() + 6) % 7));
          veckoNr = Math.ceil((ts.getTime() - startOfWeek1.getTime()) / (7 * 24 * 3600 * 1000)) + 1;
          break;
        }
      }

      const veckoInfo = veckoMap[veckoNr];

      this.kalender.push({
        veckoNr,
        dagar: veckasDagar,
        snittIbc:   veckoInfo?.snitt_ibc   ?? 0,
        snittKval:  veckoInfo?.snitt_kval  ?? 0,
        foregSnitt: veckoInfo?.foreg_snitt ?? null,
        trend:      veckoInfo?.trend       ?? null,
      });
    }
  }

  // ================================================================
  // HJÄLPMETODER för templaten
  // ================================================================

  fargKlass(dag: KalenderDag): string {
    if (!dag.dagData?.har_data) return '';
    return 'dag-' + dag.dagData.farg;
  }

  trendIkon(trend: string | null): string {
    if (trend === 'upp')    return 'fa-arrow-up text-success';
    if (trend === 'ner')    return 'fa-arrow-down text-danger';
    if (trend === 'stabil') return 'fa-minus text-warning';
    return '';
  }

  formateraTid(sekunder: number): string {
    if (!sekunder || sekunder <= 0) return '—';
    const h = Math.floor(sekunder / 3600);
    const m = Math.floor((sekunder % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
  }

  formateraDatum(datum: string | null): string {
    if (!datum) return '—';
    const d = parseLocalDate(datum);
    return d.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  private buildYears(): number[] {
    const yr = new Date().getFullYear();
    const result = [];
    for (let y = yr - 3; y <= yr + 1; y++) result.push(y);
    return result;
  }

  get aktuelltManadNamn(): string {
    return MANAD_NAMN[this.selectedMonth] + ' ' + this.selectedYear;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
