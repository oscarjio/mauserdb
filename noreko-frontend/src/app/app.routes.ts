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
      { path: 'rebotling/statistik', loadComponent: () => import('./pages/rebotling-statistik/rebotling-statistik').then(m => m.RebotlingStatistikPage) },
      { path: 'rebotling/admin', loadComponent: () => import('./pages/rebotling-admin/rebotling-admin').then(m => m.RebotlingAdminPage) },
      { path: 'tvattlinje/live', loadComponent: () => import('./pages/tvattlinje-live/tvattlinje-live').then(m => m.TvattlinjeLivePage) },
      { path: 'tvattlinje/skiftrapport', loadComponent: () => import('./pages/tvattlinje-skiftrapport/tvattlinje-skiftrapport').then(m => m.TvattlinjeSkiftrapportPage) },
      { path: 'tvattlinje/statistik', loadComponent: () => import('./pages/tvattlinje-statistik/tvattlinje-statistik').then(m => m.TvattlinjeStatistikPage) },
      { path: 'login', loadComponent: () => import('./pages/login/login').then(m => m.LoginPage) },
      { path: 'register', loadComponent: () => import('./pages/register/register').then(m => m.RegisterPage) },
      { path: 'about', loadComponent: () => import('./pages/about/about').then(m => m.AboutPage) },
      { path: 'contact', loadComponent: () => import('./pages/contact/contact').then(m => m.ContactPage) },
      { path: 'admin/users', loadComponent: () => import('./pages/users/users').then(m => m.UsersPage) },
    ]
  }
];
