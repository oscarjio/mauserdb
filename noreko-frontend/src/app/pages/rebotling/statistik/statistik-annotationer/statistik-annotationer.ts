import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { of } from 'rxjs';
import { RebotlingService, ManualAnnotation } from '../../../../services/rebotling.service';

@Component({
  standalone: true,
  selector: 'app-statistik-annotationer',
  templateUrl: './statistik-annotationer.html',
  styleUrls: ['./statistik-annotationer.css'],
  imports: [CommonModule, FormsModule]
})
export class StatistikAnnotationerComponent implements OnInit, OnDestroy {
  annotations: ManualAnnotation[] = [];
  loading = false;
  error: string | null = null;
  filterTyp = '';

  // Formulär
  showForm = false;
  newDatum = '';
  newTyp = 'driftstopp';
  newTitel = '';
  newBeskrivning = '';
  saving = false;
  formError: string | null = null;
  formSuccess: string | null = null;

  deleting: number | null = null;

  private destroy$ = new Subject<void>();

  constructor(private rebotlingService: RebotlingService) {}

  ngOnInit() {
    // Standarddatum: idag
    const today = new Date();
    this.newDatum = this.fmtDate(today);
    this.loadAnnotations();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private fmtDate(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  loadAnnotations() {
    this.loading = true;
    this.error = null;
    // Hämta 1 år bakåt + 1 månad framåt
    const end = new Date();
    end.setMonth(end.getMonth() + 1);
    const start = new Date();
    start.setFullYear(start.getFullYear() - 1);

    this.rebotlingService.getManualAnnotations(
      this.fmtDate(start), this.fmtDate(end), this.filterTyp || undefined
    ).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of(null))
    ).subscribe((res: any) => {
      this.loading = false;
      if (res?.success && res.annotations) {
        this.annotations = res.annotations;
      } else {
        this.error = res?.error || 'Kunde inte hämta annotationer';
      }
    });
  }

  createAnnotation() {
    this.saving = true;
    this.formError = null;
    this.formSuccess = null;

    this.rebotlingService.createManualAnnotation({
      datum: this.newDatum,
      typ: this.newTyp,
      titel: this.newTitel,
      beskrivning: this.newBeskrivning
    }).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError((err) => of({ success: false, error: err?.error?.error || 'Serverfel' }))
    ).subscribe((res: any) => {
      this.saving = false;
      if (res?.success) {
        this.formSuccess = 'Annotation sparad!';
        this.newTitel = '';
        this.newBeskrivning = '';
        this.loadAnnotations();
        setTimeout(() => { this.formSuccess = null; }, 3000);
      } else {
        this.formError = res?.error || 'Kunde inte spara';
      }
    });
  }

  confirmDelete(ann: ManualAnnotation) {
    if (!confirm(`Ta bort "${ann.titel}" (${ann.datum})?`)) return;
    this.deleting = ann.id;

    this.rebotlingService.deleteManualAnnotation(ann.id).pipe(
      timeout(10000),
      takeUntil(this.destroy$),
      catchError(() => of({ success: false }))
    ).subscribe((res: any) => {
      this.deleting = null;
      if (res?.success) {
        this.annotations = this.annotations.filter(a => a.id !== ann.id);
      }
    });
  }

  typColor(typ: string): string {
    const colors: Record<string, string> = {
      driftstopp: '#e53e3e',
      helgdag: '#4299e1',
      handelse: '#48bb78',
      ovrigt: '#a0aec0'
    };
    return colors[typ] || '#a0aec0';
  }

  typLabel(typ: string): string {
    const labels: Record<string, string> = {
      driftstopp: 'Driftstopp',
      helgdag: 'Helgdag',
      handelse: 'Händelse',
      ovrigt: 'Övrigt'
    };
    return labels[typ] || typ;
  }
}
