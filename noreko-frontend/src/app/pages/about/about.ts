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
  version = '2.0';
  features = [
    { icon: 'fa-industry', title: 'Realtidsövervakning', desc: 'Live-data från alla produktionslinjer med automatisk uppdatering var 2:a sekund.' },
    { icon: 'fa-chart-line', title: 'Statistik & Analys', desc: 'Detaljerad statistik med interaktiva grafer, drill-down och CSV-export.' },
    { icon: 'fa-trophy', title: 'Bonussystem', desc: 'Automatisk bonusberäkning baserad på produktivitet, kvalitet och cykeltid.' },
    { icon: 'fa-gauge-high', title: 'OEE-beräkning', desc: 'Overall Equipment Effectiveness beräknas i realtid med tillgänglighet, prestanda och kvalitet.' },
    { icon: 'fa-file-alt', title: 'Skiftrapporter', desc: 'Automatiska skiftrapporter med godkännandeflöde och historik.' },
    { icon: 'fa-exclamation-triangle', title: 'Stopporsaker', desc: 'Loggning och Pareto-analys av produktionsstopp per linje.' },
  ];

  lines = [
    { name: 'Rebotling', status: 'Fullt implementerad', color: '#22c55e' },
    { name: 'Tvättlinje', status: 'Fullt implementerad', color: '#22c55e' },
    { name: 'Såglinje', status: 'Under utveckling', color: '#f97316' },
    { name: 'Klassificeringslinje', status: 'Under utveckling', color: '#f97316' },
  ];
}
