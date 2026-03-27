import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, ActivatedRoute, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { environment } from '../../../environments/environment';
Chart.register(...registerables);

// ================================================================
// Interfaces
// ================================================================

interface OperatorInfo {
  id: number;
  namn: string;
  initialer: string;
  aktiv: boolean;
  nummer: number;
  skapad_datum: string;
}

interface Stats30d {
  total_ibc: number;
  avg_ibc_per_h: number | null;
  avg_quality_pct: number | null;
  avg_oee: number | null;
  skift_count: number;
}

interface StatsAll {
  total_ibc_all_time: number;
  bast_ibc_per_h_ever: number | null;
  bast_ibc_skift: number;
  bast_datum: string | null;
}

interface TrendWeek {
  vecka: string;
  yw: number;
  ibc: number;
  ibc_per_h: number | null;
  quality_pct: number | null;
  vecka_start: string;
}

interface RecentShift {
  datum: string;
  skiftnr: number;
  ibc: number;
  ibc_per_h: number | null;
  quality_pct: number | null;
  runtime_min: number | null;
}

interface Certification {
  line: string;
  certified_date: string;
  expires_date: string | null;
  notes: string | null;
  active: number;
}

interface Achievements {
  has_100_ibc_day: boolean;
  has_95_quality_week: boolean;
  streak_days: number;
}

interface RankThisWeek {
  rank: number | null;
  total_ops: number | null;
}

interface ProfileResponse {
  success: boolean;
  operator: OperatorInfo;
  stats_30d: Stats30d;
  stats_all: StatsAll;
  trend_weekly: TrendWeek[];
  recent_shifts: RecentShift[];
  certifications: Certification[];
  achievements: Achievements;
  rank_this_week: RankThisWeek;
}

// ================================================================
// Component
// ================================================================

