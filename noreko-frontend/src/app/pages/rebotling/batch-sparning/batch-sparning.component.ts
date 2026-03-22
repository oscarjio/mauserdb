import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import {
  BatchSparningService,
  BatchOverview,
  ActiveBatch,
  BatchDetailResponse,
  HistoryBatch,
  CreateBatchData,
} from '../../../services/batch-sparning.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-batch-sparning',
  templateUrl: './batch-sparning.component.html',
  styleUrls: ['./batch-sparning.component.css'],
  imports: [CommonModule, FormsModule],
})
export class BatchSparningPage implements OnInit, OnDestroy {

  // Loading
  loadingOverview = false;
  loadingActive = false;
  loadingDetail = false;
  loadingHistory = false;

  // Errors
  errorOverview = false;
  errorActive = false;
  errorHistory = false;

  // Data
  overview: BatchOverview | null = null;
  activeBatches: ActiveBatch[] = [];
  selectedBatchDetail: BatchDetailResponse | null = null;
  historyBatches: HistoryBatch[] = [];

  // Tabs
  activeTab: 'aktiva' | 'historik' = 'aktiva';

  // History filters
  historyFrom = '';
  historyTo = '';
  historySearch = '';

  // Create batch modal
  showCreateModal = false;
  createForm: CreateBatchData = { batch_nummer: '', planerat_antal: 10 };
  createComment = '';
  savingBatch = false;
  createMessage = '';
  createError = '';

  // Chart
  private progressChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private chartTimerId: ReturnType<typeof setTimeout> | null = null;
  private modalTimerId: ReturnType<typeof setTimeout> | null = null;

  constructor(private svc: BatchSparningService) {}

