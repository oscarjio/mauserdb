import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart } from 'chart.js';
import { localDateStr } from '../../../../utils/date-utils';
import { exportChartAsPng } from '../../../../shared/chart-export.util';
import { environment } from '../../../../../environments/environment';

interface OperatorOption {
  id: number;
  name: string;
  number: number;
}

interface ShiftRow {
  id: number;
  datum: string;
  skift: string;
  skiftraknare: number;
  ibc_ok: number;
  kasserade: number;
  totalt: number;
  cykeltid: number | null;
  oee: number | null;
  drifttid: number;
  stopptid: number | null;
  rasttime: number;
  op1_name: string;
  op2_name: string;
  op3_name: string;
}

@Component({
  standalone: true,
  selector: 'app-statistik-skiftrapport-operator',
  templateUrl: './statistik-skiftrapport-operator.html',
  styleUrls: ['./statistik-skiftrapport-operator.css'],
  imports: [CommonModule, FormsModule]
})
export class StatistikSkiftrapportOperatorComponent implements OnInit, OnDestroy {
  operators: OperatorOption[] = [];
  selectedOperatorId: number = 0;
  periodDays: number = 30;
  customFrom: string = '';
  customTo: string = '';
  useCustomRange: boolean = false;

  loading: boolean = false;
  operatorsLoading: boolean = false;
  operatorName: string = '';
  data: ShiftRow[] = [];

  // Sammanfattning
  totalIbc: number = 0;
  avgCykeltid: number = 0;
  bastaSkift: string = '-';
  samstaSkift: string = '-';

