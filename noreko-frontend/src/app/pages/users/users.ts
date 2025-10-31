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
          this.fetchUsers(); // Uppdatera listan
        } else {
          alert('Kunde inte spara användare: ' + (res.message || 'Okänt fel'));
        }
      },
      error: () => {
        alert('Kunde inte spara användare.');
      }
    });
  }

  deleteUser(user: any) {
    if (!confirm(`Är du säker på att du vill ta bort användaren "${user.username}"?`)) {
      return;
    }
    
    this.usersService.deleteUser(user.id).subscribe({
      next: (res) => {
        if (res.success) {
          this.fetchUsers(); // Uppdatera listan
        } else {
          alert(res.message || 'Kunde inte ta bort användare');
        }
      },
      error: (error) => {
        alert(error.error?.message || 'Kunde inte ta bort användare');
      }
    });
  }

  toggleAdmin(user: any) {
    this.usersService.toggleAdmin(user.id).subscribe({
      next: (res) => {
        if (res.success) {
          user.admin = res.admin;
          user.role = res.admin === 1 ? 'admin' : 'user';
          this.fetchUsers(); // Uppdatera listan
        } else {
          alert(res.message || 'Kunde inte ändra admin-status');
        }
      },
      error: (error) => {
        alert(error.error?.message || 'Kunde inte ändra admin-status');
      }
    });
  }

  toggleActive(user: any) {
    this.usersService.toggleActive(user.id).subscribe({
      next: (res) => {
        if (res.success) {
          user.active = res.active;
          this.fetchUsers(); // Uppdatera listan
        } else {
          alert(res.message || 'Kunde inte ändra status');
        }
      },
      error: (error) => {
        alert(error.error?.message || 'Kunde inte ändra status');
      }
    });
  }
} 