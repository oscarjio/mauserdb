import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { environment } from '../../../environments/environment';
import { ComponentCanDeactivate } from '../../guards/pending-changes.guard';

const API = `${environment.apiUrl}?action=shift-handover`;

export interface HandoverNote {
  id: number;
  datum: string;
  skift_nr: number;
  skift_label: string;
  note: string;
  priority: 'normal' | 'important' | 'urgent';
  audience: 'alla' | 'ansvarig' | 'teknik';
  op_number: number | null;
  op_name: string | null;
  created_by_user_id: number | null;
  created_at: string;
  time_ago: string;
  acknowledged_by: number | null;
  acknowledged_at: string | null;
  acknowledged_by_name: string | null;
  acknowledged_time_ago: string | null;
}

export type FilterTab = 'alla' | 'bradskande' | 'oppna' | 'kvitterade';

@Component({
  standalone: true,
  selector: 'app-shift-handover',
  imports: [CommonModule, FormsModule],
  templateUrl: './shift-handover.html',
  styleUrl: './shift-handover.css'
})
export class ShiftHandoverPage implements OnInit, OnDestroy, AfterViewInit, ComponentCanDeactivate {

  @ViewChild('noteTextarea') noteTextareaRef?: ElementRef<HTMLTextAreaElement>;

  notes: HandoverNote[] = [];
  isLoading = false;
  isFetching = false;
  loadError = '';
  lastUpdated = '';

  // Filterflik
  activeFilter: FilterTab = 'alla';

  // Formulärfält
  newNote = '';
  newPriority: 'normal' | 'important' | 'urgent' = 'normal';
  newAudience: 'alla' | 'ansvarig' | 'teknik' = 'alla';
  newSkiftNr: number;
  isSubmitting = false;

  canDeactivate(): boolean {
    return !this.newNote.trim();
  }

  // Formulär synlig/dold toggle
  showForm = true;

  currentUser: any = null;

  private destroy$ = new Subject<void>();
  private pollInterval: ReturnType<typeof setInterval> | null = null;
  private focusTimer: ReturnType<typeof setTimeout> | null = null;

  constructor(
    private http: HttpClient,
    private auth: AuthService,
    private toast: ToastService
  ) {
    this.newSkiftNr = this.getCurrentSkift();
  }

