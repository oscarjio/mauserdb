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
  selector: 'app-tvattlinje-operator-prestation',
  imports: [CommonModule, RouterModule],
  templateUrl: './tvattlinje-operator-prestation.html',
  styleUrl: './tvattlinje-operator-prestation.css'
})
export class TvattlinjeOperatorPrestationPage {
  groups: { label: string; tools: ToolCard[] }[] = [
    {
      label: 'Ranking & Jämförelse',
      tools: [
        {
          title: 'Operatörsranking',
          desc: 'Rankar alla operatörer på total IBC och IBC/h för vald period — se vem som presterar bäst på tvättlinjen',
          route: '/tvattlinje/operator-ranking',
          icon: 'fas fa-trophy',
          color: '#f6c90e'
        },
        {
          title: 'Topplista',
          desc: 'Podium-visning av topp 3 operatörer — perfekt för tavlan i produktionen',
          route: '/tvattlinje/operator-topplista',
          icon: 'fas fa-medal',
          color: '#68d391'
        },
      ]
    },
    {
      label: 'Diagram & Analys',
      tools: [
        {
          title: 'Poängfördelning',
          desc: 'Stapeldiagram som visar IBC-fördelningen per operatör — snabb visuell jämförelse av bidrag under perioden',
          route: '/tvattlinje/operator-poang',
          icon: 'fas fa-chart-bar',
          color: '#63b3ed'
        },
      ]
    },
  ];
}
