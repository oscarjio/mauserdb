import { Component, OnInit, OnDestroy, AfterViewInit, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError } from 'rxjs/operators';
import { RebotlingService } from '../../services/rebotling.service';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

interface AndonStatus {
  datum: string;
  skift: string;
  ibc_idag: number;
  mal_idag: number;
  mal_pct: number;
  oee_pct: number;
  ibc_per_h: number;
  runtime_min: number;
  rasttime_min: number;
  senaste_ibc_tid: string;
  minuter_sedan_senaste_ibc: number | null;
  linje_status: string;
}

interface Stoppage {
  id: number;
  reason_name: string;
  category: string;
  duration_minutes: number;
  created_at: string;
  notes: string;
}

interface HandoverNote {
  id: number;
  note: string;
  priority: 'normal' | 'important' | 'urgent';
  op_name: string | null;
  created_at: string;
}

interface HourlyTodayEntry {
  timme: number;
  label: string;
  plan_kumulativ: number;
  faktisk_kumulativ: number | null;
}

interface HourlyTodayResponse {
  success: boolean;
  datum: string;
  mal_idag: number;
  data: HourlyTodayEntry[];
}

@Component({
  standalone: true,
  selector: 'app-andon',
  templateUrl: './andon.html',
  styleUrls: ['./andon.css'],
  imports: [CommonModule]
})
export class AndonPage implements OnInit, OnDestroy, AfterViewInit {
  Math = Math;

  private readonly apiUrl = '/noreko-backend/api.php';

  // Befintlig state
  status: AndonStatus | null = null;
  laddas = true;
  fel: string | null = null;
  klockslag = '';
  nedrakning = 10;
  isFetching = false;
  isFetchingStoppages = false;

  // Skiftbyte-notis
  private previousShift: string | null = null;
  showShiftChangeNotice = false;
  shiftChangeDate = '';

  // Feature 1: Skifttimer
  skifttimerText = '';
  skifttimerKvar = '';          // "HH:MM:SS"
  skiftProgressPct = 0;         // 0–100
  skiftStatus: 'kör' | 'slut' | 'ej_startat' = 'kör';

  // Feature 2: Senaste stopporsaker
  stoppages: Stoppage[] = [];
  stoppagesLaddas = true;

  // Feature 3: Produktionsprognos
  prognosBanner: {
    text: string;
    ibcPrognos: number;
    niva: 'rekord' | 'ok' | 'warn' | 'critical' | 'ingen';
  } = { text: '', ibcPrognos: 0, niva: 'ingen' };

  // Feature 4: Skiftöverlämningsnoter
  handoverNotes: HandoverNote[] = [];
  unreadNoteCount = 0;
  isFetchingNotes = false;
  private notesInterval: any = null;

  // Feature 5: Winning the Shift + S-kurva
  @ViewChild('cumulativeChartRef') cumulativeChartRef!: ElementRef<HTMLCanvasElement>;
  private cumulativeChart: Chart | null = null;
  hourlyTodayData: HourlyTodayEntry[] = [];
  isFetchingHourly = false;
  private hourlyInterval: any = null;

  // Feature 7: Daily Challenge
  dailyChallenge: {
    challenge: string;
    icon: string;
    target: number;
    current: number;
    progress_pct: number;
    completed: boolean;
    type: string;
  } | null = null;
  isFetchingChallenge = false;
  private challengeInterval: any = null;

  // Feature 6: Produktionstakt
  productionRate: {
    avg_ibc_per_day_7d: number;
    avg_ibc_per_day_30d: number;
    avg_ibc_per_day_90d: number;
    dag_mal: number;
  } | null = null;
  isFetchingProductionRate = false;
  private productionRateInterval: any = null;

  private destroy$ = new Subject<void>();
  private pollInterval: any = null;
  private stoppagePollInterval: any = null;
  private clockInterval: any = null;
  private skiftTimerInterval: any = null;
  private shiftNoticeTimeout: any = null;

  // ---- Visibilitychange-guard ----
  private visibilityHandler = () => this.onVisibilityChange();