  exportFeedback: boolean = false;
  private chart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private _timers: ReturnType<typeof setTimeout>[] = [];

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadOperators();
    const today = new Date();
    this.customTo = localDateStr(today);
    const from = new Date();
    from.setDate(from.getDate() - 29);
    this.customFrom = localDateStr(from);
  }

  ngOnDestroy(): void {
    try { this.chart?.destroy(); } catch (_e) { /* ignore */ }
    this.chart = null;
    this.destroy$.next();
    this.destroy$.complete();
    this._timers.forEach(t => clearTimeout(t));
  }

  loadOperators(): void {
    this.operatorsLoading = true;
    this.http.get<any>(`${environment.apiUrl}?action=skiftrapport&run=operator-list`, { withCredentials: true })
      .pipe(
        timeout(8000),
        takeUntil(this.destroy$),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        this.operatorsLoading = false;
        if (res?.success && res.data) {
          this.operators = res.data;
        }
      });
  }

  onOperatorChange(): void {
    if (this.selectedOperatorId > 0) {
      this.loadReport();
    }
  }

  onPeriodChange(): void {
    this.useCustomRange = false;
    if (this.selectedOperatorId > 0) {
      this.loadReport();
    }
  }

  applyCustomRange(): void {
    if (this.customFrom && this.customTo && this.selectedOperatorId > 0) {
      this.useCustomRange = true;
      this.loadReport();
    }
  }

  private getDateRange(): { from: string; to: string } {
    if (this.useCustomRange && this.customFrom && this.customTo) {
      return { from: this.customFrom, to: this.customTo };
    }
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - (this.periodDays - 1));
    return { from: localDateStr(from), to: localDateStr(to) };
  }

  loadReport(): void {
    if (this.loading || this.selectedOperatorId <= 0) return;
    this.loading = true;

    const { from, to } = this.getDateRange();
    const url = `${environment.apiUrl}?action=skiftrapport&run=shift-report-by-operator&operator_id=${this.selectedOperatorId}&from=${from}&to=${to}`;

    this.http.get<any>(url, { withCredentials: true })
      .pipe(
        timeout(10000),
        takeUntil(this.destroy$),
        catchError(() => of(null))
      )
      .subscribe((res: any) => {
        this.loading = false;
        if (res?.success) {
          this.operatorName = res.operator_name || '';
          this.data = res.data || [];
          this.calculateSummary();
          this._timers.push(setTimeout(() => {
            if (!this.destroy$.closed) this.renderChart();
          }, 100));
        } else {
          this.data = [];
          this.operatorName = '';
          this.calculateSummary();
        }
      });
  }

  private calculateSummary(): void {
    if (this.data.length === 0) {
      this.totalIbc = 0;
      this.avgCykeltid = 0;
      this.bastaSkift = '-';
      this.samstaSkift = '-';
      return;
    }

    this.totalIbc = this.data.reduce((sum, r) => sum + r.ibc_ok, 0);

    const cykeltider = this.data.filter(r => r.cykeltid !== null && r.cykeltid > 0);
    this.avgCykeltid = cykeltider.length > 0
      ? Math.round(cykeltider.reduce((sum, r) => sum + (r.cykeltid ?? 0), 0) / cykeltider.length * 100) / 100
      : 0;

    // Basta skift = hogst ibc_ok
    const sorted = [...this.data].filter(r => r.ibc_ok > 0).sort((a, b) => b.ibc_ok - a.ibc_ok);
    if (sorted.length > 0) {
      this.bastaSkift = sorted[0].datum + ' ' + sorted[0].skift + ' (' + sorted[0].ibc_ok + ' IBC)';
    } else {
      this.bastaSkift = '-';
    }
    // Samsta skift = lagst ibc_ok (bland skift med data)
    if (sorted.length > 0) {
      const worst = sorted[sorted.length - 1];
      this.samstaSkift = worst.datum + ' ' + worst.skift + ' (' + worst.ibc_ok + ' IBC)';
    } else {
      this.samstaSkift = '-';
    }
  }

  private renderChart(): void {
    try { this.chart?.destroy(); } catch (_e) { /* ignore */ }
    this.chart = null;

    const canvas = document.getElementById('skiftrapportOpChart') as HTMLCanvasElement;
    if (!canvas) return;
    if (!canvas || this.data.length === 0) return;

    // Sortera kronologiskt
    const sorted = [...this.data].sort((a, b) => {
      const dc = a.datum.localeCompare(b.datum);
      if (dc !== 0) return dc;
      return a.skiftraknare - b.skiftraknare;
    });

    const labels = sorted.map(r => {
      const parts = r.datum.split('-');
      return parts[2] + '/' + parts[1] + ' ' + r.skift;
    });
    const ibcValues = sorted.map(r => r.ibc_ok);
    const cykeltidValues = sorted.map(r => r.cykeltid ?? 0);

    if (this.chart) { (this.chart as any).destroy(); }
    this.chart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Antal IBC',
            data: ibcValues,
            backgroundColor: 'rgba(66, 153, 225, 0.7)',
            borderColor: 'rgba(66, 153, 225, 1)',
            borderWidth: 1,
            borderRadius: 3,
            yAxisID: 'y',
            order: 2
          },
          {
            label: 'Cykeltid (min)',
            data: cykeltidValues,
            type: 'line',
            borderColor: '#f6ad55',
            backgroundColor: 'rgba(246, 173, 85, 0.15)',
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#f6ad55',
            tension: 0.3,
            fill: false,
            yAxisID: 'y1',
            order: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            labels: { color: '#a0aec0', font: { size: 11 }, boxWidth: 14, padding: 16 }
          },
          tooltip: {
            backgroundColor: 'rgba(15,17,23,0.96)',
            titleColor: '#fff',
            bodyColor: '#e0e0e0',
            borderColor: '#4299e1',
            borderWidth: 1
          }
        },
        scales: {
          x: {
            ticks: { color: '#a0aec0', maxRotation: 45, font: { size: 10 } },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          y: {
            beginAtZero: true,
            position: 'left',
            ticks: { color: '#4299e1' },
            grid: { color: 'rgba(255,255,255,0.05)' },
            title: { display: true, text: 'Antal IBC', color: '#4299e1', font: { size: 12 } }
          },
          y1: {
            beginAtZero: true,
            position: 'right',
            ticks: { color: '#f6ad55' },
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Cykeltid (min)', color: '#f6ad55', font: { size: 12 } }
          }
        }
      }
    });
  }

  exportChart(): void {
    const canvas = document.getElementById('skiftrapportOpChart') as HTMLCanvasElement;
    if (!canvas) return;
    const { from, to } = this.getDateRange();
    exportChartAsPng(canvas, {
      chartName: 'Skiftrapport - ' + (this.operatorName || 'operator'),
      startDate: from,
      endDate: to
    });
    this.exportFeedback = true;
    this._timers.push(setTimeout(() => { if (!this.destroy$.closed) this.exportFeedback = false; }, 2000));
  }

  exportCSV(): void {
    if (this.data.length === 0) return;

    const headers = ['Datum', 'Skift', 'IBC Godkanda', 'Kasserade', 'Totalt', 'Cykeltid (min)', 'OEE (%)', 'Drifttid (min)', 'Stopptid (min)', 'Rasttid (min)'];
    const rows = this.data.map(r => [
      r.datum,
      r.skift,
      r.ibc_ok,
      r.kasserade,
      r.totalt,
      r.cykeltid !== null ? r.cykeltid.toFixed(2) : '',
      r.oee !== null ? r.oee.toFixed(1) : '',
      r.drifttid,
      r.stopptid !== null ? r.stopptid : '',
      r.rasttime
    ].join(';'));

    const csv = '\uFEFF' + headers.join(';') + '\n' + rows.join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `skiftrapport-${this.operatorName || 'operator'}-${this.getDateRange().from}-${this.getDateRange().to}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  get todayStr(): string {
    return localDateStr(new Date());
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
