import { Component, OnInit, OnDestroy, HostListener, HostBinding } from '@angular/core';
import { RouterOutlet, Router } from '@angular/router';
import { Header } from '../header/header';
import { Menu } from '../menu/menu';
import { ToastComponent } from '../components/toast/toast';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [Header, Menu, RouterOutlet, CommonModule, ToastComponent],
  templateUrl: './layout.html',
  styleUrl: './layout.css'
})
export class Layout implements OnInit, OnDestroy {
  mouseInactive = false;
  mouseMoveTimer: any = null;
  cursorHideTimer: any = null;
  private readonly MOUSE_TIMEOUT = 2000; // 2 sekunder för knappen
  private readonly CURSOR_TIMEOUT = 3000; // 3 sekunder för muspekaren

  constructor(public router: Router) {}

  ngOnInit() {
    this.resetTimers();
  }

  ngOnDestroy() {
    this.clearTimers();
  }

  @HostListener('document:mousemove', ['$event'])
  onMouseMove(event: MouseEvent) {
    this.mouseInactive = false;
    this.resetTimers();
  }

  get hideMenu() {
    // Hide menu on any 'live', 'login' eller 'register' route
    return this.router.url.includes('/live') || this.router.url.includes('/login') || this.router.url.includes('/register');
  }

  get showBackButton() {
    // Show back button on live or login pages
    return this.router.url.includes('/live') || this.router.url.includes('/login');
  }

  get isLivePage() {
    return this.router.url.includes('/live');
  }

  get backButtonClass() {
    const baseClass = 'go-back-bottom-left';
    if (this.isLivePage && this.mouseInactive) {
      return `${baseClass} hide-back-button`;
    }
    return baseClass;
  }

  private resetTimers() {
    this.clearTimers();
    
    if (this.isLivePage) {
      // Timer för att dölja tillbaka-knappen
      this.mouseMoveTimer = setTimeout(() => {
        this.mouseInactive = true;
      }, this.MOUSE_TIMEOUT);

      // Timer för att dölja muspekaren
      this.cursorHideTimer = setTimeout(() => {
        document.body.style.cursor = 'none';
      }, this.CURSOR_TIMEOUT);
    }
  }

  private clearTimers() {
    if (this.mouseMoveTimer) {
      clearTimeout(this.mouseMoveTimer);
      this.mouseMoveTimer = null;
    }
    if (this.cursorHideTimer) {
      clearTimeout(this.cursorHideTimer);
      this.cursorHideTimer = null;
    }
    document.body.style.cursor = '';
  }

  goHome() {
    this.router.navigate(['/']);
  }
}
