import { Component } from '@angular/core';
import { RouterOutlet, Router } from '@angular/router';
import { NgIf } from '@angular/common';
import { Header } from '../header/header';
import { Menu } from '../menu/menu';
import { Submenu } from '../submenu/submenu';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [Header, Menu, Submenu, RouterOutlet, NgIf],
  templateUrl: './layout.html',
  styleUrl: './layout.css'
})
export class Layout {
  constructor(private router: Router) {}

  get hideMenu() {
    // Hide menu on any 'live' or 'login' route
    return this.router.url.includes('/live') || this.router.url.includes('/login');
  }

  get showBackButton() {
    // Show back button on live or login pages
    return this.router.url.includes('/live') || this.router.url.includes('/login');
  }

  goHome() {
    this.router.navigate(['/']);
  }
}