  ngOnInit(): void {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      this.currentUser = user;
    });

    this.loadNotes(true);

    // Poll var 60s
    this.pollInterval = setInterval(() => {
      this.loadNotes(false);
    }, 60000);
  }

  ngAfterViewInit(): void {
    // Auto-fokus på textfältet vid start
    this.focusTextarea();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval !== null) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
    if (this.focusTimer !== null) {
      clearTimeout(this.focusTimer);
      this.focusTimer = null;
    }
  }

  focusTextarea(): void {
    if (this.focusTimer !== null) clearTimeout(this.focusTimer);
    this.focusTimer = setTimeout(() => {
      this.noteTextareaRef?.nativeElement?.focus();
    }, 100);
  }

  // ---------------------------------------------------------------------------
  // Skift-nr baserat på klockslag
  // ---------------------------------------------------------------------------

  getCurrentSkift(): number {
    const h = new Date().getHours();
    if (h >= 6 && h < 14) return 1;  // Morgon
    if (h >= 14 && h < 22) return 2; // Eftermiddag
    return 3;                         // Natt
  }

  get currentSkiftLabel(): string {
    const labels: Record<number, string> = {
      1: 'Skift 1 — Morgon (06–14)',
      2: 'Skift 2 — Eftermiddag (14–22)',
      3: 'Skift 3 — Natt (22–06)',
    };
    return labels[this.getCurrentSkift()] ?? `Skift ${this.getCurrentSkift()}`;
  }

  skiftLabel(nr: number): string {
    const labels: Record<number, string> = {
      1: 'Skift 1 — Morgon',
      2: 'Skift 2 — Eftermiddag',
      3: 'Skift 3 — Natt',
    };
    return labels[nr] ?? `Skift ${nr}`;
  }

  // ---------------------------------------------------------------------------
  // Behörighetskontroll
  // ---------------------------------------------------------------------------

  canDelete(note: HandoverNote): boolean {
    if (!this.currentUser) return false;
    if (this.currentUser.role === 'admin') return true;
    if (note.created_by_user_id !== null && note.created_by_user_id === this.currentUser.id) return true;
    return false;
  }

  canAcknowledge(note: HandoverNote): boolean {
    if (!this.currentUser) return false;
    return note.acknowledged_at === null;
  }

  // ---------------------------------------------------------------------------
  // Prioritet-hjälpare
  // ---------------------------------------------------------------------------

  priorityIcon(priority: string): string {
    if (priority === 'urgent') return '🔴';
    if (priority === 'important') return '🟡';
    return '⚪';
  }

  priorityLabel(priority: string): string {
    if (priority === 'urgent') return 'Brådskande';
    if (priority === 'important') return 'Viktig';
    return 'Normal';
  }

  priorityClass(priority: string): string {
    if (priority === 'urgent') return 'priority-urgent';
    if (priority === 'important') return 'priority-important';
    return 'priority-normal';
  }

  // ---------------------------------------------------------------------------
  // Åtkomst-hjälpare
  // ---------------------------------------------------------------------------

  audienceIcon(audience: string): string {
    if (audience === 'ansvarig') return '👔';
    if (audience === 'teknik') return '🔧';
    return '👥';
  }

  audienceLabel(audience: string): string {
    if (audience === 'ansvarig') return 'Ansvarig';
    if (audience === 'teknik') return 'Teknik';
    return 'Alla';
  }

  // ---------------------------------------------------------------------------
  // Relativ tid (klient-sida)
  // ---------------------------------------------------------------------------

  timeAgo(dateStr: string): string {
    if (!dateStr) return '';
    const now = new Date();
    const then = new Date(dateStr);
    const diffSec = Math.floor((now.getTime() - then.getTime()) / 1000);

    if (diffSec < 60) return 'Just nu';
    if (diffSec < 3600) {
      const mins = Math.floor(diffSec / 60);
      return `${mins} ${mins === 1 ? 'minut' : 'minuter'} sedan`;
    }
    if (diffSec < 86400) {
      const hours = Math.floor(diffSec / 3600);
      return `${hours} ${hours === 1 ? 'timme' : 'timmar'} sedan`;
    }
    // Kalenderdag-jämförelse
    const nowDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const thenDate = new Date(then.getFullYear(), then.getMonth(), then.getDate());
    const dayDiff = Math.round((nowDate.getTime() - thenDate.getTime()) / 86400000);
    if (dayDiff === 1) return 'Igår';
    return `${dayDiff} dagar sedan`;
  }

  // ---------------------------------------------------------------------------
  // Filterflikar
  // ---------------------------------------------------------------------------

  setFilter(filter: FilterTab): void {
    this.activeFilter = filter;
  }

  get filteredNotes(): HandoverNote[] {
    switch (this.activeFilter) {
      case 'bradskande':
        return this.notes.filter(n => n.priority === 'urgent');
      case 'oppna':
        return this.notes.filter(n => n.acknowledged_at === null);
      case 'kvitterade':
        return this.notes.filter(n => n.acknowledged_at !== null);
      default:
        return this.notes;
    }
  }

  // Räknare per flik
  get countAll(): number { return this.notes.length; }
  get countBradskande(): number { return this.notes.filter(n => n.priority === 'urgent').length; }
  get countOppna(): number { return this.notes.filter(n => n.acknowledged_at === null).length; }
  get countKvitterade(): number { return this.notes.filter(n => n.acknowledged_at !== null).length; }

  // ---------------------------------------------------------------------------
  // Datahämtning
  // ---------------------------------------------------------------------------

  loadNotes(showLoader: boolean): void {
    if (this.isFetching) return;
    this.isFetching = true;
    if (showLoader) this.isLoading = true;

    this.http.get<any>(`${API}&run=recent`, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isFetching = false;
      if (showLoader) this.isLoading = false;

      if (res === null) {
        if (showLoader) this.loadError = 'Kunde inte hämta anteckningar';
        return;
      }

      if (res.success) {
        this.notes = res.notes ?? [];
        this.loadError = '';
        const now = new Date();
        this.lastUpdated = now.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
      } else {
        if (showLoader) this.loadError = res.error ?? 'Okänt fel';
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Kvittera anteckning
  // ---------------------------------------------------------------------------

  acknowledgeNote(note: HandoverNote): void {
    if (!this.canAcknowledge(note)) return;

    // Optimistic update
    note.acknowledged_at = new Date().toISOString();
    note.acknowledged_by = this.currentUser?.id ?? null;
    note.acknowledged_by_name = this.currentUser?.username ?? this.currentUser?.name ?? 'Du';
    note.acknowledged_time_ago = 'Just nu';

    this.http.post<any>(`${API}&run=acknowledge`, { id: note.id }, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res === null || !res.success) {
        // Rulla tillbaka optimistic update
        note.acknowledged_at = null;
        note.acknowledged_by = null;
        note.acknowledged_by_name = null;
        note.acknowledged_time_ago = null;
        this.toast.error('Kunde inte kvittera anteckning');
        return;
      }
      // Uppdatera med server-svar
      if (res.acknowledged_at) note.acknowledged_at = res.acknowledged_at;
      if (res.acknowledged_by_name) note.acknowledged_by_name = res.acknowledged_by_name;
      if (res.acknowledged_time_ago) note.acknowledged_time_ago = res.acknowledged_time_ago;
      this.toast.success('Anteckning kvitterad');
    });
  }

  // ---------------------------------------------------------------------------
  // Lägg till anteckning
  // ---------------------------------------------------------------------------

  submitNote(): void {
    const text = this.newNote.trim();
    if (!text) {
      this.toast.error('Ange en anteckningstext');
      return;
    }
    if (text.length > 500) {
      this.toast.error('Anteckning får inte vara längre än 500 tecken');
      return;
    }
    if (this.isSubmitting) return;

    this.isSubmitting = true;
    const body = {
      skift_nr: this.newSkiftNr,
      note: text,
      priority: this.newPriority,
      audience: this.newAudience,
    };

    this.http.post<any>(`${API}&run=add`, body, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      this.isSubmitting = false;
      if (res === null) {
        this.toast.error('Nätverksfel — kunde inte spara anteckning');
        return;
      }
      if (res.success) {
        this.toast.success('Anteckning sparad');
        this.newNote = '';
        this.newPriority = 'normal';
        this.newAudience = 'alla';
        this.newSkiftNr = this.getCurrentSkift();
        // Lägg in den nya noten direkt överst
        if (res.note) {
          this.notes.unshift(res.note);
          if (this.notes.length > 30) this.notes.pop();
        }
        // Fokusera textarea igen
        this.focusTextarea();
      } else {
        this.toast.error(res.error ?? 'Kunde inte spara anteckning');
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Ta bort anteckning
  // ---------------------------------------------------------------------------

  deleteNote(note: HandoverNote): void {
    if (!confirm(`Ta bort anteckning från ${note.time_ago}?`)) return;

    this.http.post<any>(`${API}&run=delete&id=${note.id}`, {}, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(() => of(null)),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res === null) {
        this.toast.error('Nätverksfel — kunde inte ta bort anteckning');
        return;
      }
      if (res.success) {
        this.notes = this.notes.filter(n => n.id !== note.id);
        this.toast.success('Anteckning borttagen');
      } else {
        this.toast.error(res.error ?? 'Kunde inte ta bort anteckning');
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Formulär-hjälpare
  // ---------------------------------------------------------------------------

  setPriority(p: 'normal' | 'important' | 'urgent'): void {
    this.newPriority = p;
  }

  get charCount(): number {
    return this.newNote.length;
  }

  toggleForm(): void {
    this.showForm = !this.showForm;
    if (this.showForm) {
      this.focusTextarea();
    }
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
