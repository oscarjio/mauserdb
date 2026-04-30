import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorRow {
  number: number; name: string;
  ibc: number; drift_h: number; ibc_h: number;
  vs_team: number; shifts: number;
}

interface ScenarioResponse {
  success: boolean;
  from: string; to: string; period: string;
  total_ibc: number; total_ej: number; total_produced: number;
  total_drift_h: number; total_stopp_h: number; avail_h: number;
  kass_rate: number; stoppgrad: number; team_ibc_h: number;
  n_shifts: number; operators: OperatorRow[];
}

@Component({
  standalone: true,
  selector: 'app-scenarioanalys',
  imports: [CommonModule, FormsModule],
  templateUrl: './scenarioanalys.html',
  styleUrl: './scenarioanalys.css'
})
export class ScenarioanalysPage implements OnInit, OnDestroy {
  Math = Math;

  // Period
  selectedPeriod: string = '';
  availablePeriods: string[] = [];

  isLoading = false;
  data: ScenarioResponse | null = null;

  // Scenario sliders (target values)
  targetKass   = 0;    // target kassation% (slider 0..current)
  targetStopp  = 0;    // target stoppgrad% (slider 0..current)
  perfThreshold = 90;  // what % of team avg should bottom-performers be lifted to

  private destroy$ = new Subject<void>();

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.buildPeriods();
    this.load();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private buildPeriods() {
    const now = new Date();
    const periods: string[] = [];
    for (let i = 0; i < 18; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      periods.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    }
    this.availablePeriods = periods;
    this.selectedPeriod = periods[0];
  }

  load() {
    if (this.isLoading) return;
    this.isLoading = true;
    this.data = null;
    this.http.get<ScenarioResponse>(
      `${environment.apiUrl}?action=scenarioanalys&period=${encodeURIComponent(this.selectedPeriod)}`,
      { withCredentials: true }
    ).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isLoading = false;
      if (!res?.success) return;
      this.data = res;
      this.targetKass  = Math.max(0, Math.round(res.kass_rate - 1));
      this.targetStopp = Math.max(0, Math.round(res.stoppgrad - 1));
      this.perfThreshold = 90;
    });
  }

  // ── Scenario calculations (client-side, real-time) ─────────────────

  // 1. Kassation improvement
  get kassGainIbc(): number {
    if (!this.data || this.data.total_produced === 0) return 0;
    const currentKass = this.data.kass_rate / 100;
    const targetKass  = this.targetKass / 100;
    if (targetKass >= currentKass) return 0;
    // At same production rate, more IBCs pass inspection
    return Math.round(this.data.total_produced * (currentKass - targetKass));
  }

  get kassGainPct(): number {
    if (!this.data || this.data.total_ibc === 0) return 0;
    return Math.round((this.kassGainIbc / this.data.total_ibc) * 100 * 10) / 10;
  }

  // 2. Uptime improvement
  get stoppGainH(): number {
    if (!this.data || this.data.avail_h === 0) return 0;
    const currentStopp = this.data.stoppgrad / 100;
    const targetStopp  = this.targetStopp / 100;
    if (targetStopp >= currentStopp) return 0;
    // Extra hours unlocked
    return Math.round((currentStopp - targetStopp) * this.data.avail_h * 10) / 10;
  }

  get stoppGainIbc(): number {
    if (!this.data) return 0;
    return Math.round(this.stoppGainH * this.data.team_ibc_h);
  }

  get stoppGainPct(): number {
    if (!this.data || this.data.total_ibc === 0) return 0;
    return Math.round((this.stoppGainIbc / this.data.total_ibc) * 100 * 10) / 10;
  }

  // 3. Underperformer lift
  get underperformers(): OperatorRow[] {
    if (!this.data) return [];
    const threshold = this.data.team_ibc_h * (this.perfThreshold / 100);
    return this.data.operators.filter(op => op.ibc_h < threshold && op.drift_h > 0);
  }

  get perfGainIbc(): number {
    if (!this.data) return 0;
    const threshold = this.data.team_ibc_h * (this.perfThreshold / 100);
    let gain = 0;
    for (const op of this.data.operators) {
      if (op.ibc_h < threshold && op.drift_h > 0) {
        gain += (threshold - op.ibc_h) * op.drift_h;
      }
    }
    return Math.round(gain);
  }

  get perfGainPct(): number {
    if (!this.data || this.data.total_ibc === 0) return 0;
    return Math.round((this.perfGainIbc / this.data.total_ibc) * 100 * 10) / 10;
  }

  // Combined (non-overlapping estimate; simple sum — partial overlap possible)
  get totalGainIbc(): number {
    return this.kassGainIbc + this.stoppGainIbc + this.perfGainIbc;
  }

  get totalGainPct(): number {
    if (!this.data || this.data.total_ibc === 0) return 0;
    return Math.round((this.totalGainIbc / this.data.total_ibc) * 100 * 10) / 10;
  }

  get projectedIbcH(): number {
    if (!this.data || this.data.total_drift_h === 0) return 0;
    const projectedIbc = this.data.total_ibc + this.kassGainIbc + this.stoppGainIbc + this.perfGainIbc;
    const projectedHours = this.data.total_drift_h + this.stoppGainH;
    return Math.round((projectedIbc / projectedHours) * 10) / 10;
  }

  barWidth(gain: number): number {
    if (!this.data || this.data.total_ibc === 0) return 0;
    const maxGain = this.totalGainIbc || 1;
    return Math.min(100, Math.round((gain / maxGain) * 100));
  }

  periodLabel(p: string): string {
    const months = ['Jan','Feb','Mar','Apr','Maj','Jun','Jul','Aug','Sep','Okt','Nov','Dec'];
    const [y, m] = p.split('-');
    return `${months[parseInt(m, 10) - 1]} ${y}`;
  }
}
