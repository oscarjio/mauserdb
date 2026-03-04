import { Routes } from '@angular/router';
import { Layout } from './layout/layout';
import { News } from './news/news';
import { authGuard, adminGuard } from './guards/auth.guard';

export const routes: Routes = [
  {
    path: '',
    component: Layout,
    children: [
      // Public
      { path: '', component: News },
      { path: 'login', loadComponent: () => import('./pages/login/login').then(m => m.LoginPage) },
      { path: 'register', loadComponent: () => import('./pages/register/register').then(m => m.RegisterPage) },
      { path: 'about', loadComponent: () => import('./pages/about/about').then(m => m.AboutPage) },
      { path: 'contact', loadComponent: () => import('./pages/contact/contact').then(m => m.ContactPage) },

      // Public live views
      { path: 'rebotling/live', loadComponent: () => import('./pages/rebotling-live/rebotling-live').then(m => m.RebotlingLivePage) },
      { path: 'rebotling/live-ranking', loadComponent: () => import('./pages/live-ranking/live-ranking').then(m => m.LiveRankingPage) },
      { path: 'tvattlinje/live', loadComponent: () => import('./pages/tvattlinje-live/tvattlinje-live').then(m => m.TvattlinjeLivePage) },
      { path: 'saglinje/live', loadComponent: () => import('./pages/saglinje-live/saglinje-live').then(m => m.SaglinjeLivePage) },
      { path: 'klassificeringslinje/live', loadComponent: () => import('./pages/klassificeringslinje-live/klassificeringslinje-live').then(m => m.KlassificeringslinjeLivePage) },

      // Public reports/stats
      { path: 'rebotling/skiftrapport', loadComponent: () => import('./pages/rebotling-skiftrapport/rebotling-skiftrapport').then(m => m.RebotlingSkiftrapportPage) },
      { path: 'rebotling/statistik', loadComponent: () => import('./pages/rebotling/rebotling-statistik').then(m => m.RebotlingStatistikPage) },
      { path: 'rebotling/benchmarking', canActivate: [authGuard], loadComponent: () => import('./pages/benchmarking/benchmarking').then(m => m.BenchmarkingPage) },
      { path: 'tvattlinje/skiftrapport', loadComponent: () => import('./pages/tvattlinje-skiftrapport/tvattlinje-skiftrapport').then(m => m.TvattlinjeSkiftrapportPage) },
      { path: 'tvattlinje/statistik', loadComponent: () => import('./pages/tvattlinje-statistik/tvattlinje-statistik').then(m => m.TvattlinjeStatistikPage) },
      { path: 'saglinje/skiftrapport', loadComponent: () => import('./pages/saglinje-skiftrapport/saglinje-skiftrapport').then(m => m.SaglinjeSkiftrapportPage) },
      { path: 'saglinje/statistik', loadComponent: () => import('./pages/saglinje-statistik/saglinje-statistik').then(m => m.SaglinjeStatistikPage) },
      { path: 'klassificeringslinje/skiftrapport', loadComponent: () => import('./pages/klassificeringslinje-skiftrapport/klassificeringslinje-skiftrapport').then(m => m.KlassificeringslinjeSkiftrapportPage) },
      { path: 'klassificeringslinje/statistik', loadComponent: () => import('./pages/klassificeringslinje-statistik/klassificeringslinje-statistik').then(m => m.KlassificeringslinjeStatistikPage) },

      // Rapporter
      { path: 'rapporter/manad', canActivate: [authGuard], loadComponent: () => import('./pages/monthly-report/monthly-report').then(m => m.MonthlyReportPage) },

      // Authenticated routes
      { path: 'min-bonus', canActivate: [authGuard], loadComponent: () => import('./pages/my-bonus/my-bonus').then(m => m.MyBonusPage) },
      { path: 'rebotling/overlamnin', canActivate: [authGuard], loadComponent: () => import('./pages/shift-handover/shift-handover').then(m => m.ShiftHandoverPage) },
      { path: 'stopporsaker', canActivate: [authGuard], loadComponent: () => import('./pages/stoppage-log/stoppage-log').then(m => m.StoppageLogPage) },

      // Admin routes
      { path: 'oversikt', canActivate: [adminGuard], loadComponent: () => import('./pages/executive-dashboard/executive-dashboard').then(m => m.ExecutiveDashboardPage) },
      { path: 'rebotling/admin', canActivate: [adminGuard], loadComponent: () => import('./pages/rebotling-admin/rebotling-admin').then(m => m.RebotlingAdminPage) },
      { path: 'rebotling/bonus', canActivate: [adminGuard], loadComponent: () => import('./pages/bonus-dashboard/bonus-dashboard').then(m => m.BonusDashboardPage) },
      { path: 'rebotling/bonus-admin', canActivate: [adminGuard], loadComponent: () => import('./pages/bonus-admin/bonus-admin').then(m => m.BonusAdminPage) },
      { path: 'rebotling/analys', canActivate: [adminGuard], loadComponent: () => import('./pages/production-analysis/production-analysis').then(m => m.ProductionAnalysisPage) },
      { path: 'rebotling/kalender', canActivate: [adminGuard], loadComponent: () => import('./pages/production-calendar/production-calendar').then(m => m.ProductionCalendarPage) },
      { path: 'tvattlinje/admin', canActivate: [adminGuard], loadComponent: () => import('./pages/tvattlinje-admin/tvattlinje-admin').then(m => m.TvattlinjeAdminPage) },
      { path: 'saglinje/admin', canActivate: [adminGuard], loadComponent: () => import('./pages/saglinje-admin/saglinje-admin').then(m => m.SaglinjeAdminPage) },
      { path: 'klassificeringslinje/admin', canActivate: [adminGuard], loadComponent: () => import('./pages/klassificeringslinje-admin/klassificeringslinje-admin').then(m => m.KlassificeringslinjeAdminPage) },
      { path: 'admin/users', canActivate: [adminGuard], loadComponent: () => import('./pages/users/users').then(m => m.UsersPage) },
      { path: 'admin/create-user', canActivate: [adminGuard], loadComponent: () => import('./pages/create-user/create-user').then(m => m.CreateUserPage) },
      { path: 'admin/vpn', canActivate: [adminGuard], loadComponent: () => import('./pages/vpn-admin/vpn-admin').then(m => m.VpnAdminPage) },
      { path: 'admin/audit', canActivate: [adminGuard], loadComponent: () => import('./pages/audit-log/audit-log').then(m => m.AuditLogPage) },
      { path: 'admin/operators', canActivate: [adminGuard], loadComponent: () => import('./pages/operators/operators').then(m => m.OperatorsPage) },
      { path: 'admin/skiftplan', canActivate: [adminGuard], loadComponent: () => import('./pages/shift-plan/shift-plan').then(m => m.ShiftPlanPage) },
      { path: 'admin/certifiering', canActivate: [adminGuard], loadComponent: () => import('./pages/certifications/certifications').then(m => m.CertificationsPage) },
      { path: 'admin/operator-dashboard', canActivate: [adminGuard], loadComponent: () => import('./pages/operator-dashboard/operator-dashboard').then(m => m.OperatorDashboardPage) },

      { path: 'rebotling/andon', loadComponent: () => import('./pages/andon/andon').then(m => m.AndonPage) },
      { path: '**', loadComponent: () => import('./pages/not-found/not-found').then(m => m.NotFoundPage) }
    ]
  }
];
