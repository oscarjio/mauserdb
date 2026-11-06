import { Component, OnInit, OnDestroy, ElementRef, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject, of, forkJoin } from 'rxjs';
import { catchError, timeout, takeUntil } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import {
  StatusDag, EffektivitetTyp, PeriodSumma,
  perioder, summera, manadsserie, braDagar, dagserie, isoWeek, manadNamn, addDays,
} from '../../utils/statusvy';

type Linje = 'tvattlinje' | 'rebotling';
type ScenKey = 'idag' | 'vecka' | 'forraVecka' | 'manad' | 'forraManad' | 'rullande12' | 'bastaDag';

interface StatusvySvar {
  linje: Linje; idag: string; senasteData: string | null; fran: string; dataFran: string | null;
  effektivitetTyp: EffektivitetTyp; dagar: StatusDag[];
}

/** En linjes innehåll i en historikscen: ett huvudvärde, högst två korta fakta, högst en jämförelse, högst en graf. */
export interface ScenLinje {
  huvudEtikett: string;
  huvudVarde: string;
  /** En kort faktarad under värdet. */
  rad1: string | null;
  /** Andra raden: en jämförelse (grön pil bara vid verklig förbättring) eller en datanot (bärnsten). */
  rad2: { text: string; forbattring: boolean; datanot: boolean } | null;
  graf: { staplar: (number | null)[]; markerad: number | null; etiketter?: [string, string] } | null;
  tom: string | null;
}

export interface Scen { key: ScenKey; knapp: string; titel: string; intervall: string; pagar: boolean; linjer: Record<Linje, ScenLinje>; }

interface Panel {
  huvud: string;
  huvudEtikett: string;
  ibcIdag: string;
  ibcEtikett: string;
  jamfor: { text: string; forbattring: boolean } | null;
  dataTid: string;
  dataProblem: boolean;
  tom: boolean;
}

const LINJER: { linje: Linje; namn: string }[] = [
  { linje: 'tvattlinje', namn: 'Tvättlinje' },
  { linje: 'rebotling', namn: 'Rebotling' },
];
const UPPDATERA_MS = 60_000;
const SCEN_MS = 30_000;
const INAKTUELL_MS = 5 * 60_000;
/** Förbättring markeras bara när takten är minst så här mycket bättre (brus annars). */
const FORBATTRING_TROSKEL = 0.03;
/** Dagens takt jämförs först när dagen har minst så mycket drifttid. */
const MIN_DRIFT_JAMFOR = 60;

/**
 * Statusvy — lugn produktionstavla för TV (Tvättlinje + Rebotling).
 * Endast lagnivå och aggregat (inga personer), kräver inloggning (authGuard + backend).
 * Varje linje mäts med sin egen definition och jämförs bara med sig själv.
 */
@Component({
  selector: 'app-statusvy',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './statusvy.html',
  styleUrl: './statusvy.css',
})
export class StatusvyPage implements OnInit, OnDestroy {
  readonly linjer = LINJER;
  svar: Record<Linje, StatusvySvar | null> = { tvattlinje: null, rebotling: null };
  fel: Record<Linje, boolean> = { tvattlinje: false, rebotling: false };
  paneler: Record<Linje, Panel | null> = { tvattlinje: null, rebotling: null };
  scener: Scen[] = [];
  scenIndex = 0;
  pausad = false;
  laddar = true;
  senastHamtad: number | null = null;
  senasteForsokFel = false;
  helskarm = false;
  nu = Date.now();

  private destroy$ = new Subject<void>();
  private timers: ReturnType<typeof setInterval>[] = [];

  constructor(private http: HttpClient, private host: ElementRef<HTMLElement>) {}

  ngOnInit(): void {
    this.hamta();
    this.timers.push(setInterval(() => this.hamta(), UPPDATERA_MS));
    this.timers.push(setInterval(() => { if (!this.pausad && this.scener.length) this.scenIndex = (this.scenIndex + 1) % this.scener.length; }, SCEN_MS));
    this.timers.push(setInterval(() => { this.nu = Date.now(); }, 15_000));
  }