@Component({
  standalone: true,
  selector: 'app-operator-detail',
  imports: [CommonModule, RouterModule, FormsModule],
  template: `
    <div style="background:#1a202c;min-height:100vh;padding:24px 16px;">

      <!-- Tillbaka-länk -->
      <div class="mb-4">
        <a routerLink="/admin/operator-dashboard"
           style="color:#63b3ed;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
          <i class="fas fa-arrow-left"></i> Tillbaka till operatörsdashboard
        </a>
      </div>

      <!-- Laddning -->
      <div *ngIf="laddar && !profil" class="text-center py-5">
        <div class="spinner-border" style="color:#63b3ed;" role="status">
          <span class="visually-hidden">Laddar...</span>
        </div>
        <p style="color:#718096;margin-top:12px;">Hämtar operatörsprofil...</p>
      </div>

      <!-- Felmeddelande -->
      <div *ngIf="felmeddelande && !profil" class="text-center py-5">
        <i class="fas fa-exclamation-triangle" style="font-size:2rem;color:#fc8181;"></i>
        <p style="color:#fc8181;margin-top:12px;">{{ felmeddelande }}</p>
        <a routerLink="/admin/operator-dashboard"
           style="color:#63b3ed;font-size:14px;">Tillbaka</a>
      </div>

      <!-- Empty-state: ingen operatörsdata -->
      <div *ngIf="!laddar && !felmeddelande && !profil" class="text-center py-5">
        <i class="fas fa-inbox" style="font-size: 2rem; color: #4a5568;"></i>
        <p style="color: #a0aec0; margin-top: 0.5rem;">Ingen operatörsdata hittades.</p>
        <a routerLink="/admin/operator-dashboard"
           style="color:#63b3ed;font-size:14px;">Tillbaka</a>
      </div>

      <!-- Profil -->
      <ng-container *ngIf="profil">

        <!-- ============================================================ -->
        <!-- HEADER -->
        <!-- ============================================================ -->
        <div style="background:#2d3748;border-radius:16px;padding:24px;border:1px solid #4a5568;margin-bottom:24px;">
          <div class="d-flex align-items-center gap-4 flex-wrap">
            <!-- Avatar -->
            <div [style.background]="getAvatarColor(profil.operator.namn)"
                 style="width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:22px;color:#fff;flex-shrink:0;">
              {{ profil.operator.initialer }}
            </div>
            <!-- Namn + info -->
            <div style="flex:1;">
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <h2 style="color:#e2e8f0;margin:0;font-size:1.6rem;font-weight:700;">
                  {{ profil.operator.namn }}
                </h2>
                <span [style.background]="profil.operator.aktiv ? '#276749' : '#742a2a'"
                      [style.color]="profil.operator.aktiv ? '#9ae6b4' : '#feb2b2'"
                      style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:600;">
                  {{ profil.operator.aktiv ? 'Aktiv' : 'Inaktiv' }}
                </span>
              </div>
              <div style="color:#a0aec0;font-size:13px;margin-top:6px;">
                <span class="me-3">
                  <i class="fas fa-id-badge me-1"></i>Operatör #{{ profil.operator.nummer }}
                </span>
                <span *ngIf="profil.operator.skapad_datum">
                  <i class="fas fa-calendar-plus me-1"></i>Tillagd {{ formatDatum(profil.operator.skapad_datum) }}
                </span>
              </div>
            </div>
            <!-- Rank -->
            <div *ngIf="profil.rank_this_week.rank !== null"
                 style="text-align:center;background:#1a202c;border-radius:12px;padding:12px 20px;border:1px solid #4a5568;">
              <div style="font-size:1.8rem;font-weight:800;"
                   [style.color]="profil.rank_this_week.rank === 1 ? '#f6e05e' : profil.rank_this_week.rank === 2 ? '#a0aec0' : profil.rank_this_week.rank === 3 ? '#ed8936' : '#e2e8f0'">
                #{{ profil.rank_this_week.rank }}
              </div>
              <div style="color:#718096;font-size:12px;">
                av {{ profil.rank_this_week.total_ops }} denna vecka
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- KPI-BRICKOR (30 dagar) -->
        <!-- ============================================================ -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-md-3">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;color:#63b3ed;">
                {{ profil.stats_30d.total_ibc }}
              </div>
              <div style="color:#a0aec0;font-size:12px;margin-top:4px;">
                <i class="fas fa-boxes me-1"></i>Total IBC (30 dagar)
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;"
                   [style.color]="profil.stats_30d.avg_ibc_per_h !== null && profil.stats_30d.avg_ibc_per_h >= 18 ? '#68d391' : profil.stats_30d.avg_ibc_per_h !== null && profil.stats_30d.avg_ibc_per_h >= 12 ? '#f6e05e' : '#fc8181'">
                {{ profil.stats_30d.avg_ibc_per_h !== null ? (profil.stats_30d.avg_ibc_per_h | number:'1.1-1') : '—' }}
              </div>
              <div style="color:#a0aec0;font-size:12px;margin-top:4px;">
                <i class="fas fa-tachometer-alt me-1"></i>IBC/h snitt (30 dagar)
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;"
                   [style.color]="profil.stats_30d.avg_quality_pct !== null && profil.stats_30d.avg_quality_pct >= 95 ? '#68d391' : profil.stats_30d.avg_quality_pct !== null && profil.stats_30d.avg_quality_pct >= 85 ? '#f6e05e' : '#fc8181'">
                {{ profil.stats_30d.avg_quality_pct !== null ? (profil.stats_30d.avg_quality_pct | number:'1.1-1') + '%' : '—' }}
              </div>
              <div style="color:#a0aec0;font-size:12px;margin-top:4px;">
                <i class="fas fa-check-circle me-1"></i>Kvalitet snitt (30 dagar)
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div style="background:#2d3748;border-radius:12px;padding:20px;text-align:center;border:1px solid #4a5568;">
              <div style="font-size:2rem;font-weight:700;color:#e2e8f0;">
                {{ profil.stats_30d.skift_count }}
              </div>
              <div style="color:#a0aec0;font-size:12px;margin-top:4px;">
                <i class="fas fa-calendar-check me-1"></i>Skift (30 dagar)
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- ALL-TIME REKORD -->
        <!-- ============================================================ -->
        <div style="background:#2d3748;border-radius:12px;padding:20px;border:1px solid #f6e05e40;margin-bottom:24px;">
          <h5 style="color:#f6e05e;margin:0 0 16px;font-size:15px;font-weight:700;">
            <i class="fas fa-trophy me-2"></i>All-time rekord
          </h5>
          <div class="row g-3">
            <div class="col-12 col-md-4">
              <div style="background:#1a202c;border-radius:10px;padding:16px;text-align:center;border:1px solid #4a5568;">
                <div style="font-size:1.6rem;font-weight:700;color:#f6e05e;">
                  {{ profil.stats_all.bast_ibc_per_h_ever !== null ? (profil.stats_all.bast_ibc_per_h_ever | number:'1.1-1') : '—' }}
                </div>
                <div style="color:#a0aec0;font-size:12px;margin-top:4px;">Bästa IBC/h någonsin</div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div style="background:#1a202c;border-radius:10px;padding:16px;text-align:center;border:1px solid #4a5568;">
                <div style="font-size:1.6rem;font-weight:700;color:#68d391;">
                  {{ profil.stats_all.bast_ibc_skift }}
                </div>
                <div style="color:#a0aec0;font-size:12px;margin-top:4px;">
                  Bästa IBC ett skift
                  <span *ngIf="profil.stats_all.bast_datum" style="display:block;color:#718096;font-size:11px;">
                    {{ formatDatum(profil.stats_all.bast_datum!) }}
                  </span>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-4">
              <div style="background:#1a202c;border-radius:10px;padding:16px;text-align:center;border:1px solid #4a5568;">
                <div style="font-size:1.6rem;font-weight:700;color:#63b3ed;">
                  {{ profil.stats_all.total_ibc_all_time }}
                </div>
                <div style="color:#a0aec0;font-size:12px;margin-top:4px;">Total IBC all-time</div>
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- TRENDGRAF — 8 veckor -->
        <!-- ============================================================ -->
        <div *ngIf="profil.trend_weekly.length > 0"
             style="background:#2d3748;border-radius:12px;padding:20px;border:1px solid #4a5568;margin-bottom:24px;">
          <h5 style="color:#e2e8f0;margin:0 0 16px;font-size:15px;font-weight:700;">
            <i class="fas fa-chart-line me-2" style="color:#63b3ed;"></i>Prestandatrend — 8 veckor (IBC/h)
          </h5>
          <div style="position:relative;height:240px;">
            <canvas id="trendChart"></canvas>
          </div>
        </div>

        <!-- Ingen trenddata -->
        <div *ngIf="profil.trend_weekly.length === 0"
             style="background:#2d3748;border-radius:12px;padding:24px;border:1px solid #4a5568;margin-bottom:24px;text-align:center;">
          <i class="fas fa-chart-line" style="font-size:2rem;color:#4a5568;"></i>
          <p style="color:#718096;margin-top:12px;margin-bottom:0;">Ingen trenddata tillgänglig</p>
        </div>

        <!-- ============================================================ -->
        <!-- SENASTE SKIFTEN -->
        <!-- ============================================================ -->
        <div *ngIf="profil.recent_shifts.length > 0"
             style="background:#2d3748;border-radius:12px;overflow:hidden;border:1px solid #4a5568;margin-bottom:24px;">
          <div style="padding:16px 20px;border-bottom:1px solid #4a5568;">
            <h5 style="color:#e2e8f0;margin:0;font-size:15px;font-weight:700;">
              <i class="fas fa-history me-2" style="color:#63b3ed;"></i>Senaste skiften
            </h5>
          </div>
          <div class="table-responsive">
            <table class="table table-dark mb-0"
                   style="--bs-table-bg:#2d3748;--bs-table-border-color:#4a5568;color:#e2e8f0;">
              <thead>
                <tr style="background:#1e2535;color:#a0aec0;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">
                  <th style="padding:10px 16px;border-bottom:1px solid #4a5568;">Datum</th>
                  <th style="padding:10px 16px;border-bottom:1px solid #4a5568;text-align:right;">Skift #</th>
                  <th style="padding:10px 16px;border-bottom:1px solid #4a5568;text-align:right;">IBC</th>
                  <th style="padding:10px 16px;border-bottom:1px solid #4a5568;text-align:right;">IBC/h</th>
                  <th style="padding:10px 16px;border-bottom:1px solid #4a5568;text-align:right;">Kvalitet%</th>
                  <th style="padding:10px 16px;border-bottom:1px solid #4a5568;text-align:right;">Drifttid</th>
                </tr>
              </thead>
              <tbody>
                <tr *ngFor="let s of profil.recent_shifts; trackBy: trackByIndex" style="border-bottom:1px solid #3d4a5c;">
                  <td style="padding:12px 16px;color:#a0aec0;font-size:13px;">{{ s.datum }}</td>
                  <td style="padding:12px 16px;text-align:right;color:#718096;font-size:13px;">{{ s.skiftnr }}</td>
                  <td style="padding:12px 16px;text-align:right;font-weight:700;color:#63b3ed;">{{ s.ibc }}</td>
                  <td style="padding:12px 16px;text-align:right;">
                    <span *ngIf="s.ibc_per_h !== null"
                          [style.color]="s.ibc_per_h >= 18 ? '#68d391' : s.ibc_per_h >= 12 ? '#f6e05e' : '#fc8181'"
                          style="font-weight:700;">
                      {{ s.ibc_per_h | number:'1.1-1' }}
                    </span>
                    <span *ngIf="s.ibc_per_h === null" style="color:#4a5568;">—</span>
                  </td>
                  <td style="padding:12px 16px;text-align:right;">
                    <span *ngIf="s.quality_pct !== null"
                          [style.color]="s.quality_pct >= 95 ? '#68d391' : s.quality_pct >= 85 ? '#f6e05e' : '#fc8181'">
                      {{ s.quality_pct | number:'1.1-1' }}%
                    </span>
                    <span *ngIf="s.quality_pct === null" style="color:#4a5568;">—</span>
                  </td>
                  <td style="padding:12px 16px;text-align:right;color:#a0aec0;font-size:13px;">
                    <span *ngIf="s.runtime_min !== null">{{ s.runtime_min }} min</span>
                    <span *ngIf="s.runtime_min === null" style="color:#4a5568;">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Inga skift -->
        <div *ngIf="profil.recent_shifts.length === 0"
             style="background:#2d3748;border-radius:12px;padding:24px;border:1px solid #4a5568;margin-bottom:24px;text-align:center;">
          <i class="fas fa-inbox" style="font-size:2rem;color:#4a5568;"></i>
          <p style="color:#718096;margin-top:12px;margin-bottom:0;">Inga skift registrerade</p>
        </div>

        <!-- ============================================================ -->
        <!-- ACHIEVEMENTS -->
        <!-- ============================================================ -->
        <div style="background:#2d3748;border-radius:12px;padding:20px;border:1px solid #4a5568;margin-bottom:24px;">
          <h5 style="color:#e2e8f0;margin:0 0 16px;font-size:15px;font-weight:700;">
            <i class="fas fa-medal me-2" style="color:#f6e05e;"></i>Achievements
          </h5>
          <div class="row g-3">

            <!-- 100-IBC skift -->
            <div class="col-12 col-md-4">
              <div [style.background]="profil.achievements.has_100_ibc_day ? '#2c2a0a' : '#1a202c'"
                   [style.border]="profil.achievements.has_100_ibc_day ? '1px solid #f6e05e66' : '1px solid #4a5568'"
                   style="border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:1.8rem;margin-bottom:8px;">
                  <i class="fas fa-fire"
                     [style.color]="profil.achievements.has_100_ibc_day ? '#f6e05e' : '#4a5568'"></i>
                </div>
                <div [style.color]="profil.achievements.has_100_ibc_day ? '#f6e05e' : '#718096'"
                     style="font-weight:700;font-size:14px;">100+ IBC ett skift</div>
                <div style="font-size:12px;margin-top:4px;"
                     [style.color]="profil.achievements.has_100_ibc_day ? '#d69e2e' : '#4a5568'">
                  {{ profil.achievements.has_100_ibc_day ? 'Uppnått!' : 'Ej uppnått' }}
                </div>
              </div>
            </div>

            <!-- 95%+ kvalitetsvecka -->
            <div class="col-12 col-md-4">
              <div [style.background]="profil.achievements.has_95_quality_week ? '#0a2c0e' : '#1a202c'"
                   [style.border]="profil.achievements.has_95_quality_week ? '1px solid #68d39166' : '1px solid #4a5568'"
                   style="border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:1.8rem;margin-bottom:8px;">
                  <i class="fas fa-star"
                     [style.color]="profil.achievements.has_95_quality_week ? '#68d391' : '#4a5568'"></i>
                </div>
                <div [style.color]="profil.achievements.has_95_quality_week ? '#68d391' : '#718096'"
                     style="font-weight:700;font-size:14px;">95%+ kvalitet en vecka</div>
                <div style="font-size:12px;margin-top:4px;"
                     [style.color]="profil.achievements.has_95_quality_week ? '#38a169' : '#4a5568'">
                  {{ profil.achievements.has_95_quality_week ? 'Uppnått senaste veckan!' : 'Ej uppnått' }}
                </div>
              </div>
            </div>

            <!-- Aktiv streak -->
            <div class="col-12 col-md-4">
              <div [style.background]="profil.achievements.streak_days >= 5 ? '#0a1a2c' : '#1a202c'"
                   [style.border]="profil.achievements.streak_days >= 5 ? '1px solid #63b3ed66' : '1px solid #4a5568'"
                   style="border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:1.8rem;margin-bottom:8px;">
                  <i class="fas fa-bolt"
                     [style.color]="profil.achievements.streak_days >= 5 ? '#63b3ed' : '#4a5568'"></i>
                </div>
                <div [style.color]="profil.achievements.streak_days >= 5 ? '#63b3ed' : '#718096'"
                     style="font-weight:700;font-size:14px;">
                  {{ profil.achievements.streak_days }} dagars aktiv streak
                </div>
                <div style="font-size:12px;margin-top:4px;"
                     [style.color]="profil.achievements.streak_days >= 5 ? '#3182ce' : '#4a5568'">
                  {{ profil.achievements.streak_days >= 10 ? 'Imponerande!' : profil.achievements.streak_days >= 5 ? 'Bra jobbat!' : 'Senaste 90 dagarna' }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ============================================================ -->
        <!-- CERTIFIERINGAR -->
        <!-- ============================================================ -->
        <div *ngIf="profil.certifications.length > 0"
             style="background:#2d3748;border-radius:12px;padding:20px;border:1px solid #4a5568;margin-bottom:24px;">
          <h5 style="color:#e2e8f0;margin:0 0 16px;font-size:15px;font-weight:700;">
            <i class="fas fa-certificate me-2" style="color:#63b3ed;"></i>Certifieringar
          </h5>
          <div class="row g-2">
            <div *ngFor="let cert of profil.certifications; trackBy: trackByIndex" class="col-12 col-md-6">
              <div style="background:#1a202c;border-radius:8px;padding:12px 16px;border:1px solid #4a5568;display:flex;align-items:center;gap:12px;">
                <i class="fas fa-check-circle" style="color:#68d391;font-size:1.2rem;"></i>
                <div>
                  <div style="font-weight:600;color:#e2e8f0;font-size:14px;">{{ lineLabel(cert.line) }}</div>
                  <div style="color:#718096;font-size:12px;">
                    Godkänd {{ cert.certified_date }}
                    <span *ngIf="cert.expires_date"> &bull; Utgår {{ cert.expires_date }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tillbaka-knapp nere -->
        <div class="mt-2 mb-4">
          <a routerLink="/admin/operator-dashboard"
             style="color:#63b3ed;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-arrow-left"></i> Tillbaka till operatörsdashboard
          </a>
        </div>

      </ng-container>
    </div>
  `
})
export class OperatorDetailPage implements OnInit, OnDestroy {
  Math = Math;

