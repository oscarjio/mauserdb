import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';

const API = '/noreko-backend/api.php?action=shift-handover';

export interface HandoverNote {
  id: number;
  datum: string;
  skift_nr: number;
  skift_label: string;
  note: string;
  priority: 'normal' | 'important' | 'urgent';
  op_number: number | null;
  op_name: string | null;
  created_by_user_id: number | null;
  created_at: string;
  time_ago: string;
}

@Component({
  standalone: true,
  selector: 'app-shift-handover',
  imports: [CommonModule, FormsModule],
  templateUrl: './shift-handover.html',
  styleUrl: './shift-handover.css'
})
export class ShiftHandoverPage implements OnInit, OnDestroy {

  notes: HandoverNote[] = [];
  isLoading = false;
  isFetching = false;
  loadError = '';
  lastUpdated = '';

  // Formulärfält
  newNote = '';
  newPriority: 'normal' | 'important' | 'urgent' = 'normal';
  newSkiftNr: number;
  isSubmitting = false;

  currentUser: any = null;

  private destroy$ = new Subject<void>();
  private pollInterval: ReturnType<typeof setInterval> | null = null;

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

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollInterval !== null) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
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
  // Datahämtning
  // ---------------------------------------------------------------------------

  loadNotes(showLoader: boolean): void {
    if (this.isFetching) return;
    this.isFetching = true;
    if (showLoader) this.isLoading = true;

    this.http.get<any>(`${API}&run=recent`, { withCredentials: true }).pipe(
      timeout(5000),
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
  // Lägg till anteckning
  // ---------------------------------------------------------------------------

  submitNote(): void {
    const text = this.newNote.trim();
    if (!text) {
      this.toast.error('Ange en anteckningstext');
      return;
    }
    if (text.length > 1000) {
      this.toast.error('Anteckning får inte vara längre än 1000 tecken');
      return;
    }
    if (this.isSubmitting) return;

    this.isSubmitting = true;
    const body = {
      skift_nr: this.newSkiftNr,
      note: text,
      priority: this.newPriority,
    };

    this.http.post<any>(`${API}&run=add`, body, { withCredentials: true }).pipe(
      timeout(5000),
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
        this.newSkiftNr = this.getCurrentSkift();
        // Lägg in den nya noten direkt överst
        if (res.note) {
          this.notes.unshift(res.note);
          if (this.notes.length > 10) this.notes.pop();
        }
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
      timeout(5000),
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
}
