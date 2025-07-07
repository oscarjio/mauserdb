import { Component } from '@angular/core';
import { RouterModule, Router } from '@angular/router';
import { NgIf } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-menu',
  standalone: true,
  imports: [RouterModule, NgIf, FormsModule],
  templateUrl: './menu.html',
  styleUrl: './menu.css'
})
export class Menu {
  loggedIn = false;
  selectedMenu: string = 'Älvängen';

  constructor(private router: Router) {
    this.loggedIn = !!localStorage.getItem('loggedIn');
    const saved = localStorage.getItem('selectedMenu');
    if (saved) this.selectedMenu = saved;
  }

  onMenuChange(event: Event) {
    localStorage.setItem('selectedMenu', this.selectedMenu);
  }

  logout() {
    localStorage.removeItem('loggedIn');
    this.loggedIn = false;
    this.router.navigate(['/']);
  }
}
