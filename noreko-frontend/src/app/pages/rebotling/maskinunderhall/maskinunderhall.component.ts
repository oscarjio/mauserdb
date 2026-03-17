import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { Chart, registerables } from 'chart.js';
import { localToday } from '../../../utils/date-utils';
import {
  MaskinunderhallService,
  MaskinOverview,
  MaskinItem,
  ServiceHistoryItem,
  TimelineItem,
  AddServiceData,
  AddMachineData,
} from '../../../services/maskinunderhall.service';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-maskinunderhall',
  templateUrl: './maskinunderhall.component.html',
  styleUrls: ['./maskinunderhall.component.css'],
  imports: [CommonModule, FormsModule],
})
export class MaskinunderhallPage implements OnInit, OnDestroy {

  // Loading
  loadingOverview = false;
  loadingMachines = false;
  loadingHistory  = false;
  loadingTimeline = false;

  // Errors
  errorOverview = false;
  errorMachines = false;
  errorHistory  = false;
  errorTimeline = false;

  // Data
  overview: MaskinOverview | null = null;
  maskiner: MaskinItem[] = [];
  filteredMaskiner: MaskinItem[] = [];
  selectedMaskin: MaskinItem | null = null;
  historik: ServiceHistoryItem[] = [];
  timelineItems: TimelineItem[] = [];

  // Sortering
  sortColumn: keyof MaskinItem = 'namn';
  sortAsc = true;

  // Filter
  statusFilter: 'alla' | 'gron' | 'gul' | 'rod' = 'alla';

  // Modal: lägg till service
  showAddServiceModal = false;
  addServiceForm: AddServiceData = this.emptyServiceForm();
  savingService = false;
  serviceMessage = '';
  serviceError = '';

  // Modal: lägg till maskin
  showAddMachineModal = false;
  addMachineForm: AddMachineData = this.emptyMachineForm();
  savingMachine = false;
  maskinMessage = '';
  maskinError = '';

  // Chart
  private timelineChart: Chart | null = null;
  private destroy$ = new Subject<void>();
  private refreshInterval: ReturnType<typeof setInterval> | null = null;
  private modalTimerId: ReturnType<typeof setTimeout> | null = null;
  private chartTimerId: ReturnType<typeof setTimeout> | null = null;

  constructor(private svc: MaskinunderhallService) {}

