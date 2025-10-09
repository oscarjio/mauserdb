import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { UsersService } from '../../services/users.service';
import { AuthService } from '../../services/auth.service';
import { Router } from '@angular/router';

@Component({
  standalone: true,
  selector: 'app-users',
  imports: [CommonModule, FormsModule, HttpClientModule],
  templateUrl: './users.html',
  styleUrl: './users.css'
})
export class UsersPage implements OnInit {
  users: any[] = [];
  expanded: { [id: number]: boolean } = {};
  loading = false;
  error = '';

  constructor(
    private usersService: UsersService,
    private auth: AuthService,
    private router: Router
  ) {}

  ngOnInit() {
    this.auth.user$.subscribe(user => {
      if (!user || user.role !== 'admin') {
        this.router.navigate(['/']);
      }
    });
    this.fetchUsers();
  }

  fetchUsers() {
    this.loading = true;
    this.usersService.getUsers().subscribe({
      next: (res) => {
        this.users = res.users || [];
        this.loading = false;
      },
      error: () => {
        this.error = 'Kunde inte hämta användare.';
        this.loading = false;
      }
    });
  }

  toggleExpand(id: number) {
    this.expanded[id] = !this.expanded[id];
  }

  saveUser(user: any) {
    this.usersService.updateUser(user).subscribe({
      next: (res) => {
        if (res.success) {
          this.expanded[user.id] = false;
        } else {
          alert('Kunde inte spara användare: ' + (res.message || 'Okänt fel'));
        }
      },
      error: () => {
        alert('Kunde inte spara användare.');
      }
    });
  }
} 