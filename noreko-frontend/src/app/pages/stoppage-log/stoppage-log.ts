import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth.service';
import { StoppageService, StoppageReason, StoppageEntry, StoppageStats } from '../../services/stoppage.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  standalone: true,
  selector: 'app-stoppage-log',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './stoppage-log.html',
  styleUrl: './stoppage-log.css'
})
export class StoppageLogPage implements OnInit, OnDestroy {
  loggedIn = false;
  isAdmin = false;
  user: any = null;

  reasons: StoppageReason[] = [];
  stoppages: StoppageEntry[] = [];
  stats: StoppageStats | null = null;

  selectedLine: string = 'rebotling';
  selectedPeriod: string = 'week';
  loading = false;
  showForm = false;
  activeTab: 'log' | 'stats' = 'log';

  newEntry = {
    line: 'rebotling',
    reason_id: 0,
    start_time: '',
    end_time: '',
    comment: ''
  };

  successMessage = '';
  errorMessage = '';
  private refreshInterval: any;
  private paretoChart: Chart | null = null;
  private dailyChart: Chart | null = null;

  constructor(
    private auth: AuthService,
    private stoppageService: StoppageService
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.subscribe((val: boolean) => this.loggedIn = val);
    this.auth.user$.subscribe((val: any) => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });

    // Set default times
    const now = new Date();
    this.newEntry.start_time = this.formatDateTime(now);
    this.newEntry.end_time = this.formatDateTime(now);

    this.loadReasons();
    this.loadStoppages();

    this.refreshInterval = setInterval(() => this.loadStoppages(), 30000);
  }

  ngOnDestroy() {
    if (this.refreshInterval) clearInterval(this.refreshInterval);
    if (this.paretoChart) this.paretoChart.destroy();
    if (this.dailyChart) this.dailyChart.destroy();
  }

  loadReasons() {
    this.stoppageService.getReasons().subscribe({
      next: (res) => {
        if (res.success) this.reasons = res.data;
      }
    });
  }

  loadStoppages() {
    this.stoppageService.getStoppages(this.selectedLine, this.selectedPeriod).subscribe({
      next: (res) => {
        if (res.success) this.stoppages = res.data;
        this.loading = false;
      },
      error: () => this.loading = false
    });
  }

  loadStats() {
    this.stoppageService.getStats(this.selectedLine, this.selectedPeriod).subscribe({
      next: (res) => {
        if (res.success) {
          this.stats = res.data;
          setTimeout(() => {
            this.buildParetoChart();
            this.buildDailyChart();
          }, 100);
        }
      }
    });
  }

  switchTab(tab: 'log' | 'stats') {
    this.activeTab = tab;
    if (tab === 'stats') this.loadStats();
  }

  onFilterChange() {
    this.loading = true;
    this.newEntry.line = this.selectedLine;
    this.loadStoppages();
    if (this.activeTab === 'stats') this.loadStats();
  }

  addStoppage() {
    this.errorMessage = '';
    if (!this.newEntry.reason_id) {
      this.errorMessage = 'Välj en stopporsak';
      return;
    }
    if (!this.newEntry.start_time) {
      this.errorMessage = 'Starttid krävs';
      return;
    }

    this.stoppageService.create(this.newEntry).subscribe({
      next: (res) => {
        if (res.success) {
          this.showSuccess('Stoppost registrerad');
          this.showForm = false;
          this.loadStoppages();
          // Reset form
          const now = new Date();
          this.newEntry.reason_id = 0;
          this.newEntry.start_time = this.formatDateTime(now);
          this.newEntry.end_time = this.formatDateTime(now);
          this.newEntry.comment = '';
        } else {
          this.errorMessage = res.message || 'Kunde inte registrera';
        }
      },
      error: (err) => this.errorMessage = err.error?.message || 'Ett fel uppstod'
    });
  }

  deleteStoppage(id: number) {
    if (!confirm('Är du säker på att du vill ta bort denna stoppost?')) return;
    this.stoppageService.delete(id).subscribe({
      next: (res) => {
        if (res.success) {
          this.stoppages = this.stoppages.filter(s => s.id !== id);
          this.showSuccess('Stoppost borttagen');
        }
      }
    });
  }

  canEdit(entry: StoppageEntry): boolean {
    return this.isAdmin || (this.user && entry.user_id === this.user.id);
  }

  formatDuration(minutes: number | null): string {
    if (minutes === null || minutes === undefined) return 'Pågår';
    if (minutes < 60) return minutes + ' min';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h + ' h ' + (m > 0 ? m + ' min' : '');
  }

  getTotalDowntime(): number {
    return this.stoppages.reduce((sum, s) => sum + (s.duration_minutes || 0), 0);
  }

  getUnplannedCount(): number {
    return this.stoppages.filter(s => s.category === 'unplanned').length;
  }

  exportCSV() {
    if (this.stoppages.length === 0) return;
    const header = ['ID', 'Linje', 'Orsak', 'Kategori', 'Start', 'Slut', 'Varaktighet (min)', 'Kommentar', 'Användare'];
    const rows = this.stoppages.map(s => [
      s.id, s.line, s.reason_name, s.category === 'planned' ? 'Planerat' : 'Oplanerat',
      s.start_time, s.end_time || 'Pågår', s.duration_minutes ?? '', s.comment, s.user_name
    ]);
    const csv = [header, ...rows].map(r => r.map(c => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `stopporsaker-${this.selectedLine}-${this.selectedPeriod}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  private formatDateTime(d: Date): string {
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  private showSuccess(msg: string) {
    this.successMessage = msg;
    setTimeout(() => this.successMessage = '', 3000);
  }

  private buildParetoChart() {
    if (this.paretoChart) this.paretoChart.destroy();
    const canvas = document.getElementById('paretoChart') as HTMLCanvasElement;
    if (!canvas || !this.stats) return;

    const reasons = this.stats.reasons;
    this.paretoChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels: reasons.map(r => r.name),
        datasets: [{
          label: 'Stopptid (min)',
          data: reasons.map(r => r.total_minutes),
          backgroundColor: reasons.map(r => r.color + 'cc'),
          borderColor: reasons.map(r => r.color),
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          title: { display: true, text: 'Stopptid per orsak (Pareto)', color: '#e2e8f0' }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, title: { display: true, text: 'Minuter', color: '#a0aec0' } }
        }
      }
    });
  }

  private buildDailyChart() {
    if (this.dailyChart) this.dailyChart.destroy();
    const canvas = document.getElementById('dailyChart') as HTMLCanvasElement;
    if (!canvas || !this.stats) return;

    const daily = this.stats.daily;
    this.dailyChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: daily.map(d => d.dag),
        datasets: [{
          label: 'Stopptid (min/dag)',
          data: daily.map(d => d.total_minutes),
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239, 68, 68, 0.1)',
          fill: true,
          tension: 0.3
        }, {
          label: 'Antal stopp/dag',
          data: daily.map(d => d.count),
          borderColor: '#f97316',
          backgroundColor: 'rgba(249, 115, 22, 0.1)',
          fill: false,
          tension: 0.3,
          yAxisID: 'y1'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: { display: true, text: 'Stopptid per dag', color: '#e2e8f0' },
          legend: { labels: { color: '#a0aec0' } }
        },
        scales: {
          x: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#a0aec0' }, grid: { color: '#2d3748' }, title: { display: true, text: 'Minuter', color: '#a0aec0' } },
          y1: { position: 'right', ticks: { color: '#a0aec0' }, grid: { display: false }, title: { display: true, text: 'Antal', color: '#a0aec0' } }
        }
      }
    });
  }
}
