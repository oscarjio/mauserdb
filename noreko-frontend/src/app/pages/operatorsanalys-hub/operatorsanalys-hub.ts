import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';

interface AnalysCard {
  title: string;
  desc: string;
  route: string;
  icon: string;
  color: string;
}

@Component({
  standalone: true,
  selector: 'app-operatorsanalys-hub',
  imports: [CommonModule, RouterModule],
  templateUrl: './operatorsanalys-hub.html',
  styleUrl: './operatorsanalys-hub.css'
})
export class OperatorsanalysHubPage {
  readonly cards: AnalysCard[] = [
    { title: 'Operatörsdashboard',  desc: 'Samlad KPI-vy — prestanda, närvaro och trend per operatör',          route: '/admin/operator-dashboard',  icon: 'fas fa-users-cog',      color: '#63b3ed' },
    { title: 'Operatörsjämförelse', desc: 'Jämför valfria operatörer sida vid sida på alla KPI:er',              route: '/admin/operator-compare',    icon: 'fas fa-exchange-alt',   color: '#a78bfa' },
    { title: 'Närvaro',             desc: 'Närvaro- och frånvaroanalys per operatör och period',                  route: '/admin/operator-attendance', icon: 'fas fa-calendar-check', color: '#68d391' },
    { title: 'Prestanda-trend',     desc: 'Veckovis prestandautveckling — identifiera förbättring och fall',      route: '/admin/operator-trend',      icon: 'fas fa-chart-line',     color: '#4299e1' },
    { title: 'Kvalitetstrend',      desc: 'Kassations- och kvalitetstrend per period och operatör',               route: '/admin/kvalitetstrend',      icon: 'fas fa-star-half-alt',  color: '#4299e1' },
    { title: 'Stopporsak-trend',    desc: 'Stopporsaker och deras frekvenstrender — identifiera mönster',         route: '/admin/stopporsak-trend',    icon: 'fas fa-chart-bar',      color: '#ed8936' },
  ];
}
