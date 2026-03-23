import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { FeatureFlagService } from '../../services/feature-flag.service';

export interface HubItem {
  route: string;
  label: string;
  icon: string;
  description: string;
  featureKey: string;
}

export interface HubCategory {
  id: string;
  label: string;
  color: string;
  icon: string;
  items: HubItem[];
}

const CATEGORIES: HubCategory[] = [
  {
    id: 'produktion', label: 'Produktion', color: '#4fd1c5', icon: 'fas fa-industry',
    items: [
      { route: '/rebotling/produktions-dashboard', label: 'Dashboard', icon: 'fas fa-tv', featureKey: 'rebotling/produktions-dashboard', description: 'Översiktlig produktionsdashboard' },
      { route: '/rebotling/sammanfattning', label: 'Sammanfattning', icon: 'fas fa-tachometer-alt', featureKey: 'rebotling/sammanfattning', description: 'Daglig sammanfattning av produktionen' },
      { route: '/rebotling/produktionspuls', label: 'Produktionspuls', icon: 'fas fa-heartbeat', featureKey: 'rebotling/produktionspuls', description: 'Realtidspuls med aktuell takt och status' },
      { route: '/rebotling/produktionstakt', label: 'Produktionstakt', icon: 'fas fa-tachometer-alt', featureKey: 'rebotling/produktionstakt', description: 'Cykeltid och takt per timme' },
      { route: '/rebotling/produktionskalender', label: 'Kalender', icon: 'fas fa-calendar-alt', featureKey: 'rebotling/produktionskalender', description: 'Produktionsdata per dag i kalendervy' },
      { route: '/rebotling/produktionsprognos', label: 'Prognos', icon: 'fas fa-chart-line', featureKey: 'rebotling/produktionsprognos', description: 'Prognos för kommande produktion' },
      { route: '/rebotling/produktionseffektivitet', label: 'Effektivitet/h', icon: 'fas fa-clock', featureKey: 'rebotling/produktionseffektivitet', description: 'Effektivitet uppdelat per timme' },
      { route: '/rebotling/produktionskostnad', label: 'Kostnad/IBC', icon: 'fas fa-coins', featureKey: 'rebotling/produktionskostnad', description: 'Produktionskostnad per IBC-enhet' },
      { route: '/rebotling/produktionsflode', label: 'Produktionsflöde', icon: 'fas fa-project-diagram', featureKey: 'rebotling/produktionsflode', description: 'Visualisera flödet genom stationerna' },
      { route: '/rebotling/produktionsmal', label: 'Produktionsmål', icon: 'fas fa-bullseye', featureKey: 'rebotling/produktionsmal', description: 'Sätt och följ upp produktionsmål' },
      { route: '/rebotling/produktionsmal-uppfoljning', label: 'Måluppföljning', icon: 'fas fa-chart-line', featureKey: 'rebotling/produktionsmal-uppfoljning', description: 'Detaljerad uppföljning mot mål' },
      { route: '/rebotling/produktions-sla', label: 'Måluppfyllnad', icon: 'fas fa-bullseye', featureKey: 'rebotling/produktions-sla', description: 'SLA och måluppfyllnad över tid' },
      { route: '/rebotling/kapacitetsplanering', label: 'Kapacitetsplanering', icon: 'fas fa-chart-area', featureKey: 'rebotling/kapacitetsplanering', description: 'Planera kapacitet och beläggning' },
      { route: '/rebotling/historisk-produktion', label: 'Historisk produktion', icon: 'fas fa-chart-line', featureKey: 'rebotling/historisk-produktion', description: 'Produktion över längre tidsperioder' },
      { route: '/rebotling/historisk-sammanfattning', label: 'Historisk sammanfattning', icon: 'bi bi-file-earmark-bar-graph', featureKey: 'rebotling/historisk-sammanfattning', description: 'Sammanfattning av historisk data' },
      { route: '/rebotling/rebotling-trendanalys', label: 'Trendanalys', icon: 'fas fa-chart-line', featureKey: 'rebotling/rebotling-trendanalys', description: 'Identifiera trender i produktionsdata' },
      { route: '/rebotling/benchmarking', label: 'Benchmarking', icon: 'fas fa-trophy', featureKey: 'rebotling/benchmarking', description: 'Jämför perioder och skift' },
    ]
  },
  {
    id: 'oee', label: 'OEE & Kvalitet', color: '#9f7aea', icon: 'fas fa-chart-pie',
    items: [
      { route: '/rebotling/oee-benchmark', label: 'OEE Benchmark', icon: 'fas fa-chart-pie', featureKey: 'rebotling/oee-benchmark', description: 'Jämför OEE mellan perioder' },
      { route: '/rebotling/oee-waterfall', label: 'OEE-analys', icon: 'fas fa-chart-bar', featureKey: 'rebotling/oee-waterfall', description: 'Waterfall-diagram av OEE-förluster' },
      { route: '/rebotling/oee-trendanalys', label: 'OEE Trendanalys', icon: 'fas fa-chart-area', featureKey: 'rebotling/oee-trendanalys', description: 'OEE-trender över tid' },
      { route: '/rebotling/oee-jamforelse', label: 'OEE-jämförelse', icon: 'fas fa-chart-line', featureKey: 'rebotling/oee-jamforelse', description: 'Jämför OEE dag-för-dag' },
      { route: '/rebotling/maskin-oee', label: 'Maskin-OEE', icon: 'fas fa-tachometer-alt', featureKey: 'rebotling/maskin-oee', description: 'OEE per enskild maskin' },
      { route: '/rebotling/utnyttjandegrad', label: 'Utnyttjandegrad', icon: 'fas fa-gauge-high', featureKey: 'rebotling/utnyttjandegrad', description: 'Maskinernas utnyttjandegrad' },
      { route: '/rebotling/effektivitet', label: 'Maskineffektivitet', icon: 'fas fa-bolt', featureKey: 'rebotling/effektivitet', description: 'Effektivitet per maskin och station' },
      { route: '/rebotling/kvalitetscertifikat', label: 'Kvalitetscertifikat', icon: 'fas fa-certificate', featureKey: 'rebotling/kvalitetscertifikat', description: 'Hantera kvalitetscertifikat' },
      { route: '/rebotling/kvalitets-trendbrott', label: 'Kvalitets-trendbrott', icon: 'fas fa-chart-line', featureKey: 'rebotling/kvalitets-trendbrott', description: 'Upptäck avvikelser i kvalitetsdata' },
      { route: '/rebotling/kvalitetstrendanalys', label: 'Kvalitetstrend-analys', icon: 'fas fa-chart-area', featureKey: 'rebotling/kvalitetstrendanalys', description: 'Analysera kvalitetstrender' },
    ]
  },
  {
    id: 'kassation', label: 'Kassation & Stopp', color: '#fc8181', icon: 'fas fa-exclamation-triangle',
    items: [
      { route: '/rebotling/kassationsanalys', label: 'Kassationsanalys', icon: 'fas fa-trash-alt', featureKey: 'rebotling/kassationsanalys', description: 'Analysera kassationsorsaker' },
      { route: '/rebotling/kassationsorsak-drilldown', label: 'Kassationsanalys+', icon: 'fas fa-search-plus', featureKey: 'rebotling/kassationsorsak-drilldown', description: 'Djupanalys av kassationsorsaker' },
      { route: '/rebotling/kassationsorsak', label: 'Orsak per station', icon: 'fas fa-times-circle', featureKey: 'rebotling/kassationsorsak', description: 'Kassationsorsaker per station' },
      { route: '/rebotling/kassationsorsak-statistik', label: 'Orsak-statistik', icon: 'fas fa-exclamation-triangle', featureKey: 'rebotling/kassationsorsak-statistik', description: 'Statistik över kassationsorsaker' },
      { route: '/rebotling/kassationskvot-alarm', label: 'Kassationskvot-alarm', icon: 'fas fa-exclamation-circle', featureKey: 'rebotling/kassationskvot-alarm', description: 'Larm vid hög kassationskvot' },
      { route: '/rebotling/stopporsaker', label: 'Stopporsak-dashboard', icon: 'fas fa-exclamation-triangle', featureKey: 'rebotling/stopporsaker', description: 'Överblick av alla stopporsaker' },
      { route: '/rebotling/stopporsak-operator', label: 'Stopporsak per operatör', icon: 'fas fa-exclamation-triangle', featureKey: 'rebotling/stopporsak-operator', description: 'Stopporsaker uppdelat per operatör' },
      { route: '/rebotling/stopptidsanalys', label: 'Stopptidsanalys', icon: 'fas fa-stopwatch', featureKey: 'rebotling/stopptidsanalys', description: 'Analysera stopptider i detalj' },
      { route: '/rebotling/pareto', label: 'Pareto-analys', icon: 'fas fa-chart-bar', featureKey: 'rebotling/pareto', description: '80/20-analys av stopporsaker' },
      { route: '/rebotling/avvikelselarm', label: 'Avvikelselarm', icon: 'fas fa-exclamation-triangle', featureKey: 'rebotling/avvikelselarm', description: 'Automatiska avvikelselarm' },
    ]
  },
  {
    id: 'operator', label: 'Operatör', color: '#63b3ed', icon: 'fas fa-user-hard-hat',
    items: [
      { route: '/rebotling/operatorsportal', label: 'Min portal', icon: 'fas fa-user-circle', featureKey: 'rebotling/operatorsportal', description: 'Personlig operatörsportal' },
      { route: '/rebotling/min-dag', label: 'Min dag', icon: 'fas fa-user-clock', featureKey: 'rebotling/min-dag', description: 'Dagens prestanda och statistik' },
      { route: '/rebotling/operator-dashboard', label: 'Min statistik', icon: 'fas fa-id-badge', featureKey: 'rebotling/operator-dashboard', description: 'Personlig statistik-dashboard' },
      { route: '/min-bonus', label: 'Min Bonus', icon: 'fas fa-star', featureKey: 'min-bonus', description: 'Se din aktuella bonus' },
      { route: '/rebotling/operator-ranking', label: 'Ranking', icon: 'fas fa-trophy', featureKey: 'rebotling/operator-ranking', description: 'Operatörsranking idag' },
      { route: '/rebotling/ranking-historik', label: 'Ranking-historik', icon: 'fas fa-trophy', featureKey: 'rebotling/ranking-historik', description: 'Historisk ranking över tid' },
      { route: '/rebotling/operator-jamforelse', label: 'Operatörsjämförelse', icon: 'fas fa-users', featureKey: 'rebotling/operator-jamforelse', description: 'Jämför operatörsprestanda' },
      { route: '/rebotling/operators-prestanda', label: 'Operatörs-prestanda', icon: 'fas fa-users', featureKey: 'rebotling/operators-prestanda', description: 'Detaljerad prestandavy' },
      { route: '/rebotling/operatorsbonus', label: 'Operatörsbonus', icon: 'fas fa-award', featureKey: 'rebotling/operatorsbonus', description: 'Bonusberäkning och utfall' },
      { route: '/rebotling/operator-onboarding', label: 'Onboarding', icon: 'fas fa-user-graduate', featureKey: 'rebotling/operator-onboarding', description: 'Introduktionsprogram för nya' },
      { route: '/rebotling/gamification', label: 'Gamification', icon: 'fas fa-gamepad', featureKey: 'rebotling/gamification', description: 'Achievements och utmaningar' },
      { route: '/rebotling/narvarotracker', label: 'Närvarotracker', icon: 'fas fa-calendar-check', featureKey: 'rebotling/narvarotracker', description: 'Spåra närvaro och frånvaro' },
      { route: '/rebotling/tidrapport', label: 'Tidrapport', icon: 'fas fa-clock', featureKey: 'rebotling/tidrapport', description: 'Arbetstidsrapportering' },
      { route: '/rebotling/feedback-analys', label: 'Feedback-analys', icon: 'fas fa-comment-dots', featureKey: 'rebotling/feedback-analys', description: 'Analysera feedback från skift' },
    ]
  },
  {
    id: 'underhall', label: 'Underhåll & Maskin', color: '#68d391', icon: 'fas fa-tools',
    items: [
      { route: '/rebotling/underhallslogg', label: 'Underhållslogg', icon: 'fas fa-tools', featureKey: 'rebotling/underhallslogg', description: 'Logga underhållsarbete' },
      { route: '/rebotling/maskinunderhall', label: 'Maskinunderhåll', icon: 'fas fa-wrench', featureKey: 'rebotling/maskinunderhall', description: 'Underhållsschema per maskin' },
      { route: '/rebotling/underhallsprognos', label: 'Underhållsprognos', icon: 'fas fa-tools', featureKey: 'rebotling/underhallsprognos', description: 'Prognostisera underhållsbehov' },
      { route: '/rebotling/prediktivt-underhall', label: 'Prediktivt underhåll', icon: 'fas fa-brain', featureKey: 'rebotling/prediktivt-underhall', description: 'AI-baserad underhållsprediktion' },
      { route: '/rebotling/stationsdetalj', label: 'Stationsdetalj', icon: 'fas fa-industry', featureKey: 'rebotling/stationsdetalj', description: 'Detaljerad vy per station' },
      { route: '/rebotling/maskinhistorik', label: 'Maskinhistorik', icon: 'fas fa-industry', featureKey: 'rebotling/maskinhistorik', description: 'Historik per maskin' },
      { route: '/rebotling/maskin-drifttid', label: 'Maskin-drifttid heatmap', icon: 'fas fa-th', featureKey: 'rebotling/maskin-drifttid', description: 'Heatmap av drifttider' },
      { route: '/rebotling/drifttids-timeline', label: 'Drifttids-timeline', icon: 'fas fa-stream', featureKey: 'rebotling/drifttids-timeline', description: 'Tidslinje av drift och stopp' },
    ]
  },
  {
    id: 'rapporter', label: 'Rapporter', color: '#fbd38d', icon: 'fas fa-file-alt',
    items: [
      { route: '/rebotling/daglig-sammanfattning', label: 'Daglig sammanfattning', icon: 'fas fa-tachometer-alt', featureKey: 'rebotling/daglig-sammanfattning', description: 'Sammanfattning av dagens produktion' },
      { route: '/rebotling/daglig-briefing', label: 'Daglig briefing', icon: 'fas fa-newspaper', featureKey: 'rebotling/daglig-briefing', description: 'Morgonbriefing med nattens data' },
      { route: '/rebotling/morgonrapport', label: 'Morgonrapport', icon: 'fas fa-sun', featureKey: 'rebotling/morgonrapport', description: 'Rapport för morgonmöte' },
      { route: '/rebotling/veckorapport', label: 'Veckorapport', icon: 'fas fa-file-alt', featureKey: 'rebotling/veckorapport', description: 'Veckovis sammanställning' },
      { route: '/rebotling/vd-veckorapport', label: 'VD Veckorapport', icon: 'fas fa-file-invoice', featureKey: 'rebotling/vd-veckorapport', description: 'Ledningsrapport per vecka' },
      { route: '/rebotling/vd-dashboard', label: 'VD Dashboard', icon: 'bi bi-speedometer2', featureKey: 'rebotling/vd-dashboard', description: 'Översikts-dashboard för VD' },
      { route: '/rebotling/skiftrapport-export', label: 'Skiftrapport PDF', icon: 'fas fa-file-pdf', featureKey: 'rebotling/skiftrapport-export', description: 'Exportera skiftrapport som PDF' },
      { route: '/rebotling/skiftrapport-sammanstallning', label: 'Skiftsammanställning', icon: 'fas fa-clipboard-list', featureKey: 'rebotling/skiftrapport-sammanstallning', description: 'Sammanställ flera skiftrapporter' },
      { route: '/rebotling/skiftjamforelse', label: 'Skiftjämförelse', icon: 'fas fa-people-arrows', featureKey: 'rebotling/skiftjamforelse', description: 'Jämför prestanda mellan skift' },
      { route: '/rebotling/malhistorik', label: 'Målhistorik', icon: 'fas fa-bullseye', featureKey: 'rebotling/malhistorik', description: 'Historik för produktionsmål' },
      { route: '/rebotling/alarm-historik', label: 'Alarm-historik', icon: 'fas fa-bell', featureKey: 'rebotling/alarm-historik', description: 'Historik över utlösta larm' },
    ]
  },
  {
    id: 'visualisering', label: 'Visualisering', color: '#ecc94b', icon: 'fas fa-chart-bar',
    items: [
      { route: '/rebotling/statistik-dashboard', label: 'Statistik-dashboard', icon: 'fas fa-tachometer-alt', featureKey: 'rebotling/statistik-dashboard', description: 'Interaktiv statistik-dashboard' },
      { route: '/rebotling/cykeltid-heatmap', label: 'Cykeltids-heatmap', icon: 'fas fa-th', featureKey: 'rebotling/cykeltid-heatmap', description: 'Heatmap av cykeltider' },
      { route: '/rebotling/produktions-heatmap', label: 'Produktions-heatmap', icon: 'fas fa-th', featureKey: 'rebotling/produktions-heatmap', description: 'Heatmap av produktionsvolymer' },
      { route: '/rebotling/produkttyp-effektivitet', label: 'Produkttyp-effektivitet', icon: 'fas fa-boxes', featureKey: 'rebotling/produkttyp-effektivitet', description: 'Effektivitet per produkttyp' },
      { route: '/rebotling/forsta-timme-analys', label: 'Första timmen', icon: 'fas fa-stopwatch', featureKey: 'rebotling/forsta-timme-analys', description: 'Analys av skiftets första timme' },
      { route: '/rebotling/batch-sparning', label: 'Batch-spårning', icon: 'fas fa-boxes', featureKey: 'rebotling/batch-sparning', description: 'Spåra batcher genom processen' },
      { route: '/rebotling/leveransplanering', label: 'Leveransplanering', icon: 'fas fa-truck-loading', featureKey: 'rebotling/leveransplanering', description: 'Planera leveranser' },
      { route: '/rebotling/analys', label: 'Produktionsanalys', icon: 'fas fa-chart-line', featureKey: 'rebotling/analys', description: 'Avancerad produktionsanalys' },
      { route: '/rebotling/kalender', label: 'Produktionskalender', icon: 'fas fa-calendar-alt', featureKey: 'rebotling/kalender', description: 'Kalendervy med produktionsdata' },
      { route: '/rebotling/prognos', label: 'Leveransprognos', icon: 'fas fa-chart-line', featureKey: 'rebotling/prognos', description: 'Prognos för leveranser' },
      { route: '/rebotling/skiftplanering', label: 'Skiftplanering', icon: 'fas fa-calendar-alt', featureKey: 'rebotling/skiftplanering', description: 'Planera och hantera skift' },
    ]
  },
];

