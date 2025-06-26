import { Component } from '@angular/core';
import { RouterModule, Router } from '@angular/router';
import { NgIf } from '@angular/common';

@Component({
  selector: 'app-menu',
  standalone: true,
  imports: [RouterModule, NgIf],
  templateUrl: './menu.html',
  styleUrl: './menu.css'
})
export class Menu {
  loggedIn = false;

  constructor(private router: Router) {
    this.loggedIn = !!localStorage.getItem('loggedIn');
  }

  logout() {
    localStorage.removeItem('loggedIn');
    this.loggedIn = false;
    this.router.navigate(['/']);
  }
}
