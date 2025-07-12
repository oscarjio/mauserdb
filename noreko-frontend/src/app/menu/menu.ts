import { Component } from '@angular/core';
import { RouterModule, Router } from '@angular/router';
import { NgIf, CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../services/auth.service';

@Component({
  selector: 'app-menu',
  standalone: true,
  imports: [RouterModule, NgIf, FormsModule, CommonModule],
  templateUrl: './menu.html',
  styleUrl: './menu.css'
})
export class Menu {
  loggedIn = false;
  user: any = null;
  showMenu = false;
  selectedMenu: string = 'Älvängen';

  constructor(private router: Router, public auth: AuthService) {
    const saved = localStorage.getItem('selectedMenu');
    if (saved) this.selectedMenu = saved;
    this.auth.loggedIn$.subscribe(val => this.loggedIn = val);
    this.auth.user$.subscribe(val => this.user = val);
  }

  onMenuChange(event: Event) {
    localStorage.setItem('selectedMenu', this.selectedMenu);
  }

  logout() {
    this.auth.logout();
    this.showMenu = false;
    this.router.navigate(['/']);
  }
}