  // Skifttider (06:00–22:00)
  private readonly SKIFT_START_H = 6;
  private readonly SKIFT_SLUT_H = 22;
  private readonly SKIFT_TOTAL_S = (22 - 6) * 3600; // 57600 s

  // Exponerade skifttider (används i template för nedräkningsbaren)
  readonly shiftStartTime = '06:00';
  readonly shiftEndTime = '22:00';

  // IBC-måltakt (IBC per timme för att nå dagsmålet på 16h skift)
  get targetRate(): number {
    if (!this.status || this.status.mal_idag <= 0) return 0;
    return this.status.mal_idag / 16; // 16h skift
  }

  get ibcPerHour(): number {
    return this.status ? this.status.ibc_per_h : 0;
  }

  // ─────────────────────────────────────────────
  // Feature 5: Winning the Shift — beräknade getters
  // ─────────────────────────────────────────────

  /** Antal IBC kvar att producera för att nå dagsmålet */
  get ibcKvar(): number {
    if (!this.status) return 0;
    return Math.max(0, this.status.mal_idag - this.status.ibc_idag);
  }

  /** Kvarvarande tid i skiftet i timmar (beräknat från skifttimerKvar "HH:MM:SS") */
  get skifttimerKvarH(): number {
    if (!this.skifttimerKvar || this.skiftStatus !== 'kör') return 0;
    const delar = this.skifttimerKvar.split(':');
    if (delar.length !== 3) return 0;
    const h = parseInt(delar[0], 10);
    const m = parseInt(delar[1], 10);
    const s = parseInt(delar[2], 10);
    if (isNaN(h) || isNaN(m) || isNaN(s)) return 0;
    return h + m / 60 + s / 3600;
  }

  /** Behövd takt (IBC/h) för att nå målet de resterande timmarna av skiftet */
  get behovdTakt(): number | null {
    if (this.ibcKvar <= 0) return 0;
    const kvarH = this.skifttimerKvarH;
    if (kvarH <= 0) return null; // skiftet är slut
    return Math.ceil(this.ibcKvar / kvarH);
  }

  /** Prognos: hur många IBC vid skiftslut om nuvarande takt håller */
  get prognosVidSkiftslut(): number {
    if (!this.status) return 0;
    const kvarH = this.skifttimerKvarH;
    return Math.round(this.status.ibc_idag + this.ibcPerHour * kvarH);
  }

  /** Winning-status baserat på jämförelse behövd vs faktisk takt */
  get winningStatus(): 'winning' | 'on-track' | 'behind' | 'done' {
    if (this.ibcKvar <= 0) return 'done';
    const bt = this.behovdTakt;
    if (bt === null) return 'behind'; // skiftet slut, mål ej nått
    const faktisk = this.ibcPerHour;
    if (faktisk <= 0) return 'behind';
    if (bt <= faktisk * 1.05) return 'winning';  // behöver ≤105% av nuvarande takt
    if (bt <= faktisk * 1.25) return 'on-track'; // behöver ≤125% av nuvarande takt
    return 'behind';
  }

  /** Färg för "IBC kvar"-siffran */
  get ibcKvarFarg(): string {
    switch (this.winningStatus) {
      case 'done':     return '#48bb78';
      case 'winning':  return '#48bb78';
      case 'on-track': return '#ed8936';
      case 'behind':   return '#f44336';
      default:         return '#718096';
    }
  }

  /** Färg för behövd takt */
  get behovdTaktFarg(): string {
    switch (this.winningStatus) {
      case 'done':     return '#48bb78';
      case 'winning':  return '#48bb78';
      case 'on-track': return '#ed8936';
      case 'behind':   return '#f44336';
      default:         return '#718096';
    }
  }

  /** Progress-procent mot dagsmål (0–100) */
  get progressPctMot100(): number {
    if (!this.status || this.status.mal_idag <= 0) return 0;
    return Math.min(100, Math.round((this.status.ibc_idag / this.status.mal_idag) * 100));
  }

  constructor(private http: HttpClient, private rebotlingService: RebotlingService) {}

