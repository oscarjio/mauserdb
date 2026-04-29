import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

type Tier = 'Elite' | 'Solid' | 'Developing' | 'Behöver stöd';
type PosKey = 'op1' | 'op2' | 'op3';

interface PosCell {
  ibc_per_h: number;
  team_avg: number;
  antal_skift: number;
  vs_avg_pct: number;
  tier: Tier;
}

interface MatrisRow {
  number: number;
  name: string;
  overall_ibch: number;
  overall_rating: string;
  antal_skift: number;
  cells: Record<PosKey, PosCell | null>;
  validPositions: number;
}

@Component({
  standalone: true,
  selector: 'app-kompetens-matris',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './kompetens-matris.html',
  styleUrl: './kompetens-matris.css',
})
export class KompetensMatrisPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private isFetching = false;
  Math = Math;

  days = 90;
  loading = false;
  error = '';
  minSkift = 2;

  rows: MatrisRow[] = [];
  teamAvg: Record<PosKey, number> = { op1: 0, op2: 0, op3: 0 };

  sortBy: 'overall' | 'name' | 'versatility' = 'overall';

  readonly POSITIONS: PosKey[] = ['op1', 'op2', 'op3'];
  readonly POS_LABELS: Record<PosKey, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };

  readonly TIER_ORDER: Tier[] = ['Elite', 'Solid', 'Developing', 'Behöver stöd'];

  get sortedRows(): MatrisRow[] {
    const r = [...this.rows];
    if (this.sortBy === 'name') return r.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    if (this.sortBy === 'versatility') return r.sort((a, b) => b.validPositions - a.validPositions || b.overall_ibch - a.overall_ibch);
    return r.sort((a, b) => b.overall_ibch - a.overall_ibch);
  }

  tierCountPerPos(pos: PosKey, tier: Tier): number {
    return this.rows.filter(r => r.cells[pos]?.tier === tier).length;
  }

  eliteSolidCount(pos: PosKey): number {
    return this.rows.filter(r => {
      const t = r.cells[pos]?.tier;
      return t === 'Elite' || t === 'Solid';
    }).length;
  }

  get weakestPosition(): string {
    let minCount = Infinity;
    let weakPos: PosKey = 'op1';
    for (const p of this.POSITIONS) {
      const c = this.eliteSolidCount(p);
      if (c < minCount) { minCount = c; weakPos = p; }
    }
    return this.POS_LABELS[weakPos];
  }

  get spofPositions(): string[] {
    return this.POSITIONS
      .filter(p => this.eliteSolidCount(p) === 1)
      .map(p => this.POS_LABELS[p]);
  }

  get mostVersatile(): MatrisRow | null {
    return [...this.rows]
      .filter(r => r.validPositions >= 3)
      .sort((a, b) => b.overall_ibch - a.overall_ibch)[0] ?? null;
  }

  get crossTrainingCandidates(): MatrisRow[] {
    return [...this.rows]
      .filter(r => {
        const hasGood = this.POSITIONS.some(p => {
          const t = r.cells[p]?.tier;
          return t === 'Elite' || t === 'Solid';
        });
        const missingPos = this.POSITIONS.some(p => !r.cells[p]);
        return hasGood && missingPos;
      })
      .sort((a, b) => b.overall_ibch - a.overall_ibch)
      .slice(0, 6);
  }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    if (this.isFetching) return;
    this.isFetching = true;
    this.loading = true;
    this.error = '';
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - this.days);
    const fmt = (d: Date) => d.toISOString().slice(0, 10);

    this.http.get<any>(
      `${environment.apiUrl}?action=rebotling&run=operator-scores&from=${fmt(from)}&to=${fmt(to)}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.isFetching = false;
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda data.'; return; }
        this.buildMatrix(res.data?.operatorer ?? []);
      });
  }

  private buildMatrix(raw: any[]): void {
    // Extract team avg per position from first available per_position entry
    const ta: Record<PosKey, number> = { op1: 0, op2: 0, op3: 0 };
    for (const op of raw) {
      for (const p of this.POSITIONS) {
        const pd = op.per_position?.[p];
        if (pd?.team_avg > 0) ta[p] = pd.team_avg;
      }
    }
    this.teamAvg = ta;

    this.rows = raw
      .filter(op => op.antal_skift >= 3)
      .map(op => {
        const cells: Record<PosKey, PosCell | null> = { op1: null, op2: null, op3: null };
        for (const p of this.POSITIONS) {
          const pd = op.per_position?.[p];
          if (pd && pd.antal_skift >= this.minSkift && pd.ibc_per_h > 0) {
            cells[p] = {
              ibc_per_h: pd.ibc_per_h,
              team_avg: pd.team_avg,
              antal_skift: pd.antal_skift,
              vs_avg_pct: pd.vs_avg_pct,
              tier: this.vsToTier(pd.vs_avg_pct),
            };
          }
        }
        return {
          number: op.number,
          name: op.name,
          overall_ibch: op.ibc_per_h,
          overall_rating: op.rating,
          antal_skift: op.antal_skift,
          cells,
          validPositions: this.POSITIONS.filter(p => cells[p] !== null).length,
        };
      });
  }

  private vsToTier(vs: number): Tier {
    if (vs >= 15) return 'Elite';
    if (vs >= 0) return 'Solid';
    if (vs >= -15) return 'Developing';
    return 'Behöver stöd';
  }

  tierBg(tier: Tier | null | undefined): string {
    if (!tier) return '#1a202c';
    if (tier === 'Elite') return '#1c4532';
    if (tier === 'Solid') return '#1d4044';
    if (tier === 'Developing') return '#3d2c00';
    return '#3d1515';
  }

  tierText(tier: Tier | null | undefined): string {
    if (!tier) return '#4a5568';
    if (tier === 'Elite') return '#68d391';
    if (tier === 'Solid') return '#76e4f7';
    if (tier === 'Developing') return '#f6ad55';
    return '#fc8181';
  }

  tierBorder(tier: Tier | null | undefined): string {
    if (!tier) return '#2d3748';
    if (tier === 'Elite') return '#48bb78';
    if (tier === 'Solid') return '#4fd1c5';
    if (tier === 'Developing') return '#ed8936';
    return '#fc8181';
  }

  overallColor(rating: string): string {
    if (rating === 'Elite') return '#68d391';
    if (rating === 'Solid') return '#76e4f7';
    if (rating === 'Developing') return '#f6ad55';
    return '#fc8181';
  }

  missingPositions(row: MatrisRow): string {
    return this.POSITIONS
      .filter(p => !row.cells[p])
      .map(p => this.POS_LABELS[p])
      .join(', ');
  }

  bestPositionLabel(row: MatrisRow): string {
    let best: PosCell | null = null;
    let bestPos: PosKey = 'op1';
    for (const p of this.POSITIONS) {
      const c = row.cells[p];
      if (c && (!best || c.vs_avg_pct > best.vs_avg_pct)) { best = c; bestPos = p; }
    }
    return best ? this.POS_LABELS[bestPos] : '-';
  }
}
