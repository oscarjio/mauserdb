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
      { path: 'tvattlinje/live', loadComponent: () => import('./pages/tvattlinje-live/tvattlinje-live').then(m => m.TvattlinjeLivePage) },
      { path: 'saglinje/live', loadComponent: () => import('./pages/saglinje-live/saglinje-live').then(m => m.SaglinjeLivePage) },
      { path: 'klassificeringslinje/live', loadComponent: () => import('./pages/klassificeringslinje-live/klassificeringslinje-live').then(m => m.KlassificeringslinjeLivePage) },

      // Public reports/stats
      { path: 'rebotling/skiftrapport', loadComponent: () => import('./pages/rebotling-skiftrapport/rebotling-skiftrapport').then(m => m.RebotlingSkiftrapportPage) },
      { path: 'rebotling/statistik', loadComponent: () => import('./pages/rebotling/rebotling-statistik').then(m => m.RebotlingStatistikPage) },
      { path: 'tvattlinje/skiftrapport', loadComponent: () => import('./pages/tvattlinje-skiftrapport/tvattlinje-skiftrapport').then(m => m.TvattlinjeSkiftrapportPage) },
      { path: 'tvattlinje/statistik', loadComponent: () => import('./pages/tvattlinje-statistik/tvattlinje-statistik').then(m => m.TvattlinjeStatistikPage) },
      { path: 'saglinje/skiftrapport', loadComponent: () => import('./pages/saglinje-skiftrapport/saglinje-skiftrapport').then(m => m.SaglinjeSkiftrapportPage) },
      { path: 'saglinje/statistik', loadComponent: () => import('./pages/saglinje-statistik/saglinje-statistik').then(m => m.SaglinjeStatistikPage) },
      { path: 'klassificeringslinje/skiftrapport', loadComponent: () => import('./pages/klassificeringslinje-skiftrapport/klassificeringslinje-skiftrapport').then(m => m.KlassificeringslinjeSkiftrapportPage) },
      { path: 'klassificeringslinje/statistik', loadComponent: () => import('./pages/klassificeringslinje-statistik/klassificeringslinje-statistik').then(m => m.KlassificeringslinjeStatistikPage) },

      // Authenticated routes
      { path: 'min-bonus', canActivate: [authGuard], loadComponent: () => import('./pages/my-bonus/my-bonus').then(m => m.MyBonusPage) },
      { path: 'stopporsaker', canActivate: [authGuard], loadComponent: () => import('./pages/stoppage-log/stoppage-log').then(m => m.StoppageLogPage) },

      // Admin routes
      { path: 'oversikt', canActivate: [adminGuard], loadComponent: () => import('./pages/executive-dashboard/executive-dashboard').then(m => m.ExecutiveDashboardPage) },
      { path: 'rebotling/admin', canActivate: [adminGuard], loadComponent: () => import('./pages/rebotling-admin/rebotling-admin').then(m => m.RebotlingAdminPage) },
      { path: 'rebotling/bonus', canActivate: [adminGuard], loadComponent: () => import('./pages/bonus-dashboard/bonus-dashboard').then(m => m.BonusDashboardPage) },
      { path: 'rebotling/bonus-admin', canActivate: [adminGuard], loadComponent: () => import('./pages/bonus-admin/bonus-admin').then(m => m.BonusAdminPage) },
      { path: 'tvattlinje/admin', canActivate: [adminGuard], loadComponent: () => import('./pages/tvattlinje-admin/tvattlinje-admin').then(m => m.TvattlinjeAdminPage) },
      { path: 'admin/users', canActivate: [adminGuard], loadComponent: () => import('./pages/users/users').then(m => m.UsersPage) },
      { path: 'admin/create-user', canActivate: [adminGuard], loadComponent: () => import('./pages/create-user/create-user').then(m => m.CreateUserPage) },
      { path: 'admin/vpn', canActivate: [adminGuard], loadComponent: () => import('./pages/vpn-admin/vpn-admin').then(m => m.VpnAdminPage) },
      { path: 'admin/audit', canActivate: [adminGuard], loadComponent: () => import('./pages/audit-log/audit-log').then(m => m.AuditLogPage) },

      { path: '**', loadComponent: () => import('./pages/not-found/not-found').then(m => m.NotFoundPage) }
    ]
  }
];
