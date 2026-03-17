import { Component, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { KpiRow } from '../maintenance-log.models';
import { SHARED_STYLES } from '../maintenance-log.helpers';

@Component({
  selector: 'app-kpi-analysis',
  standalone: true,
  imports: [CommonModule],
  template: `
    <!-- Datumfilter -->
    <div class="filter-bar mb-3 d-flex align-items-center gap-3 flex-wrap">
      <label class="filter-label mb-0">Period:</label>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-sm" *ngFor="let d of kpiDayOptions; trackBy: trackByIndex"
                [class.btn-info]="kpiDays === d"
                [class.btn-outline-secondary]="kpiDays !== d"
                (click)="setKpiDays(d)">
          {{ d }} dagar
        </button>
      </div>
      <button class="btn btn-sm btn-outline-info ms-auto" (click)="loadKpiData()">
        <i class="fas fa-sync me-1"></i>Uppdatera
      </button>
    </div>

    <!-- Laddning -->
    <div *ngIf="kpiLoading" class="text-center py-4 text-muted">
      <i class="fas fa-circle-notch fa-spin me-2"></i>Laddar KPI-data...
    </div>

    <!-- Felmeddelande -->
    <div *ngIf="!kpiLoading && kpiError" class="text-center py-4" style="color:#fc8181;">
      <i class="fas fa-exclamation-triangle me-2" style="font-size:1.5rem;"></i>
      <p class="mt-2 mb-1">Kunde inte ladda KPI-data.</p>
      <button class="btn btn-sm btn-outline-secondary mt-1" (click)="loadKpiData()">
        <i class="fas fa-sync me-1"></i>Forsok igen
      </button>
    </div>

    <div *ngIf="!kpiLoading && !kpiError">

      <!-- Ingen data -->
      <div *ngIf="kpiRows.length === 0" class="empty-state">
        <i class="fas fa-tachometer-alt fa-3x mb-3 text-muted"></i>
        <p class="mb-0">Inga underhållshändelser med registrerad utrustning de senaste {{ kpiDays }} dagarna.</p>
      </div>

      <!-- KPI-tabell -->
      <div class="stats-table-wrap" *ngIf="kpiRows.length > 0">
        <div class="table-responsive">
          <table class="table table-dark table-stats">
            <thead>
              <tr>
                <th>Utrustning</th>
                <th class="text-end">Antal fel</th>
                <th class="text-end">MTBF (dagar)</th>
                <th class="text-end">MTTR (timmar)</th>
                <th class="text-end">Total stillestånd</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let row of kpiRows; trackBy: trackByIndex">
                <td class="fw-semibold">{{ row.equipment }}</td>
                <td class="text-end">
                  <span class="text-warning fw-bold">{{ row.antal_fel }}</span>
                </td>
                <td class="text-end">
                  <span *ngIf="row.avg_mtbf_dagar !== null" [class.text-success]="row.avg_mtbf_dagar >= 30" [class.text-warning]="row.avg_mtbf_dagar >= 7 && row.avg_mtbf_dagar < 30" [class.text-danger]="row.avg_mtbf_dagar < 7">
                    {{ row.avg_mtbf_dagar }} d
                  </span>
                  <span *ngIf="row.avg_mtbf_dagar === null" class="text-muted">— (1 händelse)</span>
                </td>
                <td class="text-end">
                  <span *ngIf="row.avg_mttr_h > 0" [class.text-success]="row.avg_mttr_h < 1" [class.text-warning]="row.avg_mttr_h >= 1 && row.avg_mttr_h < 4" [class.text-danger]="row.avg_mttr_h >= 4">
                    {{ row.avg_mttr_h }} h
                  </span>
                  <span *ngIf="row.avg_mttr_h === 0" class="text-muted">—</span>
                </td>
                <td class="text-end">
                  <span *ngIf="row.total_stillestand_h > 0" class="text-danger">{{ row.total_stillestand_h }} h</span>
                  <span *ngIf="row.total_stillestand_h === 0" class="text-muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Förklaring -->
        <div class="kpi-legend p-3 border-top" style="border-color: #3d4f6b !important; font-size: 0.78rem; color: #718096;">
          <strong class="text-muted">MTBF</strong> = Genomsnittlig tid mellan fel (dagar) &nbsp;·&nbsp;
          <strong class="text-muted">MTTR</strong> = Genomsnittlig reparationstid per incident (timmar) &nbsp;·&nbsp;
          Period: senaste {{ kpiDays }} dagar
        </div>
      </div>

    </div>
  `,
  styles: [`${SHARED_STYLES}`]
})
export class KpiAnalysisComponent implements OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  kpiRows: KpiRow[] = [];
  kpiLoading = false;
  kpiError = false;
  kpiDays = 90;
  kpiDayOptions = [30, 90, 180, 365];

  constructor(private http: HttpClient) {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadKpiData(): void {
    this.kpiLoading = true;
    this.kpiError = false;
    const url = `${this.apiBase}?action=maintenance&run=mttr-mtbf&days=${this.kpiDays}`;
    this.http.get<any>(url, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        this.kpiLoading = false;
        if (data?.kpis) {
          this.kpiRows = data.kpis;
        } else {
          this.kpiError = true;
        }
      });
  }

  setKpiDays(days: number): void {
    this.kpiDays = days;
    this.loadKpiData();
  }
  trackByIndex(index: number): number { return index; }
}
