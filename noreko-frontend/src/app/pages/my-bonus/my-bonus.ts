import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, distinctUntilChanged } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { BonusService, OperatorStatsResponse, KPIDetailsResponse, OperatorHistoryResponse } from '../../services/bonus.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

@Component({
  selector: 'app-my-bonus',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './my-bonus.html',
  styleUrls: ['./my-bonus.css']
})
export class MyBonusPage implements OnInit, OnDestroy {
  loggedIn = false;
  operatorId = '';
  savedOperatorId = '';
  loading = false;
  error = '';
  operatorIdFromAccount = false;

  stats: any = null;
  history: any[] = [];
  selectedPeriod = 'week';
  showFormula = false;

  Math = Math;
  private kpiChart: Chart | null = null;
  private historyChart: Chart | null = null;
  private ibcTrendChart: Chart | null = null;
  private destroy$ = new Subject<void>();

  constructor(private auth: AuthService, private bonusService: BonusService) {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe((val: boolean) => this.loggedIn = val);
  }

  ngOnInit(): void {
    this.auth.user$.pipe(
      takeUntil(this.destroy$),
      distinctUntilChanged((a: any, b: any) => a?.operator_id === b?.operator_id)
    ).subscribe((user: any) => {
      if (user?.operator_id) {
        this.operatorId = String(user.operator_id);
        this.savedOperatorId = String(user.operator_id);
        this.operatorIdFromAccount = true;
        this.loadStats();
      } else {
        this.operatorIdFromAccount = false;
        const saved = localStorage.getItem('myOperatorId');
        if (saved) {
          this.operatorId = saved;
          this.savedOperatorId = saved;
          this.loadStats();
        }
      }
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.kpiChart) this.kpiChart.destroy();
    if (this.historyChart) this.historyChart.destroy();
    if (this.ibcTrendChart) this.ibcTrendChart.destroy();
  }

  saveAndLoad(): void {
    if (!this.operatorId.trim()) return;
    localStorage.setItem('myOperatorId', this.operatorId.trim());
    this.savedOperatorId = this.operatorId.trim();
    this.loadStats();
  }

  clearOperator(): void {
    localStorage.removeItem('myOperatorId');
    this.operatorId = '';
    this.savedOperatorId = '';
    this.stats = null;
    this.history = [];
    if (this.kpiChart) { this.kpiChart.destroy(); this.kpiChart = null; }
    if (this.historyChart) { this.historyChart.destroy(); this.historyChart = null; }
    if (this.ibcTrendChart) { this.ibcTrendChart.destroy(); this.ibcTrendChart = null; }
  }

  changePeriod(period: string): void {
    this.selectedPeriod = period;
    this.loadStats();
  }

  loadStats(): void {
    if (!this.savedOperatorId) return;
    this.loading = true;
    this.error = '';

    this.bonusService.getOperatorStats(this.savedOperatorId, this.selectedPeriod).subscribe({
      next: (res: OperatorStatsResponse) => {
        if (res.success && res.data) {
          this.stats = res.data;
          this.buildKPIChart(res.data);
        } else {
          this.error = res.error || 'Ingen data hittades för detta operatörs-ID.';
          this.stats = null;
        }
        this.loading = false;
      },
      error: () => {
        this.error = 'Kunde inte hämta data. Försök igen.';
        this.loading = false;
      }
    });

    // Historik: hämta 20 senaste skift för historikgraf + ibcTrend
    this.bonusService.getOperatorHistory(this.savedOperatorId, 20).subscribe({
      next: (res: OperatorHistoryResponse) => {
        if (res.success && res.data) {
          this.history = res.data.history || [];
          this.buildHistoryChart(this.history);
          this.buildIbcTrendChart(this.history);
        }
      },
      error: () => {}
    });
  }

  // ===== Motivational status badge =====
  getStatusBadge(): { text: string; cssClass: string } {
    const bonus = this.stats?.kpis?.bonus_avg ?? 0;
    const trend = this.getTrendDirection();
    if (bonus >= 95) return { text: 'Rekordniva!', cssClass: 'badge-outstanding' };
    if (bonus >= 90 && trend === 'up') return { text: 'Uppat mot toppen!', cssClass: 'badge-excellent-up' };
    if (bonus >= 90) return { text: 'Utmarkt prestanda!', cssClass: 'badge-excellent' };
    if (bonus >= 80 && trend === 'up') return { text: 'Over genomsnitt!', cssClass: 'badge-good-up' };
    if (bonus >= 80) return { text: 'Over genomsnitt', cssClass: 'badge-good' };
    if (bonus >= 70) return { text: 'Pa ratt spår', cssClass: 'badge-base' };
    return { text: 'Fortsätt kämpa!', cssClass: 'badge-below' };
  }

  // Beräkna mitt IBC/h-snitt senaste 7 skiften
  getMyAvgIbcPerHour(): number {
    if (!this.history || this.history.length === 0) return 0;
    const recent = this.history.slice(0, 7);
    const withProd = recent.filter((h: any) => (h.kpis?.produktivitet ?? 0) > 0);
    if (withProd.length === 0) return 0;
    const sum = withProd.reduce((s: number, h: any) => s + (h.kpis?.produktivitet ?? 0), 0);
    return Math.round((sum / withProd.length) * 10) / 10;
  }

  // Prognos: antal poäng + IBC/h om fortsätter i detta tempo
  getShiftPrognosis(): { bonusPoang: number; ibcPerHour: number; weeklyIbc: number } | null {
    if (!this.history || this.history.length < 3) return null;
    const recent = this.history.slice(0, 7);
    const avgBonus = recent.reduce((s: number, h: any) => s + (h.kpis?.bonus ?? 0), 0) / recent.length;
    const avgProd = recent.reduce((s: number, h: any) => s + (h.kpis?.produktivitet ?? 0), 0) / recent.length;
    const avgIbcPerShift = recent.reduce((s: number, h: any) => s + (h.ibc_ok ?? 0), 0) / recent.length;

    return {
      bonusPoang: Math.round(avgBonus * 10) / 10,
      ibcPerHour: Math.round(avgProd * 10) / 10,
      weeklyIbc: Math.round(avgIbcPerShift * 5)  // 5 skift/vecka
    };
  }

  getBonusClass(bonus: number): string {
    if (bonus >= 90) return 'text-success';
    if (bonus >= 70) return 'text-info';
    if (bonus >= 50) return 'text-warning';
    return 'text-danger';
  }

  getBonusTier(bonus: number): string {
    if (bonus >= 95) return 'Outstanding (x2.0)';
    if (bonus >= 90) return 'Excellent (x1.5)';
    if (bonus >= 80) return 'God prestanda (x1.25)';
    if (bonus >= 70) return 'Basbonus (x1.0)';
    return 'Under förväntan (x0.75)';
  }

  getProductName(id: number): string {
    switch (id) {
      case 1: return 'FoodGrade';
      case 4: return 'NonUN';
      case 5: return 'Tvättade';
      default: return 'Produkt ' + id;
    }
  }

  getNextTierInfo(bonus: number): { name: string; pointsNeeded: number } | null {
    const tiers = [
      { name: 'Outstanding (x2.0)', threshold: 95 },
      { name: 'Excellent (x1.5)', threshold: 90 },
      { name: 'God prestanda (x1.25)', threshold: 80 },
      { name: 'Basbonus (x1.0)', threshold: 70 }
    ];
    for (const tier of tiers) {
      if (bonus < tier.threshold) {
        return { name: tier.name, pointsNeeded: tier.threshold - bonus };
      }
    }
    return null;
  }

  getProjectedBonus(): { weekly: number; monthly: number } | null {
    if (!this.history || this.history.length < 3) return null;
    const recent = this.history.slice(0, 7);
    const avg = recent.reduce((sum: number, h: any) => sum + (h.kpis?.bonus ?? 0), 0) / recent.length;
    return {
      weekly: Math.round(avg * 10) / 10,
      monthly: Math.round(avg * 4 * 10) / 10
    };
  }

  getTrendDirection(): 'up' | 'down' | 'flat' {
    if (!this.history || this.history.length < 6) return 'flat';
    const recent3 = this.history.slice(0, 3).reduce((s: number, h: any) => s + (h.kpis?.bonus ?? 0), 0) / 3;
    const prev3 = this.history.slice(3, 6).reduce((s: number, h: any) => s + (h.kpis?.bonus ?? 0), 0) / 3;
    const diff = recent3 - prev3;
    if (diff > 2) return 'up';
    if (diff < -2) return 'down';
    return 'flat';
  }

  exportBonusCSV(): void {
    if (!this.stats?.daily_breakdown?.length) return;
    const header = ['Datum', 'Cykler', 'IBC OK', 'IBC Ej OK', 'Effektivitet', 'Produktivitet', 'Kvalitet', 'Bonus'];
    const rows = this.stats.daily_breakdown.map((d: any) => [
      d.date, d.cycles, d.ibc_ok, d.ibc_ej_ok,
      (d.effektivitet ?? 0).toFixed(1) + '%',
      (d.produktivitet ?? 0).toFixed(1),
      (d.kvalitet ?? 0).toFixed(1) + '%',
      (d.bonus_poang ?? 0).toFixed(1)
    ]);
    const csv = [header, ...rows].map(r => r.map((c: any) => `"${c}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `min-bonus-${this.savedOperatorId}-${this.selectedPeriod}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  exportBonusPDF(): void {
    if (!this.stats) return;
    import('pdfmake/build/pdfmake').then((pdfMakeModule: any) => {
      import('pdfmake/build/vfs_fonts').then((vfsFontsModule: any) => {
        const pdfMake = pdfMakeModule.default || pdfMakeModule;
        const vfsFonts = vfsFontsModule.default || vfsFontsModule;
        pdfMake.vfs = vfsFonts?.pdfMake?.vfs || vfsFonts?.vfs || vfsFonts;
        const s = this.stats;
        const opName = s.operator_name || ('Operatör ' + this.savedOperatorId);
        const trend = this.getTrendDirection();
        const trendText = trend === 'up' ? '↑ Uppåtgående' : trend === 'down' ? '↓ Nedåtgående' : '→ Stabil';
        const projected = this.getProjectedBonus();
        const prognosis = this.getShiftPrognosis();
        const breakdownRows = (s.daily_breakdown || []).slice(0, 20).map((d: any) => [
          d.date || '',
          String(d.cycles || 0),
          (d.effektivitet ?? 0).toFixed(1) + '%',
          (d.produktivitet ?? 0).toFixed(1),
          (d.kvalitet ?? 0).toFixed(1) + '%',
          { text: (d.bonus_poang ?? 0).toFixed(1), bold: true }
        ]);
        pdfMake.createPdf({
          content: [
            { text: 'Bonusrapport', style: 'header' },
            { text: opName + '  |  ' + this.getPositionName(s.position) + '  |  Period: ' + s.period, style: 'subheader' },
            { text: ' ' },
            { text: 'Sammanfattning', style: 'sectionHeader' },
            {
              table: { widths: ['*', '*', '*', '*'],
                body: [
                  [{ text: 'Snittbonus', bold: true, fillColor: '#eeeeee' }, { text: 'Maxbonus', bold: true, fillColor: '#eeeeee' }, { text: 'Minbonus', bold: true, fillColor: '#eeeeee' }, { text: 'Trend', bold: true, fillColor: '#eeeeee' }],
                  [
                    { text: (s.kpis?.bonus_avg ?? 0).toFixed(1), alignment: 'center' },
                    { text: (s.kpis?.bonus_max ?? 0).toFixed(1), alignment: 'center' },
                    { text: (s.kpis?.bonus_min ?? 0).toFixed(1), alignment: 'center' },
                    { text: trendText, alignment: 'center' }
                  ]
                ]
              }, layout: 'lightHorizontalLines'
            },
            ...(prognosis ? [
              { text: ' ' },
              { text: 'Skiftprognos (om du fortsätter i detta tempo)', style: 'sectionHeader' },
              {
                table: { widths: ['*', '*', '*'],
                  body: [
                    [{ text: 'Förv. bonus', bold: true, fillColor: '#eeeeee' }, { text: 'IBC/h', bold: true, fillColor: '#eeeeee' }, { text: 'IBC/vecka (5 skift)', bold: true, fillColor: '#eeeeee' }],
                    [
                      { text: prognosis.bonusPoang.toFixed(1) + ' p', alignment: 'center' },
                      { text: prognosis.ibcPerHour.toFixed(1), alignment: 'center' },
                      { text: String(prognosis.weeklyIbc), alignment: 'center' }
                    ]
                  ]
                }, layout: 'lightHorizontalLines'
              }
            ] : []),
            { text: ' ' },
            { text: 'KPI:er', style: 'sectionHeader' },
            {
              table: { widths: ['*', '*', '*'],
                body: [
                  [{ text: 'Effektivitet', bold: true, fillColor: '#eeeeee' }, { text: 'Produktivitet', bold: true, fillColor: '#eeeeee' }, { text: 'Kvalitet', bold: true, fillColor: '#eeeeee' }],
                  [
                    { text: (s.kpis?.effektivitet ?? 0).toFixed(1) + '%', alignment: 'center' },
                    { text: (s.kpis?.produktivitet ?? 0).toFixed(1), alignment: 'center' },
                    { text: (s.kpis?.kvalitet ?? 0).toFixed(1) + '%', alignment: 'center' }
                  ]
                ]
              }, layout: 'lightHorizontalLines'
            },
            ...(breakdownRows.length > 0 ? [
              { text: ' ' },
              { text: 'Daglig uppdelning (senaste ' + breakdownRows.length + ' skift)', style: 'sectionHeader' },
              {
                table: {
                  widths: ['*', 'auto', 'auto', 'auto', 'auto', 'auto'],
                  body: [
                    [{ text: 'Datum', bold: true, fillColor: '#eeeeee' }, { text: 'Cykler', bold: true, fillColor: '#eeeeee' }, { text: 'Effektivitet', bold: true, fillColor: '#eeeeee' }, { text: 'Produktivitet', bold: true, fillColor: '#eeeeee' }, { text: 'Kvalitet', bold: true, fillColor: '#eeeeee' }, { text: 'Bonus', bold: true, fillColor: '#eeeeee' }],
                    ...breakdownRows
                  ]
                }, layout: 'lightHorizontalLines'
              }
            ] : []),
            { text: ' ' },
            { text: 'Genererad: ' + new Date().toLocaleString('sv-SE'), style: 'meta' }
          ],
          styles: {
            header: { fontSize: 20, bold: true, margin: [0, 0, 0, 4] },
            subheader: { fontSize: 12, color: '#555', margin: [0, 0, 0, 10] },
            sectionHeader: { fontSize: 13, bold: true, margin: [0, 8, 0, 4] },
            meta: { fontSize: 10, color: '#777', margin: [0, 2, 0, 0] }
          },
          defaultStyle: { fontSize: 11 }
        }).download(`bonusrapport-${this.savedOperatorId}-${this.selectedPeriod}.pdf`);
      });
    });
  }

  getPositionName(pos: string): string {
    switch (pos) {
      case 'position_1': return 'Tvättplats';
      case 'position_2': return 'Kontrollstation';
      case 'position_3': return 'Truckförare';
      default: return pos;
    }
  }

  private buildKPIChart(data: any): void {
    if (this.kpiChart) this.kpiChart.destroy();

    const canvas = document.getElementById('myKpiChart') as HTMLCanvasElement;
    if (!canvas) return;

    this.kpiChart = new Chart(canvas, {
      type: 'radar',
      data: {
        labels: ['Effektivitet', 'Produktivitet', 'Kvalitet'],
        datasets: [{
          label: 'Dina KPI:er',
          data: [
            data.kpis?.effektivitet ?? 0,
            data.kpis?.produktivitet ?? 0,
            data.kpis?.kvalitet ?? 0
          ],
          borderColor: '#38b2ac',
          backgroundColor: 'rgba(56, 178, 172, 0.2)',
          pointBackgroundColor: '#38b2ac'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          r: {
            beginAtZero: true,
            max: 120,
            ticks: { color: '#a0aec0', backdropColor: 'transparent' },
            grid: { color: '#4a5568' },
            angleLines: { color: '#4a5568' },
            pointLabels: { color: '#e2e8f0', font: { size: 12 } }
          }
        },
        plugins: {
          legend: { labels: { color: '#a0aec0' } }
        }
      }
    });
  }

  private buildHistoryChart(history: any[]): void {
    if (this.historyChart) this.historyChart.destroy();

    const canvas = document.getElementById('myHistoryChart') as HTMLCanvasElement;
    if (!canvas || history.length === 0) return;

    const recent = history.slice(0, 15).reverse();
    const labels = recent.map((h: any) => h.datum?.substring(5) || '');
    const bonusData = recent.map((h: any) => h.kpis?.bonus ?? 0);

    this.historyChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Bonus',
          data: bonusData,
          backgroundColor: bonusData.map((b: number) =>
            b >= 90 ? 'rgba(72, 187, 120, 0.7)' :
            b >= 70 ? 'rgba(56, 178, 172, 0.7)' :
            b >= 50 ? 'rgba(236, 201, 75, 0.7)' :
            'rgba(229, 62, 62, 0.7)'
          ),
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { ticks: { color: '#718096' }, grid: { color: '#2d3748' } },
          y: { ticks: { color: '#718096' }, grid: { color: '#2d3748' }, min: 0, max: 200 }
        }
      }
    });
  }

  /** IBC/h de senaste 7 skiften vs ett glidande snitt */
  private buildIbcTrendChart(history: any[]): void {
    if (this.ibcTrendChart) this.ibcTrendChart.destroy();

    const canvas = document.getElementById('myIbcTrendChart') as HTMLCanvasElement;
    if (!canvas || history.length < 2) return;

    const recent = history.slice(0, 7).reverse();
    const labels = recent.map((h: any) => h.datum?.substring(5, 10) || '');
    const ibcData = recent.map((h: any) => +(h.kpis?.produktivitet ?? 0).toFixed(1));

    // Beräkna rullande medelvärde (3 punkter)
    const avgData = ibcData.map((_: number, i: number) => {
      const window = ibcData.slice(Math.max(0, i - 2), i + 1);
      return +(window.reduce((a: number, b: number) => a + b, 0) / window.length).toFixed(1);
    });

    this.ibcTrendChart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'IBC/h per skift',
            data: ibcData,
            borderColor: '#4299e1',
            backgroundColor: 'rgba(66,153,225,0.15)',
            fill: true,
            tension: 0.3,
            pointRadius: 5,
            pointBackgroundColor: '#4299e1'
          },
          {
            label: 'Glidande snitt (3)',
            data: avgData,
            borderColor: '#f6e05e',
            backgroundColor: 'transparent',
            borderDash: [5, 3],
            tension: 0.3,
            pointRadius: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#a0aec0', font: { size: 11 } } }
        },
        scales: {
          x: { ticks: { color: '#718096' }, grid: { color: '#2d3748' } },
          y: {
            ticks: { color: '#718096' },
            grid: { color: '#2d3748' },
            title: { display: true, text: 'IBC/h', color: '#718096' },
            beginAtZero: false
          }
        }
      }
    });
  }
}
