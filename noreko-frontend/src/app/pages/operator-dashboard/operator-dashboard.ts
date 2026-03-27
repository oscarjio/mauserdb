import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { parseLocalDate } from '../../utils/date-utils';
import { environment } from '../../../environments/environment';
Chart.register(...registerables);

// ================================================================
// Interfaces
// ================================================================

interface Operator {
  op_id: number;
  namn: string;
  initialer: string;
  ibc_idag: number;
  ibc_per_h: number;
  kvalitet_pct: number | null;
  minuter_sedan: number | null;
  status: 'bra' | 'ok' | 'lag' | 'inaktiv';
}

interface DashboardData {
  success: boolean;
  datum: string;
  operatorer: Operator[];
  total_ibc: number;
  snitt_ibc_per_h: number;
  bast_namn: string | null;
  bast_ibc_per_h: number;
}

interface WeeklyOperator {
  op_id: number;
  namn: string;
  initialer: string;
  total_ibc: number;
  snitt_ibc_per_h: number;
  aktiva_dagar: number;
  trend: 'upp' | 'ner' | 'stabil';
  bast_dag_ibc: number;
}

interface WeeklyData {
  success: boolean;
  operatorer: WeeklyOperator[];
  fran: string;
  till: string;
}

interface HistoryOperator {
  op_id: number;
  namn: string;
  initialer: string;
  data: number[];
}

interface HistoryData {
  success: boolean;
  dates: string[];
  operators: HistoryOperator[];
}

interface SummaryData {
  success: boolean;
  idag_total_ibc: number;
  idag_snitt_ibc_per_h: number;
  idag_aktiva_operatorer: number;
  vecka_total_ibc: number;
  vecka_snitt_ibc_per_h: number;
  'vecka_bast_operatör': string | null;
  manad_total_ibc: number;
}

interface FeedbackSummary {
  avg_stamning: number;
  total: number;
  per_dag: Array<{ datum: string; snitt: number }>;
}

// ================================================================
// Component
// ================================================================

