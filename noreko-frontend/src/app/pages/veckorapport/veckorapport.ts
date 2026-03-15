import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  VeckorapportService,
  VeckorapportData
} from '../../services/veckorapport.service';

@Component({
  selector: 'app-veckorapport',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './veckorapport.html',
  styleUrl: './veckorapport.css'
})
export class VeckorapportPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();

  // Data
  report: VeckorapportData | null = null;

  // UI state
  loading = true;
  error = '';
  selectedWeek = '';

  constructor(private veckorapportService: VeckorapportService) {}

  ngOnInit(): void {
    // Default: senaste avslutade veckan
    this.selectedWeek = this.getLastCompletedWeek();
    this.fetchReport();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  fetchReport(): void {
    this.loading = true;
    this.error = '';
    this.veckorapportService.getReport(this.selectedWeek).pipe(
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.loading = false;
      if (res?.success && res.data) {
        this.report = res.data;
      } else {
        this.error = 'Kunde inte ladda veckorapport. Kontrollera att du ar inloggad.';
      }
    });
  }

  onWeekChange(): void {
    if (this.selectedWeek) {
      this.fetchReport();
    }
  }

  printReport(): void {
    window.print();
  }

  // ---- Helpers ----

  private getLastCompletedWeek(): string {
    const now = new Date();
    // Ga till foregaende sondag
    const day = now.getDay(); // 0=son, 1=man, ...
    const diff = day === 0 ? 7 : day;
    const lastSunday = new Date(now);
    lastSunday.setDate(now.getDate() - diff);

    // ISO vecka for det datumet
    return this.getISOWeekString(lastSunday);
  }

  private getISOWeekString(date: Date): string {
    // Kopiera datumet
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    // Satt till narmaste torsdag (ISO vecka definieras av torsdag)
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    const weekNo = Math.ceil((((d.getTime() - yearStart.getTime()) / 86400000) + 1) / 7);
    return `${d.getUTCFullYear()}-W${weekNo.toString().padStart(2, '0')}`;
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '-';
    const parts = dateStr.split('-');
    if (parts.length !== 3) return dateStr;
    return `${parts[2]}/${parts[1]}`;
  }

  formatDateFull(dateStr: string): string {
    if (!dateStr) return '-';
    const days = ['Son', 'Man', 'Tis', 'Ons', 'Tor', 'Fre', 'Lor'];
    const d = new Date(dateStr + 'T00:00:00');
    return `${days[d.getDay()]} ${d.getDate()}/${d.getMonth() + 1}`;
  }

  trendIcon(changePct: number): string {
    if (changePct > 1) return '\u25B2'; // up arrow
    if (changePct < -1) return '\u25BC'; // down arrow
    return '\u2014'; // em dash
  }

  trendClass(changePct: number, invertedBetterDown = false): string {
    const betterUp = !invertedBetterDown;
    if (changePct > 1) return betterUp ? 'trend-better' : 'trend-worse';
    if (changePct < -1) return betterUp ? 'trend-worse' : 'trend-better';
    return 'trend-neutral';
  }

  /** For stopp och kassation ar lagre battre */
  trendClassInverted(changePct: number): string {
    return this.trendClass(changePct, true);
  }

  get weekLabel(): string {
    if (!this.report) return '';
    const wi = this.report.week_info;
    return `Vecka ${wi.week_number}, ${wi.year}`;
  }

  get dateRange(): string {
    if (!this.report) return '';
    const wi = this.report.week_info;
    return `${this.formatDate(wi.start_date)} - ${this.formatDate(wi.end_date)}`;
  }
  trackByIndex(index: number): number { return index; }
}