  ngOnDestroy(): void {
    this.destroy$.next(); this.destroy$.complete();
    this.timers.forEach(t => clearInterval(t));
  }

  // ---------------- data ----------------
  private hamta(): void {
    const url = (l: Linje) => `${environment.apiUrl}?action=${l}&run=statusvy-dagar`;
    const get = (l: Linje) => this.http.get<{ success: boolean; data?: StatusvySvar }>(url(l), { withCredentials: true })
      .pipe(timeout(30000), catchError(() => of(null)));
    forkJoin({ tvattlinje: get('tvattlinje'), rebotling: get('rebotling') })
      .pipe(takeUntil(this.destroy$))
      .subscribe(res => {
        this.laddar = false;
        let allaOk = true;
        for (const { linje } of LINJER) {
          const r = res[linje];
          if (r?.success && r.data) { this.svar[linje] = r.data; this.fel[linje] = false; }
          else { this.fel[linje] = true; allaOk = false; } // behåll senast hämtade data
        }
        this.senasteForsokFel = !allaOk;
        if (allaOk) this.senastHamtad = Date.now();
        else if (LINJER.some(l => !this.fel[l.linje])) this.senastHamtad = this.senastHamtad ?? Date.now();
        this.nu = Date.now();
        this.bygg();
      });
  }

  private bygg(): void {
    for (const { linje } of LINJER) this.paneler[linje] = this.byggPanel(linje);
    const idag = this.idag;
    if (!idag) { this.scener = []; return; }
    const p = Object.fromEntries(perioder(idag).map(x => [x.key, x]));
    // Startdatumet får år när intervallet korsar ett årsskifte (annars tvetydigt: "7 nov – 6 nov 2026").
    const ord = (fran: string, till: string) =>
      `${this.fmtDatum(fran, fran.slice(0, 4) !== till.slice(0, 4))} – ${this.fmtDatum(till, true)}`;
    const scen = (key: ScenKey, knapp: string, titel: string, intervall: string, pagar: boolean,
                  f: (l: Linje, d: StatusvySvar) => ScenLinje): Scen => ({
      key, knapp, titel, intervall, pagar,
      linjer: Object.fromEntries(LINJER.map(({ linje }) => [linje, this.svar[linje] ? f(linje, this.svar[linje]!) : this.saknas()])) as Record<Linje, ScenLinje>,
    });

    this.scener = [
      scen('idag', 'Idag', 'Idag', this.fmtDatum(idag, true), true, (_, d) => {
        const s = summera(d.dagar, idag, idag, d.effektivitetTyp);
        const tom = s.dagarMedData === 0;
        return {
          huvudEtikett: 'Drifttid idag', huvudVarde: this.fmtTid(s.drifttidMin),
          rad1: `Driftstopp ${this.fmtTid(s.driftstoppMin)}`,
          rad2: null, graf: null, tom: tom ? 'Ingen produktion rapporterad idag' : null,
        };
      }),
      scen('vecka', 'Denna vecka', `Denna vecka · v. ${isoWeek(idag)}`, ord(p['vecka'].fran, p['vecka'].till), true, (_, d) =>
        this.periodScen(d, p['vecka'].fran, p['vecka'].till, p['vecka'].jamfor!, true, addDays(p['vecka'].fran, 6))),
      scen('forraVecka', 'Förra veckan', `Förra veckan · v. ${isoWeek(p['forraVecka'].fran)}`, ord(p['forraVecka'].fran, p['forraVecka'].till), false, (_, d) =>
        this.periodScen(d, p['forraVecka'].fran, p['forraVecka'].till, null, false, p['forraVecka'].till)),
      scen('manad', 'Denna månad', `Denna månad · ${manadNamn(idag)}`, ord(p['manad'].fran, p['manad'].till), true, (_, d) =>
        this.periodScen(d, p['manad'].fran, p['manad'].till, p['manad'].jamfor!, true, null)),
      scen('forraManad', 'Förra månaden', `Förra månaden · ${manadNamn(p['forraManad'].fran)}`, ord(p['forraManad'].fran, p['forraManad'].till), false, (_, d) =>
        this.periodScen(d, p['forraManad'].fran, p['forraManad'].till, null, false, null)),
      scen('rullande12', '12 månader', 'Rullande 12 månader', ord(p['rullande12'].fran, p['rullande12'].till), false, (_, d) =>
        this.rullandeScen(d, p['rullande12'].fran)),
      scen('bastaDag', 'Bästa dag', 'Bästa dag · senaste 12 månaderna', 'Avslutade dagar med minst 4 h drifttid', false, (_, d) =>
        this.bastaDagScen(d)),
    ];
    if (this.scenIndex >= this.scener.length) this.scenIndex = 0;
  }

