import { Component, OnInit, OnDestroy, ElementRef, ViewChild, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import {
  UnderhallsloggService,
  Station,
  RebotlingUnderhallsPost,
  Sammanfattning,
  PerStationRad,
  ManadsChartData,
  // Legacy
  UnderhallKategori,
  UnderhallsPost,
  UnderhallsStats
} from '../../services/underhallslogg.service';
import { localToday } from '../../utils/date-utils';

declare const Chart: any;

@Component({
  standalone: true,
  selector: 'app-underhallslogg',
  imports: [CommonModule, FormsModule],
  templateUrl: './underhallslogg.html',
  styleUrl: './underhallslogg.css'
})
export class UnderhallsloggComponent implements OnInit, OnDestroy, AfterViewInit {
  @ViewChild('manadChart', { static: false }) chartRef!: ElementRef<HTMLCanvasElement>;

  loggedIn = false;
  user: any = null;
  private destroy$ = new Subject<void>();

  // Tab
  activeTab: 'rebotling' | 'general' = 'rebotling';

  // Stationer
  stationer: Station[] = [];

  // KPI sammanfattning
  kpi: Sammanfattning | null = null;

  // Per station
  perStation: PerStationRad[] = [];
  perStationDays = 30;

  // Lista (rebotling)
  items: RebotlingUnderhallsPost[] = [];
  loadingItems = false;
  filterStation = 0;
  filterTyp = 'alla';
  filterFrom = '';
  filterTo = '';

  // Chart
  chartData: ManadsChartData | null = null;
  private chartInstance: any = null;
  private chartReady = false;

  // Formular (rebotling)
  showForm = false;
  formStationId = 0;
  formTyp: 'planerat' | 'oplanerat' = 'planerat';
  formBeskrivning = '';
  formVaraktighet: number | null = null;
  formStopporsak = '';
  formUtfordAv = '';
  formDatum = '';
  submitting = false;

  // ---- Legacy (general tab) ----
  kategorier: UnderhallKategori[] = [];
  legacyFormKategori = '';
  legacyFormTyp: 'planerat' | 'oplanerat' = 'planerat';
  legacyFormVaraktighet: number | null = null;
  legacyFormKommentar = '';
  legacyFormMaskin = 'Rebotling';
  legacySubmitting = false;
  historik: UnderhallsPost[] = [];
  loadingHistorik = false;
  legacyFilterDays = 30;
  legacyFilterType = 'all';
  legacyFilterCategory = 'all';
  stats: UnderhallsStats | null = null;
  loadingStats = false;
  deletingId: number | null = null;

  // Messages
  successMessage = '';
  errorMessage = '';
  private successTimer: any;

  constructor(
    private auth: AuthService,
    private svc: UnderhallsloggService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(v => this.loggedIn = v);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(v => {
      this.user = v;
      if (v && !this.formUtfordAv) this.formUtfordAv = v.username || '';
    });

    this.formDatum = new Date().toISOString().substring(0, 10);

    this.loadStationer();
    this.loadKpi();
    this.loadPerStation();
    this.loadItems();
    this.loadChart();
  }

  ngAfterViewInit(): void {
    this.chartReady = true;
    if (this.chartData) {
      this.renderChart();
    }
  }

  ngOnDestroy(): void {
    clearTimeout(this.successTimer);
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartInstance) {
      this.chartInstance.destroy();
      this.chartInstance = null;
    }
  }

  // =========================================================================
  // Data loading
  // =========================================================================

  loadStationer(): void {
    this.svc.getStationer().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) this.stationer = res.stationer;
    });
  }

  loadKpi(): void {
    this.svc.getSammanfattning().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.kpi = {
          totalt_denna_manad: res.totalt_denna_manad,
          total_tid_min: res.total_tid_min,
          planerat_antal: res.planerat_antal,
          oplanerat_antal: res.oplanerat_antal,
          planerat_pct: res.planerat_pct,
          oplanerat_pct: res.oplanerat_pct,
          snitt_tid_min: res.snitt_tid_min,
          top_station: res.top_station,
        };
      }
    });
  }

  loadPerStation(): void {
    this.svc.getPerStation(this.perStationDays).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) this.perStation = res.stationer;
    });
  }

  loadItems(): void {
    this.loadingItems = true;
    this.svc.getLista({
      station: this.filterStation > 0 ? this.filterStation : undefined,
      typ: this.filterTyp !== 'alla' ? this.filterTyp : undefined,
      from: this.filterFrom || undefined,
      to: this.filterTo || undefined,
      limit: 50,
    }).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.loadingItems = false;
      if (res?.success) this.items = res.items;
    });
  }

  loadChart(): void {
    this.svc.getManadsChart(6).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.chartData = { labels: res.labels, planerat: res.planerat, oplanerat: res.oplanerat };
        if (this.chartReady) this.renderChart();
      }
    });
  }

  onFilterChange(): void {
    this.loadItems();
  }

  onPerStationDaysChange(): void {
    this.loadPerStation();
  }

  refreshAll(): void {
    this.loadKpi();
    this.loadPerStation();
    this.loadItems();
    this.loadChart();
  }

  // =========================================================================
  // Chart
  // =========================================================================

  private renderChart(): void {
    if (!this.chartRef?.nativeElement || !this.chartData) return;
    if (typeof Chart === 'undefined') return;

    if (this.chartInstance) {
      this.chartInstance.destroy();
    }

    const ctx = this.chartRef.nativeElement.getContext('2d');
    this.chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: this.chartData.labels,
        datasets: [
          {
            label: 'Planerat',
            data: this.chartData.planerat,
            backgroundColor: 'rgba(72, 187, 120, 0.7)',
            borderColor: 'rgba(72, 187, 120, 1)',
            borderWidth: 1,
          },
          {
            label: 'Oplanerat',
            data: this.chartData.oplanerat,
            backgroundColor: 'rgba(245, 101, 101, 0.7)',
            borderColor: 'rgba(245, 101, 101, 1)',
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#a0aec0' },
          },
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(74, 85, 104, 0.3)' },
          },
          y: {
            beginAtZero: true,
            ticks: { color: '#a0aec0', stepSize: 1 },
            grid: { color: 'rgba(74, 85, 104, 0.3)' },
          },
        },
      },
    });
  }

  // =========================================================================
  // Form actions (Rebotling)
  // =========================================================================

  toggleForm(): void {
    this.showForm = !this.showForm;
    if (this.showForm) {
      this.formDatum = new Date().toISOString().substring(0, 10);
      this.formStationId = 0;
      this.formTyp = 'planerat';
      this.formBeskrivning = '';
      this.formVaraktighet = null;
      this.formStopporsak = '';
      this.formUtfordAv = this.user?.username || '';
    }
  }

  spara(): void {
    if (this.submitting) return;
    this.errorMessage = '';

    if (!this.formStationId) {
      this.errorMessage = 'Valj en station';
      return;
    }
    if (!this.formDatum) {
      this.errorMessage = 'Ange ett datum';
      return;
    }
    if (!this.formVaraktighet || this.formVaraktighet <= 0) {
      this.errorMessage = 'Ange varaktighet i minuter (minst 1)';
      return;
    }

    this.submitting = true;
    this.svc.skapa({
      station_id: this.formStationId,
      typ: this.formTyp,
      beskrivning: this.formBeskrivning,
      varaktighet_min: this.formVaraktighet,
      stopporsak: this.formStopporsak || undefined,
      utford_av: this.formUtfordAv || undefined,
      datum: this.formDatum,
    }).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      this.submitting = false;
      if (res?.success) {
        this.toast.success('Underhall registrerat!');
        this.showForm = false;
        this.refreshAll();
      } else {
        this.errorMessage = res?.error || 'Kunde inte spara — kontrollera anslutningen';
      }
    });
  }

  taBort(post: RebotlingUnderhallsPost): void {
    if (!confirm(`Ta bort underhallspost fran ${this.formatDatum(post.datum)}?`)) return;
    this.svc.taBort(post.id).pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.items = this.items.filter(i => i.id !== post.id);
        this.toast.success('Post borttagen');
        this.loadKpi();
        this.loadPerStation();
        this.loadChart();
      } else {
        this.toast.error(res?.error || 'Kunde inte ta bort — kontrollera anslutningen');
      }
    });
  }

  // =========================================================================
  // Legacy tab actions
  // =========================================================================

  switchTab(tab: 'rebotling' | 'general'): void {
    this.activeTab = tab;
    if (tab === 'general' && this.kategorier.length === 0) {
      this.loadLegacy();
    }
  }

  loadLegacy(): void {
    this.svc.getCategories().pipe(catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.kategorier = res.data;
        if (this.kategorier.length > 0 && !this.legacyFormKategori) {
          this.legacyFormKategori = this.kategorier[0].namn;
        }
      }
    });
    this.loadLegacyHistorik();
    this.loadLegacyStats();
  }

  loadLegacyHistorik(): void {
    this.loadingHistorik = true;
    this.svc.getList(this.legacyFilterDays, this.legacyFilterType, this.legacyFilterCategory)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingHistorik = false;
          if (res.success) this.historik = res.data;
        },
        error: () => { this.loadingHistorik = false; }
      });
  }

  loadLegacyStats(): void {
    this.loadingStats = true;
    this.svc.getStats(this.legacyFilterDays)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingStats = false;
          if (res.success && res.data) this.stats = res.data;
        },
        error: () => { this.loadingStats = false; }
      });
  }

  onLegacyFilterChange(): void {
    this.loadLegacyHistorik();
  }

  sparaLegacy(): void {
    if (this.legacySubmitting) return;
    this.errorMessage = '';

    if (!this.legacyFormKategori) {
      this.errorMessage = 'Valj en kategori';
      return;
    }
    if (!this.legacyFormVaraktighet || this.legacyFormVaraktighet <= 0) {
      this.errorMessage = 'Ange varaktighet i minuter (minst 1)';
      return;
    }

    this.legacySubmitting = true;
    this.svc.logUnderhall({
      kategori: this.legacyFormKategori,
      typ: this.legacyFormTyp,
      varaktighet_min: this.legacyFormVaraktighet,
      kommentar: this.legacyFormKommentar,
      maskin: this.legacyFormMaskin || 'Rebotling'
    }).pipe(takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.legacySubmitting = false;
        if (res.success) {
          this.toast.success('Underhall loggat!');
          this.legacyFormKommentar = '';
          this.legacyFormVaraktighet = null;
          this.legacyFormTyp = 'planerat';
          if (this.kategorier.length > 0) this.legacyFormKategori = this.kategorier[0].namn;
          this.loadLegacyHistorik();
          this.loadLegacyStats();
        } else {
          this.errorMessage = res.error || 'Kunde inte spara';
        }
      },
      error: () => {
        this.legacySubmitting = false;
        this.errorMessage = 'Anslutningsfel';
      }
    });
  }

  deleteLegacy(post: UnderhallsPost): void {
    if (this.deletingId !== null) return;
    if (!confirm(`Ta bort underhallspost fran ${this.formatDatum(post.created_at)}?`)) return;
    this.deletingId = post.id;
    this.svc.deleteEntry(post.id).pipe(takeUntil(this.destroy$)).subscribe({
      next: res => {
        this.deletingId = null;
        if (res.success) {
          this.historik = this.historik.filter(h => h.id !== post.id);
          this.loadLegacyStats();
          this.toast.success('Post borttagen');
        } else {
          this.toast.error(res.error || 'Kunde inte ta bort');
        }
      },
      error: () => { this.deletingId = null; }
    });
  }

  // =========================================================================
  // Helpers
  // =========================================================================

  formatDatum(dt: string): string {
    if (!dt) return '--';
    const d = new Date(dt);
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  formatTid(min: number): string {
    if (!min) return '0 min';
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h > 0 && m > 0) return `${h} h ${m} min`;
    if (h > 0) return `${h} h`;
    return `${m} min`;
  }

  get totalTidHistorik(): number {
    return this.historik.reduce((sum, h) => sum + (h.varaktighet_min || 0), 0);
  }

  maxKategoriAntal(): number {
    if (!this.stats || !this.stats.top_kategorier.length) return 1;
    return Math.max(...this.stats.top_kategorier.map(k => k.antal));
  }

  maxStationAntal(): number {
    if (!this.perStation.length) return 1;
    return Math.max(...this.perStation.map(s => s.antal));
  }

  exportCSV(): void {
    if (this.items.length === 0) return;
    const header = ['Datum', 'Station', 'Typ', 'Varaktighet (min)', 'Stopporsak', 'Utford av', 'Beskrivning'];
    const rows = this.items.map(i => [
      i.datum,
      i.station_namn,
      i.typ,
      String(i.varaktighet_min),
      i.stopporsak || '',
      i.utford_av || '',
      (i.beskrivning || '').replace(/"/g, '""')
    ]);
    const csvContent = [header, ...rows]
      .map(r => r.map(v => `"${v}"`).join(','))
      .join('\n');
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `rebotling_underhallslogg_${localToday()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }
  trackByIndex(index: number): number { return index; }
}
