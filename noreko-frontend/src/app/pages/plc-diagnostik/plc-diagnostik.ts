import { Component, OnInit, OnDestroy, ViewChild, ElementRef, AfterViewChecked } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, finalize } from 'rxjs/operators';
import { environment } from '../../../environments/environment';

interface PlcEvent {
  id: number;
  datum: string;
  source: string;      // onoff, ibc, rast, driftstopp, _system
  event_type: string;   // ON, OFF, IBC, RAST_START, RAST_END, STOPP_START, STOPP_END
  _systemText?: string;
  _systemType?: string;
  [key: string]: any;
}

interface PlcDiagnostikResponse {
  success: boolean;
  data: {
    events: PlcEvent[];
    max_id: number;
    stats: {
      running: boolean;
      skiftraknare: number;
      last_event: string | null;
      ibc_today: number;
    };
    date: string;
    event_count: number;
  };
}

@Component({
  standalone: true,
  selector: 'app-plc-diagnostik',
  imports: [CommonModule, FormsModule],
  templateUrl: './plc-diagnostik.html',
  styleUrl: './plc-diagnostik.css'
})
export class PlcDiagnostikPage implements OnInit, OnDestroy, AfterViewChecked {
  private destroy$ = new Subject<void>();
  private pollIntervalId: any;
  isFetching = false;

  // Console state
  events: PlcEvent[] = [];
  maxId = 0;
  isPaused = false;
  autoScroll = true;
  selectedDate: string = '';
  isToday = true;

  // Filters
  showOnoff = true;
  showIbc = true;
  showRast = true;
  showDriftstopp = true;

  // Stats
  stats = {
    running: false,
    skiftraknare: 0,
    last_event: null as string | null,
    ibc_today: 0,
  };

  // Connection health
  lastFetchTime: Date | null = null;
  fetchError = false;
  connectionHealthSec = 0;
  private healthIntervalId: any;

  // Command input
  commandInput = '';
  commandHistory: string[] = [];

