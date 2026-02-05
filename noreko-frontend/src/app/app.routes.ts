import { Routes } from '@angular/router';
import { Layout } from './layout/layout';
import { News } from './news/news';

export const routes: Routes = [
  {
    path: '',
    component: Layout,
    children: [
      { path: '', component: News },
      { path: 'rebotling/live', loadComponent: () => import('./pages/rebotling-live/rebotling-live').then(m => m.RebotlingLivePage) },
      { path: 'rebotling/skiftrapport', loadComponent: () => import('./pages/rebotling-skiftrapport/rebotling-skiftrapport').then(m => m.RebotlingSkiftrapportPage) },
      { path: 'rebotling/statistik', loadComponent: () => import('./pages/rebotling/rebotling-statistik').then(m => m.RebotlingStatistikPage) },
      { path: 'rebotling/admin', loadComponent: () => import('./pages/rebotling-admin/rebotling-admin').then(m => m.RebotlingAdminPage) },
      { path: 'tvattlinje/live', loadComponent: () => import('./pages/tvattlinje-live/tvattlinje-live').then(m => m.TvattlinjeLivePage) },
      { path: 'tvattlinje/skiftrapport', loadComponent: () => import('./pages/tvattlinje-skiftrapport/tvattlinje-skiftrapport').then(m => m.TvattlinjeSkiftrapportPage) },
      { path: 'tvattlinje/statistik', loadComponent: () => import('./pages/tvattlinje-statistik/tvattlinje-statistik').then(m => m.TvattlinjeStatistikPage) },
      { path: 'tvattlinje/admin', loadComponent: () => import('./pages/tvattlinje-admin/tvattlinje-admin').then(m => m.TvattlinjeAdminPage) },
      { path: 'saglinje/live', loadComponent: () => import('./pages/saglinje-live/saglinje-live').then(m => m.SaglinjeLivePage) },
      { path: 'saglinje/skiftrapport', loadComponent: () => import('./pages/saglinje-skiftrapport/saglinje-skiftrapport').then(m => m.SaglinjeSkiftrapportPage) },
      { path: 'saglinje/statistik', loadComponent: () => import('./pages/saglinje-statistik/saglinje-statistik').then(m => m.SaglinjeStatistikPage) },
      { path: 'klassificeringslinje/live', loadComponent: () => import('./pages/klassificeringslinje-live/klassificeringslinje-live').then(m => m.KlassificeringslinjeLivePage) },
      { path: 'klassificeringslinje/skiftrapport', loadComponent: () => import('./pages/klassificeringslinje-skiftrapport/klassificeringslinje-skiftrapport').then(m => m.KlassificeringslinjeSkiftrapportPage) },
      { path: 'klassificeringslinje/statistik', loadComponent: () => import('./pages/klassificeringslinje-statistik/klassificeringslinje-statistik').then(m => m.KlassificeringslinjeStatistikPage) },
      { path: 'login', loadComponent: () => import('./pages/login/login').then(m => m.LoginPage) },
      { path: 'register', loadComponent: () => import('./pages/register/register').then(m => m.RegisterPage) },
      { path: 'about', loadComponent: () => import('./pages/about/about').then(m => m.AboutPage) },
      { path: 'contact', loadComponent: () => import('./pages/contact/contact').then(m => m.ContactPage) },
      { path: 'admin/users', loadComponent: () => import('./pages/users/users').then(m => m.UsersPage) },
      { path: 'admin/create-user', loadComponent: () => import('./pages/create-user/create-user').then(m => m.CreateUserPage) },
      { path: 'admin/vpn', loadComponent: () => import('./pages/vpn-admin/vpn-admin').then(m => m.VpnAdminPage) },
    ]
  }
];