  ngOnInit(): void {
    this.loadAll();
    this.refreshInterval = setInterval(() => this.loadAll(), 30000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.refreshInterval) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.chartTimerId) {
      clearTimeout(this.chartTimerId);
      this.chartTimerId = null;
    }
    if (this.modalTimerId) {
      clearTimeout(this.modalTimerId);
      this.modalTimerId = null;
    }
    if (this.progressChart) {
      this.progressChart.destroy();
      this.progressChart = null;
    }
  }

  loadAll(): void {
    this.loadOverview();
    this.loadActiveBatches();
    if (this.activeTab === 'historik') {
      this.loadHistory();
    }
  }

  loadOverview(): void {
    if (this.loadingOverview) return;
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingOverview = false;
      if (res?.success) {
        this.overview = res.data;
      } else if (res !== null) {
        this.errorOverview = true;
      }
    });
  }

  loadActiveBatches(): void {
    if (this.loadingActive) return;
    this.loadingActive = true;
    this.errorActive = false;
    this.svc.getActiveBatches().pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingActive = false;
      if (res?.success) {
        this.activeBatches = res.batchar;
        this.chartTimerId = setTimeout(() => { if (!this.destroy$.closed) this.renderProgressChart(); }, 100);
      } else if (res !== null) {
        this.errorActive = true;
      }
    });
  }

  loadHistory(): void {
    if (this.loadingHistory) return;
    this.loadingHistory = true;
    this.errorHistory = false;
    this.svc.getBatchHistory(
      this.historyFrom || undefined,
      this.historyTo || undefined,
      this.historySearch || undefined
    ).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingHistory = false;
      if (res?.success) {
        this.historyBatches = res.batchar;
      } else if (res !== null) {
        this.errorHistory = true;
      }
    });
  }

  switchTab(tab: 'aktiva' | 'historik'): void {
    this.activeTab = tab;
    if (tab === 'historik' && this.historyBatches.length === 0) {
      this.loadHistory();
    }
  }

  errorDetail = false;
  completeError = '';

  selectBatch(batchId: number): void {
    this.loadingDetail = true;
    this.errorDetail = false;
    this.selectedBatchDetail = null;
    this.svc.getBatchDetail(batchId).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.loadingDetail = false;
      if (res?.success) {
        this.selectedBatchDetail = res;
      } else {
        this.errorDetail = true;
      }
    });
  }

  closeDetail(): void {
    this.selectedBatchDetail = null;
    this.errorDetail = false;
    this.completeError = '';
  }

  completeBatch(batchId: number): void {
    if (!confirm('Vill du markera denna batch som klar?')) return;
    this.completeError = '';
    this.svc.completeBatch(batchId).pipe(takeUntil(this.destroy$)).subscribe(res => {
      if (res?.success) {
        this.loadAll();
        this.closeDetail();
      } else {
        this.completeError = 'Kunde inte markera batchen som klar. Försök igen.';
      }
    });
  }

  // Create batch modal
  openCreateModal(): void {
    this.createForm = { batch_nummer: '', planerat_antal: 10 };
    this.createComment = '';
    this.createMessage = '';
    this.createError = '';
    this.showCreateModal = true;
  }

  closeCreateModal(): void {
    this.showCreateModal = false;
  }

  submitCreateBatch(): void {
    if (!this.createForm.batch_nummer.trim()) {
      this.createError = 'Batch-nummer krävs';
      return;
    }
    if (this.createForm.planerat_antal < 1) {
      this.createError = 'Planerat antal måste vara minst 1';
      return;
    }

    const data: CreateBatchData = {
      batch_nummer: this.createForm.batch_nummer.trim(),
      planerat_antal: this.createForm.planerat_antal,
    };
    if (this.createComment.trim()) {
      data.kommentar = this.createComment.trim();
    }

    this.savingBatch = true;
    this.createError = '';
    this.createMessage = '';
    this.svc.createBatch(data).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.savingBatch = false;
      if (res?.success) {
        this.createMessage = 'Batch skapad!';
        this.loadAll();
        this.modalTimerId = setTimeout(() => this.closeCreateModal(), 1000);
      } else {
        this.createError = res?.error || 'Kunde inte skapa batch';
      }
    });
  }

  // History filter
  applyHistoryFilter(): void {
    this.loadHistory();
  }

  clearHistoryFilter(): void {
    this.historyFrom = '';
    this.historyTo = '';
    this.historySearch = '';
    this.loadHistory();
  }

  // Chart
  renderProgressChart(): void {
    if (this.progressChart) {
      this.progressChart.destroy();
      this.progressChart = null;
    }

    const canvas = document.getElementById('batchProgressChart') as HTMLCanvasElement | null;
    if (!canvas || this.activeBatches.length === 0) return;

    const labels = this.activeBatches.map(b => b.batch_nummer);
    const klara = this.activeBatches.map(b => b.antal_klara);
    const kvar = this.activeBatches.map(b => Math.max(0, b.planerat_antal - b.antal_klara));

    this.progressChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Klara',
            data: klara,
            backgroundColor: '#48bb78',
            borderRadius: 4,
          },
          {
            label: 'Kvar',
            data: kvar,
            backgroundColor: '#4a5568',
            borderRadius: 4,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0' },
          },
        },
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#a0aec0' },
            grid: { color: '#4a556833' },
          },
          y: {
            stacked: true,
            ticks: { color: '#e2e8f0' },
            grid: { display: false },
          },
        },
      },
    });
  }

  // Helpers
  progressPct(batch: ActiveBatch): number {
    return batch.planerat_antal > 0
      ? Math.round((batch.antal_klara / batch.planerat_antal) * 100)
      : 0;
  }

  statusBadgeClass(status: string): string {
    switch (status) {
      case 'pagaende': return 'badge bg-success';
      case 'klar':     return 'badge bg-primary';
      case 'pausad':   return 'badge bg-warning text-dark';
      default:         return 'badge bg-secondary';
    }
  }

  formatMinutes(min: number | null): string {
    if (min === null || min === undefined) return '-';
    if (min < 60) return min + ' min';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h + ' h ' + (m > 0 ? m + ' min' : '');
  }

  formatSeconds(s: number | null): string {
    if (s === null || s === undefined) return '-';
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return m + ':' + String(sec).padStart(2, '0') + ' min';
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
  trackById(index: number, item: any): any { return item?.id ?? index; }
  trackByIbcNummer(index: number, item: any): any { return item?.ibc_nummer ?? index; }
}
