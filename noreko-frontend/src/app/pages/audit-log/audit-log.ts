import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth.service';
import { AuditService, AuditEntry, AuditStats } from '../../services/audit.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-audit-log',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './audit-log.html',
  styleUrl: './audit-log.css'
})
export class AuditLogPage implements OnInit {
  loggedIn = false;
  isAdmin = false;
  user: any = null;

  logs: AuditEntry[] = [];
  stats: AuditStats | null = null;
  loading = false;

  // Filters
  selectedPeriod = 'month';
  filterAction = '';
  filterUser = '';

  // Pagination
  currentPage = 1;
  totalPages = 1;
  totalCount = 0;

  activeTab: 'log' | 'stats' = 'log';
  expandedId: number | null = null;

  private activityChart: Chart | null = null;

  constructor(
    private auth: AuthService,
    private auditService: AuditService
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.subscribe((val: any) => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
      if (this.isAdmin) {
        this.loadLogs();
      }
    });
  }

  loadLogs() {
    this.loading = true;
    this.auditService.getLogs({
      page: this.currentPage,
      period: this.selectedPeriod,
      filter_action: this.filterAction,
      filter_user: this.filterUser
    }).subscribe({
      next: (res) => {
        if (res.success) {
          this.logs = res.data;
          this.totalCount = res.total;
          this.totalPages = res.pages;
        }
        this.loading = false;
      },
      error: () => this.loading = false
    });
  }

  loadStats() {
    this.auditService.getStats(this.selectedPeriod).subscribe({
      next: (res) => {
        if (res.success) {
          this.stats = res.data;
          setTimeout(() => this.buildActivityChart(), 100);
        }
      }
    });
  }

  switchTab(tab: 'log' | 'stats') {
    this.activeTab = tab;
    if (tab === 'stats') this.loadStats();
  }

  onFilterChange() {
    this.currentPage = 1;
    this.loadLogs();
    if (this.activeTab === 'stats') this.loadStats();
  }

  goToPage(page: number) {
    if (page < 1 || page > this.totalPages) return;
    this.currentPage = page;
    this.loadLogs();
  }

  toggleExpand(id: number) {
    this.expandedId = this.expandedId === id ? null : id;
  }

  getActionLabel(action: string): string {
    const labels: Record<string, string> = {
      'create_user': 'Skapa användare',
      'delete_user': 'Ta bort användare',
      'toggle_admin': 'Ändra admin-status',
      'toggle_active': 'Ändra aktiv-status',
      'update_user': 'Uppdatera användare',
      'update_weights': 'Uppdatera vikter',
      'set_targets': 'Ändra mål',
      'approve_bonuses': 'Godkänn bonus'
    };
    return labels[action] || action;
  }

  getActionIcon(action: string): string {
    const icons: Record<string, string> = {
      'create_user': 'fa-user-plus',
      'delete_user': 'fa-user-minus',
      'toggle_admin': 'fa-user-shield',
      'toggle_active': 'fa-user-check',
      'update_user': 'fa-user-edit',
      'update_weights': 'fa-balance-scale',
      'set_targets': 'fa-bullseye',
      'approve_bonuses': 'fa-check-double'
    };
    return icons[action] || 'fa-cog';
  }

  getActionColor(action: string): string {
    if (action.includes('delete')) return '#ef4444';
    if (action.includes('create')) return '#22c55e';
    if (action.includes('toggle')) return '#f97316';
    if (action.includes('approve')) return '#3b82f6';
    return '#a0aec0';
  }

  parseJson(value: string | null): any {
    if (!value) return null;
    try { return JSON.parse(value); } catch { return value; }
  }

  formatJsonKeys(obj: any): string[] {
    if (!obj || typeof obj !== 'object') return [];
    return Object.keys(obj);
  }

  private buildActivityChart() {
    if (this.activityChart) this.activityChart.destroy();
    const canvas = document.getElementById('activityChart') as HTMLCanvasElement;
    if (!canvas || !this.stats) return;

    const daily = this.stats.daily;
    this.activityChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: daily.map(d => d.dag),
        datasets: [{
          label: 'Aktiviteter per dag',
          data: daily.map(d => d.count),
          backgroundColor: 'rgba(0, 212, 255, 0.5)',
          borderColor: '#00d4ff',
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          title: { display: true, text: 'Aktivitet per dag', color: '#e2e8f0' }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0', stepSize: 1 }, grid: { color: '#2d3748' }, beginAtZero: true }
        }
      }
    });
  }
}