  ngOnInit(): void {
    this.loadAll();
    // Auto-refresh var 5:e minut
    this.refreshInterval = setInterval(() => this.loadAll(), 5 * 60 * 1000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.destroyChart();
    if (this.refreshInterval !== null) {
      clearInterval(this.refreshInterval);
      this.refreshInterval = null;
    }
    if (this.modalTimerId !== null) {
      clearTimeout(this.modalTimerId);
      this.modalTimerId = null;
    }
    if (this.chartTimerId !== null) {
      clearTimeout(this.chartTimerId);
      this.chartTimerId = null;
    }
  }

  loadAll(): void {
    this.loadOverview();
    this.loadMachines();
    this.loadTimeline();
  }

  // ---- Overview ----
  private loadOverview(): void {
    this.loadingOverview = true;
    this.errorOverview = false;
    this.svc.getOverview().pipe(
      timeout(15000),
      catchError(() => { this.errorOverview = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingOverview = false;
      if (res?.success) {
        this.overview = res.data;
      } else if (res !== null) {
        this.errorOverview = true;
      }
    });
  }

  // ---- Machines ----
  private loadMachines(): void {
    this.loadingMachines = true;
    this.errorMachines = false;
    this.svc.getMachines().pipe(
      timeout(15000),
      catchError(() => { this.errorMachines = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingMachines = false;
      if (res?.success) {
        this.maskiner = res.maskiner;
        this.applyFilter();
      } else if (res !== null) {
        this.errorMachines = true;
      }
    });
  }

  // ---- Timeline ----
  private loadTimeline(): void {
    this.loadingTimeline = true;
    this.errorTimeline = false;
    this.svc.getTimeline().pipe(
      timeout(15000),
      catchError(() => { this.errorTimeline = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingTimeline = false;
      if (res?.success) {
        this.timelineItems = res.items;
        this.chartTimerId = setTimeout(() => this.buildTimelineChart(), 100);
      } else if (res !== null) {
        this.errorTimeline = true;
      }
    });
  }

  // ---- Machine history (drill-down) ----
  selectMaskin(maskin: MaskinItem): void {
    if (this.selectedMaskin?.id === maskin.id) {
      this.selectedMaskin = null;
      this.historik = [];
      return;
    }
    this.selectedMaskin = maskin;
    this.loadHistory(maskin.id);
  }

  private loadHistory(maskinId: number): void {
    this.loadingHistory = true;
    this.errorHistory = false;
    this.historik = [];
    this.svc.getMachineHistory(maskinId).pipe(
      timeout(15000),
      catchError(() => { this.errorHistory = true; return of(null); }),
      takeUntil(this.destroy$),
    ).subscribe(res => {
      this.loadingHistory = false;
      if (res?.success) {
        this.historik = res.historik;
      } else if (res !== null) {
        this.errorHistory = true;
      }
    });
  }

  closeHistory(): void {
    this.selectedMaskin = null;
    this.historik = [];
  }

  // ---- Sortering ----
  sortBy(col: keyof MaskinItem): void {
    if (this.sortColumn === col) {
      this.sortAsc = !this.sortAsc;
    } else {
      this.sortColumn = col;
      this.sortAsc = true;
    }
    this.applyFilter();
  }

  getSortIcon(col: keyof MaskinItem): string {
    if (this.sortColumn !== col) return 'fas fa-sort text-muted ms-1';
    return this.sortAsc ? 'fas fa-sort-up ms-1' : 'fas fa-sort-down ms-1';
  }

  // ---- Filter ----
  onStatusFilter(): void {
    this.applyFilter();
  }

  private applyFilter(): void {
    let items = [...this.maskiner];
    if (this.statusFilter !== 'alla') {
      items = items.filter(m => m.status === this.statusFilter);
    }
    // Sortering
    items.sort((a, b) => {
      const aVal = a[this.sortColumn] ?? '';
      const bVal = b[this.sortColumn] ?? '';
      if (aVal < bVal) return this.sortAsc ? -1 : 1;
      if (aVal > bVal) return this.sortAsc ? 1 : -1;
      return 0;
    });
    this.filteredMaskiner = items;
  }

  // ---- Add Service Modal ----
  openAddServiceModal(maskin?: MaskinItem): void {
    this.addServiceForm = this.emptyServiceForm();
    if (maskin) {
      this.addServiceForm.maskin_id = maskin.id;
    }
    this.serviceMessage = '';
    this.serviceError = '';
    this.showAddServiceModal = true;
  }

  closeAddServiceModal(): void {
    this.showAddServiceModal = false;
  }

  submitAddService(): void {
    if (!this.addServiceForm.maskin_id) {
      this.serviceError = 'Välj en maskin';
      return;
    }
    this.savingService = true;
    this.serviceMessage = '';
    this.serviceError = '';

    this.svc.addService(this.addServiceForm).pipe(
      takeUntil(this.destroy$),
    ).subscribe((res: any) => {
      this.savingService = false;
      if (res?.success) {
        this.serviceMessage = 'Service registrerad!';
        this.modalTimerId = setTimeout(() => {
          this.showAddServiceModal = false;
          this.loadAll();
          // Uppdatera historik om aktuell maskin visas
          if (this.selectedMaskin?.id === this.addServiceForm.maskin_id) {
            this.loadHistory(this.selectedMaskin.id);
          }
        }, 1000);
      } else {
        this.serviceError = res?.error || 'Kunde inte spara service';
      }
    });
  }

  // ---- Add Machine Modal ----
  openAddMachineModal(): void {
    this.addMachineForm = this.emptyMachineForm();
    this.maskinMessage = '';
    this.maskinError = '';
    this.showAddMachineModal = true;
  }

  closeAddMachineModal(): void {
    this.showAddMachineModal = false;
  }

  submitAddMachine(): void {
    if (!this.addMachineForm.namn?.trim()) {
      this.maskinError = 'Namn krävs';
      return;
    }
    this.savingMachine = true;
    this.maskinMessage = '';
    this.maskinError = '';

    this.svc.addMachine(this.addMachineForm).pipe(
      takeUntil(this.destroy$),
    ).subscribe((res: any) => {
      this.savingMachine = false;
      if (res?.success) {
        this.maskinMessage = 'Maskin registrerad!';
        this.modalTimerId = setTimeout(() => {
          this.showAddMachineModal = false;
          this.loadAll();
        }, 1000);
      } else {
        this.maskinError = res?.error || 'Kunde inte spara maskin';
      }
    });
  }

  // ---- Chart ----
  private destroyChart(): void {
    try { this.timelineChart?.destroy(); } catch (_) {}
    this.timelineChart = null;
  }

  private buildTimelineChart(): void {
    this.destroyChart();
    if (!this.timelineItems.length) return;

    const canvas = document.getElementById('timelineChart') as HTMLCanvasElement;
    if (!canvas) return;

    const labels = this.timelineItems.map(i => i.namn);
    // Överskridna dagar (rött del): max(0, dagar_sedan - intervall)
    const overskirdet = this.timelineItems.map(i => Math.max(0, (i.dagar_sedan ?? 0) - i.intervall));
    // Normal del (grön): min(dagar_sedan, intervall)
    const normalDel = this.timelineItems.map(i => Math.min(i.dagar_sedan ?? 0, i.intervall));

    this.timelineChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Dagar sedan service (inom intervall)',
            data: normalDel,
            backgroundColor: this.timelineItems.map(i =>
              i.status === 'gron' ? 'rgba(72,187,120,0.7)' :
              i.status === 'gul'  ? 'rgba(236,201,75,0.7)' :
              'rgba(229,62,62,0.7)'
            ),
            borderColor: this.timelineItems.map(i =>
              i.status === 'gron' ? '#48bb78' :
              i.status === 'gul'  ? '#ecc94b' :
              '#e53e3e'
            ),
            borderWidth: 1,
          },
          {
            label: 'Försenat (dagar efter interval)',
            data: overskirdet,
            backgroundColor: 'rgba(229,62,62,0.9)',
            borderColor: '#c53030',
            borderWidth: 1,
          },
        ],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: '#e2e8f0', font: { size: 12 } },
          },
          tooltip: {
            callbacks: {
              label: (ctx: any) => {
                const idx = ctx.dataIndex;
                const item = this.timelineItems[idx];
                if (!item) return '';
                return [
                  `Intervall: ${item.intervall} dagar`,
                  `Dagar sedan service: ${item.dagar_sedan ?? 'Ingen'}`,
                  item.dagar_kvar !== null
                    ? (item.dagar_kvar >= 0 ? `Dagar kvar: ${item.dagar_kvar}` : `Försenat: ${Math.abs(item.dagar_kvar)} dagar`)
                    : '',
                ].filter(Boolean);
              },
            },
          },
        },
        scales: {
          x: {
            stacked: true,
            title: { display: true, text: 'Dagar', color: '#a0aec0' },
            ticks: { color: '#a0aec0' },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
          y: {
            stacked: true,
            ticks: { color: '#e2e8f0', font: { size: 13 } },
            grid: { color: 'rgba(255,255,255,0.05)' },
          },
        },
      },
    });
  }

  // ---- Helpers ----
  getStatusClass(status: string): string {
    switch (status) {
      case 'gron': return 'badge-gron';
      case 'gul':  return 'badge-gul';
      case 'rod':  return 'badge-rod';
      default:     return 'badge-gul';
    }
  }

  getStatusText(status: string): string {
    switch (status) {
      case 'gron': return 'OK';
      case 'gul':  return 'Snart';
      case 'rod':  return 'Forsenat';
      default:     return 'Okand';
    }
  }

  getDagarKvarText(m: MaskinItem): string {
    if (m.dagar_kvar === null) return '—';
    if (m.dagar_kvar < 0) return `${Math.abs(m.dagar_kvar)} dagar försenat`;
    if (m.dagar_kvar === 0) return 'Idag';
    return `${m.dagar_kvar} dagar`;
  }

  getRowClass(m: MaskinItem): string {
    switch (m.status) {
      case 'rod': return 'row-rod';
      case 'gul': return 'row-gul';
      default:    return '';
    }
  }

  getTypBadgeClass(typ: string): string {
    switch (typ) {
      case 'akut':       return 'badge bg-danger';
      case 'inspektion': return 'badge bg-info';
      default:           return 'badge bg-secondary';
    }
  }

  private emptyServiceForm(): AddServiceData {
    return {
      maskin_id: 0,
      service_datum: localToday(),
      service_typ: 'planerat',
      beskrivning: '',
      utfort_av: '',
      nasta_planerad_datum: '',
    };
  }

  private emptyMachineForm(): AddMachineData {
    return {
      namn: '',
      beskrivning: '',
      service_intervall_dagar: 90,
    };
  }
  trackByIndex(index: number): number { return index; }
}
