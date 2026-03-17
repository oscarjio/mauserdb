import { Component, OnDestroy, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { ServiceInterval } from '../maintenance-log.models';
import { SHARED_STYLES } from '../maintenance-log.helpers';

@Component({
  selector: 'app-service-intervals',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <!-- Varning-banner om kritisk -->
    <div class="alert alert-danger d-flex align-items-center mb-3" *ngIf="serviceKritiskCount > 0">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <span><strong>{{ serviceKritiskCount }} maskin{{ serviceKritiskCount > 1 ? 'er' : '' }}</strong> har &lt;10% kvar till service! Planera underhåll omgående.</span>
    </div>

    <!-- Laddning -->
    <div *ngIf="serviceLoading" class="text-center py-4 text-muted">
      <i class="fas fa-circle-notch fa-spin me-2"></i>Laddar serviceintervall...
    </div>

    <div *ngIf="!serviceLoading">

      <!-- Ingen data -->
      <div *ngIf="serviceIntervals.length === 0" class="empty-state">
        <i class="fas fa-oil-can fa-3x mb-3 text-muted"></i>
        <p class="mb-0">Inga serviceintervall konfigurerade.</p>
        <button class="btn btn-outline-success btn-sm mt-3" (click)="openServiceForm()">
          <i class="fas fa-plus me-1"></i>Lägg till serviceintervall
        </button>
      </div>

      <!-- Tabell -->
      <div class="stats-table-wrap" *ngIf="serviceIntervals.length > 0">
        <div class="d-flex justify-content-between align-items-center p-3" style="border-bottom: 1px solid #3d4f6b;">
          <h6 class="mb-0 text-muted"><i class="fas fa-oil-can me-2"></i>Konfigurerade serviceintervall</h6>
          <button class="btn btn-sm btn-outline-success" (click)="openServiceForm()">
            <i class="fas fa-plus me-1"></i>Nytt intervall
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-dark table-stats mb-0">
            <thead>
              <tr>
                <th>Maskin</th>
                <th class="text-end">Intervall</th>
                <th class="text-end">IBC sedan service</th>
                <th class="text-end">Kvar</th>
                <th>Status</th>
                <th style="width: 200px;">Progress</th>
                <th class="text-end">Åtgärder</th>
              </tr>
            </thead>
            <tbody>
              <tr *ngFor="let si of serviceIntervals; trackBy: trackByIndex">
                <td class="fw-semibold">{{ si.maskin_namn }}</td>
                <td class="text-end">{{ si.intervall_ibc | number }} IBC</td>
                <td class="text-end">{{ si.ibc_sedan_service | number }} IBC</td>
                <td class="text-end">
                  <span [class.text-success]="si.status === 'ok'"
                        [class.text-warning]="si.status === 'varning'"
                        [class.text-danger]="si.status === 'kritisk'">
                    {{ si.kvar | number }} IBC
                  </span>
                </td>
                <td>
                  <span class="badge"
                        [class.bg-success]="si.status === 'ok'"
                        [class.bg-warning]="si.status === 'varning'"
                        [class.text-dark]="si.status === 'varning'"
                        [class.bg-danger]="si.status === 'kritisk'">
                    {{ si.status === 'ok' ? 'OK' : si.status === 'varning' ? 'Snart service' : 'Kritisk' }}
                  </span>
                </td>
                <td>
                  <div class="progress" style="height: 10px; border-radius: 6px; background: #1a202c;">
                    <div class="progress-bar"
                         [class.bg-success]="si.status === 'ok'"
                         [class.bg-warning]="si.status === 'varning'"
                         [class.bg-danger]="si.status === 'kritisk'"
                         [style.width.%]="si.procent_kvar"
                         style="border-radius: 6px; transition: width 0.6s ease;">
                    </div>
                  </div>
                  <small class="text-muted" style="font-size: 0.7rem;">{{ si.procent_kvar }}% kvar</small>
                </td>
                <td class="text-end">
                  <div class="d-flex gap-1 justify-content-end">
                    <button class="btn btn-sm btn-action btn-service-reset" (click)="resetServiceCounter(si)"
                            title="Registrera utförd service">
                      <i class="fas fa-check-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-action btn-edit" (click)="openServiceEditForm(si)"
                            title="Redigera intervall">
                      <i class="fas fa-edit"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="p-3 text-muted" style="font-size: 0.78rem; border-top: 1px solid #3d4f6b;">
          <i class="fas fa-info-circle me-1"></i>
          Serviceintervall baseras på antal tvättade IBC sedan senaste service. Nollställ räknaren efter utförd service.
        </div>
      </div>

      <!-- Uppdatera-knapp -->
      <div class="text-end mt-2">
        <button class="btn btn-sm btn-outline-info" (click)="loadServiceIntervals()">
          <i class="fas fa-sync me-1"></i>Uppdatera
        </button>
      </div>
    </div>

    <!-- SERVICEINTERVALL-MODAL -->
    <div class="modal-overlay" *ngIf="showServiceForm" (click)="closeServiceForm()">
      <div class="modal-panel" (click)="$event.stopPropagation()">
        <div class="modal-header-custom">
          <h5 class="mb-0">
            <i class="fas fa-oil-can me-2"></i>
            {{ editingServiceId ? 'Redigera serviceintervall' : 'Nytt serviceintervall' }}
          </h5>
          <button class="btn-close-custom" (click)="closeServiceForm()">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body-custom">
          <form (ngSubmit)="saveServiceInterval()">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label form-label-dark">Maskinnamn *</label>
                <input type="text" class="form-control form-control-dark"
                       [(ngModel)]="serviceForm.maskin_namn" name="maskin_namn"
                       placeholder="t.ex. Rebotling-linje 1" maxlength="100" required />
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label form-label-dark">Intervall (IBC) *</label>
                <input type="number" class="form-control form-control-dark"
                       [(ngModel)]="serviceForm.intervall_ibc" name="intervall_ibc"
                       placeholder="5000" min="1" required />
                <div class="form-text text-muted">Service var X:e IBC</div>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label form-label-dark">Senaste service IBC-räknare</label>
                <input type="number" class="form-control form-control-dark"
                       [(ngModel)]="serviceForm.senaste_service_ibc" name="senaste_service_ibc"
                       placeholder="0" min="0" />
              </div>
              <div class="col-12">
                <label class="form-label form-label-dark">Senaste servicedatum</label>
                <input type="datetime-local" class="form-control form-control-dark"
                       [(ngModel)]="serviceForm.senaste_service_datum" name="senaste_service_datum" />
              </div>
            </div>
            <div *ngIf="serviceFormError" class="alert alert-danger mt-3 mb-0 py-2">
              <i class="fas fa-exclamation-triangle me-2"></i>{{ serviceFormError }}
            </div>
            <div class="d-flex gap-2 mt-4 justify-content-end">
              <button type="button" class="btn btn-secondary" (click)="closeServiceForm()">Avbryt</button>
              <button type="submit" class="btn btn-success" [disabled]="isSavingService || !serviceForm.maskin_namn.trim() || !serviceForm.intervall_ibc || serviceForm.intervall_ibc <= 0">
                <span *ngIf="isSavingService"><i class="fas fa-circle-notch fa-spin me-2"></i>Sparar...</span>
                <span *ngIf="!isSavingService"><i class="fas fa-save me-2"></i>{{ editingServiceId ? 'Uppdatera' : 'Spara' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  `,
  styles: [`${SHARED_STYLES}`]
})
export class ServiceIntervalsComponent implements OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  @Output() showSuccess = new EventEmitter<string>();
  @Output() showError = new EventEmitter<string>();

  serviceIntervals: ServiceInterval[] = [];
  serviceLoading = false;
  showServiceForm = false;
  editingServiceId: number | null = null;
  isSavingService = false;
  serviceFormError = '';
  serviceForm = {
    maskin_namn: '',
    intervall_ibc: 5000,
    senaste_service_datum: '',
    senaste_service_ibc: 0
  };

  get serviceKritiskCount(): number {
    return this.serviceIntervals.filter(s => s.status === 'kritisk').length;
  }

  constructor(private http: HttpClient) {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadServiceIntervals(): void {
    this.serviceLoading = true;
    this.http.get<any>(`${this.apiBase}?action=maintenance&run=service-intervals`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        this.serviceLoading = false;
        if (data?.intervals) {
          this.serviceIntervals = data.intervals;
        }
      });
  }

  openServiceForm(): void {
    this.editingServiceId = null;
    this.serviceForm = {
      maskin_namn: '',
      intervall_ibc: 5000,
      senaste_service_datum: '',
      senaste_service_ibc: 0
    };
    this.serviceFormError = '';
    this.showServiceForm = true;
  }

  openServiceEditForm(si: ServiceInterval): void {
    this.editingServiceId = si.id;
    this.serviceForm = {
      maskin_namn: si.maskin_namn,
      intervall_ibc: si.intervall_ibc,
      senaste_service_datum: si.senaste_service_datum?.replace(' ', 'T').slice(0, 16) ?? '',
      senaste_service_ibc: si.senaste_service_ibc
    };
    this.serviceFormError = '';
    this.showServiceForm = true;
  }

  closeServiceForm(): void {
    this.showServiceForm = false;
    this.serviceFormError = '';
  }

  saveServiceInterval(): void {
    this.serviceFormError = '';
    if (!this.serviceForm.maskin_namn.trim()) {
      this.serviceFormError = 'Maskinnamn krävs';
      return;
    }
    if (!this.serviceForm.intervall_ibc || this.serviceForm.intervall_ibc <= 0) {
      this.serviceFormError = 'Intervall måste vara > 0';
      return;
    }

    this.isSavingService = true;
    const payload: any = {
      ...this.serviceForm,
      senaste_service_datum: this.serviceForm.senaste_service_datum || null
    };
    if (this.editingServiceId) {
      payload.id = this.editingServiceId;
    }

    this.http.post<any>(`${this.apiBase}?action=maintenance&run=set-service-interval`, payload, { withCredentials: true })
      .pipe(timeout(10000), catchError(err => of({ error: err?.error?.error || 'Nätverksfel' })), takeUntil(this.destroy$))
      .subscribe(data => {
        this.isSavingService = false;
        if (data?.success) {
          this.showSuccess.emit(this.editingServiceId ? 'Serviceintervall uppdaterat!' : 'Serviceintervall sparat!');
          this.closeServiceForm();
          this.loadServiceIntervals();
        } else {
          this.serviceFormError = data?.error || 'Kunde inte spara';
        }
      });
  }

  resetServiceCounter(si: ServiceInterval): void {
    if (!confirm(`Registrera utförd service för "${si.maskin_namn}"?\nDetta nollställer IBC-räknaren.`)) return;

    this.http.post<any>(`${this.apiBase}?action=maintenance&run=reset-service-counter`, { id: si.id }, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(data => {
        if (data?.success) {
          this.showSuccess.emit('Serviceräknare nollställd!');
          this.loadServiceIntervals();
        } else {
          this.showError.emit(data?.error || 'Kunde inte nollställa räknare');
        }
      });
  }
  trackByIndex(index: number): number { return index; }
}
