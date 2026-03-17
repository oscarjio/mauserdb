import { Component, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { EquipmentStat, EquipmentSummary } from '../maintenance-log.models';
import {
  formatDuration, formatCost, formatDateTime,
  getKategoriBadgeClass, getKategoriLabel,
  SHARED_STYLES
} from '../maintenance-log.helpers';

@Component({
  selector: 'app-equipment-stats',
  standalone: true,
  imports: [CommonModule],
  template: `
    <!-- Laddning -->
    <div *ngIf="statsLoading" class="text-center py-4 text-muted">
      <i class="fas fa-circle-notch fa-spin me-2"></i>Laddar statistik...
    </div>

    <!-- Felmeddelande -->
    <div *ngIf="!statsLoading && statsError" class="text-center py-4" style="color:#fc8181;">
      <i class="fas fa-exclamation-triangle me-2" style="font-size:1.5rem;"></i>
      <p class="mt-2 mb-1">Kunde inte ladda utrustningsstatistik.</p>
      <button class="btn btn-sm btn-outline-secondary mt-1" (click)="loadEquipmentStats()">
        <i class="fas fa-sync me-1"></i>Forsok igen
      </button>
    </div>

    <div *ngIf="!statsLoading && !statsError">
      <!-- KPI-summering (90 dagar) -->
      <div class="row g-3 mb-4" *ngIf="equipmentSummary">
        <div class="col-12 col-md-4">
          <div class="kpi-card">
            <div class="kpi-icon text-danger"><i class="fas fa-pause-circle"></i></div>
            <div class="kpi-value">{{ formatDuration(equipmentSummary.total_downtime_min) }}</div>
            <div class="kpi-label">Total driftstopp (90 dagar)</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="kpi-card">
            <div class="kpi-icon text-warning"><i class="fas fa-coins"></i></div>
            <div class="kpi-value">{{ formatCost(equipmentSummary.total_cost) }}</div>
            <div class="kpi-label">Total kostnad (90 dagar)</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="kpi-card" [class.kpi-alert]="!!equipmentSummary.worst_equipment">
            <div class="kpi-icon text-orange"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="kpi-value kpi-value-sm">{{ equipmentSummary.worst_equipment ?? '—' }}</div>
            <div class="kpi-label">Mest problembenägen utrustning</div>
          </div>
        </div>
      </div>

      <!-- Ingen data -->
      <div *ngIf="equipmentStats.length === 0" class="empty-state">
        <i class="fas fa-chart-bar fa-3x mb-3 text-muted"></i>
        <p class="mb-0">Inga underhållshändelser registrerade de senaste 90 dagarna.</p>
      </div>

      <!-- Statistiktabell -->
      <div class="stats-table-wrap" *ngIf="equipmentStats.length > 0">
        <div class="table-responsive">
          <table class="table table-dark table-stats">
            <thead>
              <tr>
                <th (click)="sortStats('namn')" class="sortable">
                  Utrustning <i class="fas" [class]="getSortIcon('namn')"></i>
                </th>
                <th (click)="sortStats('kategori')" class="sortable">
                  Kategori <i class="fas" [class]="getSortIcon('kategori')"></i>
                </th>
                <th (click)="sortStats('antal_handelser')" class="sortable text-end">
                  Händelser <i class="fas" [class]="getSortIcon('antal_handelser')"></i>
                </th>
                <th (click)="sortStats('total_driftstopp_min')" class="sortable text-end">
                  Total driftstopp <i class="fas" [class]="getSortIcon('total_driftstopp_min')"></i>
                </th>
                <th (click)="sortStats('snitt_driftstopp_min')" class="sortable text-end">
                  Snitt/händelse <i class="fas" [class]="getSortIcon('snitt_driftstopp_min')"></i>
                </th>
                <th (click)="sortStats('total_kostnad')" class="sortable text-end">
                  Total kostnad <i class="fas" [class]="getSortIcon('total_kostnad')"></i>
                </th>
                <th (click)="sortStats('senaste_handelse')" class="sortable">
                  Senaste händelse <i class="fas" [class]="getSortIcon('senaste_handelse')"></i>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let row of sortedEquipmentStats; trackBy: trackByIndex">
                <td class="fw-semibold">{{ row.namn }}</td>
                <td>
                  <span class="badge kategori-badge" [class]="getKategoriBadgeClass(row.kategori)">
                    {{ getKategoriLabel(row.kategori) }}
                  </span>
                </td>
                <td class="text-end">
                  <span *ngIf="row.antal_handelser > 0" class="text-warning fw-bold">{{ row.antal_handelser }}</span>
                  <span *ngIf="row.antal_handelser === 0" class="text-muted">0</span>
                </td>
                <td class="text-end">
                  <span *ngIf="row.total_driftstopp_min > 0" class="text-danger">{{ formatDuration(row.total_driftstopp_min) }}</span>
                  <span *ngIf="row.total_driftstopp_min === 0" class="text-muted">—</span>
                </td>
                <td class="text-end text-muted">
                  <span *ngIf="row.snitt_driftstopp_min > 0">{{ formatDuration(mathRound(row.snitt_driftstopp_min)) }}</span>
                  <span *ngIf="row.snitt_driftstopp_min === 0">—</span>
                </td>
                <td class="text-end">
                  <span *ngIf="row.total_kostnad > 0" class="cost-item">{{ formatCost(row.total_kostnad) }}</span>
                  <span *ngIf="row.total_kostnad === 0" class="text-muted">—</span>
                </td>
                <td class="text-muted small">
                  {{ row.senaste_handelse ? formatDateTime(row.senaste_handelse) : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Uppdatera-knapp -->
      <div class="text-end mt-2">
        <button class="btn btn-sm btn-outline-info" (click)="loadEquipmentStats()">
          <i class="fas fa-sync me-1"></i>Uppdatera statistik
        </button>
      </div>
    </div>
  `,
  styles: [`${SHARED_STYLES}`]
})
export class EquipmentStatsComponent implements OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  equipmentStats: EquipmentStat[] = [];
  equipmentSummary: EquipmentSummary | null = null;
  statsLoading = false;
  statsError = false;

  sortField: keyof EquipmentStat = 'total_driftstopp_min';
  sortDir: 'asc' | 'desc' = 'desc';

  // Expose helpers to template
  formatDuration = formatDuration;
  formatCost = formatCost;
  formatDateTime = formatDateTime;
  getKategoriBadgeClass = getKategoriBadgeClass;
  getKategoriLabel = getKategoriLabel;
  mathRound = Math.round;

  constructor(private http: HttpClient) {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadEquipmentStats(): void {
    this.statsLoading = true;
    this.statsError = false;
    this.http.get<any>(`${this.apiBase}?action=maintenance&run=equipment-stats`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        this.statsLoading = false;
        if (data?.stats) {
          this.equipmentStats = data.stats;
          this.equipmentSummary = data.summary ?? null;
        } else {
          this.statsError = true;
        }
      });
  }

  get sortedEquipmentStats(): EquipmentStat[] {
    const field = this.sortField;
    const dir = this.sortDir === 'asc' ? 1 : -1;
    return [...this.equipmentStats].sort((a, b) => {
      const av = a[field];
      const bv = b[field];
      if (av === null || av === undefined) return 1;
      if (bv === null || bv === undefined) return -1;
      if (typeof av === 'string' && typeof bv === 'string') {
        return av.localeCompare(bv, 'sv') * dir;
      }
      return (+av - +bv) * dir;
    });
  }

  sortStats(field: keyof EquipmentStat): void {
    if (this.sortField === field) {
      this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortField = field;
      this.sortDir = 'desc';
    }
  }

  getSortIcon(field: string): string {
    if (this.sortField !== field) return 'fa-sort';
    return this.sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
  }
  trackByIndex(index: number): number { return index; }
}
