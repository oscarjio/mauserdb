import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';

interface ToolCard {
  title: string;
  desc: string;
  route: string;
  icon: string;
  color: string;
}

@Component({
  standalone: true,
  selector: 'app-operator-prestation',
  imports: [CommonModule, RouterModule],
  templateUrl: './operator-prestation.html',
  styleUrl: './operator-prestation.css'
})
export class OperatorPrestationPage {
  groups: { label: string; tools: ToolCard[] }[] = [
    {
      label: 'Träning & Uppföljning',
      tools: [
        { title: 'Stjärnoperatörer', desc: 'Sammansatt poäng (fart, trend, mångsidighet, kassation) — rankar alla operatörer och identifierar nyckelspelare, dolda talanger och vem som behöver stöd', route: '/rebotling/stjarnoperatorer', icon: 'fas fa-star', color: '#f6c90e' },
        { title: 'Tränarvy', desc: 'Prioriterad coachingöversikt — vem behöver stöd, beröm eller bevakning?', route: '/rebotling/coach-view', icon: 'fas fa-chalkboard-teacher', color: '#a78bfa' },
        { title: 'Operatörsutveckling', desc: '12 månaders IBC/h-sparkline per operatör — se vem som förbättrar sig, är stabil eller försämras långsiktigt', route: '/rebotling/operator-utveckling', icon: 'fas fa-chart-line', color: '#a78bfa' },
        { title: 'Milstolpar & Karriärrekord', desc: 'Karriär-IBC, personliga rekord och progress mot nästa milstolpe per operatör — från Lärling till Hall of Fame', route: '/rebotling/milstolpar', icon: 'fas fa-medal', color: '#f6c90e' },
        { title: 'Korsträningsplan', desc: 'Vilka operatörer behöver träna på vilka positioner? Prioriterad lista baserad på teamets täckningsbehov och operatörens potential — identifiera SPOF-risker och träningskandidater', route: '/rebotling/korstraning', icon: 'fas fa-people-arrows', color: '#a78bfa' },
      ]
    },
    {
      label: 'Ranking & Jämförelse',
      tools: [
        { title: 'Operatörsranking', desc: 'Ranking på IBC/h vs teamsnitt', route: '/rebotling/operator-scores', icon: 'fas fa-star', color: '#68d391' },
        { title: 'Periodanalys A/B', desc: 'Jämför två perioder sida vid sida', route: '/rebotling/operator-analys', icon: 'fas fa-balance-scale', color: '#63b3ed' },
        { title: 'Operatörsjämförelse', desc: 'Jämför valfria operatörer', route: '/rebotling/operator-compare', icon: 'fas fa-exchange-alt', color: '#63b3ed' },
        { title: 'Schemaläggningsmatris', desc: 'Bästa operatör per position', route: '/rebotling/operator-matcher', icon: 'fas fa-th', color: '#63b3ed' },
        { title: 'Ranking-historik', desc: 'Veckovis rankningstrend per operatör — bump-diagram som visar vem som klättrar, placeringsändringar senaste veckan och pågående vinstserier', route: '/rebotling/ranking-historik', icon: 'fas fa-chart-bar', color: '#68d391' },
        { title: 'Skiftduell', desc: 'Välj två operatörer — se alla deras enskilda skift på en gemensam tidslinje med 7-skift glidande snitt, bästa-skift-jämförelse och samarbetseffekt när de jobbat ihop', route: '/rebotling/skift-duell', icon: 'fas fa-swords', color: '#f6c90e' },
      ]
    },
    {
      label: 'Djupanalys',
      tools: [
        { title: 'Skift-DNA', desc: 'Feed med alla senaste skift', route: '/rebotling/shift-dna', icon: 'fas fa-dna', color: '#f6ad55' },
        { title: 'Skift-insikt', desc: 'Slå upp ett specifikt skift — fullständig analys per datum eller skiftnummer', route: '/rebotling/skift-insikt', icon: 'fas fa-search', color: '#63b3ed' },
        { title: 'Skiftlogg', desc: 'Filtrerbar historik: sök skift på operatör, produkt och datum — sortera på IBC/h, kassation eller stopptid, exportera CSV', route: '/rebotling/skift-logg', icon: 'fas fa-list-alt', color: '#63b3ed' },
        { title: 'Skiftavvikelser', desc: 'Statistisk avvikelsedetektering — flaggar skift som avviker >1.5σ från normalvärdet för IBC/h, kassation eller stoppgrad. Visar operatör- och produktmönster i avvikelserna.', route: '/rebotling/skift-avvikelser', icon: 'fas fa-bell', color: '#fc8181' },
        { title: 'Trendkarta', desc: 'Värmekarta IBC/h per vecka', route: '/rebotling/operator-trend-heatmap', icon: 'fas fa-fire', color: '#fc8181' },
        { title: 'Prestandakarta', desc: 'Scatter IBC/h per operatör', route: '/rebotling/operator-performance-map', icon: 'fas fa-crosshairs', color: '#f6ad55' },
        { title: 'Prestanda per veckodag', desc: 'Vilken dag presterar bäst?', route: '/rebotling/operator-veckodag', icon: 'fas fa-calendar-day', color: '#f6ad55' },
        { title: 'Operatörsaktivitet', desc: 'Närvaro och aktivitetstrender', route: '/rebotling/operator-aktivitet', icon: 'fas fa-user-clock', color: '#63b3ed' },
        { title: 'Inlärningskurva', desc: 'Prestanda över tid för nya', route: '/rebotling/operator-inlarning', icon: 'fas fa-graduation-cap', color: '#63b3ed' },
        { title: 'Prestandavarning', desc: 'Flaggar operatörer med trend ned', route: '/rebotling/operator-varning', icon: 'fas fa-exclamation-triangle', color: '#fc8181' },
        { title: 'Positionsspecialisering', desc: 'Operatörens IBC/h per position normaliserat mot deras eget totalsnitt — identifiera specialist vs generalist och optimal positionsplacering', route: '/rebotling/positions-specialisering', icon: 'fas fa-map-pin', color: '#f6ad55' },
        { title: 'Produktnormaliserad Prestanda', desc: 'Ranking justerad för produktmix — vem är egentligen snabbast oberoende av vilka produkter de jobbat med? Jämför rå ranking vs produktkompenserad ranking och identifiera vem som gynnas av lätta produkter', route: '/rebotling/produkt-normaliserad', icon: 'fas fa-balance-scale', color: '#76e4f7' },
        { title: 'Kompetensmatris', desc: 'Operatör × position-matris — IBC/h-tier per position för schemaläggning och korsträning', route: '/rebotling/kompetens-matris', icon: 'fas fa-th', color: '#f6ad55' },
        { title: 'Teamkemi', desc: 'Vilka operatörer presterar bättre ihop?', route: '/rebotling/operator-synergy', icon: 'fas fa-heart', color: '#fc8181' },
        { title: 'Produktionsintensitet', desc: 'Daglig IBC/h-heatmap (GitHub-stil)', route: '/rebotling/produktion-heatmap', icon: 'fas fa-th', color: '#63b3ed' },
        { title: 'Skifttyp-analys', desc: 'IBC/h per dag-, kväll- och nattskift', route: '/rebotling/operator-skifttyp', icon: 'fas fa-clock', color: '#a78bfa' },
        { title: 'Veckovis trend', desc: 'IBC/h per vecka vs lagsnitt + prognos', route: '/rebotling/operator-trend', icon: 'fas fa-chart-line', color: '#63b3ed' },
        { title: 'Skiftmomentum', desc: 'Löpande form — streak av skift över/under snitt', route: '/rebotling/operator-momentum', icon: 'fas fa-bolt', color: '#f6ad55' },
        { title: 'Konsistensanalys', desc: 'Vem är mest pålitlig skift för skift? (CV)', route: '/rebotling/operator-konsistens', icon: 'fas fa-ruler', color: '#a78bfa' },
        { title: 'Operatörsvärde', desc: 'Hur påverkas IBC/h när operatören är frånvarande?', route: '/rebotling/operator-avsaknad', icon: 'fas fa-user-minus', color: '#fc8181' },
        { title: 'Rastanalys', desc: 'Rastmönster per skift — korrelation med IBC/h och veckovis trend', route: '/rebotling/rast-analys', icon: 'fas fa-coffee', color: '#f6ad55' },
        { title: 'Produktionsrytm', desc: 'Heatmap: IBC/h per skifttyp × veckodag — vilka slot är systemiskt starka/svaga?', route: '/rebotling/produktionsrytm', icon: 'fas fa-grip-horizontal', color: '#63b3ed' },
        { title: 'Fart–Kvalitet-Korrelation', desc: 'Scatter av alla skift: IBC/h vs kassation — finns en avvägning mellan takt och kvalitet?', route: '/rebotling/fart-kvalitet', icon: 'fas fa-project-diagram', color: '#76e4f7' },
        { title: 'Stopptidsmönster', desc: 'Stopptidens fördelning per skifttyp × veckodag — identifiera mönster för underhållsplanering', route: '/rebotling/stopptidsmonster', icon: 'fas fa-pause-circle', color: '#fc8181' },
        { title: 'Stopp per Produkt', desc: 'Stoppgrad (driftstoppbortfall%) per produkttyp — vilka produkter orsakar mest maskinproblem? Identifiera högriskprodukter för underhållsplanering och schemaläggning.', route: '/rebotling/stopp-per-produkt', icon: 'fas fa-box-open', color: '#fc8181' },
        { title: 'Maskinpark-Analys', desc: 'Stopptidsanalys per maskin — identifiera flaskhalsmaskin, stopporsaks-fördelning (maskin/material/operatör/planerat), incidentlogg och trend vs föregående period', route: '/rebotling/maskinpark-analys', icon: 'fas fa-cogs', color: '#76e4f7' },
        { title: 'Fart–Produkt-Matris', desc: 'IBC/h per operatör × produkt — se vilka kombinationer som är snabba eller långsamma vs produktsnitt', route: '/rebotling/fart-produkt-matris', icon: 'fas fa-table', color: '#76e4f7' },
        { title: 'Fart–Stopp-Korrelation', desc: 'Scatter av alla skift: stoppgrad vs IBC/h — kompenserar operatörerna maskinstopp eller drabbas produktionen?', route: '/rebotling/fart-stopp', icon: 'fas fa-pause-circle', color: '#f6ad55' },
        { title: 'Kassation–Stopp-Korrelation', desc: 'Scatter av alla skift: stoppgrad vs kassation% — leder maskinstopp till sämre kvalitet eller är de oberoende?', route: '/rebotling/kassation-stopp', icon: 'fas fa-vial', color: '#fc8181' },
        { title: 'Kassationsrytm', desc: '3×7 heatmap av kassation% per skifttyp (dag/kväll/natt) × veckodag — avslöjar om kvaliteten är systematiskt sämre på fredagsnätter eller specifika skifttyper', route: '/rebotling/kassationsrytm', icon: 'fas fa-calendar-times', color: '#fc8181' },
        { title: 'Produktbytesanalys', desc: 'IBC/h på första skiftet efter produktbyte jämfört med fortsättningsskift — visar bytteskostnaden per produkt', route: '/rebotling/produktbyten', icon: 'fas fa-random', color: '#76e4f7' },
        { title: 'Väder–Produktion', desc: 'Korrelation mellan utetemperatur och IBC/h — påverkar väder produktionstakten?', route: '/rebotling/vader-produktion', icon: 'fas fa-cloud-sun', color: '#76e4f7' },
        { title: 'Skift-Sekvensanalys', desc: 'Prestanda vid utvilade starter vs sammanhängande skift — vem behöver vila, vem trivs på rad?', route: '/rebotling/skift-sekvens', icon: 'fas fa-redo', color: '#76e4f7' },
        { title: 'Rotationsanalys', desc: 'Jämför IBC/h när operatören byter position vs stannar kvar — gynnar rotation eller specialisering per individ?', route: '/rebotling/operator-rotation', icon: 'fas fa-sync-alt', color: '#76e4f7' },
        { title: 'Skifttypsmatris', desc: 'Månadsmatris Dag × Kväll × Natt — IBC/h per cell, färgkodad vs snitt. Identifiera vilka skifttyper och dagar som systematiskt är starka eller svaga.', route: '/rebotling/skifttyps-matris', icon: 'fas fa-border-all', color: '#76e4f7' },
      ]
    },
    {
      label: 'Rapporter',
      tools: [
        { title: 'Daglig Översikt', desc: 'Alla skift och KPI:er för ett valt datum — IBC, IBC/h, kassation, stoppgrad, operatörer och 7-dagars sparkline. Auto-uppdateras var 5:e minut.', route: '/rebotling/idag', icon: 'fas fa-sun', color: '#f6c90e' },
        { title: 'Månadsrapport', desc: 'Sammanfattning per månad', route: '/rebotling/operator-monthly-report', icon: 'fas fa-calendar-alt', color: '#63b3ed' },
        { title: 'Kvartalsutvärdering', desc: 'Tier + bonusnivå per kvartal', route: '/rebotling/operator-kvartal', icon: 'fas fa-award', color: '#68d391' },
        { title: 'Produktanalys', desc: 'IBC/h per produkt per operatör', route: '/rebotling/operator-produkt', icon: 'fas fa-box', color: '#63b3ed' },
        { title: 'Stopptidsanalys', desc: 'Stopptid kopplad till operatör', route: '/rebotling/operator-stopptid', icon: 'fas fa-stop-circle', color: '#fc8181' },
        { title: 'Kassationsanalys', desc: 'Kassationsgrad per operatör', route: '/rebotling/operator-kassation', icon: 'fas fa-trash-alt', color: '#fc8181' },
        { title: 'Kassationskarta', desc: 'Operator × Produkt kassationsmatris', route: '/rebotling/kassations-karta', icon: 'fas fa-table', color: '#fc8181' },
        { title: 'Kassationstrender', desc: 'Veckovis kassationsgrad — identifiera kvalitetsglidning', route: '/rebotling/kassation-trend', icon: 'fas fa-chart-line', color: '#fc8181' },
        { title: 'Kassationsorsaker / Op', desc: 'Vilka orsaker kopplas till varje operatörs skift?', route: '/rebotling/kassationsorsak-per-operator', icon: 'fas fa-microscope', color: '#f6ad55' },
        { title: 'Kassationsorsaker / Produkt', desc: 'Vilka kassationsorsaker registreras för varje produkttyp? Identifiera om vissa produkter systematiskt orsakar specifika feltyper', route: '/rebotling/kassationsorsak-per-produkt', icon: 'fas fa-flask', color: '#f6ad55' },
        { title: 'Kassationsorsaker Trend', desc: 'Månadsvis trendanalys av registrerade kassationsorsaker — ser frekvensen av varje orsak öka eller minska? Identifiera om kvalitetsproblem förvärras.', route: '/rebotling/kassationsorsak-trend', icon: 'fas fa-chart-line', color: '#f6ad55' },
        { title: 'Kassationstyper', desc: 'IBC-kassation vs Bur-kassation — analysera de två felmoderna separat per månad, produkt och operatör. Identifiera om kassationen beror på containerfel (IBC) eller ramverksfel (Bur).', route: '/rebotling/kassationstyper', icon: 'fas fa-layer-group', color: '#f6ad55' },
        { title: 'Produktkvalitetstrender', desc: 'Månadsvis kassationsgrad per produkt — se vilka produkter förbättras eller försämras i kvalitet över tid. Multi-line diagram med trendindikator.', route: '/rebotling/produkt-kvalitetstrender', icon: 'fas fa-chart-area', color: '#4fd1c5' },
        { title: 'IBC-förlustkalkyl', desc: 'IBC vunnet/förlorat vs lagsnitt', route: '/rebotling/ibc-forlust', icon: 'fas fa-tint-slash', color: '#fc8181' },
        { title: 'Skift-topplista', desc: 'Bästa och sämsta skift rankade', route: '/rebotling/skift-topplista', icon: 'fas fa-trophy', color: '#f6ad55' },
        { title: 'Rekord & Karriär', desc: 'Bästa skift, karriärtotaler, månadsrekord', route: '/rebotling/rekord-statistik', icon: 'fas fa-crown', color: '#f6c90e' },
        { title: 'Skiftlag-historik', desc: 'Vilka tremannalag har presterat bäst historiskt?', route: '/rebotling/skiftlag-historik', icon: 'fas fa-users', color: '#68d391' },
        { title: 'Produkteffektivitet', desc: 'Effektivitet och kassation per produkt', route: '/rebotling/produkt-analys', icon: 'fas fa-flask', color: '#a78bfa' },
        { title: 'Produkt-Jämförelse', desc: 'Jämför två produkttyper sida vid sida — IBC/h, kassation, stoppgrad, effektivitet vs cykeltid, månadsvis trendlinje och vanligaste operatörer per produkt', route: '/rebotling/produkt-jamforelse', icon: 'fas fa-columns', color: '#76e4f7' },
        { title: 'Veckosammanfattning', desc: 'Veckans digest — IBC, IBC/h, kassation, stoppgrad, operatörsranking och jämförelse med förra veckan', route: '/rebotling/veckosammanfattning', icon: 'fas fa-calendar-week', color: '#63b3ed' },
        { title: 'Veckans Topplista', desc: 'Vem vann varje vecka? Veckovis mästare', route: '/rebotling/veckans-topplista', icon: 'fas fa-crown', color: '#f6c90e' },
        { title: 'Produktionspuls', desc: 'Anläggningens IBC/h-tidsserie med 7-dagars MA och månadsöversikt', route: '/rebotling/produktions-tidsserie', icon: 'fas fa-chart-area', color: '#63b3ed' },
        { title: 'Säsongsanalys', desc: 'IBC/h per månad — säsongsmönster och år-för-år-jämförelse', route: '/rebotling/sasongsanalys', icon: 'fas fa-snowflake', color: '#76e4f7' },
        { title: 'Belastningsbalans', desc: 'Hur jämnt är skiftarbetet fördelat? Gini-index + positionsfördelning per operatör', route: '/rebotling/belastningsbalans', icon: 'fas fa-balance-scale-right', color: '#a78bfa' },
        { title: 'OEE-Dashboard', desc: 'Overall Equipment Effectiveness — Tillgänglighet × Prestanda × Kvalitet. Industristandard-KPI för anläggningens totala effektivitet', route: '/rebotling/oee-dashboard', icon: 'fas fa-tachometer-alt', color: '#a78bfa' },
        { title: 'Månads-Jämförelse', desc: 'Jämför valfria månader sida vid sida — total IBC, IBC/h, kassation, stoppgrad, daglig overlay och operatörsförändring. Perfekt för månadsmöten och år-för-år-uppföljning.', route: '/rebotling/manads-jamforelse', icon: 'fas fa-columns', color: '#76e4f7' },
        { title: 'Årsproduktionskurva', desc: 'Kumulativ IBC per år på samma tidslinje — är årets takt snabbare eller långsammare än förra året? Jämför alla år sida vid sida med "samma dag"-analys.', route: '/rebotling/ars-kurva', icon: 'fas fa-chart-area', color: '#63b3ed' },
      ]
    },
    {
      label: 'Bonus & Planering',
      tools: [
        { title: 'Operatörsmål', desc: 'Sätt individuella IBC/h-mål per operatör och följ framsteg — sparas i webbläsaren', route: '/rebotling/operatormaal', icon: 'fas fa-crosshairs', color: '#68d391' },
        { title: 'Produktionsmål', desc: 'Sätt vecko-/månads-/kvartalsmål för IBC och följ framsteg med prognos och kumulativt diagram', route: '/rebotling/produktionsmaal', icon: 'fas fa-bullseye', color: '#68d391' },
        { title: 'Bonuskalkylator', desc: 'Räkna ut bonus manuellt', route: '/rebotling/bonus-kalkylator', icon: 'fas fa-calculator', color: '#68d391' },
        { title: 'Teamoptimering', desc: 'Föreslaget skiftlag', route: '/rebotling/team-optimizer', icon: 'fas fa-users-cog', color: '#63b3ed' },
        { title: 'Skift-kalender', desc: 'Kalender med skiftdata', route: '/rebotling/skift-kalender', icon: 'fas fa-calendar-week', color: '#63b3ed' },
        { title: 'Skift-prognos', desc: 'Prognos baserad på historik', route: '/rebotling/skift-prognos', icon: 'fas fa-magic', color: '#f6ad55' },
        { title: 'Produkt-Prognos', desc: 'Välj lag och produkt — se varje operatörs produkt-specifika IBC/h vs lagsnitt och teamets förväntade utfall för skiftet. Inkluderar lagkemi, trendindikatorer och tillförlitlighetsbedömning.', route: '/rebotling/produkt-prognos', icon: 'fas fa-box-open', color: '#a78bfa' },
        { title: 'Täckningsanalys', desc: 'Positionstäckning, reservstyrka och SPOF-varning per position', route: '/rebotling/tacknings-analys', icon: 'fas fa-shield-alt', color: '#68d391' },
        { title: 'Personalkalender', desc: 'Månadsvy med närvaro och positioner per operatör — vem jobbade vilken dag?', route: '/rebotling/personal-kalender', icon: 'fas fa-id-badge', color: '#76e4f7' },
        { title: 'Schemaläggningsassistent', desc: 'Datadrivna rekommendationer per operatör — bästa position, bästa skifttyp och aktuell form sammanfattade i ett beslutsstöd för nästa veckas schema', route: '/rebotling/schema-rekommendationer', icon: 'fas fa-clipboard-list', color: '#68d391' },
        { title: 'Kassationsbudget', desc: 'Sätt ett kassations%-mål per månad och följ framsteg — kumulativ daglig kurva vs budget, produkter och operatörer med högst kassation. Prognos för månadsslut vid oförändrad takt.', route: '/rebotling/kassationsbudget', icon: 'fas fa-vial', color: '#fc8181' },
        { title: 'Stopptidsbudget', desc: 'Sätt ett max stoppgrad%-mål per månad och följ maskinens tillgänglighet mot budget — kumulativ daglig stoppgradskurva, stoppgrad per produkt och prognos för månadsslut.', route: '/rebotling/stopptidsbudget', icon: 'fas fa-pause-circle', color: '#f6ad55' },
        { title: 'Dagsplanering', desc: 'Välj dagens lag med stöd av data — automatisk rekommendation balanserar positionsstyrka, aktuell form och vila (vem har jobbat mest/minst de senaste 7 dagarna). Manuell picker med positionsstatistik.', route: '/rebotling/dagsplanering', icon: 'fas fa-calendar-day', color: '#68d391' },
      ]
    },
  ];
}
