import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { environment } from '../../../environments/environment';

interface MaintenanceEntry {
  id: number;
  line: string;
  maintenance_type: string;
  title: string;
  description: string | null;
  start_time: string;
  duration_minutes: number | null;
  performed_by: string | null;
  cost_sek: number | null;
  status: string;
  created_by: number | null;
  created_at: string;
}

interface MaintenanceStats {
  total_events: number;
  total_minutes: number;
  total_cost: number;
  akut_count: number;
  pagaende_count: number;
}

@Component({
  selector: 'app-maintenance-log',
  standalone: true,
  imports: [CommonModule, FormsModule],
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

  <!-- Filter -->
  <div class="filter-bar mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-sm-6 col-md-3">
        <label class="filter-label">Linje</label>
        <select class="form-select form-select-sm filter-select" [(ngModel)]="filterLine" (ngModelChange)="loadEntries()">
          <option value="">Alla linjer</option>
          <option value="rebotling">Rebotling</option>
          <option value="tvattlinje">Tvättlinje</option>
          <option value="saglinje">Såglinje</option>
          <option value="klassificeringslinje">Klassificeringslinje</option>
          <option value="allmant">Allmänt</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <label class="filter-label">Status</label>
        <select class="form-select form-select-sm filter-select" [(ngModel)]="filterStatus" (ngModelChange)="loadEntries()">
          <option value="">Alla statusar</option>
          <option value="planerat">Planerat</option>
          <option value="pagaende">Pågående</option>
          <option value="klart">Klart</option>
          <option value="avbokat">Avbokat</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <label class="filter-label">Fr.o.m. datum</label>
        <input type="date" class="form-control form-control-sm filter-select"
               [(ngModel)]="filterFromDate" (ngModelChange)="loadEntries()" />
      </div>
      <div class="col-12 col-sm-6 col-md-3 d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary flex-fill" (click)="clearFilters()">
          <i class="fas fa-times me-1"></i>Rensa
        </button>
        <button class="btn btn-sm btn-outline-info flex-fill" (click)="loadEntries(); loadStats()">
          <i class="fas fa-sync me-1"></i>Uppdatera
        </button>
      </div>
    </div>
  </div>

  <!-- Laddningsindikator -->
  <div *ngIf="isLoading" class="text-center py-3 text-muted">
    <i class="fas fa-circle-notch fa-spin me-2"></i>Laddar...
  </div>

  <!-- Inga poster -->
  <div *ngIf="!isLoading && entries.length === 0" class="empty-state">
    <i class="fas fa-tools fa-3x mb-3 text-muted"></i>
    <p class="mb-0">Inga underhållsposter hittades för valda filter.</p>
    <button class="btn btn-outline-success btn-sm mt-3" (click)="openAddForm()">
      <i class="fas fa-plus me-1"></i>Lägg till första posten
    </button>
  </div>

  <!-- Postlista -->
  <div class="entries-list" *ngIf="!isLoading && entries.length > 0">
    <div class="entry-card" *ngFor="let entry of entries" [class.entry-pagaende]="entry.status === 'pagaende'" [class.entry-akut]="entry.maintenance_type === 'akut'">
      <div class="entry-header">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge line-badge" [class]="getLineBadgeClass(entry.line)">
            {{ getLineLabel(entry.line) }}
          </span>
          <span class="badge type-badge" [class]="getTypeBadgeClass(entry.maintenance_type)">
            {{ getTypeLabel(entry.maintenance_type) }}
          </span>
          <span class="badge status-badge" [class]="getStatusBadgeClass(entry.status)">
            {{ getStatusLabel(entry.status) }}
          </span>
          <span class="entry-title">{{ entry.title }}</span>
        </div>
        <div class="entry-actions">
          <button class="btn btn-sm btn-action btn-edit" (click)="openEditForm(entry)" title="Redigera">
            <i class="fas fa-edit"></i>
          </button>
          <button class="btn btn-sm btn-action btn-delete" (click)="deleteEntry(entry.id, entry.title)" title="Ta bort">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>

      <div class="entry-meta">
        <span class="meta-item">
          <i class="fas fa-calendar-alt me-1 text-muted"></i>
          {{ formatDateTime(entry.start_time) }}
        </span>
        <span class="meta-sep">—</span>
        <span class="meta-item">
          <i class="fas fa-hourglass-half me-1 text-muted"></i>
          {{ formatDuration(entry.duration_minutes) }}
        </span>
        <span class="meta-sep" *ngIf="entry.performed_by">—</span>
        <span class="meta-item" *ngIf="entry.performed_by">
          <i class="fas fa-user me-1 text-muted"></i>
          {{ entry.performed_by }}
        </span>
        <span class="meta-sep" *ngIf="entry.cost_sek !== null && entry.cost_sek !== undefined">—</span>
        <span class="meta-item cost-item" *ngIf="entry.cost_sek !== null && entry.cost_sek !== undefined">
          <i class="fas fa-tag me-1 text-warning"></i>
          {{ formatCost(entry.cost_sek) }}
        </span>
      </div>

      <div class="entry-description" *ngIf="entry.description">
        {{ entry.description }}
      </div>
    </div>
  </div>

  <!-- Räknare -->
  <div class="text-muted small mt-2" *ngIf="entries.length > 0">
    Visar {{ entries.length }} av {{ totalCount }} poster
  </div>

  <!-- FORMULÄRMODAL (overlay) -->
  <div class="modal-overlay" *ngIf="showForm" (click)="closeForm()">
    <div class="modal-panel" (click)="$event.stopPropagation()">
      <div class="modal-header-custom">
        <h5 class="mb-0">
          <i class="fas fa-tools me-2"></i>
          {{ editingId ? 'Redigera underhållspost' : 'Ny underhållspost' }}
        </h5>
        <button class="btn-close-custom" (click)="closeForm()">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="modal-body-custom">
        <form (ngSubmit)="saveEntry()" #f="ngForm">
          <div class="row g-3">

            <!-- Linje -->
            <div class="col-12 col-md-6">
              <label class="form-label form-label-dark">Linje *</label>
              <select class="form-select form-select-dark" [(ngModel)]="form.line" name="line" required>
                <option value="rebotling">Rebotling</option>
                <option value="tvattlinje">Tvättlinje</option>
                <option value="saglinje">Såglinje</option>
                <option value="klassificeringslinje">Klassificeringslinje</option>
                <option value="allmant">Allmänt</option>
              </select>
            </div>

            <!-- Typ -->
            <div class="col-12 col-md-6">
              <label class="form-label form-label-dark">Typ *</label>
              <select class="form-select form-select-dark" [(ngModel)]="form.maintenance_type" name="maintenance_type" required>
                <option value="planerat">Planerat underhåll</option>
                <option value="akut">Akut reparation</option>
                <option value="inspektion">Inspektion</option>
                <option value="kalibrering">Kalibrering</option>
                <option value="rengoring">Rengöring</option>
                <option value="ovrigt">Övrigt</option>
              </select>
            </div>

            <!-- Titel -->
            <div class="col-12">
              <label class="form-label form-label-dark">Titel *</label>
              <input type="text" class="form-control form-control-dark"
                     [(ngModel)]="form.title" name="title"
                     placeholder="Kortfattad beskrivning av underhållet"
                     maxlength="150" required />
            </div>

            <!-- Beskrivning -->
            <div class="col-12">
              <label class="form-label form-label-dark">Beskrivning</label>
              <textarea class="form-control form-control-dark" [(ngModel)]="form.description"
                        name="description" rows="3"
                        placeholder="Detaljerad beskrivning, orsak, åtgärd..."></textarea>
            </div>

            <!-- Starttid -->
            <div class="col-12 col-md-6">
              <label class="form-label form-label-dark">Starttid *</label>
              <input type="datetime-local" class="form-control form-control-dark"
                     [(ngModel)]="form.start_time" name="start_time" required />
            </div>

            <!-- Varaktighet -->
            <div class="col-12 col-md-6">
              <label class="form-label form-label-dark">Varaktighet (minuter)</label>
              <input type="number" class="form-control form-control-dark"
                     [(ngModel)]="form.duration_minutes" name="duration_minutes"
                     placeholder="Lämna tomt om pågående" min="0" />
              <div class="form-text text-muted">Lämna tomt om underhållet pågår</div>
            </div>

            <!-- Utförd av -->
            <div class="col-12 col-md-6">
              <label class="form-label form-label-dark">Utförd av</label>
              <input type="text" class="form-control form-control-dark"
                     [(ngModel)]="form.performed_by" name="performed_by"
                     placeholder="Namn eller företag" maxlength="100" />
            </div>

            <!-- Kostnad -->
            <div class="col-12 col-md-6">
              <label class="form-label form-label-dark">Kostnad (kr)</label>
              <input type="number" class="form-control form-control-dark"
                     [(ngModel)]="form.cost_sek" name="cost_sek"
                     placeholder="Valfritt — lämna tomt om okänd" min="0" step="0.01" />
            </div>

            <!-- Status -->
            <div class="col-12">
              <label class="form-label form-label-dark">Status *</label>
              <select class="form-select form-select-dark" [(ngModel)]="form.status" name="status" required>
                <option value="planerat">Planerat</option>
                <option value="pagaende">Pågående</option>
                <option value="klart">Klart</option>
                <option value="avbokat">Avbokat</option>
              </select>
            </div>
          </div>

          <!-- Formulärfel -->
          <div *ngIf="formError" class="alert alert-danger mt-3 mb-0 py-2">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ formError }}
          </div>

          <!-- Knappar -->
          <div class="d-flex gap-2 mt-4 justify-content-end">
            <button type="button" class="btn btn-secondary" (click)="closeForm()">Avbryt</button>
            <button type="submit" class="btn btn-success" [disabled]="isSaving">
              <span *ngIf="isSaving"><i class="fas fa-circle-notch fa-spin me-2"></i>Sparar...</span>
              <span *ngIf="!isSaving"><i class="fas fa-save me-2"></i>{{ editingId ? 'Uppdatera' : 'Spara' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
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
    .kpi-icon {
      font-size: 1.4rem;
      margin-bottom: 0.4rem;
    }
    .kpi-value {
      font-size: 1.6rem;
      font-weight: 700;
      color: #e2e8f0;
      line-height: 1.2;
    }
    .kpi-label {
      font-size: 0.75rem;
      color: #a0aec0;
      margin-top: 0.2rem;
    }
    .kpi-skeleton {
      min-height: 100px;
    }
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

    /* Filter */
    .filter-bar {
      background: #2d3748;
      border-radius: 10px;
      padding: 1rem;
      border: 1px solid #3d4f6b;
    }
    .filter-label {
      font-size: 0.75rem;
      color: #a0aec0;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.3rem;
      display: block;
    }
    .filter-select {
      background: #1a202c;
      border: 1px solid #4a5568;
      color: #e2e8f0;
      font-size: 0.875rem;
    }
    .filter-select:focus {
      background: #1a202c;
      border-color: #63b3ed;
      color: #e2e8f0;
      box-shadow: 0 0 0 2px rgba(99,179,237,0.25);
    }

    /* Lista */
    .entries-list {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .entry-card {
      background: #2d3748;
      border-radius: 10px;
      padding: 1rem 1.1rem;
      border: 1px solid #3d4f6b;
      transition: border-color 0.2s;
    }
    .entry-card:hover {
      border-color: #4a6fa5;
    }
    .entry-card.entry-pagaende {
      border-left: 3px solid #ed8936;
    }
    .entry-card.entry-akut {
      border-left: 3px solid #e53e3e;
    }

    .entry-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
    }

    .entry-title {
      font-weight: 600;
      color: #e2e8f0;
      font-size: 0.95rem;
    }

    .entry-actions {
      display: flex;
      gap: 0.35rem;
      flex-shrink: 0;
    }

    .btn-action {
      width: 30px;
      height: 30px;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      font-size: 0.8rem;
    }
    .btn-edit {
      background: rgba(99,179,237,0.15);
      border: 1px solid rgba(99,179,237,0.4);
      color: #63b3ed;
    }
    .btn-edit:hover {
      background: rgba(99,179,237,0.3);
      color: #63b3ed;
    }
    .btn-delete {
      background: rgba(229,62,62,0.15);
      border: 1px solid rgba(229,62,62,0.4);
      color: #fc8181;
    }
    .btn-delete:hover {
      background: rgba(229,62,62,0.3);
      color: #fc8181;
    }

    .entry-meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.3rem;
      font-size: 0.82rem;
      color: #a0aec0;
      margin-bottom: 0.3rem;
    }
    .meta-sep {
      color: #4a5568;
    }
    .cost-item {
      color: #ecc94b;
    }

    .entry-description {
      font-size: 0.85rem;
      color: #a0aec0;
      margin-top: 0.4rem;
      padding-top: 0.4rem;
      border-top: 1px solid #3d4f6b;
      line-height: 1.5;
    }

    /* Badges — linje */
    .line-badge { font-size: 0.7rem; letter-spacing: 0.03em; }
    .badge-line-rebotling { background: #2b6cb0; color: #bee3f8; }
    .badge-line-tvattlinje { background: #276749; color: #c6f6d5; }
    .badge-line-saglinje { background: #744210; color: #fefcbf; }
    .badge-line-klassificeringslinje { background: #553c9a; color: #e9d8fd; }
    .badge-line-allmant { background: #4a5568; color: #e2e8f0; }

    /* Badges — typ */
    .type-badge { font-size: 0.7rem; }
    .badge-type-akut { background: #c53030; color: #fed7d7; }
    .badge-type-planerat { background: #2b6cb0; color: #bee3f8; }
    .badge-type-inspektion { background: #7b341e; color: #fbd38d; }
    .badge-type-kalibrering { background: #086f83; color: #c4f1f9; }
    .badge-type-rengoring { background: #276749; color: #c6f6d5; }
    .badge-type-ovrigt { background: #4a5568; color: #e2e8f0; }

    /* Badges — status */
    .status-badge { font-size: 0.7rem; }
    .badge-status-planerat { background: #2b6cb0; color: #bee3f8; }
    .badge-status-pagaende { background: #c05621; color: #fed7aa; }
    .badge-status-klart { background: #276749; color: #c6f6d5; }
    .badge-status-avbokat { background: #4a5568; color: #a0aec0; }

    /* Tom lista */
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: #718096;
    }

    /* Modal */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1050;
      padding: 1rem;
    }

    .modal-panel {
      background: #2d3748;
      border-radius: 14px;
      width: 100%;
      max-width: 640px;
      max-height: 90vh;
      overflow-y: auto;
      border: 1px solid #4a5568;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }

    .modal-header-custom {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.2rem 1.5rem;
      border-bottom: 1px solid #4a5568;
      color: #e2e8f0;
    }

    .modal-body-custom {
      padding: 1.5rem;
    }

    .btn-close-custom {
      background: none;
      border: none;
      color: #a0aec0;
      font-size: 1.1rem;
      cursor: pointer;
      padding: 0.25rem 0.5rem;
      border-radius: 6px;
      transition: color 0.2s, background 0.2s;
    }
    .btn-close-custom:hover {
      color: #e2e8f0;
      background: rgba(255,255,255,0.1);
    }

    /* Dark form controls */
    .form-label-dark {
      color: #a0aec0;
      font-size: 0.82rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 0.3rem;
    }
    .form-control-dark,
    .form-select-dark {
      background: #1a202c;
      border: 1px solid #4a5568;
      color: #e2e8f0;
      font-size: 0.9rem;
    }
    .form-control-dark:focus,
    .form-select-dark:focus {
      background: #1a202c;
      border-color: #63b3ed;
      color: #e2e8f0;
      box-shadow: 0 0 0 2px rgba(99,179,237,0.25);
    }
    .form-control-dark::placeholder {
      color: #718096;
    }
    .form-control-dark option,
    .form-select-dark option {
      background: #2d3748;
    }
    .form-text { font-size: 0.75rem; }

    @media (max-width: 576px) {
      .maintenance-page { padding: 1rem; }
      .kpi-value { font-size: 1.3rem; }
      .entry-header { flex-direction: column; }
      .entry-actions { align-self: flex-end; }
    }
  `]
})
export class MaintenanceLogPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  entries: MaintenanceEntry[] = [];
  stats: MaintenanceStats | null = null;
  isLoading = false;
  isSaving = false;
  showForm = false;
  editingId: number | null = null;
  totalCount = 0;

  successMessage = '';
  errorMessage = '';
  formError = '';

  private successTimer: any = null;

  // Filtrar
  filterLine = '';
  filterStatus = '';
  filterFromDate = '';

  // Formulär
  form = {
    line: 'rebotling',
    maintenance_type: 'ovrigt',
    title: '',
    description: '',
    start_time: '',
    duration_minutes: null as number | null,
    performed_by: '',
    cost_sek: null as number | null,
    status: 'klart'
  };

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    // Sätt default starttid till aktuell timme
    const now = new Date();
    now.setMinutes(0, 0, 0);
    this.form.start_time = now.toISOString().slice(0, 16);

    this.loadEntries();
    this.loadStats();
  }

  ngOnDestroy(): void {
    clearTimeout(this.successTimer);
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadEntries(): void {
    if (this.isLoading) return;
    this.isLoading = true;

    const params = new URLSearchParams();
    if (this.filterLine) params.set('line', this.filterLine);
    if (this.filterStatus) params.set('status', this.filterStatus);
    if (this.filterFromDate) params.set('from_date', this.filterFromDate);

    const paramStr = params.toString();
    const url = `${this.apiBase}?action=maintenance&run=list${paramStr ? '&' + paramStr : ''}`;

    this.http.get<any>(url, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        this.isLoading = false;
        if (data?.entries) {
          this.entries = data.entries;
          this.totalCount = data.total_count ?? data.entries.length;
        } else if (data?.error) {
          this.showError(data.error);
        }
      });
  }

  loadStats(): void {
    this.http.get<any>(`${this.apiBase}?action=maintenance&run=stats`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
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

  openAddForm(): void {
    this.editingId = null;
    const now = new Date();
    now.setMinutes(0, 0, 0);
    this.form = {
      line: 'rebotling',
      maintenance_type: 'ovrigt',
      title: '',
      description: '',
      start_time: now.toISOString().slice(0, 16),
      duration_minutes: null,
      performed_by: '',
      cost_sek: null,
      status: 'klart'
    };
    this.formError = '';
    this.showForm = true;
  }

  openEditForm(entry: MaintenanceEntry): void {
    this.editingId = entry.id;
    this.form = {
      line: entry.line,
      maintenance_type: entry.maintenance_type,
      title: entry.title,
      description: entry.description ?? '',
      start_time: entry.start_time?.replace(' ', 'T').slice(0, 16) ?? '',
      duration_minutes: entry.duration_minutes,
      performed_by: entry.performed_by ?? '',
      cost_sek: entry.cost_sek,
      status: entry.status
    };
    this.formError = '';
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.formError = '';
  }

  saveEntry(): void {
    this.formError = '';

    if (!this.form.title.trim()) {
      this.formError = 'Titel krävs';
      return;
    }
    if (!this.form.start_time) {
      this.formError = 'Starttid krävs';
      return;
    }

    this.isSaving = true;
    const payload = {
      ...this.form,
      duration_minutes: this.form.duration_minutes !== null && this.form.duration_minutes !== undefined && this.form.duration_minutes !== ('' as any)
        ? +this.form.duration_minutes : null,
      cost_sek: this.form.cost_sek !== null && this.form.cost_sek !== undefined && this.form.cost_sek !== ('' as any)
        ? +this.form.cost_sek : null
    };

    const url = this.editingId
      ? `${this.apiBase}?action=maintenance&run=update&id=${this.editingId}`
      : `${this.apiBase}?action=maintenance&run=add`;

    this.http.post<any>(url, payload, { withCredentials: true })
      .pipe(timeout(10000), catchError(err => of({ error: err?.error?.error || 'Nätverksfel' })), takeUntil(this.destroy$))
      .subscribe(data => {
        this.isSaving = false;
        if (data?.success) {
          this.showSuccess(this.editingId ? 'Post uppdaterad!' : 'Post sparad!');
          this.closeForm();
          this.loadEntries();
          this.loadStats();
        } else {
          this.formError = data?.error || 'Kunde inte spara';
        }
      });
  }

  deleteEntry(id: number, title: string): void {
    if (!confirm(`Ta bort underhållsposten "${title}"?\n(Posten markeras som avbokad och bevaras i historiken.)`)) return;

    this.http.post<any>(`${this.apiBase}?action=maintenance&run=delete&id=${id}`, {}, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        if (data?.success) {
          this.showSuccess('Post borttagen');
          this.loadEntries();
          this.loadStats();
        } else {
          this.showError(data?.error || 'Kunde inte ta bort posten');
        }
      });
  }

  clearFilters(): void {
    this.filterLine = '';
    this.filterStatus = '';
    this.filterFromDate = '';
    this.loadEntries();
  }

  // --- Formattering ---

  formatDuration(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined) return 'Pågående';
    if (+minutes === 0) return 'Pågående';
    const m = +minutes;
    if (m < 60) return `${m} min`;
    const h = Math.floor(m / 60);
    const rem = m % 60;
    return rem > 0 ? `${h}h ${rem}min` : `${h}h`;
  }

  formatCost(cost: number | null | undefined): string {
    if (cost === null || cost === undefined) return '';
    return new Intl.NumberFormat('sv-SE', { style: 'currency', currency: 'SEK', maximumFractionDigits: 0 }).format(+cost);
  }

  formatDateTime(dt: string | null): string {
    if (!dt) return '';
    return dt.replace('T', ' ').slice(0, 16);
  }

  // --- Badges ---

  getLineBadgeClass(line: string): string {
    const map: Record<string, string> = {
      rebotling: 'badge-line-rebotling',
      tvattlinje: 'badge-line-tvattlinje',
      saglinje: 'badge-line-saglinje',
      klassificeringslinje: 'badge-line-klassificeringslinje',
      allmant: 'badge-line-allmant'
    };
    return map[line] ?? 'badge-line-allmant';
  }

  getLineLabel(line: string): string {
    const map: Record<string, string> = {
      rebotling: 'Rebotling',
      tvattlinje: 'Tvättlinje',
      saglinje: 'Såglinje',
      klassificeringslinje: 'Klassificeringslinje',
      allmant: 'Allmänt'
    };
    return map[line] ?? line;
  }

  getTypeBadgeClass(type: string): string {
    const map: Record<string, string> = {
      akut: 'badge-type-akut',
      planerat: 'badge-type-planerat',
      inspektion: 'badge-type-inspektion',
      kalibrering: 'badge-type-kalibrering',
      rengoring: 'badge-type-rengoring',
      ovrigt: 'badge-type-ovrigt'
    };
    return map[type] ?? 'badge-type-ovrigt';
  }

  getTypeLabel(type: string): string {
    const map: Record<string, string> = {
      planerat: 'Planerat',
      akut: 'Akut',
      inspektion: 'Inspektion',
      kalibrering: 'Kalibrering',
      rengoring: 'Rengöring',
      ovrigt: 'Övrigt'
    };
    return map[type] ?? type;
  }

  getStatusBadgeClass(status: string): string {
    const map: Record<string, string> = {
      planerat: 'badge-status-planerat',
      pagaende: 'badge-status-pagaende',
      klart: 'badge-status-klart',
      avbokat: 'badge-status-avbokat'
    };
    return map[status] ?? 'badge-status-klart';
  }

  getStatusLabel(status: string): string {
    const map: Record<string, string> = {
      planerat: 'Planerat',
      pagaende: 'Pågående',
      klart: 'Klart',
      avbokat: 'Avbokat'
    };
    return map[status] ?? status;
  }

  private showSuccess(msg: string): void {
    this.successMessage = msg;
    this.errorMessage = '';
    clearTimeout(this.successTimer);
    this.successTimer = setTimeout(() => {
      if (!this.destroy$.closed) this.successMessage = '';
    }, 4000);
  }

  private showError(msg: string): void {
    this.errorMessage = msg;
  }
}
