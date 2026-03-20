import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  KvalitetscertifikatService,
  KvalitetOverviewData,
  Certifikat,
  OperatorFilter,
  Kriterium,
  StatistikItem,
} from '../../../services/kvalitetscertifikat.service';
import { localToday } from '../../../utils/date-utils';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-kvalitetscertifikat',
  templateUrl: './kvalitetscertifikat.component.html',
  styleUrls: ['./kvalitetscertifikat.component.css'],
  imports: [CommonModule, FormsModule],
})
export class KvalitetscertifikatPage implements OnInit, OnDestroy {

  // Loading states
  loadingOverview   = false;
  loadingLista      = false;
  loadingDetalj     = false;
  loadingStatistik  = false;

  // Error states
  errorData = false;
  errorLista = false;
  errorDetalj = false;
  errorStatistik = false;

  // Data
  overview: KvalitetOverviewData | null = null;
  certifikat: Certifikat[] = [];
  operatorer: OperatorFilter[] = [];
  selectedCert: Certifikat | null = null;
  selectedKriterier: Kriterium[] = [];
  statistik: StatistikItem[] = [];

  // Filters
  filterStatus   = '';
  filterPeriod   = '';
  filterOperator = 0;

  // Sorting
  sortColumn = 'datum';
  sortAsc    = false;

  // Modal
  showModal  = false;
  showGenereraModal = false;

  // Bedom
  bedomStatus   = '';
  bedomKommentar = '';
  bedomLoading  = false;
  bedomMessage  = '';
  bedomError    = '';

  // Generera
  genBatchNummer    = '';
  genDatum          = '';
  genOperatorNamn   = '';
  genOperatorId: number | null = null;
  genAntalIbc       = 0;
  genKassationPct   = 0;
  genCykeltidSnitt  = 0;
  genLoading        = false;
  genMessage        = '';
  genError          = '';

  // Chart
  private barChart: Chart | null = null;

  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private isFetching = false;

  constructor(private svc: KvalitetscertifikatService) {}

