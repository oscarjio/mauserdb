import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import {
  UnderhallsloggService,
  UnderhallKategori,
  UnderhallsPost,
  UnderhallsStats
} from '../../services/underhallslogg.service';

@Component({
  standalone: true,
  selector: 'app-underhallslogg',
  imports: [CommonModule, FormsModule],
  templateUrl: './underhallslogg.html',
  styleUrl: './underhallslogg.css'
})
export class UnderhallsloggComponent implements OnInit, OnDestroy {
  loggedIn = false;
  user: any = null;

  // Kategorier
  kategorier: UnderhallKategori[] = [];

  // Formulärdata
  formKategori = '';
  formTyp: 'planerat' | 'oplanerat' = 'planerat';
  formVaraktighet: number | null = null;
  formKommentar = '';
  formMaskin = 'Rebotling';
  submitting = false;
  successMessage = '';
  errorMessage = '';

  // Historiklista
  historik: UnderhallsPost[] = [];
  loadingHistorik = false;
  filterDays = 30;
  filterType = 'all';
  filterCategory = 'all';

  // Statistik
  stats: UnderhallsStats | null = null;
  loadingStats = false;
  statsDays = 30;

  // Delete
  deletingId: number | null = null;

  private destroy$ = new Subject<void>();
  private successTimerId: any;

  constructor(
    private auth: AuthService,
    private service: UnderhallsloggService
  ) {}

  ngOnInit() {
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(v => this.loggedIn = v);
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(v => this.user = v);
    this.loadKategorier();
    this.loadHistorik();
    this.loadStats();
  }

  ngOnDestroy() {
    clearTimeout(this.successTimerId);
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadKategorier() {
    this.service.getCategories()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          if (res.success) {
            this.kategorier = res.data;
            if (this.kategorier.length > 0 && !this.formKategori) {
              this.formKategori = this.kategorier[0].namn;
            }
          }
        }
      });
  }

  loadHistorik() {
    this.loadingHistorik = true;
    this.service.getList(this.filterDays, this.filterType, this.filterCategory)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingHistorik = false;
          if (res.success) this.historik = res.data;
        },
        error: () => { this.loadingHistorik = false; }
      });
  }

  loadStats() {
    this.loadingStats = true;
    this.service.getStats(this.statsDays)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.loadingStats = false;
          if (res.success && res.data) this.stats = res.data;
        },
        error: () => { this.loadingStats = false; }
      });
  }

  onFilterChange() {
    this.loadHistorik();
  }

  onStatsDaysChange() {
    this.loadStats();
  }

  spara() {
    if (this.submitting) return;
    this.errorMessage = '';

    if (!this.formKategori) {
      this.errorMessage = 'Välj en kategori';
      return;
    }
    if (!this.formVaraktighet || this.formVaraktighet <= 0) {
      this.errorMessage = 'Ange varaktighet i minuter (minst 1)';
      return;
    }

    this.submitting = true;
    this.service.logUnderhall({
      kategori: this.formKategori,
      typ: this.formTyp,
      varaktighet_min: this.formVaraktighet,
      kommentar: this.formKommentar,
      maskin: this.formMaskin || 'Rebotling'
    }).pipe(takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.submitting = false;
          if (res.success) {
            this.visaBekraftelse('Underhall loggat!');
            this.formKommentar = '';
            this.formVaraktighet = null;
            this.formTyp = 'planerat';
            if (this.kategorier.length > 0) this.formKategori = this.kategorier[0].namn;
            this.loadHistorik();
            this.loadStats();
          } else {
            this.errorMessage = res.message || 'Kunde inte spara';
          }
        },
        error: () => {
          this.submitting = false;
          this.errorMessage = 'Anslutningsfel — försök igen';
        }
      });
  }

  deletePost(post: UnderhallsPost) {
    if (this.deletingId !== null) return;
    if (!confirm(`Ta bort underhallspost från ${this.formatDatum(post.created_at)}?`)) return;
    this.deletingId = post.id;
    this.service.deleteEntry(post.id)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: res => {
          this.deletingId = null;
          if (res.success) {
            this.historik = this.historik.filter(h => h.id !== post.id);
            this.loadStats();
            this.visaBekraftelse('Post borttagen');
          } else {
            this.errorMessage = res.message || 'Kunde inte ta bort post';
          }
        },
        error: () => {
          this.deletingId = null;
          this.errorMessage = 'Anslutningsfel';
        }
      });
  }

  exportCSV() {
    if (this.historik.length === 0) return;
    const header = ['Datum', 'Operatör', 'Kategori', 'Typ', 'Varaktighet (min)', 'Maskin', 'Kommentar'];
    const rows = this.historik.map(h => [
      h.created_at,
      h.operator_namn || '',
      h.kategori,
      h.typ,
      String(h.varaktighet_min),
      h.maskin,
      (h.kommentar || '').replace(/"/g, '""')
    ]);
    const csvContent = [header, ...rows]
      .map(r => r.map(v => `"${v}"`).join(','))
      .join('\n');
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `underhallslogg_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }

  formatDatum(dt: string): string {
    if (!dt) return '';
    const d = new Date(dt);
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  formatTid(min: number): string {
    if (!min) return '0 min';
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h > 0 && m > 0) return `${h} h ${m} min`;
    if (h > 0) return `${h} h`;
    return `${m} min`;
  }

  formatTidmar(totalMin: number): string {
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    if (h > 0) return `${h} h ${m > 0 ? m + ' min' : ''}`.trim();
    return `${m} min`;
  }

  get totalTidHistorik(): number {
    return this.historik.reduce((sum, h) => sum + (h.varaktighet_min || 0), 0);
  }

  maxKategoriAntal(): number {
    if (!this.stats || !this.stats.top_kategorier.length) return 1;
    return Math.max(...this.stats.top_kategorier.map(k => k.antal));
  }

  private visaBekraftelse(msg: string) {
    this.successMessage = msg;
    clearTimeout(this.successTimerId);
    this.successTimerId = setTimeout(() => {
      if (!this.destroy$.closed) this.successMessage = '';
    }, 4000);
  }
}