  private byggPanel(linje: Linje): Panel | null {
    const d = this.svar[linje];
    if (!d) return null;
    const typ = d.effektivitetTyp;
    const idagS = summera(d.dagar, d.idag, d.idag, typ);
    const p = perioder(d.idag).find(x => x.key === 'forraVecka')!;
    const fv = summera(d.dagar, p.fran, p.till, typ);
    let jamfor: Panel['jamfor'] = null;
    if (fv.taktPerTimme !== null) {
      const forbattring = idagS.taktPerTimme !== null && (idagS.drifttidMin ?? 0) >= MIN_DRIFT_JAMFOR
        && idagS.taktPerTimme >= fv.taktPerTimme * (1 + FORBATTRING_TROSKEL);
      jamfor = { text: `Förra veckan ${this.fmtTakt(fv.taktPerTimme)} per drifttimme`, forbattring };
    }
    const senaste = d.senasteData;
    const idagRegistrerat = !!senaste && senaste.slice(0, 10) === d.idag;
    return {
      huvud: this.fmtTakt(idagS.taktPerTimme),
      huvudEtikett: typ === 'signerad' ? 'IBC per drifttimme idag' : 'Godkända IBC per drifttimme idag',
      ibcIdag: this.fmtAntal(idagS.ibc),
      ibcEtikett: typ === 'signerad' ? 'IBC tvättade idag' : 'Godkända IBC idag',
      jamfor,
      dataTid: idagRegistrerat
        ? `Rapporterat idag t.o.m. ${senaste!.slice(11, 16)}`
        : senaste ? `Ingen registrering idag · senast ${this.fmtDatum(senaste.slice(0, 10), false)} ${senaste.slice(11, 16)}` : 'Ingen registrering',
      dataProblem: this.fel[linje],
      tom: idagS.dagarMedData === 0,
    };
  }

  private periodScen(d: StatusvySvar, fran: string, till: string,
                     jamfor: { fran: string; till: string; label: string } | null, pagar: boolean, grafTill: string | null): ScenLinje {
    const typ = d.effektivitetTyp;
    const s = summera(d.dagar, fran, till, typ);
    const serie = dagserie(d.dagar, fran, grafTill ?? till);
    let j: ScenLinje['rad2'] = null;
    if (jamfor) {
      const js = summera(d.dagar, jamfor.fran, jamfor.till, typ);
      if (js.taktPerTimme !== null) {
        const forbattring = s.taktPerTimme !== null && s.taktPerTimme >= js.taktPerTimme * (1 + FORBATTRING_TROSKEL);
        j = { text: `${jamfor.label.charAt(0).toUpperCase()}${jamfor.label.slice(1)}: ${this.fmtTakt(js.taktPerTimme)} / h`, forbattring, datanot: false };
      }
    }
    return {
      huvudEtikett: typ === 'signerad' ? 'IBC tvättade' : 'Godkända IBC',
      huvudVarde: this.fmtAntal(s.ibc),
      rad1: this.periodFakta(s, typ),
      rad2: j ?? this.tackningsNot(d, fran),
      graf: { staplar: serie.map(x => x.ibc), markerad: pagar ? serie.findIndex(x => x.dag === d.idag) : null,
              etiketter: [this.fmtDatum(serie[0].dag, false), this.fmtDatum(serie[serie.length - 1].dag, false)] },
      tom: s.dagarMedData === 0 ? 'Ingen produktion rapporterad under perioden' : null,
    };
  }