@Component({
  standalone: true,
  selector: 'app-operator-dashboard',
  imports: [CommonModule, RouterModule],
  template: `
    <div class="operator-dashboard-page" style="background:#1a202c;min-height:100vh;padding:24px 16px;">

      <!-- Rubrik -->
      <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
          <h2 style="color:#e2e8f0;margin:0;font-weight:700;">
            <i class="fas fa-users-cog me-2" style="color:#63b3ed;"></i>Operatörsdashboard
          </h2>
          <small style="color:#718096;">
            Uppdateras automatiskt var 60:e sekund &bull; {{ datum }}
          </small>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span *ngIf="isFetching" style="color:#63b3ed;font-size:13px;">
            <i class="fas fa-sync-alt fa-spin me-1"></i>Uppdaterar&hellip;
          </span>
          <span *ngIf="felmeddelande" style="color:#fc8181;font-size:13px;">
            <i class="fas fa-exclamation-triangle me-1"></i>{{ felmeddelande }}
          </span>
        </div>
      </div>

      <!-- Tab-navigation -->
      <div class="d-flex gap-2 mb-4 flex-wrap">
        <button
          (click)="setTab('idag')"
          [style.background]="activeTab === 'idag' ? '#3182ce' : '#2d3748'"
          [style.color]="activeTab === 'idag' ? '#fff' : '#a0aec0'"
          [style.border]="activeTab === 'idag' ? '1px solid #3182ce' : '1px solid #4a5568'"
          style="padding:8px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;">
          <i class="fas fa-calendar-day me-1"></i>Idag
        </button>
        <button
          (click)="setTab('vecka')"
          [style.background]="activeTab === 'vecka' ? '#3182ce' : '#2d3748'"
          [style.color]="activeTab === 'vecka' ? '#fff' : '#a0aec0'"
          [style.border]="activeTab === 'vecka' ? '1px solid #3182ce' : '1px solid #4a5568'"
          style="padding:8px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;">
          <i class="fas fa-calendar-week me-1"></i>Vecka
        </button>
        <button
          (click)="setTab('stamning')"
          [style.background]="activeTab === 'stamning' ? '#805ad5' : '#2d3748'"
          [style.color]="activeTab === 'stamning' ? '#fff' : '#a0aec0'"
          [style.border]="activeTab === 'stamning' ? '1px solid #805ad5' : '1px solid #4a5568'"
          style="padding:8px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;">
          &#128522; Teamst&auml;mning
        </button>
      </div>

      <!-- ============================================================ -->
      <!-- IDAG-FLIKEN -->
      <!-- ============================================================ -->
      <ng-container *ngIf="activeTab === 'idag'">

        <!-- Skeleton / Laddningsindikator -->
        <div *ngIf="laddar && operatorer.length === 0" class="text-center py-5">
          <div class="spinner-border" style="color:#63b3ed;" role="status">
            <span class="visually-hidden">Laddar&hellip;</span>
          </div>
          <p style="color:#718096;margin-top:12px;">H&auml;mtar operat&ouml;rsstatus&hellip;</p>
        </div>

        <!-- KPI-kort -->
        <div *ngIf="!laddar || operatorer.length > 0" class="row g-3 mb-4">
          <div class="col-6 col-md-3">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;color:#63b3ed;">{{ operatorer.length }}</div>
              <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
                <i class="fas fa-user me-1"></i>Aktiva idag
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;color:#68d391;">{{ snittIbcPerH | number:'1.1-1' }}</div>
              <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
                <i class="fas fa-tachometer-alt me-1"></i>Snitt IBC/h
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:1.3rem;font-weight:700;color:#f6e05e;line-height:1.2;">{{ bastNamn || '&mdash;' }}</div>
              <div style="color:#a0aec0;font-size:12px;">{{ bastIbcPerH | number:'1.1-1' }} IBC/h</div>
              <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
                <i class="fas fa-trophy me-1"></i>B&auml;st idag
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;color:#fc8181;">{{ totalIbc }}</div>
              <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
                <i class="fas fa-box me-1"></i>Totalt IBC idag
              </div>
            </div>
          </div>
        </div>

        <!-- Operatörstabell -->
        <div *ngIf="!laddar || operatorer.length > 0"
             style="background:#2d3748;border-radius:12px;overflow:hidden;border:1px solid #4a5568;">
          <div style="padding:16px 20px;border-bottom:1px solid #4a5568;">
            <h5 style="color:#e2e8f0;margin:0;font-size:16px;">
              <i class="fas fa-list me-2" style="color:#63b3ed;"></i>Operat&ouml;rsstatus
            </h5>
          </div>

          <!-- Tom state -->
          <div *ngIf="operatorer.length === 0 && !laddar" class="text-center py-5">
            <i class="fas fa-inbox" style="font-size:2rem;color:#4a5568;"></i>
            <p style="color:#718096;margin-top:12px;">Inga operat&ouml;rer registrerade idag</p>
          </div>

          <!-- Tabell -->
          <div *ngIf="operatorer.length > 0" class="table-responsive">
            <table class="table table-dark mb-0"
                   style="--bs-table-bg:#2d3748;--bs-table-striped-bg:#283141;--bs-table-border-color:#4a5568;color:#e2e8f0;">
              <thead>
                <tr style="background:#1e2535;color:#a0aec0;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;">Operat&ouml;r</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">IBC idag</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">IBC/h</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">Kvalitet%</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">Senast aktiv</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:center;">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr *ngFor="let op of operatorer; trackBy: trackByIndex" style="border-bottom:1px solid #3d4a5c;cursor:pointer;" [routerLink]="['/admin/operator', op.op_id]">
                  <td style="padding:14px 16px;vertical-align:middle;">
                    <div class="d-flex align-items-center gap-3">
                      <div [style.background]="getAvatarColor(op.namn)"
                           style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0;">
                        {{ op.initialer }}
                      </div>
                      <div>
                        <div style="font-weight:600;color:#e2e8f0;">
                          {{ op.namn }}
                          <i class="fas fa-external-link-alt" style="font-size:10px;color:#63b3ed;margin-left:4px;"></i>
                        </div>
                        <div style="font-size:11px;color:#718096;">Op. #{{ op.op_id }}</div>
                      </div>
                    </div>
                  </td>
                  <td style="padding:14px 16px;vertical-align:middle;text-align:right;font-size:15px;font-weight:600;color:#e2e8f0;">
                    {{ op.ibc_idag }}
                  </td>
                  <td style="padding:14px 16px;vertical-align:middle;text-align:right;">
                    <span style="font-size:15px;font-weight:700;"
                          [style.color]="getIbcColor(op.ibc_per_h, op.status)">
                      {{ op.ibc_per_h | number:'1.1-1' }}
                    </span>
                  </td>
                  <td style="padding:14px 16px;vertical-align:middle;text-align:right;">
                    <span *ngIf="op.kvalitet_pct !== null"
                          [style.color]="op.kvalitet_pct >= 95 ? '#68d391' : op.kvalitet_pct >= 85 ? '#f6e05e' : '#fc8181'"
                          style="font-size:14px;">
                      {{ op.kvalitet_pct | number:'1.1-1' }}%
                    </span>
                    <span *ngIf="op.kvalitet_pct === null" style="color:#4a5568;">&#8212;</span>
                  </td>
                  <td style="padding:14px 16px;vertical-align:middle;text-align:right;color:#a0aec0;font-size:13px;">
                    <span *ngIf="op.minuter_sedan !== null">
                      <span *ngIf="op.minuter_sedan < 60">{{ op.minuter_sedan }} min sedan</span>
                      <span *ngIf="op.minuter_sedan >= 60">{{ timmar(op.minuter_sedan) }} h {{ minuter(op.minuter_sedan) }} min sedan</span>
                    </span>
                    <span *ngIf="op.minuter_sedan === null" style="color:#4a5568;">Ok&auml;nd</span>
                  </td>
                  <td style="padding:14px 16px;vertical-align:middle;text-align:center;">
                    <span [style.background]="statusBgColor(op.status)"
                          [style.color]="statusTextColor(op.status)"
                          style="padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;">
                      {{ statusLabel(op.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Statuslegend -->
        <div class="d-flex flex-wrap gap-3 mt-3" style="font-size:12px;color:#718096;">
          <span><span style="display:inline-block;width:10px;height:10px;background:#276749;border-radius:50%;margin-right:4px;"></span>Bra (&gt;18 IBC/h)</span>
          <span><span style="display:inline-block;width:10px;height:10px;background:#744210;border-radius:50%;margin-right:4px;"></span>OK (12&#8211;18 IBC/h)</span>
          <span><span style="display:inline-block;width:10px;height:10px;background:#742a2a;border-radius:50%;margin-right:4px;"></span>L&aring;g (&lt;12 IBC/h)</span>
          <span><span style="display:inline-block;width:10px;height:10px;background:#2d3748;border:1px solid #4a5568;border-radius:50%;margin-right:4px;"></span>Inaktiv (&gt;30 min)</span>
        </div>

      </ng-container>

      <!-- ============================================================ -->
      <!-- VECKA-FLIKEN -->
      <!-- ============================================================ -->
      <ng-container *ngIf="activeTab === 'vecka'">

        <!-- Laddning veckodata (initial) -->
        <div *ngIf="laddarVecka && weeklyData.length === 0" class="text-center py-5">
          <div class="spinner-border" style="color:#63b3ed;" role="status">
            <span class="visually-hidden">Laddar&hellip;</span>
          </div>
          <p style="color:#718096;margin-top:12px;">H&auml;mtar veckostats&hellip;</p>
        </div>

        <!-- Laddning veckodata (uppdatering med befintlig data) -->
        <div *ngIf="laddarVecka && weeklyData.length > 0"
             style="text-align:right;margin-bottom:8px;color:#63b3ed;font-size:13px;">
          <i class="fas fa-sync-alt fa-spin me-1"></i>Uppdaterar veckostats&hellip;
        </div>

        <!-- Summary-kort (3 kort) -->
        <div *ngIf="summaryData" class="row g-3 mb-4">
          <div class="col-12 col-md-4">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;color:#63b3ed;">{{ summaryData.vecka_total_ibc }}</div>
              <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
                <i class="fas fa-boxes me-1"></i>Veckans IBC (totalt)
              </div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;color:#68d391;">{{ summaryData.vecka_snitt_ibc_per_h | number:'1.1-1' }}</div>
              <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
                <i class="fas fa-tachometer-alt me-1"></i>Snitt IBC/h (vecka)
              </div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:1.3rem;font-weight:700;color:#f6e05e;line-height:1.2;">
                {{ summaryData['vecka_bast_operatör'] || '&#8212;' }}
              </div>
              <div style="color:#a0aec0;font-size:13px;margin-top:4px;">
                <i class="fas fa-trophy me-1"></i>B&auml;sta operat&ouml;r (vecka)
              </div>
            </div>
          </div>
        </div>

        <!-- Veckotabell -->
        <div *ngIf="weeklyData.length > 0"
             style="background:#2d3748;border-radius:12px;overflow:hidden;border:1px solid #4a5568;margin-bottom:24px;">
          <div style="padding:16px 20px;border-bottom:1px solid #4a5568;">
            <h5 style="color:#e2e8f0;margin:0;font-size:16px;">
              <i class="fas fa-chart-bar me-2" style="color:#63b3ed;"></i>
              Veckostats &#8212; senaste 7 dagarna
            </h5>
          </div>
          <div class="table-responsive">
            <table class="table table-dark mb-0"
                   style="--bs-table-bg:#2d3748;--bs-table-border-color:#4a5568;color:#e2e8f0;">
              <thead>
                <tr style="background:#1e2535;color:#a0aec0;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;">Rang</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;">Operat&ouml;r</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">IBC (vecka)</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">Snitt IBC/h</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:center;">Aktiva dagar</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:right;">B&auml;sta dag</th>
                  <th style="padding:12px 16px;border-bottom:1px solid #4a5568;text-align:center;">Trend</th>
                </tr>
              </thead>
              <tbody>
                <tr *ngFor="let op of weeklyData; let i = index; trackBy: trackByIndex"
                    [style.background]="i === 0 ? 'rgba(246,224,94,0.08)' : ''"
                    [style.border-left]="i === 0 ? '3px solid #f6e05e' : '3px solid transparent'"
                    style="border-bottom:1px solid #3d4a5c;cursor:pointer;" [routerLink]="['/admin/operator', op.op_id]">
                  <!-- Rang -->
                  <td style="padding:14px 16px;vertical-align:middle;text-align:center;">
                    <span [style.color]="i === 0 ? '#f6e05e' : i === 1 ? '#a0aec0' : i === 2 ? '#ed8936' : '#718096'"
                          style="font-weight:700;font-size:15px;">
                      {{ i + 1 }}
                    </span>
                  </td>
                  <!-- Avatar + Namn -->
                  <td style="padding:14px 16px;vertical-align:middle;">
                    <div class="d-flex align-items-center gap-3">
                      <div [style.background]="getAvatarColor(op.namn)"
                           style="width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0;">
                        {{ op.initialer }}
                      </div>
                      <div>
                        <div style="font-weight:600;color:#e2e8f0;">
                          {{ op.namn }}
                          <i class="fas fa-external-link-alt" style="font-size:10px;color:#63b3ed;margin-left:4px;"></i>
                        </div>
                        <div style="font-size:11px;color:#718096;">Op. #{{ op.op_id }}</div>
                      </div>
                    </div>
                  </td>
                  <!-- IBC vecka -->
                  <td style="padding:14px 16px;vertical-align:middle;text-align:right;font-size:16px;font-weight:700;color:#63b3ed;">
                    {{ op.total_ibc }}
                  </td>
                  <!-- Snitt IBC/h -->
                  <td style="padding:14px 16px;vertical-align:middle;text-align:right;">
                    <span [style.color]="op.snitt_ibc_per_h >= 18 ? '#68d391' : op.snitt_ibc_per_h >= 12 ? '#f6e05e' : '#fc8181'"
                          style="font-size:15px;font-weight:700;">
                      {{ op.snitt_ibc_per_h | number:'1.1-1' }}
                    </span>
                  </td>
                  <!-- Aktiva dagar -->
                  <td style="padding:14px 16px;vertical-align:middle;text-align:center;color:#a0aec0;">
                    {{ op.aktiva_dagar }} / 7
                  </td>
                  <!-- Bästa dag -->
                  <td style="padding:14px 16px;vertical-align:middle;text-align:right;color:#e2e8f0;">
                    {{ op.bast_dag_ibc }}
                  </td>
                  <!-- Trend -->
                  <td style="padding:14px 16px;vertical-align:middle;text-align:center;">
                    <span *ngIf="op.trend === 'upp'" style="color:#68d391;font-size:16px;" title="Uppåt">
                      <i class="fas fa-arrow-up"></i>
                    </span>
                    <span *ngIf="op.trend === 'ner'" style="color:#fc8181;font-size:16px;" title="Nedåt">
                      <i class="fas fa-arrow-down"></i>
                    </span>
                    <span *ngIf="op.trend === 'stabil'" style="color:#718096;font-size:16px;" title="Stabil">
                      <i class="fas fa-minus"></i>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Linjegraf — top 3 operatörer, senaste 7 dagarna -->
        <div *ngIf="historyData && historyData.operators.length > 0"
             style="background:#2d3748;border-radius:12px;padding:20px;border:1px solid #4a5568;margin-bottom:24px;">
          <h5 style="color:#e2e8f0;margin:0 0 16px;font-size:16px;">
            <i class="fas fa-chart-line me-2" style="color:#63b3ed;"></i>
            IBC per dag &#8212; topp 3 operat&ouml;rer (7 dagar)
          </h5>
          <div style="position:relative;height:260px;">
            <canvas id="weekChart"></canvas>
          </div>
        </div>

        <!-- Tom state vecka -->
        <div *ngIf="!laddarVecka && weeklyData.length === 0" class="text-center py-5">
          <i class="fas fa-calendar-times mb-3" style="font-size:2rem;opacity:.4;color:#718096;"></i>
          <p style="color:#718096;margin-top:12px;">Ingen veckodata tillg&auml;nglig.</p>
          <p class="small" style="color:#4a5568;">V&auml;lj en annan vecka eller kontrollera att skift registrerats.</p>
        </div>

      </ng-container>

      <!-- ============================================================ -->
      <!-- TEAMSTÄMNING-FLIKEN -->
      <!-- ============================================================ -->
      <ng-container *ngIf="activeTab === 'stamning'">

        <!-- Laddning -->
        <div *ngIf="feedbackLoading && !feedbackSummary" class="text-center py-5">
          <div class="spinner-border" style="color:#805ad5;" role="status">
            <span class="visually-hidden">Laddar&hellip;</span>
          </div>
          <p style="color:#718096;margin-top:12px;">H&auml;mtar st&auml;mningsdata&hellip;</p>
        </div>

        <!-- Uppdaterar (har befintlig data) -->
        <div *ngIf="feedbackLoading && feedbackSummary"
             style="text-align:right;margin-bottom:8px;color:#805ad5;font-size:13px;">
          <i class="fas fa-sync-alt fa-spin me-1"></i>Uppdaterar st&auml;mningsdata&hellip;
        </div>

        <!-- Huvudkort -->
        <div *ngIf="!feedbackLoading || feedbackSummary">

          <!-- Tom state -->
          <div *ngIf="feedbackSummary && feedbackSummary.total === 0" class="text-center py-5">
            <div style="font-size:3rem;margin-bottom:16px;">&#128578;</div>
            <p style="color:#a0aec0;font-size:15px;margin:0;">Inga feedbacks registrerade &auml;nnu.</p>
            <p style="color:#718096;font-size:13px;margin-top:8px;">
              Operat&ouml;rerna kan skicka st&auml;mning fr&aring;n Min Bonus-sidan.
            </p>
          </div>

          <!-- Innehåll när data finns -->
          <div *ngIf="feedbackSummary && feedbackSummary.total > 0">

            <!-- KPI-kort överst -->
            <div class="row g-3 mb-4">
              <!-- Snitt-stämning stort kort -->
              <div class="col-12 col-md-6">
                <div style="background:#2d3748;border-radius:12px;padding:24px;border:1px solid #4a5568;height:100%;">
                  <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                      <div style="color:#a0aec0;font-size:13px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">
                        Snitt-st&auml;mning (30 dagar)
                      </div>
                      <div style="font-size:3rem;font-weight:700;line-height:1;"
                           [style.color]="stamningFarg(feedbackSummary.avg_stamning)">
                        {{ feedbackSummary.avg_stamning | number:'1.1-1' }}
                        <span style="font-size:1.2rem;color:#718096;">&nbsp;/ 4.0</span>
                      </div>
                    </div>
                    <div style="font-size:3rem;line-height:1;">
                      {{ stamningIkon(feedbackSummary.avg_stamning) }}
                    </div>
                  </div>
                  <!-- Progressbar -->
                  <div style="background:#1a202c;border-radius:99px;height:14px;overflow:hidden;margin-top:12px;">
                    <div [style.width]="(feedbackSummary.avg_stamning / 4 * 100) + '%'"
                         [style.background]="stamningGradient(feedbackSummary.avg_stamning)"
                         style="height:100%;border-radius:99px;transition:width .5s ease;">
                    </div>
                  </div>
                  <div class="d-flex justify-content-between mt-1" style="font-size:11px;color:#718096;">
                    <span>&#128543; 1</span>
                    <span>&#128528; 2</span>
                    <span>&#128522; 3</span>
                    <span>&#127775; 4</span>
                  </div>
                </div>
              </div>

              <!-- Antal feedbacks + varningsnivå -->
              <div class="col-12 col-md-6">
                <div class="row g-3 h-100">
                  <div class="col-6">
                    <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;height:100%;display:flex;flex-direction:column;justify-content:center;">
                      <div style="font-size:2.2rem;font-weight:700;color:#b794f4;">
                        {{ feedbackSummary.total }}
                      </div>
                      <div style="color:#a0aec0;font-size:13px;margin-top:6px;">
                        <i class="fas fa-comment-dots me-1"></i>Inskickade feedbacks
                      </div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div [style.border]="feedbackSummary.avg_stamning < 2.5 ? '1px solid #fc8181' : '1px solid #4a5568'"
                         style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;height:100%;display:flex;flex-direction:column;justify-content:center;">
                      <div style="font-size:1.8rem;">
                        <span *ngIf="feedbackSummary.avg_stamning >= 3.5">&#128994;</span>
                        <span *ngIf="feedbackSummary.avg_stamning >= 2.5 && feedbackSummary.avg_stamning < 3.5">&#128993;</span>
                        <span *ngIf="feedbackSummary.avg_stamning < 2.5">&#128308;</span>
                      </div>
                      <div style="font-size:13px;font-weight:600;margin-top:6px;"
                           [style.color]="feedbackSummary.avg_stamning >= 3.5 ? '#68d391' : feedbackSummary.avg_stamning >= 2.5 ? '#f6e05e' : '#fc8181'">
                        <span *ngIf="feedbackSummary.avg_stamning >= 3.5">Bra st&auml;mning</span>
                        <span *ngIf="feedbackSummary.avg_stamning >= 2.5 && feedbackSummary.avg_stamning < 3.5">Neutral niv&aring;</span>
                        <span *ngIf="feedbackSummary.avg_stamning < 2.5">Uppm&auml;rksamma!</span>
                      </div>
                      <div *ngIf="feedbackSummary.avg_stamning < 2.5"
                           style="font-size:11px;color:#fc8181;margin-top:4px;">
                        St&auml;mningen &auml;r under varningsniv&aring;n (2.5)
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Dagslista -->
            <div style="background:#2d3748;border-radius:12px;overflow:hidden;border:1px solid #4a5568;">
              <div style="padding:16px 20px;border-bottom:1px solid #4a5568;">
                <h5 style="color:#e2e8f0;margin:0;font-size:16px;">
                  &#128200; Senaste dagar
                </h5>
              </div>

              <!-- Tom dagslista -->
              <div *ngIf="feedbackSummary.per_dag.length === 0" class="text-center py-4">
                <p style="color:#718096;margin:0;">Ingen daglig data tillg&auml;nglig.</p>
              </div>

              <!-- Dagarna -->
              <div *ngIf="feedbackSummary.per_dag.length > 0">
                <div *ngFor="let dag of feedbackSummary.per_dag; let i = index; let odd = odd; trackBy: trackByIndex"
                     [style.background]="odd ? '#283141' : '#2d3748'"
                     style="padding:14px 20px;border-bottom:1px solid #374151;display:flex;align-items:center;gap:16px;">

                  <!-- Datum -->
                  <div style="width:90px;flex-shrink:0;font-size:13px;color:#a0aec0;font-weight:500;">
                    {{ formatShortDate(dag.datum) }}
                  </div>

                  <!-- Ikon -->
                  <div style="font-size:1.4rem;flex-shrink:0;">
                    {{ stamningIkon(dag.snitt) }}
                  </div>

                  <!-- Snittvärde -->
                  <div style="width:44px;flex-shrink:0;font-weight:700;font-size:15px;"
                       [style.color]="stamningFarg(dag.snitt)">
                    {{ dag.snitt | number:'1.1-1' }}
                  </div>

                  <!-- Progressbar -->
                  <div style="flex:1;background:#1a202c;border-radius:99px;height:10px;overflow:hidden;">
                    <div [style.width]="(dag.snitt / 4 * 100) + '%'"
                         [style.background]="stamningGradient(dag.snitt)"
                         style="height:100%;border-radius:99px;transition:width .4s ease;">
                    </div>
                  </div>

                  <!-- Procent -->
                  <div style="width:40px;flex-shrink:0;text-align:right;font-size:12px;color:#718096;">
                    {{ (dag.snitt / 4 * 100) | number:'1.0-0' }}%
                  </div>

                </div>
              </div>
            </div>

            <!-- Förklaring av ikoner -->
            <div class="d-flex flex-wrap gap-4 mt-3" style="font-size:12px;color:#718096;">
              <span>&#128543; 1 = Mycket d&aring;ligt</span>
              <span>&#128528; 2 = D&aring;ligt</span>
              <span>&#128522; 3 = Bra</span>
              <span>&#127775; 4 = Utm&auml;rkt</span>
              <span style="margin-left:auto;">
                <span style="display:inline-block;width:10px;height:10px;background:#68d391;border-radius:50%;margin-right:4px;"></span>&ge;3.5 Bra &nbsp;
                <span style="display:inline-block;width:10px;height:10px;background:#f6e05e;border-radius:50%;margin-right:4px;"></span>2.5&#8211;3.5 Neutral &nbsp;
                <span style="display:inline-block;width:10px;height:10px;background:#fc8181;border-radius:50%;margin-right:4px;"></span>&lt;2.5 Varning
              </span>
            </div>

          </div><!-- /innehåll -->

        </div><!-- /!feedbackLoading || feedbackSummary -->

      </ng-container>

    </div>
  `
})
export class OperatorDashboardPage implements OnInit, OnDestroy {
  Math = Math;