  ngOnInit(): void {
    this.genDatum = localToday();
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadOverview(), 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.barChart) {
      this.barChart.destroy();
      this.barChart = null;
    }
  }

  loadAll(): void {
    this.loadOverview();
    this.loadLista();
    this.loadStatistik();
  }

  // ---- Overview ----

  loadOverview(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loadingOverview = true;
    this.errorData = false;
    this.svc.getOverview().pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingOverview = false;
        this.isFetching = false;
        if (res?.success) { this.overview = res.data; }
        else if (res !== null) { this.errorData = true; }
    });
  }

  // ---- Lista ----

  loadLista(): void {
    this.loadingLista = true;
    this.errorLista = false;
    const status = this.filterStatus || undefined;
    const period = this.filterPeriod || undefined;
    const opId   = this.filterOperator > 0 ? this.filterOperator : undefined;

    this.svc.getLista(status, period, opId).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingLista = false;
        if (res?.success) {
          this.certifikat = res.data.certifikat;
          this.operatorer = res.data.operatorer;
          this.sortCertifikat();
        } else if (res !== null) {
          this.errorLista = true;
        }
    });
  }

  onFilterChange(): void {
    this.loadLista();
  }

  // ---- Sorting ----

  sortBy(col: string): void {
    if (this.sortColumn === col) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortColumn = col;
      this.sortAsc = false;
    }
    this.sortCertifikat();
  }

  private sortCertifikat(): void {
    const col = this.sortColumn;
    const dir = this.sortAsc ? 1 : -1;
    this.certifikat.sort((a: any, b: any) => {
      const av = a[col] ?? '';
      const bv = b[col] ?? '';
      if (typeof av === 'string') return av.localeCompare(bv) * dir;
      return (av - bv) * dir;
    });
  }

  sortIcon(col: string): string {
    if (this.sortColumn !== col) return 'fas fa-sort text-muted';
    return this.sortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
  }

  // ---- Detalj/Modal ----

  openCertifikat(cert: Certifikat): void {
    this.selectedCert = cert;
    this.showModal = true;
    this.bedomStatus = '';
    this.bedomKommentar = cert.kommentar || '';
    this.bedomMessage = '';
    this.bedomError = '';
    this.loadDetalj(cert.id);
  }

  loadDetalj(id: number): void {
    this.loadingDetalj = true;
    this.errorDetalj = false;
    this.svc.getDetalj(id).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingDetalj = false;
        if (res?.success) {
          this.selectedCert = res.data.certifikat as Certifikat;
          this.selectedKriterier = res.data.kriterier;
        } else if (res !== null) {
          this.errorDetalj = true;
        }
    });
  }

  closeModal(): void {
    this.showModal = false;
    this.selectedCert = null;
  }

  // ---- Bedom ----

  submitBedom(): void {
    if (!this.selectedCert || !this.bedomStatus) return;

    this.bedomLoading = true;
    this.bedomError = '';
    this.bedomMessage = '';

    this.svc.bedom(
      this.selectedCert.id,
      this.bedomStatus as 'godkand' | 'underkand',
      this.bedomKommentar
    ).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.bedomLoading = false;
        if (res?.success) {
          this.bedomMessage = res.message || 'Bedomning sparad';
          this.loadLista();
          this.loadOverview();
          this.loadStatistik();
          // Uppdatera valt certifikat
          if (this.selectedCert) {
            this.selectedCert.status = this.bedomStatus as any;
            this.selectedCert.kommentar = this.bedomKommentar;
          }
        } else {
          this.bedomError = res?.error || 'Kunde inte spara bedomning';
        }
    });
  }

  // ---- Generera ----

  openGenerera(): void {
    this.showGenereraModal = true;
    this.genMessage = '';
    this.genError = '';
  }

  closeGenerera(): void {
    this.showGenereraModal = false;
  }

  submitGenerera(): void {
    if (!this.genBatchNummer) {
      this.genError = 'Batchnummer kravs';
      return;
    }
    if (!this.genAntalIbc || this.genAntalIbc < 1) {
      this.genError = 'Antal IBC maste vara minst 1';
      return;
    }

    this.genLoading = true;
    this.genError = '';
    this.genMessage = '';

    this.svc.generera({
      batch_nummer: this.genBatchNummer,
      datum: this.genDatum,
      operator_id: this.genOperatorId || undefined,
      operator_namn: this.genOperatorNamn,
      antal_ibc: this.genAntalIbc,
      kassation_procent: this.genKassationPct,
      cykeltid_snitt: this.genCykeltidSnitt,
    }).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.genLoading = false;
        if (res?.success) {
          this.genMessage = `Certifikat skapat (Kvalitetspoang: ${res.kvalitetspoang})`;
          this.loadAll();
          // Reset form
          this.genBatchNummer = '';
          this.genOperatorNamn = '';
          this.genOperatorId = null;
          this.genAntalIbc = 0;
          this.genKassationPct = 0;
          this.genCykeltidSnitt = 0;
        } else {
          this.genError = res?.error || 'Kunde inte skapa certifikat';
        }
    });
  }

  // ---- Statistik/Chart ----

  loadStatistik(): void {
    this.loadingStatistik = true;
    this.errorStatistik = false;
    this.svc.getStatistik(30).pipe(takeUntil(this.destroy$)).subscribe(res => {
        this.loadingStatistik = false;
        if (res?.success) {
          this.statistik = res.data;
          setTimeout(() => { if (!this.destroy$.closed) this.renderChart(); }, 150);
        } else if (res !== null) {
          this.errorStatistik = true;
        }
    });
  }

  renderChart(): void {
    if (this.barChart) {
      this.barChart.destroy();
      this.barChart = null;
    }
    const canvas = document.getElementById('kvalitetChart') as HTMLCanvasElement | null;
    if (!canvas || this.statistik.length === 0) return;

    const labels = this.statistik.map(s => s.batch_nummer);
    const data   = this.statistik.map(s => +s.kvalitetspoang);
    const colors = this.statistik.map(s => {
      if (s.status === 'godkand') return '#48bb78';
      if (s.status === 'underkand') return '#e53e3e';
      return '#a0aec0';
    });

    // Berakna trendlinje (enkel linjear regression)
    const n = data.length;
    let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;
    for (let i = 0; i < n; i++) {
      sumX += i; sumY += data[i]; sumXY += i * data[i]; sumX2 += i * i;
    }
    const denom = n * sumX2 - sumX * sumX;
    const slope = denom !== 0 ? (n * sumXY - sumX * sumY) / denom : 0;
    const intercept = denom !== 0 ? (sumY - slope * sumX) / n : (n > 0 ? sumY / n : 0);
    const trendData = data.map((_, i) => +(slope * i + intercept).toFixed(1));

    this.barChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Kvalitetspoang',
            data,
            backgroundColor: colors,
            borderRadius: 3,
            order: 2,
          },
          {
            label: 'Trendlinje',
            data: trendData,
            type: 'line',
            borderColor: '#ecc94b',
            borderWidth: 2,
            borderDash: [5, 5],
            pointRadius: 0,
            fill: false,
            order: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.y}`,
            },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, font: { size: 10 } },
            grid: { color: '#4a556833' },
          },
          y: {
            beginAtZero: true,
            max: 100,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
        },
      },
    });
  }

  // ---- Helpers ----

  statusLabel(status: string): string {
    switch (status) {
      case 'godkand': return 'Godkand';
      case 'underkand': return 'Underkand';
      default: return 'Ej bedomd';
    }
  }

  statusBadgeClass(status: string): string {
    switch (status) {
      case 'godkand': return 'badge-godkand';
      case 'underkand': return 'badge-underkand';
      default: return 'badge-ej-bedomd';
    }
  }

  poangColor(poang: number): string {
    if (poang >= 90) return '#48bb78';
    if (poang >= 75) return '#ecc94b';
    if (poang >= 60) return '#ed8936';
    return '#e53e3e';
  }

  formatDate(d: string | null): string {
    if (!d) return '\u2014';
    return d.substring(0, 10);
  }

  formatDecimal(v: number | null | undefined, decimals: number = 1): string {
    if (v == null) return '\u2014';
    return (+v).toFixed(decimals);
  }

  printCertifikat(): void {
    window.print();
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
  trackByOperatorId(index: number, item: any): any { return item?.operator_id ?? index; }
}