  private rullandeScen(d: StatusvySvar, fran: string): ScenLinje {
    const typ = d.effektivitetTyp;
    const s = summera(d.dagar, fran, d.idag, typ);
    const man = manadsserie(d.dagar, d.idag, typ);
    return {
      huvudEtikett: typ === 'signerad' ? 'IBC tvättade' : 'Godkända IBC',
      huvudVarde: this.fmtAntal(s.ibc),
      rad1: this.periodFakta(s, typ),
      rad2: this.tackningsNot(d, fran),
      graf: { staplar: man.map(m => m.summa.ibc), markerad: man.length - 1,
              etiketter: [man[0].label, man[man.length - 1].label] },
      tom: s.dagarMedData === 0 ? 'Ingen produktion rapporterad under perioden' : null,
    };
  }

  private bastaDagScen(d: StatusvySvar): ScenLinje {
    const b = braDagar(d.dagar, d.idag, 240, 1);
    const topp = b.flestIbc[0];
    const takt = b.hogstTakt[0];
    if (!topp) {
      return { huvudEtikett: 'Flest IBC på en dag', huvudVarde: '—', rad1: null, rad2: null, graf: null,
               tom: 'För lite underlag ännu' };
    }
    return {
      huvudEtikett: d.effektivitetTyp === 'signerad' ? 'Flest IBC tvättade, en dag' : 'Flest godkända IBC, en dag',
      huvudVarde: this.fmtAntal(topp.ibc),
      rad1: this.fmtDatum(topp.dag, true, true),
      rad2: takt ? { text: `Högst takt ${this.fmtTakt(takt.taktPerTimme)} / drifttimme · ${this.fmtDatum(takt.dag, false)}`, forbattring: false, datanot: false } : null,
      graf: null, tom: null,
    };
  }

  /** "21,8 / drifttimme · +23 % mot måltakt" (tvätt) eller "… · 76 % av måltakt" (rebotling). */
  private periodFakta(s: PeriodSumma, typ: EffektivitetTyp): string | null {
    const ut: string[] = [];
    if (s.taktPerTimme !== null) ut.push(`${this.fmtTakt(s.taktPerTimme)} / drifttimme`);
    if (s.effektivitet !== null) ut.push(`${this.fmtEff(s.effektivitet, typ)} ${typ === 'signerad' ? 'mot' : 'av'} måltakt`);
    return ut.length ? ut.join(' · ') : null;
  }

  /** Bärnstensnot när perioden börjar före linjens första data enligt definitionen. */
  private tackningsNot(d: StatusvySvar, fran: string): ScenLinje['rad2'] {
    if (!d.dataFran || d.dataFran <= fran) return null;
    return { text: `Data finns från ${this.fmtDatum(d.dataFran, true)}`, forbattring: false, datanot: true };
  }

  private saknas(): ScenLinje {
    return { huvudEtikett: '', huvudVarde: '—', rad1: null, rad2: null, graf: null, tom: 'Data saknas just nu' };
  }

  // ---------------- kontroller ----------------
  get scen(): Scen | null { return this.scener[this.scenIndex] ?? null; }

  valjScen(i: number): void { this.scenIndex = i; this.pausad = true; }
  vaxlaPaus(): void { this.pausad = !this.pausad; }

  vaxlaHelskarm(): void {
    if (!document.fullscreenElement) this.host.nativeElement.requestFullscreen?.().catch(() => {});
    else document.exitFullscreen?.().catch(() => {});
  }

  @HostListener('document:fullscreenchange')
  onFullscreen(): void { this.helskarm = !!document.fullscreenElement; }

