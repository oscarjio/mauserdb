import { Component, OnInit, OnDestroy, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { MaintenanceStats, MaintenanceEntry, EquipmentItem } from './maintenance-log.models';
import { formatDuration, formatCost } from './maintenance-log.helpers';
import { MaintenanceListComponent } from './components/maintenance-list.component';
import { EquipmentStatsComponent } from './components/equipment-stats.component';
import { KpiAnalysisComponent } from './components/kpi-analysis.component';
import { ServiceIntervalsComponent } from './components/service-intervals.component';
import { MaintenanceFormComponent } from './components/maintenance-form.component';

@Component({
  selector: 'app-maintenance-log',
  standalone: true,
  imports: [
    CommonModule,
    MaintenanceListComponent,
    EquipmentStatsComponent,
    KpiAnalysisComponent,
    ServiceIntervalsComponent,
    MaintenanceFormComponent
  ],
  template: `
<div class="maintenance-page">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h2 class="page-title mb-1">Underhållslogg</h2>
      <p class="page-subtitle mb-0">Registrera maskinunderhåll, reparationer och driftstopp</p>
    </div>
    <button class="btn btn-success btn-add" (click)="openAddForm()">
      <i class="fas fa-plus me-2"></i>Ny post
    </button>
  </div>

  <!-- Meddelanden -->
  <div *ngIf="successMessage" class="alert alert-success alert-dismissible mb-3" role="alert">
    <i class="fas fa-check-circle me-2"></i>{{ successMessage }}
  </div>
  <div *ngIf="errorMessage" class="alert alert-danger alert-dismissible mb-3" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>{{ errorMessage }}
    <button type="button" class="btn-close btn-close-white" (click)="errorMessage=''"></button>
  </div>

  <!-- KPI-rad -->
  <div class="row g-3 mb-4" *ngIf="stats">
    <div class="col-6 col-md-3">
      <div class="kpi-card">
        <div class="kpi-icon text-info"><i class="fas fa-clock"></i></div>
        <div class="kpi-value">{{ formatDuration(stats.total_minutes) }}</div>
        <div class="kpi-label">Total tid (30 dagar)</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="kpi-card">
        <div class="kpi-icon text-warning"><i class="fas fa-coins"></i></div>
        <div class="kpi-value">{{ formatCost(stats.total_cost) }}</div>
        <div class="kpi-label">Total kostnad</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="kpi-card">
        <div class="kpi-icon text-danger"><i class="fas fa-bolt"></i></div>
        <div class="kpi-value">{{ stats.akut_count }}</div>
        <div class="kpi-label">Akuta stopp</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="kpi-card" [class.kpi-alert]="stats.pagaende_count > 0">
        <div class="kpi-icon text-orange"><i class="fas fa-spinner"></i></div>
        <div class="kpi-value">{{ stats.pagaende_count }}</div>
        <div class="kpi-label">Pågående</div>
      </div>
    </div>
  </div>

  <!-- KPI skeleton om ej laddad -->
  <div class="row g-3 mb-4" *ngIf="!stats && isLoading">
    <div class="col-6 col-md-3" *ngFor="let i of [1,2,3,4]">
      <div class="kpi-card kpi-skeleton"><div class="skeleton-box"></div></div>
    </div>
  </div>

  <!-- Flik-navigation -->
  <div class="tab-nav mb-3">
    <button class="tab-btn" [class.tab-active]="activeTab === 'logg'" (click)="switchTab('logg')">
      <i class="fas fa-list me-2"></i>Underhållslogg
    </button>
    <button class="tab-btn" [class.tab-active]="activeTab === 'statistik'" (click)="switchTab('statistik')">
      <i class="fas fa-chart-bar me-2"></i>Utrustningsstatistik
      <span class="tab-badge" *ngIf="statsTabLoaded">90d</span>
    </button>
    <button class="tab-btn" [class.tab-active]="activeTab === 'kpi'" (click)="switchTab('kpi')">
      <i class="fas fa-tachometer-alt me-2"></i>KPI-analys
    </button>
    <button class="tab-btn" [class.tab-active]="activeTab === 'service'" (click)="switchTab('service')">
      <i class="fas fa-oil-can me-2"></i>Serviceintervall
      <span class="tab-badge tab-badge-danger" *ngIf="serviceKritiskCount > 0">{{ serviceKritiskCount }}</span>
    </button>
  </div>

  <!-- === LOGG-FLIK === -->
  <app-maintenance-list
    *ngIf="activeTab === 'logg'"
    #listComp
    (addEntry)="openAddForm()"
    (editEntry)="openEditForm($event)"
    (refreshStats)="loadStats()"
    (entryDeleted)="onEntryChanged()">
  </app-maintenance-list>

  <!-- === STATISTIK-FLIK === -->
  <app-equipment-stats
    *ngIf="activeTab === 'statistik'"
    #statsComp>
  </app-equipment-stats>

  <!-- === KPI-ANALYS-FLIK === -->
  <app-kpi-analysis
    *ngIf="activeTab === 'kpi'"
    #kpiComp>
  </app-kpi-analysis>

  <!-- === SERVICEINTERVALL-FLIK === -->
  <app-service-intervals
    *ngIf="activeTab === 'service'"
    #serviceComp
    (showSuccess)="onShowSuccess($event)"
    (showError)="onShowError($event)">
  </app-service-intervals>

  <!-- FORMULÄR-MODAL -->
  <app-maintenance-form
    #formComp
    [equipmentList]="equipmentList"
    (saved)="onEntryChanged()"
    (closed)="onFormClosed()">
  </app-maintenance-form>
</div>
  `,
  styles: [`
    .maintenance-page {
      background: #1a202c;
      min-height: 100vh;
      padding: 1.5rem;
      color: #e2e8f0;
    }
    .page-title {
      font-size: 1.6rem;
      font-weight: 700;
      color: #e2e8f0;
    }
    .page-subtitle {
      color: #a0aec0;
      font-size: 0.9rem;
    }
    .btn-add {
      background: #38a169;
      border-color: #38a169;
      font-weight: 600;
      padding: 0.5rem 1.25rem;
    }
    .btn-add:hover {
      background: #2f855a;
      border-color: #2f855a;
    }

    /* KPI */
    .kpi-card {
      background: #2d3748;
      border-radius: 10px;
      padding: 1.2rem 1rem;
      text-align: center;
      border: 1px solid #3d4f6b;
      position: relative;
      transition: box-shadow 0.2s;
    }
    .kpi-card.kpi-alert {
      border-color: #ed8936;
      box-shadow: 0 0 0 2px rgba(237,137,54,0.25);
    }
    .kpi-icon { font-size: 1.4rem; margin-bottom: 0.4rem; }
    .kpi-value { font-size: 1.6rem; font-weight: 700; color: #e2e8f0; line-height: 1.2; }
    .kpi-label { font-size: 0.75rem; color: #a0aec0; margin-top: 0.2rem; }
    .kpi-skeleton { min-height: 100px; }
    .skeleton-box {
      height: 60px;
      background: linear-gradient(90deg, #3d4f6b 25%, #4a5f7a 50%, #3d4f6b 75%);
      border-radius: 6px;
      animation: skeleton-pulse 1.4s ease-in-out infinite;
    }
    @keyframes skeleton-pulse {
      0%, 100% { opacity: 0.6; }
      50% { opacity: 1; }
    }
    .text-orange { color: #ed8936 !important; }

    /* Flik-nav */
    .tab-nav {
      display: flex;
      gap: 0.5rem;
      border-bottom: 2px solid #3d4f6b;
      padding-bottom: 0;
    }
    .tab-btn {
      background: none;
      border: none;
      color: #a0aec0;
      padding: 0.6rem 1.2rem;
      font-size: 0.9rem;
      font-weight: 500;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      transition: color 0.2s, border-color 0.2s;
      white-space: nowrap;
    }
    .tab-btn:hover { color: #e2e8f0; }
    .tab-btn.tab-active {
      color: #63b3ed;
      border-bottom-color: #63b3ed;
    }
    .tab-badge {
      background: #3d4f6b;
      color: #a0aec0;
      font-size: 0.65rem;
      padding: 0.1rem 0.4rem;
      border-radius: 10px;
      margin-left: 0.3rem;
      vertical-align: middle;
    }
    .tab-badge-danger {
      background: #e53e3e !important;
      color: #fff !important;
    }

    @media (max-width: 576px) {
      .maintenance-page { padding: 1rem; }
      .kpi-value { font-size: 1.3rem; }
      .tab-btn { padding: 0.5rem 0.8rem; font-size: 0.82rem; }
    }
  `]
})
export class MaintenanceLogPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  @ViewChild('listComp') listComp?: MaintenanceListComponent;
  @ViewChild('statsComp') statsComp?: EquipmentStatsComponent;
  @ViewChild('kpiComp') kpiComp?: KpiAnalysisComponent;
  @ViewChild('serviceComp') serviceComp?: ServiceIntervalsComponent;
  @ViewChild('formComp') formComp!: MaintenanceFormComponent;

  stats: MaintenanceStats | null = null;
  isLoading = false;
  equipmentList: EquipmentItem[] = [];

  successMessage = '';
  errorMessage = '';
  private successTimer: any = null;

  activeTab: 'logg' | 'statistik' | 'kpi' | 'service' = 'logg';

  // Track which tabs have been loaded
  statsTabLoaded = false;
  serviceKritiskCount = 0;

  formatDuration = formatDuration;
  formatCost = formatCost;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadStats();
    this.loadEquipmentList();
    this.loadServiceKritiskCount();
  }

  ngOnDestroy(): void {
    clearTimeout(this.successTimer);
    this.destroy$.next();
    this.destroy$.complete();
  }

  switchTab(tab: 'logg' | 'statistik' | 'kpi' | 'service'): void {
    this.activeTab = tab;
    // Child components load their own data on init, but we trigger on
    // subsequent tab switches if the ViewChild is available
    if (tab === 'statistik') {
      this.statsTabLoaded = true;
      // statsComp loads via ngOnDestroy/recreate since *ngIf recreates it
      // We rely on the component loading data itself via loadEquipmentStats
      setTimeout(() => this.statsComp?.loadEquipmentStats(), 0);
    }
    if (tab === 'kpi') {
      setTimeout(() => this.kpiComp?.loadKpiData(), 0);
    }
    if (tab === 'service') {
      setTimeout(() => this.serviceComp?.loadServiceIntervals(), 0);
    }
  }

  loadStats(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.apiBase}?action=maintenance&run=stats`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        this.isLoading = false;
        if (data?.stats) {
          this.stats = {
            total_events: +data.stats.total_events,
            total_minutes: +data.stats.total_minutes,
            total_cost: +data.stats.total_cost,
            akut_count: +data.stats.akut_count,
            pagaende_count: +data.stats.pagaende_count
          };
        }
      });
  }

  loadEquipmentList(): void {
    this.http.get<any>(`${this.apiBase}?action=maintenance&run=equipment-list`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        if (data?.equipment) {
          this.equipmentList = data.equipment;
        }
      });
  }

  loadServiceKritiskCount(): void {
    this.http.get<any>(`${this.apiBase}?action=maintenance&run=service-intervals`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        if (data?.intervals) {
          this.serviceKritiskCount = data.intervals.filter((s: any) => s.status === 'kritisk').length;
        }
      });
  }

  openAddForm(): void {
    this.formComp.openAdd();
  }

  openEditForm(entry: MaintenanceEntry): void {
    this.formComp.openEdit(entry);
  }

  onEntryChanged(): void {
    this.onShowSuccess('Post sparad!');
    this.loadStats();
    this.listComp?.loadEntries();
    if (this.activeTab === 'statistik') {
      this.statsComp?.loadEquipmentStats();
    }
  }

  onFormClosed(): void {
    // noop - form handles its own state
  }

  onShowSuccess(msg: string): void {
    this.successMessage = msg;
    this.errorMessage = '';
    clearTimeout(this.successTimer);
    this.successTimer = setTimeout(() => {
      if (!this.destroy$.closed) this.successMessage = '';
    }, 4000);
  }

  onShowError(msg: string): void {
    this.errorMessage = msg;
  }
}
