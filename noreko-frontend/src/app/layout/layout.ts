import { Component } from '@angular/core';
import { RouterOutlet, Router } from '@angular/router';
import { Header } from '../header/header';
import { Menu } from '../menu/menu';
import { Submenu } from '../submenu/submenu';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [Header, Menu, Submenu, RouterOutlet, CommonModule],
  templateUrl: './layout.html',
  styleUrl: './layout.css'
})
export class Layout {
  constructor(public router: Router) {}

  get hideMenu() {
    // Hide menu on any 'live', 'login' eller 'register' route
    return this.router.url.includes('/live') || this.router.url.includes('/login') || this.router.url.includes('/register');
  }

  get showBackButton() {
    // Show back button on live or login pages
    return this.router.url.includes('/live') || this.router.url.includes('/login');
  }

  goHome() {
    this.router.navigate(['/']);
  }
}