  @HostListener('document:keydown', ['$event'])
  onKey(e: KeyboardEvent): void {
    if (e.key === ' ') { e.preventDefault(); this.vaxlaPaus(); }
    else if (e.key === 'ArrowRight' && this.scener.length) this.valjScen((this.scenIndex + 1) % this.scener.length);
    else if (e.key === 'ArrowLeft' && this.scener.length) this.valjScen((this.scenIndex - 1 + this.scener.length) % this.scener.length);
  }

  // ---------------- status / tid ----------------
  get idag(): string | null { return this.svar.tvattlinje?.idag ?? this.svar.rebotling?.idag ?? null; }

  get rubrikDatum(): string {
    const idag = this.idag;
    if (!idag) return '';
    const s = new Date(`${idag}T12:00:00`).toLocaleDateString('sv-SE', { weekday: 'long', day: 'numeric', month: 'long' });
    return `${s.charAt(0).toUpperCase()}${s.slice(1)} · vecka ${isoWeek(idag)}`;
  }

  /** Senaste tidpunkt idag som data faktiskt gäller (senaste registrering på någon linje). */
  get dataGaller(): string {
    const idag = this.idag;
    const t = LINJER.map(l => this.svar[l.linje]?.senasteData).filter((x): x is string => !!x && x.slice(0, 10) === idag).sort().pop();
    return t ? `Data t.o.m. ${t.slice(11, 16)}` : 'Ingen registrering idag';
  }

  get anslutning(): 'ok' | 'inaktuell' | 'fel' | 'laddar' {
    if (this.laddar) return 'laddar';
    if (this.senastHamtad === null) return 'fel';
    if (this.nu - this.senastHamtad > INAKTUELL_MS || this.senasteForsokFel) return 'inaktuell';
    return 'ok';
  }

  get anslutningText(): string {
    const t = this.senastHamtad ? new Date(this.senastHamtad).toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' }) : null;
    switch (this.anslutning) {
      case 'laddar': return 'Hämtar…';
      case 'fel': return 'Ingen anslutning';
      case 'inaktuell': return t ? `Hämtad ${t} · kan vara inaktuell` : 'Kan vara inaktuell';
      default: return `Hämtad ${t}`;
    }
  }

  // ---------------- formatering ("—" = saknas, aldrig 0) ----------------
  fmtAntal(v: number | null | undefined): string { return v == null ? '—' : Math.round(v).toLocaleString('sv-SE'); }
  fmtTakt(v: number | null | undefined): string { return v == null ? '—' : v.toFixed(1).replace('.', ','); }
  fmtTid(v: number | null | undefined): string {
    if (v == null) return '—';
    if (v >= 600) return `${Math.round(v / 60).toLocaleString('sv-SE')} h`;
    const h = Math.floor(v / 60), m = Math.round(v % 60);
    return h > 0 ? `${h} h ${m} min` : `${m} min`;
  }
  fmtEff(v: number, typ: EffektivitetTyp): string {
    const pct = Math.round(v * 100);
    if (typ === 'kvot') return `${pct} %`;
    return pct === 0 ? '±0 %' : pct > 0 ? `+${pct} %` : `−${Math.abs(pct)} %`;
  }
  fmtDatum(s: string, medAr: boolean, medVeckodag = false): string {
    const d = new Date(`${s}T12:00:00`);
    return d.toLocaleDateString('sv-SE', { ...(medVeckodag ? { weekday: 'long' } : {}), day: 'numeric', month: 'short', ...(medAr ? { year: 'numeric' } : {}) });
  }

  // ---------------- graf (enkel SVG) ----------------
  stapelBredd(n: number): number { return 100 / Math.max(n, 1); }
  stapelHojd(v: number | null, staplar: (number | null)[]): number {
    const max = Math.max(...staplar.map(x => x ?? 0));
    return v == null || max <= 0 ? 0 : (v / max) * 100;
  }
  trackLinje = (_: number, l: { linje: Linje }) => l.linje;
  trackIndex = (i: number) => i;
}
