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
      label: 'Ranking & Jämförelse',
      tools: [
        { title: 'Operatörsranking', desc: 'Ranking på IBC/h vs teamsnitt', route: '/rebotling/operator-scores', icon: 'fas fa-star', color: '#68d391' },
        { title: 'Periodanalys A/B', desc: 'Jämför två perioder sida vid sida', route: '/rebotling/operator-analys', icon: 'fas fa-balance-scale', color: '#63b3ed' },
        { title: 'Operatörsjämförelse', desc: 'Jämför valfria operatörer', route: '/rebotling/operator-compare', icon: 'fas fa-exchange-alt', color: '#63b3ed' },
        { title: 'Schemaläggningsmatris', desc: 'Bästa operatör per position', route: '/rebotling/operator-matcher', icon: 'fas fa-th', color: '#63b3ed' },
      ]
    },
    {
      label: 'Djupanalys',
      tools: [
        { title: 'Skift-DNA', desc: 'Feed med alla senaste skift', route: '/rebotling/shift-dna', icon: 'fas fa-dna', color: '#f6ad55' },
        { title: 'Trendkarta', desc: 'Värmekarta IBC/h per vecka', route: '/rebotling/operator-trend-heatmap', icon: 'fas fa-fire', color: '#fc8181' },
        { title: 'Prestandakarta', desc: 'Scatter IBC/h per operatör', route: '/rebotling/operator-performance-map', icon: 'fas fa-crosshairs', color: '#f6ad55' },
        { title: 'Prestanda per veckodag', desc: 'Vilken dag presterar bäst?', route: '/rebotling/operator-veckodag', icon: 'fas fa-calendar-day', color: '#f6ad55' },
        { title: 'Operatörsaktivitet', desc: 'Närvaro och aktivitetstrender', route: '/rebotling/operator-aktivitet', icon: 'fas fa-user-clock', color: '#63b3ed' },
        { title: 'Inlärningskurva', desc: 'Prestanda över tid för nya', route: '/rebotling/operator-inlarning', icon: 'fas fa-graduation-cap', color: '#63b3ed' },
        { title: 'Prestandavarning', desc: 'Flaggar operatörer med trend ned', route: '/rebotling/operator-varning', icon: 'fas fa-exclamation-triangle', color: '#fc8181' },
        { title: 'Positionsindex', desc: 'Bästa position per operatör vs eget snitt', route: '/rebotling/operator-positioner', icon: 'fas fa-map-pin', color: '#f6ad55' },
        { title: 'Teamkemi', desc: 'Vilka operatörer presterar bättre ihop?', route: '/rebotling/operator-synergy', icon: 'fas fa-heart', color: '#fc8181' },
        { title: 'Produktionsintensitet', desc: 'Daglig IBC/h-heatmap (GitHub-stil)', route: '/rebotling/produktion-heatmap', icon: 'fas fa-th', color: '#63b3ed' },
        { title: 'Skifttyp-analys', desc: 'IBC/h per dag-, kväll- och nattskift', route: '/rebotling/operator-skifttyp', icon: 'fas fa-clock', color: '#a78bfa' },
        { title: 'Veckovis trend', desc: 'IBC/h per vecka vs lagsnitt + prognos', route: '/rebotling/operator-trend', icon: 'fas fa-chart-line', color: '#63b3ed' },
        { title: 'Skiftmomentum', desc: 'Löpande form — streak av skift över/under snitt', route: '/rebotling/operator-momentum', icon: 'fas fa-bolt', color: '#f6ad55' },
        { title: 'Konsistensanalys', desc: 'Vem är mest pålitlig skift för skift? (CV)', route: '/rebotling/operator-konsistens', icon: 'fas fa-ruler', color: '#a78bfa' },
        { title: 'Operatörsvärde', desc: 'Hur påverkas IBC/h när operatören är frånvarande?', route: '/rebotling/operator-avsaknad', icon: 'fas fa-user-minus', color: '#fc8181' },
      ]
    },
    {
      label: 'Rapporter',
      tools: [
        { title: 'Månadsrapport', desc: 'Sammanfattning per månad', route: '/rebotling/operator-monthly-report', icon: 'fas fa-calendar-alt', color: '#63b3ed' },
        { title: 'Kvartalsutvärdering', desc: 'Tier + bonusnivå per kvartal', route: '/rebotling/operator-kvartal', icon: 'fas fa-award', color: '#68d391' },
        { title: 'Produktanalys', desc: 'IBC/h per produkt per operatör', route: '/rebotling/operator-produkt', icon: 'fas fa-box', color: '#63b3ed' },
        { title: 'Stopptidsanalys', desc: 'Stopptid kopplad till operatör', route: '/rebotling/operator-stopptid', icon: 'fas fa-stop-circle', color: '#fc8181' },
        { title: 'Kassationsanalys', desc: 'Kassationsgrad per operatör', route: '/rebotling/operator-kassation', icon: 'fas fa-trash-alt', color: '#fc8181' },
        { title: 'Kassationskarta', desc: 'Operator × Produkt kassationsmatris', route: '/rebotling/kassations-karta', icon: 'fas fa-table', color: '#fc8181' },
        { title: 'IBC-förlustkalkyl', desc: 'IBC vunnet/förlorat vs lagsnitt', route: '/rebotling/ibc-forlust', icon: 'fas fa-tint-slash', color: '#fc8181' },
        { title: 'Skift-topplista', desc: 'Bästa och sämsta skift rankade', route: '/rebotling/skift-topplista', icon: 'fas fa-trophy', color: '#f6ad55' },
        { title: 'Rekord & Karriär', desc: 'Bästa skift, karriärtotaler, månadsrekord', route: '/rebotling/rekord-statistik', icon: 'fas fa-crown', color: '#f6c90e' },
        { title: 'Skiftlag-historik', desc: 'Vilka tremannalag har presterat bäst historiskt?', route: '/rebotling/skiftlag-historik', icon: 'fas fa-users', color: '#68d391' },
        { title: 'Produktanalys', desc: 'Effektivitet och kassation per produkt', route: '/rebotling/produkt-analys', icon: 'fas fa-flask', color: '#a78bfa' },
        { title: 'Veckans Topplista', desc: 'Vem vann varje vecka? Veckovis mästare', route: '/rebotling/veckans-topplista', icon: 'fas fa-crown', color: '#f6c90e' },
      ]
    },
    {
      label: 'Bonus & Planering',
      tools: [
        { title: 'Bonuskalkylator', desc: 'Räkna ut bonus manuellt', route: '/rebotling/bonus-kalkylator', icon: 'fas fa-calculator', color: '#68d391' },
        { title: 'Teamoptimering', desc: 'Föreslaget skiftlag', route: '/rebotling/team-optimizer', icon: 'fas fa-users-cog', color: '#63b3ed' },
        { title: 'Skift-kalender', desc: 'Kalender med skiftdata', route: '/rebotling/skift-kalender', icon: 'fas fa-calendar-week', color: '#63b3ed' },
        { title: 'Skift-prognos', desc: 'Prognos baserad på historik', route: '/rebotling/skift-prognos', icon: 'fas fa-magic', color: '#f6ad55' },
      ]
    },
  ];
}