  @ViewChild('consoleOutput') consoleOutput!: ElementRef;
  private shouldScrollToBottom = false;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.selectedDate = this.todayStr();
    this.isToday = true;
    this.fetchEvents(false);
    this.startPolling();
    this.healthIntervalId = setInterval(() => {
      if (this.lastFetchTime) {
        this.connectionHealthSec = Math.round((Date.now() - this.lastFetchTime.getTime()) / 1000);
      }
    }, 1000);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.pollIntervalId) clearInterval(this.pollIntervalId);
    if (this.healthIntervalId) clearInterval(this.healthIntervalId);
  }

  ngAfterViewChecked(): void {
    if (this.shouldScrollToBottom && this.autoScroll && this.consoleOutput) {
      const el = this.consoleOutput.nativeElement;
      el.scrollTop = el.scrollHeight;
      this.shouldScrollToBottom = false;
    }
  }

  private todayStr(): string {
    const d = new Date();
    return d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0');
  }

  private startPolling(): void {
    this.pollIntervalId = setInterval(() => {
      if (!this.isPaused && this.isToday) {
        this.fetchEvents(true);
      }
    }, 2500);
  }

  fetchEvents(incremental: boolean): void {
    if (this.isFetching) return;
    this.isFetching = true;

    let url = `${environment.apiUrl}?action=rebotling&run=plc-diagnostik&date=${this.selectedDate}&limit=200`;
    if (incremental && this.maxId > 0) {
      url += `&since_id=${this.maxId}`;
    }

    this.http.get<PlcDiagnostikResponse>(url, { withCredentials: true })
      .pipe(
        timeout(5000),
        catchError(err => {
          this.fetchError = true;
          return of(null);
        }),
        finalize(() => { this.isFetching = false; }),
        takeUntil(this.destroy$)
      )
      .subscribe(res => {
        if (!res || !res.success) return;
        this.fetchError = false;
        this.lastFetchTime = new Date();

        if (incremental) {
          // Prepend new events (they come sorted DESC, we display ASC)
          if (res.data.events.length > 0) {
            const newEvents = res.data.events.reverse();
            this.events = [...this.events, ...newEvents];
            this.shouldScrollToBottom = true;
          }
        } else {
          // Full load — events come DESC, reverse for ASC display
          this.events = res.data.events.reverse();
          this.shouldScrollToBottom = true;
        }

        if (res.data.max_id > this.maxId) {
          this.maxId = res.data.max_id;
        }

        this.stats = res.data.stats;
      });
  }

  onDateChange(): void {
    this.isToday = this.selectedDate === this.todayStr();
    this.events = [];
    this.maxId = 0;
    this.fetchEvents(false);
  }

  goToToday(): void {
    this.selectedDate = this.todayStr();
    this.onDateChange();
  }

  togglePause(): void {
    this.isPaused = !this.isPaused;
  }

  toggleAutoScroll(): void {
    this.autoScroll = !this.autoScroll;
    if (this.autoScroll) {
      this.shouldScrollToBottom = true;
    }
  }

  clearConsole(): void {
    this.events = [];
  }

  get filteredEvents(): PlcEvent[] {
    return this.events.filter(e => {
      if (e.source === '_system') return true;
      if (e.source === 'onoff' && !this.showOnoff) return false;
      if (e.source === 'ibc' && !this.showIbc) return false;
      if (e.source === 'rast' && !this.showRast) return false;
      if (e.source === 'driftstopp' && !this.showDriftstopp) return false;
      return true;
    });
  }

  getBadgeClass(event: PlcEvent): string {
    switch (event.event_type) {
      case 'ON': return 'badge-on';
      case 'OFF': return 'badge-off';
      case 'IBC': return 'badge-ibc';
      case 'RAST_START': case 'RAST_END': return 'badge-rast';
      case 'STOPP_START': case 'STOPP_END': return 'badge-stopp';
      default: return 'badge-default';
    }
  }

  getBadgeLabel(event: PlcEvent): string {
    switch (event.event_type) {
      case 'ON': return 'ON';
      case 'OFF': return 'OFF';
      case 'IBC': return 'IBC';
      case 'RAST_START': return 'RAST START';
      case 'RAST_END': return 'RAST SLUT';
      case 'STOPP_START': return 'STOPP START';
      case 'STOPP_END': return 'STOPP SLUT';
      default: return event.event_type;
    }
  }

  formatTimestamp(datum: string): string {
    if (!datum) return '--';
    // datum is like "2026-03-28 14:32:05"
    const parts = datum.split(' ');
    return parts.length > 1 ? parts[1] : datum;
  }

  formatEventData(event: PlcEvent): string {
    const skip = ['id', 'datum', 'source', 'event_type'];
    const parts: string[] = [];
    for (const key of Object.keys(event)) {
      if (skip.includes(key)) continue;
      const val = event[key];
      if (val === null || val === undefined || val === '') continue;
      parts.push(`${key}=${val}`);
    }
    return parts.join('  ');
  }

  get healthClass(): string {
    if (this.fetchError) return 'health-error';
    if (this.connectionHealthSec > 10) return 'health-warning';
    return 'health-ok';
  }

  get healthLabel(): string {
    if (this.fetchError) return 'Anslutningsfel';
    if (!this.lastFetchTime) return 'Ansluter...';
    if (this.connectionHealthSec > 10) return `${this.connectionHealthSec}s sedan`;
    return 'OK';
  }

  get statusLabel(): string {
    return this.stats.running ? 'IGÅNG' : 'STOPPAD';
  }

  get statusClass(): string {
    return this.stats.running ? 'status-running' : 'status-stopped';
  }

  trackEvent(index: number, event: PlcEvent): string {
    return event.source + '-' + event.id;
  }

  // ---- Command system ----
  executeCommand(): void {
    const raw = this.commandInput.trim();
    if (!raw) return;
    this.commandInput = '';
    this.commandHistory.push(raw);

    // Add command echo to console
    this.addSystemLine(`$ ${raw}`, 'cmd');

    const parts = raw.split(/\s+/);
    const cmd = parts[0].toLowerCase();
    const arg = parts[1]?.toLowerCase() || '';

    switch (cmd) {
      case '/help':
        this.showHelp();
        break;
      case '/onoff':
        if (arg === 'on' || arg === 'off') {
          this.sendSimulation('onoff', arg);
        } else {
          this.addSystemLine('Användning: /onoff on|off — Starta eller stoppa linjen', 'error');
        }
        break;
      case '/rast':
        if (arg === 'on' || arg === 'off') {
          this.sendSimulation('rast', arg);
        } else {
          this.addSystemLine('Användning: /rast on|off — Starta eller avsluta rast', 'error');
        }
        break;
      case '/driftstopp':
        if (arg === 'on' || arg === 'off') {
          this.sendSimulation('driftstopp', arg);
        } else {
          this.addSystemLine('Användning: /driftstopp on|off — Aktivera eller avsluta driftstopp', 'error');
        }
        break;
      case '/status':
        this.addSystemLine(`Linje: ${this.stats.running ? 'IGÅNG' : 'STOPPAD'} | Skift: ${this.stats.skiftraknare} | IBC idag: ${this.stats.ibc_today}`, 'info');
        break;
      case '/clear':
        this.clearConsole();
        break;
      default:
        this.addSystemLine(`Okänt kommando: ${cmd}. Skriv /help för hjälp.`, 'error');
    }
  }

  private showHelp(): void {
    const lines = [
      '╔══════════════════════════════════════════════════════╗',
      '║  PLC DIAGNOSTIK — KOMMANDON                         ║',
      '╠══════════════════════════════════════════════════════╣',
      '║  /onoff on       Simulera linje START               ║',
      '║  /onoff off      Simulera linje STOPP               ║',
      '║  /rast on        Simulera rast START                 ║',
      '║  /rast off       Simulera rast SLUT                  ║',
      '║  /driftstopp on  Simulera driftstopp START           ║',
      '║  /driftstopp off Simulera driftstopp SLUT            ║',
      '║  /status         Visa aktuell linjestatus            ║',
      '║  /clear          Rensa konsolen                      ║',
      '║  /help           Visa denna hjälp                    ║',
      '╚══════════════════════════════════════════════════════╝',
    ];
    lines.forEach(l => this.addSystemLine(l, 'help'));
  }

  private sendSimulation(command: string, value: string): void {
    this.addSystemLine(`Skickar ${command} ${value}...`, 'info');
    this.http.post<{ success: boolean; message?: string; error?: string }>(
      `${environment.apiUrl}?action=rebotling&run=plc-simulate`,
      { command, value },
      { withCredentials: true }
    ).pipe(
      timeout(5000),
      catchError(() => of({ success: false, message: '', error: 'Nätverksfel' })),
      takeUntil(this.destroy$)
    ).subscribe(res => {
      if (res.success) {
        this.addSystemLine(`✓ ${res.message}`, 'success');
        // Trigger immediate refresh
        setTimeout(() => this.fetchEvents(true), 500);
      } else {
        this.addSystemLine(`✗ ${res.error || 'Okänt fel'}`, 'error');
      }
    });
  }

  private addSystemLine(text: string, type: 'cmd' | 'info' | 'success' | 'error' | 'help'): void {
    const now = new Date();
    const ts = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
    const pseudo: any = {
      id: Date.now(),
      datum: ts,
      source: '_system',
      event_type: type.toUpperCase(),
      _systemText: text,
      _systemType: type,
    };
    this.events.push(pseudo);
    this.shouldScrollToBottom = true;
  }
}