@Component({
  standalone: true,
  selector: 'app-funktionshub',
  templateUrl: './funktionshub.html',
  styleUrl: './funktionshub.css',
  imports: [CommonModule, FormsModule, RouterModule],
})
export class FunktionshubPage implements OnInit {
  categories = CATEGORIES;
  activeTab = 'all';
  searchQuery = '';
  favorites = new Set<string>();

  private readonly STORAGE_KEY = 'funktionshub-favs';

  constructor(public ff: FeatureFlagService) {}

  ngOnInit(): void {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const arr: string[] = JSON.parse(stored);
        arr.forEach(r => this.favorites.add(r));
      }
    } catch { /* ignore */ }
  }

  get visibleCategories(): HubCategory[] {
    return this.categories.map(cat => ({
      ...cat,
      items: cat.items.filter(item => this.ff.canAccess(item.featureKey))
    })).filter(cat => cat.items.length > 0);
  }

  get displayItems(): { category: HubCategory; items: HubItem[] }[] {
    const q = this.searchQuery.trim().toLowerCase();
    const cats = this.visibleCategories;

    if (this.activeTab === 'favorites') {
      const groups: { category: HubCategory; items: HubItem[] }[] = [];
      for (const cat of cats) {
        const matched = cat.items.filter(i => this.favorites.has(i.route) && this.matchesSearch(i, q));
        if (matched.length > 0) groups.push({ category: cat, items: matched });
      }
      return groups;
    }

    if (q) {
      const groups: { category: HubCategory; items: HubItem[] }[] = [];
      for (const cat of cats) {
        const matched = cat.items.filter(i => this.matchesSearch(i, q));
        if (matched.length > 0) groups.push({ category: cat, items: matched });
      }
      return groups;
    }

    if (this.activeTab === 'all') {
      return cats.map(cat => ({ category: cat, items: cat.items }));
    }

    const cat = cats.find(c => c.id === this.activeTab);
    return cat ? [{ category: cat, items: cat.items }] : [];
  }

  get totalVisible(): number {
    return this.visibleCategories.reduce((sum, cat) => sum + cat.items.length, 0);
  }

  get favCount(): number {
    let count = 0;
    for (const cat of this.visibleCategories) {
      count += cat.items.filter(i => this.favorites.has(i.route)).length;
    }
    return count;
  }

  catCount(catId: string): number {
    const cat = this.visibleCategories.find(c => c.id === catId);
    return cat ? cat.items.length : 0;
  }

  setTab(tabId: string): void {
    this.activeTab = tabId;
  }

  toggleFavorite(route: string, event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    if (this.favorites.has(route)) {
      this.favorites.delete(route);
    } else {
      this.favorites.add(route);
    }
    try {
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify([...this.favorites]));
    } catch { /* QuotaExceededError i privat surfning — ignorera */ }
  }

  isFav(route: string): boolean {
    return this.favorites.has(route);
  }

  trackByRoute(_: number, item: HubItem): string {
    return item.route;
  }

  trackByCat(_: number, item: any): string {
    return item?.category?.id ?? item?.id ?? _;
  }

  private matchesSearch(item: HubItem, q: string): boolean {
    if (!q) return true;
    return item.label.toLowerCase().includes(q) || item.description.toLowerCase().includes(q);
  }
}
