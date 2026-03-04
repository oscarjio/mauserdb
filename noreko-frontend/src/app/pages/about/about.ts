import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';

@Component({
  standalone: true,
  selector: 'app-about',
  imports: [CommonModule, RouterModule],
  templateUrl: './about.html',
  styleUrl: './about.css'
})
export class AboutPage {
  version = '3.0';
  buildDate = '2026-03-04';
  features = [
    { icon: 'fa-industry', title: 'Realtidsövervakning', desc: 'Live-data från alla produktionslinjer med automatisk uppdatering var 2:a sekund.' },
    { icon: 'fa-chart-line', title: 'Statistik & Analys', desc: 'Detaljerad statistik med interaktiva grafer, heatmap, SPC-kontrollkort, Pareto och drill-down.' },
    { icon: 'fa-trophy', title: 'Bonussystem', desc: 'Automatisk bonusberäkning baserad på IBC/h, kvalitet och cykeltid. Rankinglistor och teambonus.' },
    { icon: 'fa-gauge-high', title: 'OEE-beräkning', desc: 'Overall Equipment Effectiveness beräknas i realtid med tillgänglighet, prestanda och kvalitet.' },
    { icon: 'fa-file-alt', title: 'Skiftrapporter & Rapporter', desc: 'Skiftrapporter, månads- och veckorapporter med PDF/Excel-export.' },
    { icon: 'fa-exclamation-triangle', title: 'Stopporsaker & Underhåll', desc: 'Loggning, mönsteranalys och Pareto-analys av produktionsstopp. Underhållslogg med maskinkorrelation.' },
    { icon: 'fa-users', title: 'Operatörshantering', desc: 'Certifieringsmatris, närvaro-tracker, djupprofil per operatör, korrelationsanalys för operatörspar.' },
    { icon: 'fa-tv', title: 'Andon & Live Ranking', desc: 'Fabriksskärm med realtidsstatus. Live ranking-TV med guld/silver/brons och dagsmål-progress.' },
  ];

  lines = [
    { name: 'Rebotling', status: 'I drift — full funktionalitet', color: '#22c55e' },
    { name: 'Tvättlinje', status: 'Konfigurerad — ej i drift', color: '#f59e0b' },
    { name: 'Såglinje', status: 'Konfigurerad — ej i drift', color: '#f59e0b' },
    { name: 'Klassificeringslinje', status: 'Konfigurerad — ej i drift', color: '#f59e0b' },
  ];
}
