import { Component, OnInit, OnDestroy, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { MaintenanceEntry } from '../maintenance-log.models';
import {
  formatDuration, formatCost, formatDateTime,
  getLineBadgeClass, getLineLabel,
  getTypeBadgeClass, getTypeLabel,
  getStatusBadgeClass, getStatusLabel,
  SHARED_STYLES
} from '../maintenance-log.helpers';

@Component({
  selector: 'app-maintenance-list',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
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
          <button class="btn btn-sm btn-outline-info flex-fill" (click)="loadEntries(); refreshStats.emit()">
            <i class="fas fa-sync me-1"></i>Uppdatera
          </button>
        </div>
      </div>
    </div>

    <!-- Laddningsindikator -->
    <div *ngIf="isLoading" class="text-center py-3 text-muted">
      <i class="fas fa-circle-notch fa-spin me-2"></i>Laddar...
    </div>

    <!-- Felmeddelande -->
    <div *ngIf="!isLoading && loadError" class="text-center py-3" style="color:#fc8181;">
      <i class="fas fa-exclamation-triangle me-2" style="font-size:1.5rem;"></i>
      <p class="mt-2 mb-1">Kunde inte ladda underhållsposter.</p>
      <button class="btn btn-sm btn-outline-secondary mt-1" (click)="loadEntries()">
        <i class="fas fa-sync me-1"></i>Försök igen
      </button>
    </div>

    <!-- Inga poster -->
    <div *ngIf="!isLoading && !loadError && entries.length === 0" class="empty-state">
      <i class="fas fa-tools fa-3x mb-3 text-muted"></i>
      <p class="mb-0">Inga underhållsposter hittades för valda filter.</p>
      <button class="btn btn-outline-success btn-sm mt-3" (click)="addEntry.emit()">
        <i class="fas fa-plus me-1"></i>Lägg till första posten
      </button>
    </div>

    <!-- Postlista -->
    <div class="entries-list" *ngIf="!isLoading && !loadError && entries.length > 0">
      <div class="entry-card" *ngFor="let entry of entries; trackBy: trackById"
           [class.entry-pagaende]="entry.status === 'pagaende'"
           [class.entry-akut]="entry.maintenance_type === 'akut'">
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
            <span class="badge equipment-badge" *ngIf="entry.equipment">
              <i class="fas fa-cog me-1"></i>{{ entry.equipment }}
            </span>
            <span class="badge resolved-badge" *ngIf="entry.resolved">
              <i class="fas fa-check me-1"></i>Åtgärdad
            </span>
            <span class="entry-title">{{ entry.title }}</span>
          </div>
          <div class="entry-actions">
            <button class="btn btn-sm btn-action btn-edit" (click)="editEntry.emit(entry)" title="Redigera">
              <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-action btn-delete" (click)="onDeleteEntry(entry)" title="Ta bort">
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
          <span class="meta-sep" *ngIf="entry.downtime_minutes > 0">—</span>
          <span class="meta-item meta-downtime" *ngIf="entry.downtime_minutes > 0">
            <i class="fas fa-pause-circle me-1"></i>
            Driftstopp: {{ formatDuration(entry.downtime_minutes) }}
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
  `,
  styles: [`
    ${SHARED_STYLES}

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
    .entry-card:hover { border-color: #4a6fa5; }
    .entry-card.entry-pagaende { border-left: 3px solid #ed8936; }
    .entry-card.entry-akut { border-left: 3px solid #e53e3e; }

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
    .entry-meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.3rem;
      font-size: 0.82rem;
      color: #a0aec0;
      margin-bottom: 0.3rem;
    }
    .meta-sep { color: #4a5568; }
    .entry-description {
      font-size: 0.85rem;
      color: #a0aec0;
      margin-top: 0.4rem;
      padding-top: 0.4rem;
      border-top: 1px solid #3d4f6b;
      line-height: 1.5;
    }

    @media (max-width: 576px) {
      .entry-header { flex-direction: column; }
      .entry-actions { align-self: flex-end; }
    }
  `]
})
export class MaintenanceListComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  @Output() addEntry = new EventEmitter<void>();
  @Output() editEntry = new EventEmitter<MaintenanceEntry>();
  @Output() refreshStats = new EventEmitter<void>();
  @Output() entryDeleted = new EventEmitter<void>();

  entries: MaintenanceEntry[] = [];
  isLoading = false;
  loadError = false;
  totalCount = 0;

  filterLine = '';
  filterStatus = '';
  filterFromDate = '';

  // Expose helpers to template
  formatDuration = formatDuration;
  formatCost = formatCost;
  formatDateTime = formatDateTime;
  getLineBadgeClass = getLineBadgeClass;
  getLineLabel = getLineLabel;
  getTypeBadgeClass = getTypeBadgeClass;
  getTypeLabel = getTypeLabel;
  getStatusBadgeClass = getStatusBadgeClass;
  getStatusLabel = getStatusLabel;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadEntries();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadEntries(): void {
    if (this.isLoading) return;
    this.isLoading = true;
    this.loadError = false;

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
        } else {
          this.loadError = true;
        }
      });
  }

  clearFilters(): void {
    this.filterLine = '';
    this.filterStatus = '';
    this.filterFromDate = '';
    this.loadEntries();
  }

  onDeleteEntry(entry: MaintenanceEntry): void {
    if (!confirm(`Ta bort underhållsposten "${entry.title}"?\n(Posten markeras som avbokad och bevaras i historiken.)`)) return;

    this.http.post<any>(`${this.apiBase}?action=maintenance&run=delete&id=${entry.id}`, {}, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        if (data?.success) {
          this.entryDeleted.emit();
          this.loadEntries();
        }
      });
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
}
