import { Component } from '@angular/core';
import { RouterModule, Router } from '@angular/router';
import { NgIf, CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { interval } from 'rxjs';

@Component({
  selector: 'app-menu',
  standalone: true,
  imports: [RouterModule, NgIf, FormsModule, HttpClientModule, CommonModule],
  templateUrl: './menu.html',
  styleUrl: './menu.css'
})
export class Menu {
  loggedIn = false;
  user: any = null;
  showMenu = false;
  selectedMenu: string = 'Älvängen';

  constructor(private router: Router, private http: HttpClient) {
    const saved = localStorage.getItem('selectedMenu');
    if (saved) this.selectedMenu = saved;
  }

  ngOnInit() {
    console.log('Menu ngOnInit');
    this.fetchStatus();
    interval(60000).subscribe(() => this.fetchStatus());
  }

  fetchStatus() {
    console.log('fetchStatus körs');
    this.http.get<any>('/noreko-backend/api.php?action=status', { withCredentials: true }).subscribe(res => {
      console.log('status response', res);
      this.loggedIn = res.loggedIn;
      this.user = res.user || null;
      if (!this.loggedIn) this.showMenu = false;
    }, err => {
      console.error('status error', err);
    });
  }

  onMenuChange(event: Event) {
    localStorage.setItem('selectedMenu', this.selectedMenu);
  }

  logout() {
    this.http.get('/noreko-backend/api.php?action=logout', { withCredentials: true }).subscribe(() => {
      this.loggedIn = false;
      this.user = null;
      this.showMenu = false;
      this.router.navigate(['/']);
    });
  }
}