  // Idag
  operatorer: Operator[] = [];
  datum = '';
  totalIbc = 0;
  snittIbcPerH = 0;
  bastNamn: string | null = null;
  bastIbcPerH = 0;

  // Vecka
  weeklyData: WeeklyOperator[] = [];
  summaryData: SummaryData | null = null;
  historyData: HistoryData | null = null;

  // Feedback / Teamstämning
  feedbackSummary: FeedbackSummary | null = null;
  feedbackLoading = false;

  // UI state
  activeTab: 'idag' | 'vecka' | 'stamning' = 'idag';
  laddar = false;
  laddarVecka = false;
  isFetching = false;
  felmeddelande = '';

  private destroy$ = new Subject<void>();
  private pollingInterval: any = null;
  private weekChart: Chart | null = null;
  private weekChartTimer: any = null;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.laddaData();
    this.loadFeedbackSummary();
    this.pollingInterval = setInterval(() => {
      this.laddaData();
      if (this.activeTab === 'vecka') {
        this.laddaVeckodata();
      }
      if (this.activeTab === 'stamning') {
        this.loadFeedbackSummary();
      }
    }, 60000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollingInterval) {
      clearInterval(this.pollingInterval);
    }
    if (this.weekChartTimer) {
      clearTimeout(this.weekChartTimer);
      this.weekChartTimer = null;
    }
    try { this.weekChart?.destroy(); } catch (e) {}
    this.weekChart = null;
  }

  // ================================================================
  // Tab
  // ================================================================

  setTab(tab: 'idag' | 'vecka' | 'stamning'): void {
    this.activeTab = tab;
    if (tab === 'vecka' && this.weeklyData.length === 0) {
      this.laddaVeckodata();
    }
    if (tab === 'stamning' && !this.feedbackSummary) {
      this.loadFeedbackSummary();
    }
  }

  // ================================================================
  // Idag
  // ================================================================

  laddaData(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    if (this.operatorer.length === 0) this.laddar = true;
    this.felmeddelande = '';

    this.http.get<DashboardData>(`${environment.apiUrl}?action=operator-dashboard&run=today`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.isFetching = false;
          this.laddar = false;
          if (!res) { this.felmeddelande = 'Kunde inte hämta data'; return; }
          if (!res.success) { this.felmeddelande = 'Serverfel'; return; }
          this.operatorer    = res.operatorer || [];
          this.datum         = this.formatDatum(res.datum);
          this.totalIbc      = res.total_ibc ?? 0;
          this.snittIbcPerH  = res.snitt_ibc_per_h ?? 0;
          this.bastNamn      = res.bast_namn;
          this.bastIbcPerH   = res.bast_ibc_per_h ?? 0;
        },
        error: () => {
          this.isFetching = false;
          this.laddar = false;
          this.felmeddelande = 'Kunde inte hämta data';
        }
      });
  }

  // ================================================================
  // Veckodata
  // ================================================================

  laddaVeckodata(): void {
    if (this.laddarVecka) return;
    this.laddarVecka = true;

    this.http.get<WeeklyData>(`${environment.apiUrl}?action=operator-dashboard&run=weekly`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          this.weeklyData = res.operatorer || [];
        }
        this.laddarVecka = false;
      });

    this.http.get<SummaryData>(`${environment.apiUrl}?action=operator-dashboard&run=summary`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          this.summaryData = res;
        }
      });

    this.http.get<HistoryData>(`${environment.apiUrl}?action=operator-dashboard&run=history&days=7`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        if (res?.success) {
          this.historyData = res;
          if (this.weekChartTimer) clearTimeout(this.weekChartTimer);
          this.weekChartTimer = setTimeout(() => {
            if (!this.destroy$.closed) this.buildWeekChart();
          }, 100);
        }
      });
  }

  // ================================================================
  // Feedback / Teamstämning
  // ================================================================

  loadFeedbackSummary(): void {
    if (this.feedbackLoading) return;
    this.feedbackLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=feedback&run=summary`, { withCredentials: true })
      .pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.feedbackLoading = false;
        if (res?.success) this.feedbackSummary = res;
      });
  }

  // ================================================================
  // Hjälpmetoder — stämning
  // ================================================================

  stamningIkon(snitt: number): string {
    if (snitt >= 3.5) return '\u{1F31F}';      // 4 = stjärna
    if (snitt >= 2.5) return '\u{1F60A}';      // 3 = glad
    if (snitt >= 1.5) return '\u{1F610}';      // 2 = neutral
    return '\u{1F61F}';                         // 1 = ledsen
  }

  stamningFarg(snitt: number): string {
    if (snitt >= 3.5) return '#68d391';   // grön
    if (snitt >= 2.5) return '#f6e05e';   // gul
    return '#fc8181';                      // röd
  }

  stamningGradient(snitt: number): string {
    if (snitt >= 3.5) return 'linear-gradient(90deg, #276749, #68d391)';
    if (snitt >= 2.5) return 'linear-gradient(90deg, #744210, #f6e05e)';
    return 'linear-gradient(90deg, #742a2a, #fc8181)';
  }

  formatShortDate(datum: string): string {
    if (!datum) return '';
    const d = new Date(datum + 'T12:00:00');
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { weekday: 'short', day: 'numeric', month: 'numeric' });
  }

  // ================================================================
  // Chart.js — linjegraf topp 3 operatörer
  // ================================================================

  buildWeekChart(): void {
    const canvas = document.getElementById('weekChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.historyData) return;

    try { this.weekChart?.destroy(); } catch (e) {}

    const top3 = this.historyData.operators.slice(0, 3);
    const chartColors = ['#63b3ed', '#68d391', '#f6e05e'];

    const labels = this.historyData.dates.map(d => {
      const dt = new Date(d + 'T12:00:00');
      return dt.toLocaleDateString('sv-SE', { weekday: 'short', day: 'numeric', month: 'numeric' });
    });

    const datasets = top3.map((op, i) => ({
      label: op.namn,
      data: op.data,
      borderColor: chartColors[i],
      backgroundColor: chartColors[i] + '22',
      pointBackgroundColor: chartColors[i],
      pointRadius: 4,
      tension: 0.3,
      fill: false,
    }));

    if (this.weekChart) { (this.weekChart as any).destroy(); }
    this.weekChart = new Chart(canvas, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 } }
          },
          tooltip: {
            intersect: false, mode: 'nearest',
            callbacks: {
              label: (ctx) => { const v = ctx.parsed.y; return v != null ? ` ${ctx.dataset.label}: ${v} IBC` : ''; }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#718096', font: { size: 11 } },
            grid:  { color: '#2d3748' }
          },
          y: {
            ticks: { color: '#718096', font: { size: 11 } },
            grid:  { color: '#374151' },
            beginAtZero: true
          }
        }
      }
    });
  }

  // ================================================================
  // Hjälpmetoder — operatörer
  // ================================================================

  getAvatarColor(name: string): string {
    const colors = ['#e53e3e','#dd6b20','#d69e2e','#38a169','#3182ce','#805ad5','#d53f8c'];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
  }

  getIbcColor(ibcPerH: number, status: string): string {
    if (status === 'inaktiv') return '#718096';
    if (ibcPerH >= 18) return '#68d391';
    if (ibcPerH >= 12) return '#f6e05e';
    return '#fc8181';
  }

  statusLabel(status: string): string {
    switch (status) {
      case 'bra':    return 'Bra';
      case 'ok':     return 'OK';
      case 'lag':    return 'L\u00e5g';
      case 'inaktiv': return 'Inaktiv';
      default:       return status;
    }
  }

  statusBgColor(status: string): string {
    switch (status) {
      case 'bra':    return '#276749';
      case 'ok':     return '#744210';
      case 'lag':    return '#742a2a';
      case 'inaktiv': return '#2d3748';
      default:       return '#2d3748';
    }
  }

  statusTextColor(status: string): string {
    switch (status) {
      case 'bra':    return '#9ae6b4';
      case 'ok':     return '#fbd38d';
      case 'lag':    return '#feb2b2';
      case 'inaktiv': return '#718096';
      default:       return '#a0aec0';
    }
  }

  timmar(min: number): number { return Math.floor(min / 60); }
  minuter(min: number): number { return Math.round(min % 60); }

  private formatDatum(datum: string): string {
    if (!datum) return '';
    const d = parseLocalDate(datum);
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