  profil: ProfileResponse | null = null;
  laddar = false;
  felmeddelande = '';

  private destroy$ = new Subject<void>();
  private trendChart: Chart | null = null;
  private chartTimer: any = null;

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (!id || isNaN(+id)) {
      this.felmeddelande = 'Ogiltigt operatörs-ID';
      return;
    }
    this.loadProfile(+id);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.chartTimer) { clearTimeout(this.chartTimer); this.chartTimer = null; }
    try { this.trendChart?.destroy(); } catch (e) {}
    this.trendChart = null;
  }

  loadProfile(id: number): void {
    if (this.laddar) return;
    this.laddar = true;
    this.felmeddelande = '';

    this.http.get<ProfileResponse>(`${environment.apiUrl}?action=operator&run=profile&id=${id}`, { withCredentials: true })
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (res) => {
          this.laddar = false;
          if (!res) {
            this.felmeddelande = 'Kunde inte hämta profil — kontrollera anslutningen';
            return;
          }
          if (!res.success) {
            this.felmeddelande = 'Servern returnerade ett fel';
            return;
          }
          this.profil = res;
          // Rita trendgraf efter DOM-uppdatering
          this.chartTimer = setTimeout(() => {
            if (this.destroy$.closed) return;
            this.buildTrendChart();
          }, 120);
        },
        error: () => {
          this.laddar = false;
          this.felmeddelande = 'Kunde inte hämta profil';
        }
      });
  }

  // ================================================================
  // Trendgraf
  // ================================================================

  buildTrendChart(): void {
    const canvas = document.getElementById('trendChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || !this.profil || this.profil.trend_weekly.length === 0) return;

    try { this.trendChart?.destroy(); } catch (e) {}

    const labels = this.profil.trend_weekly.map(w => w.vecka);
    const data   = this.profil.trend_weekly.map(w => w.ibc_per_h ?? 0);

    // Beräkna snitt (streckad linje)
    const validVals = data.filter(v => v > 0);
    const avgVal = validVals.length > 0
      ? validVals.reduce((a, b) => a + b, 0) / validVals.length
      : 0;
    const avgLine = data.map(() => avgVal);

    if (this.trendChart) { (this.trendChart as any).destroy(); }
    this.trendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h per vecka',
            data,
            borderColor: '#63b3ed',
            backgroundColor: '#63b3ed22',
            pointBackgroundColor: '#63b3ed',
            pointRadius: 5,
            tension: 0.3,
            fill: true,
          },
          {
            label: `Snitt (${avgVal.toFixed(1)} IBC/h)`,
            data: avgLine,
            borderColor: '#718096',
            backgroundColor: 'transparent',
            pointRadius: 0,
            borderDash: [6, 4],
            tension: 0,
            fill: false,
            borderWidth: 2,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#a0aec0', font: { size: 12 } }
          },
          tooltip: {
            callbacks: {
              label: (ctx) => ` ${ctx.dataset.label}: ${(ctx.parsed.y ?? 0).toFixed(1)} IBC/h`
            }
          }
        },
        scales: {
          x: {
            ticks: { color: '#718096', font: { size: 11 } },
            grid:  { color: '#374151' }
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
  // Hjälpmetoder
  // ================================================================

  getAvatarColor(name: string): string {
    const colors = ['#e53e3e','#dd6b20','#d69e2e','#38a169','#3182ce','#805ad5','#d53f8c'];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
  }

  formatDatum(datum: string): string {
    if (!datum) return '';
    const d = new Date(datum.length > 10 ? datum : datum + 'T12:00:00');
    if (isNaN(d.getTime())) return datum;
    return d.toLocaleDateString('sv-SE', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  lineLabel(line: string): string {
    const map: Record<string, string> = {
      'rebotling': 'Rebotling',
      'tvattlinje': 'Tvättlinje',
      'saglinje': 'Såglinje',
      'klassificeringslinje': 'Klassificeringslinje',
    };
    return map[line] ?? line;
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
