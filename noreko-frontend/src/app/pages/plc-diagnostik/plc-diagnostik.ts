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

interface Skiftrapport {
  id: number;
  datum: string;
  ibc_ok: number;
  ibc_ej_ok: number;
  bur_ej_ok: number;
  totalt: number;
  drifttid: number;
  rasttime: number | null;
  driftstopptime: number | null;
  op1: number | null;
  op2: number | null;
  op3: number | null;
  product_id: number | null;
  skiftraknare: number | null;
  lopnummer: number | null;
  inlagd: number;
  created_at: string;
  updated_at: string;
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
    skiftrapporter: Skiftrapport[];
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
  showSkiftrapport = true;

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
        catchError(_err => {
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
          if (res.data.events.length > 0) {
            const newEvents = res.data.events.reverse();
            this.events = [...this.events, ...newEvents];
            this.shouldScrollToBottom = true;
          }
        } else {
          // Full load — merge PLC events + skiftrapporter sorted ASC by datum
          const skiftEvents = this.skiftrapportToEvents(res.data.skiftrapporter ?? []);
          const allEvents = [...res.data.events, ...skiftEvents];
          allEvents.sort((a, b) => (a.datum ?? '').localeCompare(b.datum ?? ''));
          this.events = allEvents;
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

  private skiftrapportToEvents(skiftrapporter: Skiftrapport[]): PlcEvent[] {
    return skiftrapporter.map(s => ({
      id: s.id,
      datum: s.created_at,
      source: 'skiftrapport',
      event_type: 'SKIFTRAPPORT',
      ibc_ok: s.ibc_ok,
      ibc_ej_ok: s.ibc_ej_ok,
      bur_ej_ok: s.bur_ej_ok,
      totalt: s.totalt,
      drifttid: s.drifttid,
      rasttime: s.rasttime,
      driftstopptime: s.driftstopptime,
      op1: s.op1,
      op2: s.op2,
      op3: s.op3,
      product_id: s.product_id,
      skiftraknare: s.skiftraknare,
      lopnummer: s.lopnummer,
      inlagd: s.inlagd,
    }));
  }

  get filteredEvents(): PlcEvent[] {
    return this.events.filter(e => {
      if (e.source === '_system') return true;
      if (e.source === 'onoff' && !this.showOnoff) return false;
      if (e.source === 'ibc' && !this.showIbc) return false;
      if (e.source === 'rast' && !this.showRast) return false;
      if (e.source === 'driftstopp' && !this.showDriftstopp) return false;
      if (e.source === 'skiftrapport' && !this.showSkiftrapport) return false;
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
      case 'SKIFTRAPPORT': return 'badge-skiftrapport';
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
      case 'SKIFTRAPPORT': return 'SKIFTRAPPORT';
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
    if (event['source'] === 'skiftrapport') {
      const parts: string[] = [];
      if (event['skiftraknare'] != null) parts.push(`skift=${event['skiftraknare']}`);
      // PLC D4000-D4002: operatörer
      if (event['op1'] != null && event['op1'] !== 0) parts.push(`op1(D4000)=${event['op1']}`);
      if (event['op2'] != null && event['op2'] !== 0) parts.push(`op2(D4001)=${event['op2']}`);
      if (event['op3'] != null && event['op3'] !== 0) parts.push(`op3(D4002)=${event['op3']}`);
      // PLC D4003: produkt
      if (event['product_id'] != null) parts.push(`produkt(D4003)=${event['product_id']}`);
      // PLC D4004-D4006: IBC-räknare
      parts.push(`ok(D4004)=${event['ibc_ok']}`);
      parts.push(`ej_ok(D4005)=${event['ibc_ej_ok']}`);
      parts.push(`bur_ej_ok(D4006)=${event['bur_ej_ok']}`);
      parts.push(`totalt=${event['totalt']}`);
      // PLC D4007-D4008: tider
      parts.push(`drifttid(D4007)=${event['drifttid']}min`);
      if (event['rasttime'] != null) parts.push(`rast(D4008)=${event['rasttime']}min`);
      // PLC D4009: löpnummer
      if (event['lopnummer'] != null) parts.push(`löpnr(D4009)=${event['lopnummer']}`);
      // PLC D4011: stopptid
      if (event['driftstopptime'] != null && event['driftstopptime'] !== 0) parts.push(`stopp(D4011)=${event['driftstopptime']}min`);
      parts.push(`inlagd=${event['inlagd'] ? '✓' : '—'}`);
      return parts.join('  ');
    }
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

  trackEvent(_index: number, event: PlcEvent): string {
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
