import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface OperatorBonus {
  number: number;
  name: string;
  total_shifts: number;
  active_days: number;
  ibc_per_h: number;
  vs_team_pct: number;
  tier: string;
  bonus_level: string;
}

interface BonusSummary {
  elite: number;
  solid: number;
  developing: number;
  behoever_stod: number;
}

interface ApiResponse {
  success: boolean;
  from: string;
  to: string;
  team_avg_ibc_h: number;
  total_shifts: number;
  operators: OperatorBonus[];
  summary: BonusSummary;
}

@Component({
  selector: 'app-bonus-kalkylator',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './bonus-kalkylator.html',
  styleUrls: ['./bonus-kalkylator.css']
})
export class BonusKalkylatorPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  isFetching = false;
  error: string | null = null;
  data: ApiResponse | null = null;

  fromDate = '';
  toDate = '';

  bonusA = 2000;
  bonusB = 1000;
  bonusC = 500;
  showSettings = false;

  ngOnInit(): void {
    const now = new Date();
    this.fromDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    this.toDate = `${lastDay.getFullYear()}-${String(lastDay.getMonth() + 1).padStart(2, '0')}-${String(lastDay.getDate()).padStart(2, '0')}`;

    const saved = localStorage.getItem('bonusKalkylatorSettings');
    if (saved) {
      try {
        const s = JSON.parse(saved);
        if (s.bonusA != null) this.bonusA = s.bonusA;
        if (s.bonusB != null) this.bonusB = s.bonusB;
        if (s.bonusC != null) this.bonusC = s.bonusC;
      } catch (_) {}
    }

    this.load();
  }

  constructor(private http: HttpClient) {}

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  setPreset(preset: 'denna' | 'forra' | 'kvartal'): void {
    const now = new Date();
    if (preset === 'denna') {
      this.fromDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
      const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      this.toDate = `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`;
    } else if (preset === 'forra') {
      const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      const last = new Date(now.getFullYear(), now.getMonth(), 0);
      this.fromDate = `${prev.getFullYear()}-${String(prev.getMonth() + 1).padStart(2, '0')}-01`;
      this.toDate = `${last.getFullYear()}-${String(last.getMonth() + 1).padStart(2, '0')}-${String(last.getDate()).padStart(2, '0')}`;
    } else {
      const q = Math.floor(now.getMonth() / 3);
      const qStart = new Date(now.getFullYear(), q * 3, 1);
      const qEnd = new Date(now.getFullYear(), q * 3 + 3, 0);
      this.fromDate = `${qStart.getFullYear()}-${String(qStart.getMonth() + 1).padStart(2, '0')}-01`;
      this.toDate = `${qEnd.getFullYear()}-${String(qEnd.getMonth() + 1).padStart(2, '0')}-${String(qEnd.getDate()).padStart(2, '0')}`;
    }
    this.load();
  }

  saveSettings(): void {
    localStorage.setItem('bonusKalkylatorSettings', JSON.stringify({
      bonusA: this.bonusA,
      bonusB: this.bonusB,
      bonusC: this.bonusC
    }));
    this.load();
  }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.error = null;

    const url = `${environment.apiUrl}?action=rebotling&run=bonus-kalkylator&from=${this.fromDate}&to=${this.toDate}`;
    this.http.get<ApiResponse>(url)
      .pipe(timeout(5000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        if (!res || !res.success) {
          this.error = 'Kunde inte hämta bonusdata';
          return;
        }
        this.data = res;
      });
  }

  getBonusAmount(level: string): number {
    if (level === 'A') return this.bonusA;
    if (level === 'B') return this.bonusB;
    if (level === 'C') return this.bonusC;
    return 0;
  }

  getTotalBudget(): number {
    if (!this.data) return 0;
    return this.data.operators.reduce((sum, op) => sum + this.getBonusAmount(op.bonus_level), 0);
  }

  tierClass(tier: string): string {
    if (tier === 'Elite') return 'badge-elite';
    if (tier === 'Solid') return 'badge-solid';
    if (tier === 'Developing') return 'badge-developing';
    return 'badge-behoever';
  }

  levelClass(level: string): string {
    if (level === 'A') return 'badge-level-a';
    if (level === 'B') return 'badge-level-b';
    if (level === 'C') return 'badge-level-c';
    return 'badge-level-ingen';
  }

  print(): void {
    window.print();
  }

  copyCSV(): void {
    if (!this.data) return;
    const header = 'Operatör\tSkift\tIBC/h\tVs snitt\tTier\tNivå\tBelopp (SEK)';
    const rows = this.data.operators.map(op =>
      `${op.name}\t${op.total_shifts}\t${op.ibc_per_h}\t${op.vs_team_pct >= 0 ? '+' : ''}${op.vs_team_pct}%\t${op.tier}\t${op.bonus_level}\t${this.getBonusAmount(op.bonus_level)}`
    );
    navigator.clipboard.writeText([header, ...rows].join('\n')).catch(() => {});
  }
}
