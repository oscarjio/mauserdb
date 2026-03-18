import { Component, OnDestroy, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../../environments/environment';
import { MaintenanceEntry, EquipmentItem } from '../maintenance-log.models';
import { getKategoriLabel, SHARED_STYLES } from '../maintenance-log.helpers';
import { localDateStr } from '../../../utils/date-utils';

@Component({
  selector: 'app-maintenance-form',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <!-- FORMULÄRMODAL (overlay) -->
    <div class="modal-overlay" *ngIf="showForm" (click)="close()">
      <div class="modal-panel" (click)="$event.stopPropagation()">
        <div class="modal-header-custom">
          <h5 class="mb-0">
            <i class="fas fa-tools me-2"></i>
            {{ editingId ? 'Redigera underhållspost' : 'Ny underhållspost' }}
          </h5>
          <button class="btn-close-custom" (click)="close()">
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

              <!-- Utrustning -->
              <div class="col-12">
                <label class="form-label form-label-dark">Utrustning</label>
                <select class="form-select form-select-dark" [(ngModel)]="form.equipment" name="equipment">
                  <option value="">Välj utrustning</option>
                  <option *ngFor="let eq of equipmentList; trackBy: trackByNamn" [value]="eq.namn">{{ eq.namn }} ({{ getKategoriLabel(eq.kategori) }})</option>
                </select>
              </div>

              <!-- Titel -->
              <div class="col-12">
                <label class="form-label form-label-dark">Titel *</label>
                <input type="text" class="form-control form-control-dark"
                       [(ngModel)]="form.title" name="title" #titleCtrl="ngModel"
                       placeholder="Kortfattad beskrivning av underhållet"
                       maxlength="150" required />
                <div class="text-danger small mt-1" *ngIf="titleCtrl.invalid && titleCtrl.touched">
                  Titel krävs.
                </div>
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
                       [(ngModel)]="form.start_time" name="start_time" #startTimeCtrl="ngModel" required />
                <div class="text-danger small mt-1" *ngIf="startTimeCtrl.invalid && startTimeCtrl.touched">
                  Starttid krävs.
                </div>
              </div>

              <!-- Varaktighet -->
              <div class="col-12 col-md-6">
                <label class="form-label form-label-dark">Varaktighet (minuter)</label>
                <input type="number" class="form-control form-control-dark"
                       [(ngModel)]="form.duration_minutes" name="duration_minutes"
                       placeholder="Lämna tomt om pågående" min="0" max="14400" />
                <div class="form-text text-muted">Lämna tomt om underhållet pågår</div>
              </div>

              <!-- Driftstopp -->
              <div class="col-12 col-md-6">
                <label class="form-label form-label-dark">Driftstopp (min)</label>
                <input type="number" class="form-control form-control-dark"
                       [(ngModel)]="form.downtime_minutes" name="downtime_minutes"
                       placeholder="0 om inget driftstopp" min="0" max="14400" />
                <div class="form-text text-muted">Hur länge produktionen stod stilla</div>
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
                       placeholder="Valfritt — lämna tomt om okänd" min="0" max="99999999" step="0.01" />
              </div>

              <!-- Status -->
              <div class="col-12 col-md-6">
                <label class="form-label form-label-dark">Status *</label>
                <select class="form-select form-select-dark" [(ngModel)]="form.status" name="status" required>
                  <option value="planerat">Planerat</option>
                  <option value="pagaende">Pågående</option>
                  <option value="klart">Klart</option>
                  <option value="avbokat">Avbokat</option>
                </select>
              </div>

              <!-- Åtgärdad -->
              <div class="col-12 d-flex align-items-center gap-2">
                <div class="form-check form-check-dark">
                  <input class="form-check-input" type="checkbox" id="resolvedCheck"
                         [(ngModel)]="form.resolved" name="resolved" />
                  <label class="form-check-label form-label-dark" for="resolvedCheck">
                    Åtgärdad — problemet är löst
                  </label>
                </div>
              </div>
            </div>

            <!-- Formulärfel -->
            <div *ngIf="formError" class="alert alert-danger mt-3 mb-0 py-2">
              <i class="fas fa-exclamation-triangle me-2"></i>{{ formError }}
            </div>

            <!-- Knappar -->
            <div class="d-flex gap-2 mt-4 justify-content-end">
              <button type="button" class="btn btn-secondary" (click)="close()">Avbryt</button>
              <button type="submit" class="btn btn-success" [disabled]="isSaving || !form.title.trim() || !form.start_time">
                <span *ngIf="isSaving"><i class="fas fa-circle-notch fa-spin me-2"></i>Sparar...</span>
                <span *ngIf="!isSaving"><i class="fas fa-save me-2"></i>{{ editingId ? 'Uppdatera' : 'Spara' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  `,
  styles: [`${SHARED_STYLES}`]
})
export class MaintenanceFormComponent implements OnDestroy {
  private destroy$ = new Subject<void>();
  private apiBase = environment.apiUrl;

  @Input() equipmentList: EquipmentItem[] = [];
  @Output() saved = new EventEmitter<void>();
  @Output() closed = new EventEmitter<void>();

  showForm = false;
  editingId: number | null = null;
  isSaving = false;
  formError = '';

  form = {
    line: 'rebotling',
    maintenance_type: 'ovrigt',
    title: '',
    description: '',
    start_time: '',
    duration_minutes: null as number | null,
    performed_by: '',
    cost_sek: null as number | null,
    status: 'klart',
    equipment: '',
    downtime_minutes: 0 as number,
    resolved: false
  };

  getKategoriLabel = getKategoriLabel;

  constructor(private http: HttpClient) {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  openAdd(): void {
    this.editingId = null;
    const now = new Date();
    now.setMinutes(0, 0, 0);
    this.form = {
      line: 'rebotling',
      maintenance_type: 'ovrigt',
      title: '',
      description: '',
      start_time: localDateStr(now) + 'T' + String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'),
      duration_minutes: null,
      performed_by: '',
      cost_sek: null,
      status: 'klart',
      equipment: '',
      downtime_minutes: 0,
      resolved: false
    };
    this.formError = '';
    this.showForm = true;
  }

  openEdit(entry: MaintenanceEntry): void {
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
      status: entry.status,
      equipment: entry.equipment ?? '',
      downtime_minutes: entry.downtime_minutes ?? 0,
      resolved: !!entry.resolved
    };
    this.formError = '';
    this.showForm = true;
  }

  close(): void {
    this.showForm = false;
    this.formError = '';
    this.closed.emit();
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
    if (this.form.duration_minutes !== null && this.form.duration_minutes !== undefined && (+this.form.duration_minutes < 0 || +this.form.duration_minutes > 14400)) {
      this.formError = 'Varaktighet måste vara 0–14400 minuter';
      return;
    }
    if (this.form.downtime_minutes !== null && this.form.downtime_minutes !== undefined && (+this.form.downtime_minutes < 0 || +this.form.downtime_minutes > 14400)) {
      this.formError = 'Driftstopp måste vara 0–14400 minuter';
      return;
    }
    if (this.form.cost_sek !== null && this.form.cost_sek !== undefined && (+this.form.cost_sek < 0 || +this.form.cost_sek > 99999999)) {
      this.formError = 'Kostnad måste vara 0–99 999 999 kr';
      return;
    }

    this.isSaving = true;
    const payload = {
      ...this.form,
      duration_minutes: this.form.duration_minutes !== null && this.form.duration_minutes !== undefined && this.form.duration_minutes !== ('' as any)
        ? +this.form.duration_minutes : null,
      cost_sek: this.form.cost_sek !== null && this.form.cost_sek !== undefined && this.form.cost_sek !== ('' as any)
        ? +this.form.cost_sek : null,
      downtime_minutes: this.form.downtime_minutes ? +this.form.downtime_minutes : 0,
      equipment: this.form.equipment || null,
      resolved: this.form.resolved ? 1 : 0
    };

    const url = this.editingId
      ? `${this.apiBase}?action=maintenance&run=update&id=${this.editingId}`
      : `${this.apiBase}?action=maintenance&run=add`;

    this.http.post<any>(url, payload, { withCredentials: true })
      .pipe(timeout(10000), catchError(err => of({ error: err?.error?.error || 'Nätverksfel' })), takeUntil(this.destroy$))
      .subscribe(data => {
        this.isSaving = false;
        if (data?.success) {
          this.close();
          this.saved.emit();
        } else {
          this.formError = data?.error || 'Kunde inte spara';
        }
      });
  }
  trackByIndex(index: number): number { return index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
  trackByNamn(index: number, item: any): any { return item?.namn ?? index; }
}
