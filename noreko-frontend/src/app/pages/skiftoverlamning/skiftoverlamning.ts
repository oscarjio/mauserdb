import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import {
  SkiftoverlamningService,
  SkiftSummary,
  SkiftNote,
  SkiftHistoryItem,
} from '../../services/skiftoverlamning.service';

@Component({
  standalone: true,
  selector: 'app-skiftoverlamning',
  imports: [CommonModule, FormsModule],
  templateUrl: './skiftoverlamning.html',
  styleUrl: './skiftoverlamning.css',
})
export class SkiftoverlamningPage implements OnInit, OnDestroy {
  Math = Math;

  // Tillstånd
  isLoading    = false;
  isLoadingHist = false;
  isSubmitting  = false;
  loadError    = '';
  histError    = '';

  // Data
  summary: SkiftSummary | null = null;
  history: SkiftHistoryItem[]  = [];
  historyDays = 7;

  // Formulär
  newNote = '';

  // Auth
  currentUser: any = null;
  loggedIn = false;

  private destroy$ = new Subject<void>();

  constructor(
    private svc: SkiftoverlamningService,
    private auth: AuthService,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(u => {
      this.currentUser = u;
    });
    this.auth.loggedIn$.pipe(takeUntil(this.destroy$)).subscribe(li => {
      this.loggedIn = li;
    });

    this.loadSummary();
    this.loadHistory();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  // ---------------------------------------------------------------------------
  // Datahämtning
  // ---------------------------------------------------------------------------

  loadSummary(skiftraknare?: number): void {
    if (this.isLoading) return;
    this.isLoading = true;
    this.loadError = '';

    this.svc.getSummary(skiftraknare).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.isLoading = false;
      if (!res.success) {
        this.loadError = res.error ?? 'Kunde inte hämta skiftdata';
        return;
      }
      this.summary = res;
    });
  }

  loadHistory(): void {
    if (this.isLoadingHist) return;
    this.isLoadingHist = true;
    this.histError = '';

    this.svc.getHistory(this.historyDays).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.isLoadingHist = false;
      if (!res.success) {
        this.histError = res.error ?? 'Kunde inte hämta historik';
        return;
      }
      this.history = res.history;
    });
  }

  selectHistorySkift(item: SkiftHistoryItem): void {
    this.loadSummary(item.skiftraknare);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  navigatePrev(): void {
    if (this.summary?.prev_skift) this.loadSummary(this.summary.prev_skift);
  }

  navigateNext(): void {
    if (this.summary?.next_skift) this.loadSummary(this.summary.next_skift);
  }

  // ---------------------------------------------------------------------------
  // Noteringar
  // ---------------------------------------------------------------------------

  submitNote(): void {
    const text = this.newNote.trim();
    if (!text) {
      this.toast.error('Ange en noteringstext');
      return;
    }
    if (!this.summary) {
      this.toast.error('Inget skift laddat');
      return;
    }
    if (this.isSubmitting) return;

    this.isSubmitting = true;
    this.svc.addNote(this.summary.skiftraknare, text).pipe(takeUntil(this.destroy$)).subscribe(res => {
      this.isSubmitting = false;
      if (!res.success) {
        this.toast.error(res.error ?? 'Kunde inte spara notering');
        return;
      }
      this.toast.success('Notering sparad');
      this.newNote = '';
      if (res.note && this.summary) {
        this.summary.notes = [...(this.summary.notes ?? []), res.note];
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Utskrift
  // ---------------------------------------------------------------------------

  print(): void {
    window.print();
  }

  // ---------------------------------------------------------------------------
  // Hjälpmetoder
  // ---------------------------------------------------------------------------

  formatMin(min: number): string {
    const h = Math.floor(min / 60);
    const m = min % 60;
    if (h > 0) return `${h}h ${m}min`;
    return `${m} min`;
  }

  formatSec(sec: number): string {
    return `${sec}s`;
  }

  kvalitetClass(pct: number): string {
    if (pct >= 95) return 'text-success';
    if (pct >= 85) return 'text-warning';
    return 'text-danger';
  }

  ibcPerHClass(iph: number): string {
    if (iph >= 12) return 'text-success';
    if (iph >= 8)  return 'text-warning';
    return 'text-danger';
  }

  stopptidClass(pct: number): string {
    if (pct <= 10) return 'text-success';
    if (pct <= 25) return 'text-warning';
    return 'text-danger';
  }

  formatDate(d: string | null): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('sv-SE');
  }

  formatTime(t: string | null): string {
    if (!t) return '—';
    return t.substring(0, 5);
  }

  formatDateTime(dt: string | null): string {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleString('sv-SE', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
  }

  get hasNotes(): boolean {
    return (this.summary?.notes?.length ?? 0) > 0;
  }

  onDaysChange(): void {
    this.loadHistory();
  }
}