  ngOnInit(): void {
    document.addEventListener('visibilitychange', this.visibilityHandler);

    this.uppdateraKlocka();
    this.uppdateraSkiftTimer();
    this.hamtaStatus();
    this.hamtaStoppages();
    this.hamtaHandoverNotes();
    this.hamtaHourlyToday();
    this.hamtaProductionRate();
    this.hamtaDailyChallenge();

    // Klocka + skifttimer + nedräkning uppdateras varje sekund
    this.clockInterval = setInterval(() => {
      this.uppdateraKlocka();
      if (this.nedrakning > 0) {
        this.nedrakning--;
      }
    }, 1000);

    this.skiftTimerInterval = setInterval(() => {
      this.uppdateraSkiftTimer();
    }, 1000);

    // Starta polling-timers (data-hämtning)
    this.startPollingTimers();
  }

  /** Starta polling-timers för datahämtning (10s, 30s, 60s) */
  private startPollingTimers() {
    // Statusdata var 10s
    this.pollInterval = setInterval(() => {
      this.hamtaStatus();
      this.nedrakning = 10;
    }, 10000);

    // Stopporsaker var 30s
    this.stoppagePollInterval = setInterval(() => {
      this.hamtaStoppages();
    }, 30000);

    // Skiftöverlämningsnoter var 30s
    this.notesInterval = setInterval(() => {
      this.hamtaHandoverNotes();
    }, 30000);

    // Hourly today var 60s
    this.hourlyInterval = setInterval(() => {
      this.hamtaHourlyToday();
    }, 60000);

    // Produktionstakt var 60s
    this.productionRateInterval = setInterval(() => {
      this.hamtaProductionRate();
    }, 60000);

    // Daily challenge var 60s
    this.challengeInterval = setInterval(() => {
      this.hamtaDailyChallenge();
    }, 60000);
  }

  /** Stoppa polling-timers */
  private stopPollingTimers() {
    clearInterval(this.pollInterval);
    clearInterval(this.stoppagePollInterval);
    clearInterval(this.notesInterval);
    clearInterval(this.hourlyInterval);
    clearInterval(this.productionRateInterval);
    clearInterval(this.challengeInterval);
    this.pollInterval = null;
    this.stoppagePollInterval = null;
    this.notesInterval = null;
    this.hourlyInterval = null;
    this.productionRateInterval = null;
    this.challengeInterval = null;
  }

  /** Pausa polling när tabben är dold, återuppta när synlig */
  private onVisibilityChange() {
    if (document.hidden) {
      this.stopPollingTimers();
    } else {
      // Hämta färsk data direkt vid återkomst
      this.hamtaStatus();
      this.nedrakning = 10;
      this.hamtaStoppages();
      this.hamtaHandoverNotes();
      this.hamtaHourlyToday();
      this.hamtaProductionRate();
      this.hamtaDailyChallenge();
      this.startPollingTimers();
    }
  }

  ngAfterViewInit(): void {
    // Rita chart när vy är klar — data kanske inte finns ännu, men skapar tomma chart
    this.ritaCumulativeChart();
  }

  ngOnDestroy(): void {
    document.removeEventListener('visibilitychange', this.visibilityHandler);
    this.destroy$.next();
    this.destroy$.complete();
    this.stopPollingTimers();
    if (this.clockInterval) clearInterval(this.clockInterval);
    if (this.skiftTimerInterval) clearInterval(this.skiftTimerInterval);
    if (this.shiftNoticeTimeout) clearTimeout(this.shiftNoticeTimeout);
    try { this.cumulativeChart?.destroy(); } catch (e) {}
    this.cumulativeChart = null;
  }

  // ─────────────────────────────────────────────
  // Befintliga metoder
  // ─────────────────────────────────────────────

  private uppdateraKlocka(): void {
    const nu = new Date();
    this.klockslag = nu.toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }

  hamtaStatus(): void {
    if (this.isFetching) return;
    this.isFetching = true;

    this.http.get<AndonStatus>(`${this.apiUrl}?action=andon&run=status`)
      .pipe(
        timeout(8000),
        catchError(() => {
          this.fel = 'Kunde inte hämta data från servern.';
          this.isFetching = false;
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetching = false;
        if (data) {
          // Skiftbyte-detektion
          const currentShift = data.skift;
          if (this.previousShift !== null && this.previousShift !== currentShift) {
            this.showShiftChangeNotice = true;
            this.shiftChangeDate = data.datum ? data.datum.substring(0, 10) : '';
            // Dolj notisen automatiskt efter 30 sekunder
            if (this.shiftNoticeTimeout) clearTimeout(this.shiftNoticeTimeout);
            this.shiftNoticeTimeout = setTimeout(() => { this.showShiftChangeNotice = false; }, 30000);
          }
          this.previousShift = currentShift;

          this.status = data;
          this.laddas = false;
          this.fel = null;
          this.beraknaPrognos();
        }
      });
  }

  get statusFarg(): string {
    if (!this.status) return '#888';
    if (this.status.linje_status === 'stopp') return '#f44336';
    if (this.status.linje_status === 'väntar') return '#ff9800';
    if (this.status.oee_pct < 60) return '#f44336';
    if (this.status.oee_pct < 75) return '#ff9800';
    return '#00e676';
  }

  get statusText(): string {
    if (!this.status) return '';
    if (this.status.linje_status === 'stopp') return 'STOPP';
    if (this.status.linje_status === 'väntar') return 'VÄNTAR';
    return 'KÖR';
  }

  get statusEtikett(): string {
    if (!this.status) return '';
    const min = this.status.minuter_sedan_senaste_ibc;
    const minText = min !== null && min !== undefined ? `${min} min sedan` : 'okänd tid';
    if (this.status.linje_status === 'stopp') {
      return `Senaste IBC: ${minText}`;
    }
    if (this.status.linje_status === 'väntar') {
      return `Väntar — ${minText} sedan senaste IBC`;
    }
    return `Linjen kör! Senaste IBC för ${minText}`;
  }

  get malBreddpct(): number {
    if (!this.status) return 0;
    return Math.min(100, Math.round(this.status.mal_pct));
  }

  get oeeEtikett(): string {
    if (!this.status) return '';
    if (this.status.oee_pct >= 85) return 'Utmärkt!';
    if (this.status.oee_pct >= 75) return 'Bra!';
    if (this.status.oee_pct >= 60) return 'OK';
    return 'Under mål';
  }

  // ─────────────────────────────────────────────
  // Feature 1: Skifttimer
  // ─────────────────────────────────────────────

  private uppdateraSkiftTimer(): void {
    const nu = new Date();
    const h = nu.getHours();
    const m = nu.getMinutes();
    const s = nu.getSeconds();

    // Sekunder från midnatt
    const sekundFranMidnatt = h * 3600 + m * 60 + s;
    const skiftStartSek = this.SKIFT_START_H * 3600;   // 21600
    const skiftSlutSek  = this.SKIFT_SLUT_H * 3600;    // 79200

    if (sekundFranMidnatt < skiftStartSek) {
      // Före skiftstart
      this.skiftStatus = 'ej_startat';
      const kvarTillStart = skiftStartSek - sekundFranMidnatt;
      this.skifttimerKvar = this.formatSekunder(kvarTillStart);
      this.skifttimerText = 'Startar 06:00';
      this.skiftProgressPct = 0;
    } else if (sekundFranMidnatt >= skiftSlutSek) {
      // Efter skiftslut
      this.skiftStatus = 'slut';
      this.skifttimerKvar = '00:00:00';
      this.skifttimerText = 'Skiftet är slut';
      this.skiftProgressPct = 100;
    } else {
      // Mitt i skiftet
      this.skiftStatus = 'kör';
      const gangen = sekundFranMidnatt - skiftStartSek;
      const kvar   = skiftSlutSek - sekundFranMidnatt;
      this.skifttimerKvar = this.formatSekunder(kvar);
      this.skifttimerText = 'kvar av skiftet';
      this.skiftProgressPct = Math.round((gangen / this.SKIFT_TOTAL_S) * 100);
    }
  }

  private formatSekunder(sek: number): string {
    const h = Math.floor(sek / 3600);
    const m = Math.floor((sek % 3600) / 60);
    const s = Math.floor(sek % 60);
    return [
      String(h).padStart(2, '0'),
      String(m).padStart(2, '0'),
      String(s).padStart(2, '0')
    ].join(':');
  }

  get skifttimerFarg(): string {
    if (this.skiftStatus === 'slut' || this.skiftStatus === 'ej_startat') return '#718096';
    if (this.skiftProgressPct >= 90) return '#f44336';
    if (this.skiftProgressPct >= 75) return '#ff9800';
    return '#00e676';
  }

  // ─────────────────────────────────────────────
  // Feature 2: Senaste stopporsaker
  // ─────────────────────────────────────────────

  hamtaStoppages(): void {
    if (this.isFetchingStoppages) return;
    this.isFetchingStoppages = true;

    this.http.get<{ success: boolean; stoppages: Stoppage[] }>(`${this.apiUrl}?action=andon&run=recent-stoppages`)
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetchingStoppages = false;
        this.stoppagesLaddas = false;
        if (data && data.success) {
          this.stoppages = data.stoppages;
        }
      });
  }

  stoppageFarg(category: string): string {
    switch ((category || '').toLowerCase()) {
      case 'maskin':    return '#f44336';
      case 'material':  return '#ff9800';
      case 'operatör':
      case 'operator':  return '#4299e1';
      default:          return '#718096';
    }
  }

  stoppageTidSedan(createdAt: string): string {
    const nu  = Date.now();
    const tid = new Date(createdAt).getTime();
    const diffMin = Math.max(0, Math.round((nu - tid) / 60000));

    if (diffMin < 1)  return 'Just nu';
    if (diffMin < 60) return `${diffMin} min sedan`;
    const h = Math.floor(diffMin / 60);
    const m = Math.round(diffMin % 60);
    return m > 0 ? `${h}h ${m}min sedan` : `${h}h sedan`;
  }

  // ─────────────────────────────────────────────
  // Feature 3: Produktionsprognos
  // ─────────────────────────────────────────────

  private beraknaPrognos(): void {
    if (!this.status) {
      this.prognosBanner = { text: '', ibcPrognos: 0, niva: 'ingen' };
      return;
    }

    const nu = new Date();
    const h  = nu.getHours();
    const m  = nu.getMinutes();
    const s  = nu.getSeconds();

    const sekundFranMidnatt = h * 3600 + m * 60 + s;
    const skiftStartSek = this.SKIFT_START_H * 3600;
    const skiftSlutSek  = this.SKIFT_SLUT_H * 3600;

    // Utanför skift → ingen prognos
    if (sekundFranMidnatt < skiftStartSek || sekundFranMidnatt >= skiftSlutSek) {
      this.prognosBanner = { text: '', ibcPrognos: 0, niva: 'ingen' };
      return;
    }

    const tidGangenH = (sekundFranMidnatt - skiftStartSek) / 3600;
    const tidKvarH   = (skiftSlutSek - sekundFranMidnatt) / 3600;

    // Visa inte prognos förrän minst 1h gått
    if (tidGangenH < 1) {
      this.prognosBanner = { text: '', ibcPrognos: 0, niva: 'ingen' };
      return;
    }

    const ibcHittills = this.status.ibc_idag;
    const mal         = this.status.mal_idag;

    const taktPerH    = ibcHittills / tidGangenH;
    const prognosIbc  = taktPerH * tidKvarH;
    const slutPrognos = Math.round(ibcHittills + prognosIbc);
    const slutPct     = mal > 0 ? (slutPrognos / mal) * 100 : 0;

    let text: string;
    let niva: 'rekord' | 'ok' | 'warn' | 'critical';

    if (slutPct >= 110) {
      text = `Rekorddag i sikte! Prognos: ${slutPrognos} IBC`;
      niva = 'rekord';
    } else if (slutPct >= 100) {
      text = `På väg att nå målet! Prognos: ${slutPrognos} IBC`;
      niva = 'ok';
    } else if (slutPct >= 80) {
      text = `Justera takten. Prognos: ${slutPrognos} IBC av mål ${mal}`;
      niva = 'warn';
    } else {
      text = `Takten behöver ökas. Prognos: ${slutPrognos} IBC av mål ${mal}`;
      niva = 'critical';
    }

    this.prognosBanner = { text, ibcPrognos: slutPrognos, niva };
  }

  get prognosBannerFarg(): string {
    switch (this.prognosBanner.niva) {
      case 'rekord':   return '#22543d';   // mörkgrön
      case 'ok':       return '#1a365d';   // mörkblå
      case 'warn':     return '#7b341e';   // mörkrange
      case 'critical': return '#742a2a';   // mörkröd
      default:         return 'transparent';
    }
  }

  get prognosBannerBord(): string {
    switch (this.prognosBanner.niva) {
      case 'rekord':   return '#48bb78';
      case 'ok':       return '#4299e1';
      case 'warn':     return '#ed8936';
      case 'critical': return '#f44336';
      default:         return 'transparent';
    }
  }

  get prognosBannerIkon(): string {
    switch (this.prognosBanner.niva) {
      case 'rekord':   return '🚀';
      case 'ok':       return '✅';
      case 'warn':     return '⚠️';
      case 'critical': return '🔴';
      default:         return '';
    }
  }

  // ─────────────────────────────────────────────
  // Feature 4: Skiftöverlämningsnoter
  // ─────────────────────────────────────────────

  hamtaHandoverNotes(): void {
    if (this.isFetchingNotes) return;
    this.isFetchingNotes = true;

    this.http.get<{ success: boolean; notes: HandoverNote[]; unread_count: number }>(
      `${this.apiUrl}?action=andon&run=andon-notes`
    )
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetchingNotes = false;
        if (data && data.success) {
          this.handoverNotes = data.notes;
          this.unreadNoteCount = data.unread_count ?? data.notes.length;
        }
      });
  }

  /** Returnerar true om det finns minst en 'urgent'-not */
  get hasUrgentNotes(): boolean {
    return this.handoverNotes.some(n => n.priority === 'urgent');
  }

  notePrioritetFarg(priority: string): string {
    switch (priority) {
      case 'urgent':    return '#f44336';
      case 'important': return '#ff9800';
      default:          return '#4a5568';
    }
  }

  notePrioritetEtikett(priority: string): string {
    switch (priority) {
      case 'urgent':    return 'BRÅDSKANDE';
      case 'important': return 'VIKTIG';
      default:          return '';
    }
  }

  timeAgo(dateStr: string): string {
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just nu';
    if (mins < 60) return `${mins} min sedan`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours} h sedan`;
    return `${Math.floor(hours / 24)} dagar sedan`;
  }

  // ─────────────────────────────────────────────
  // Feature 5: Kumulativ dagskurva (S-kurva)
  // ─────────────────────────────────────────────

  hamtaHourlyToday(): void {
    if (this.isFetchingHourly) return;
    this.isFetchingHourly = true;

    this.http.get<HourlyTodayResponse>(`${this.apiUrl}?action=andon&run=hourly-today`)
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetchingHourly = false;
        if (data && data.success) {
          this.hourlyTodayData = data.data;
          this.uppdateraCumulativeChart();
        }
      });
  }

  private ritaCumulativeChart(): void {
    if (!this.cumulativeChartRef?.nativeElement) return;

    try { this.cumulativeChart?.destroy(); } catch (e) {}
    this.cumulativeChart = null;

    const ctx = this.cumulativeChartRef.nativeElement.getContext('2d');
    if (!ctx) return;

    const labels = this.hourlyTodayData.map(d => d.label);
    const planData = this.hourlyTodayData.map(d => d.plan_kumulativ);
    const faktiskData = this.hourlyTodayData.map(d => d.faktisk_kumulativ);

    this.cumulativeChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels.length > 0 ? labels : ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'],
        datasets: [
          {
            label: 'Planerat',
            data: planData,
            borderColor: '#4299e1',
            borderDash: [6, 4],
            borderWidth: 2,
            pointRadius: 0,
            tension: 0.1,
            fill: false,
          },
          {
            label: 'Faktisk',
            data: faktiskData,
            borderColor: '#48bb78',
            borderWidth: 2.5,
            pointRadius: 3,
            pointBackgroundColor: '#48bb78',
            tension: 0.2,
            fill: false,
            spanGaps: false,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            align: 'end',
            labels: {
              color: '#a0aec0',
              font: { size: 11 },
              boxWidth: 18,
              padding: 10,
            }
          },
          tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: '#1a202c',
            borderColor: '#2d3748',
            borderWidth: 1,
            titleColor: '#e2e8f0',
            bodyColor: '#a0aec0',
            callbacks: {
              label: (ctx) => {
                const val = ctx.raw as number | null;
                if (val === null || val === undefined) return '';
                return `${ctx.dataset.label}: ${val} IBC`;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: {
              color: '#718096',
              font: { size: 10 },
              maxRotation: 0,
              autoSkip: true,
              maxTicksLimit: 9,
            },
            grid: {
              color: '#2d374844',
            }
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#718096',
              font: { size: 11 },
              stepSize: undefined,
            },
            grid: {
              color: '#2d374844',
            }
          }
        }
      }
    });
  }

  private uppdateraCumulativeChart(): void {
    if (!this.cumulativeChart) {
      this.ritaCumulativeChart();
      return;
    }

    const labels = this.hourlyTodayData.map(d => d.label);
    const planData = this.hourlyTodayData.map(d => d.plan_kumulativ);
    const faktiskData = this.hourlyTodayData.map(d => d.faktisk_kumulativ);

    this.cumulativeChart.data.labels = labels;
    this.cumulativeChart.data.datasets[0].data = planData;
    this.cumulativeChart.data.datasets[1].data = faktiskData;
    this.cumulativeChart.update('none');
  }

  // ─────────────────────────────────────────────
  // Feature 6: Produktionstakt
  // ─────────────────────────────────────────────

  hamtaProductionRate(): void {
    if (this.isFetchingProductionRate) return;
    this.isFetchingProductionRate = true;

    this.rebotlingService.getProductionRate()
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetchingProductionRate = false;
        if (data && data.success && data.data) {
          this.productionRate = data.data;
        }
      });
  }

  /** Färg baserat på hur nära target snitt7d ligger */
  get productionRateColor(): string {
    if (!this.productionRate) return '#718096';
    const pct = this.productionRate.dag_mal > 0
      ? (this.productionRate.avg_ibc_per_day_7d / this.productionRate.dag_mal) * 100
      : 0;
    if (pct >= 90) return '#48bb78';
    if (pct >= 70) return '#ed8936';
    return '#f44336';
  }

  get productionRatePct(): number {
    if (!this.productionRate || this.productionRate.dag_mal <= 0) return 0;
    return Math.min(100, Math.round((this.productionRate.avg_ibc_per_day_7d / this.productionRate.dag_mal) * 100));
  }

  // ─────────────────────────────────────────────
  // Feature 7: Daily Challenge
  // ─────────────────────────────────────────────

  hamtaDailyChallenge(): void {
    if (this.isFetchingChallenge) return;
    this.isFetchingChallenge = true;

    this.http.get<any>(`${this.apiUrl}?action=andon&run=daily-challenge`)
      .pipe(
        timeout(8000),
        catchError(() => of(null)),
        takeUntil(this.destroy$)
      )
      .subscribe(data => {
        this.isFetchingChallenge = false;
        if (data && data.success) {
          this.dailyChallenge = {
            challenge: data.challenge,
            icon: data.icon,
            target: data.target,
            current: data.current,
            progress_pct: data.progress_pct,
            completed: data.completed,
            type: data.type,
          };
        }
      });
  }

  get challengeStatusClass(): string {
    if (!this.dailyChallenge) return '';
    if (this.dailyChallenge.completed) return 'challenge-done';
    if (this.dailyChallenge.progress_pct >= 75) return 'challenge-close';
    return 'challenge-active';
  }

  // ─────────────────────────────────────────────
  // Skiftbyte-notis
  // ─────────────────────────────────────────────

  dismissShiftNotice() {
    this.showShiftChangeNotice = false;
  }

  openShiftReport() {
    this.showShiftChangeNotice = false;
    window.open('/rebotling-skiftrapport', '_blank');
  }
  trackByIndex(index: number): number { return index; }
}
