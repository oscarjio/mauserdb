import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, of } from 'rxjs';
import { takeUntil, catchError, timeout } from 'rxjs/operators';
import { RebotlingService, ProductionEvent } from '../../../../services/rebotling.service';
import { AuthService } from '../../../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-statistik-handelser',
  templateUrl: './statistik-handelser.html',
  imports: [CommonModule, FormsModule]
})
export class StatistikHandelserComponent implements OnInit, OnDestroy {
  isAdmin: boolean = false;
  productionEvents: ProductionEvent[] = [];
  productionEventsLoading: boolean = false;
  showEventsAdmin: boolean = false;
  newEvent: { event_date: string; title: string; event_type: string; description: string } = { event_date: '', title: '', event_type: 'ovrigt', description: '' };
  eventsAdminMessage: string = '';
  eventsAdminError: string = '';
  eventsAdminSaving: boolean = false;
  private destroy$ = new Subject<void>();
  constructor(private rebotlingService: RebotlingService, private auth: AuthService) {}
  ngOnInit() { this.auth.user$.pipe(timeout(15000), catchError(() => of(null)), takeUntil(this.destroy$)).subscribe(val => { this.isAdmin = val?.role === 'admin'; }); this.loadProductionEvents(); }
  ngOnDestroy() { this.destroy$.next(); this.destroy$.complete(); }

  eventColor(type: string): string { const colors: Record<string, string> = { underhall: '#f97316', ny_operator: '#3b82f6', mal_andring: '#a855f7', rekord: '#eab308', ovrigt: '#6b7280' }; return colors[type] ?? '#6b7280'; }
  eventTypeLabel(type: string): string { const labels: Record<string, string> = { underhall: 'Underhåll', ny_operator: 'Ny operatör', mal_andring: 'Måländring', rekord: 'Rekord', ovrigt: 'Övrigt' }; return labels[type] ?? 'Övrigt'; }

  loadProductionEvents(): void {
    if (this.productionEventsLoading) return; this.productionEventsLoading = true;
    const endDate = new Date(); const startDate = new Date(); startDate.setDate(startDate.getDate() - 89);
    const fmt = (d: Date) => d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    this.rebotlingService.getProductionEvents(fmt(startDate), fmt(endDate)).pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
    .subscribe(res => { this.productionEventsLoading = false; if (res?.success && res.events) { this.productionEvents = res.events; } });
  }

  saveNewEvent(): void {
    if (!this.newEvent.event_date || !this.newEvent.title.trim()) { this.eventsAdminError = 'Datum och titel krävs.'; return; }
    this.eventsAdminSaving = true; this.eventsAdminError = ''; this.eventsAdminMessage = '';
    this.rebotlingService.addProductionEvent(this.newEvent).pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
    .subscribe(res => { this.eventsAdminSaving = false;
      if (res?.success) { const added: ProductionEvent = { id: res.id, event_date: this.newEvent.event_date, title: this.newEvent.title, description: this.newEvent.description, event_type: this.newEvent.event_type as ProductionEvent['event_type'] };
        this.productionEvents = [...this.productionEvents, added].sort((a, b) => a.event_date.localeCompare(b.event_date));
        this.eventsAdminMessage = 'Handelsen sparades.'; this.newEvent = { event_date: '', title: '', event_type: 'ovrigt', description: '' };
      } else { this.eventsAdminError = 'Kunde inte spara handelsen.'; } });
  }

  removeEvent(id: number): void {
    if (!confirm('Ta bort denna handelse?')) return;
    this.rebotlingService.deleteProductionEvent(id).pipe(timeout(8000), catchError(() => of(null)), takeUntil(this.destroy$))
    .subscribe(res => { if (res?.success) { this.productionEvents = this.productionEvents.filter(e => e.id !== id); } });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
