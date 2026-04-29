import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PosEntry {
  pos: string;
  label: string;
  ibc_per_h: number;
  skift_count: number;
  total_ibc: number;
}

interface Operator {
  number: number;
  name: string;
  total_skift: number;
  primary_pos: string | null;
  primary_label: string;
  positions: PosEntry[];
}

interface CoverageOp {
  number: number;
  name: string;
  ibc_per_h: number;
  skift_count: number;
  is_primary: boolean;
}

interface Coverage {
  pos: string;
  label: string;
  count: number;
  risk: 'high' | 'medium' | 'low';
  operators: CoverageOp[];
}

interface BackupEntry {
  op_number: number;
  op_name: string;
  primary_pos: string;
  primary_label: string;
  best_backup: CoverageOp | null;
  backup_count: number;
}

interface ApiResponse {
  success: boolean;
  days: number;
  period: { from: string; to: string };
  operators: Operator[];
  coverage: Coverage[];
  backup_matrix: BackupEntry[];
}

@Component({
  standalone: true,
  selector: 'app-tacknings-analys',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './tacknings-analys.html',
  styleUrl: './tacknings-analys.css'
})
export class TackningsAnalysPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  Math = Math;

  days = 90;
  loading = false;
  error = '';

  operators: Operator[] = [];
  coverage: Coverage[] = [];
  backupMatrix: BackupEntry[] = [];
  period: { from: string; to: string } | null = null;

  activeTab: 'matrix' | 'coverage' | 'backup' = 'coverage';
  sortBy: 'primary' | 'coverage' | 'name' | 'skift' = 'primary';

  readonly POS_ORDER: Record<string, number> = { op1: 0, op2: 1, op3: 2 };
  readonly ALL_POSITIONS = ['op1', 'op2', 'op3'];
  readonly POS_LABELS: Record<string, string> = {
    op1: 'Tvättplats',
    op2: 'Kontrollstation',
    op3: 'Truckförare',
  };

  get sortedOperators(): Operator[] {
    const ops = [...this.operators];
    if (this.sortBy === 'name') return ops.sort((a, b) => a.name.localeCompare(b.name, 'sv'));
    if (this.sortBy === 'skift') return ops.sort((a, b) => b.total_skift - a.total_skift);
    if (this.sortBy === 'coverage') return ops.sort((a, b) => b.positions.length - a.positions.length);
    // 'primary': group by primary position
    return ops.sort((a, b) => {
      const pa = this.POS_ORDER[a.primary_pos ?? ''] ?? 99;
      const pb = this.POS_ORDER[b.primary_pos ?? ''] ?? 99;
      if (pa !== pb) return pa - pb;
      return a.name.localeCompare(b.name, 'sv');
    });
  }

  get totalOps(): number { return this.operators.length; }
  get highRiskCount(): number { return this.coverage.filter(c => c.risk === 'high').length; }
  get mediumRiskCount(): number { return this.coverage.filter(c => c.risk === 'medium').length; }
  get noBackupCount(): number { return this.backupMatrix.filter(b => b.backup_count === 0).length; }

  constructor(private http: HttpClient) {}

  ngOnInit(): void { this.load(); }
  ngOnDestroy(): void { this.destroy$.next(); this.destroy$.complete(); }

  load(): void {
    this.loading = true;
    this.error = '';
    this.http.get<ApiResponse>(
      `${environment.apiUrl}?action=rebotling&run=tacknings-analys&days=${this.days}`,
      { withCredentials: true }
    )
      .pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$))
      .subscribe(res => {
        this.loading = false;
        if (!res?.success) { this.error = 'Kunde inte ladda täckningsdata.'; return; }
        this.operators    = res.operators ?? [];
        this.coverage     = res.coverage ?? [];
        this.backupMatrix = res.backup_matrix ?? [];
        this.period       = res.period ?? null;
      });
  }

  getPosData(op: Operator, pos: string): PosEntry | null {
    return op.positions.find(p => p.pos === pos) ?? null;
  }

  ibchColor(ibch: number, avg: number): string {
    if (avg <= 0) return '';
    const pct = (ibch - avg) / avg * 100;
    if (pct >= 15)  return 'cell-elite';
    if (pct >= 5)   return 'cell-good';
    if (pct >= -5)  return 'cell-avg';
    if (pct >= -15) return 'cell-low';
    return 'cell-poor';
  }

  getCoverageAvg(cov: Coverage): number {
    if (!cov.operators.length) return 0;
    const totalIbc  = cov.operators.reduce((s, o) => s + o.ibc_per_h * o.skift_count, 0);
    const totalSkft = cov.operators.reduce((s, o) => s + o.skift_count, 0);
    return totalSkft > 0 ? totalIbc / totalSkft : 0;
  }

  riskLabel(risk: string): string {
    if (risk === 'high')   return 'Hög risk';
    if (risk === 'medium') return 'Bevaka';
    return 'OK';
  }

  riskClass(risk: string): string {
    if (risk === 'high')   return 'risk-high';
    if (risk === 'medium') return 'risk-medium';
    return 'risk-low';
  }

  getCoverageFor(pos: string): Coverage | null {
    return this.coverage.find(c => c.pos === pos) ?? null;
  }
}
