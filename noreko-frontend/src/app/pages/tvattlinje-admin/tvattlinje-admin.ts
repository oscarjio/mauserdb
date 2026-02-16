import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-tvattlinje-admin',
  imports: [CommonModule, FormsModule],
  templateUrl: './tvattlinje-admin.html',
  styleUrl: './tvattlinje-admin.css'
})
export class TvattlinjeAdminPage implements OnInit {
  loggedIn = false;
  user: any = null;
  isAdmin = false;

  settings: any = {
    antal_per_dag: 150
  };
  loading = false;
  showSuccessMessage = false;
  successMessage = '';

  constructor(private auth: AuthService, private http: HttpClient) {
    this.auth.loggedIn$.subscribe(val => this.loggedIn = val);
    this.auth.user$.subscribe(val => {
      this.user = val;
      this.isAdmin = val?.role === 'admin';
    });
  }

  ngOnInit() {
    this.loadSettings();
  }

  private loadSettings() {
    this.loading = true;
    this.http.get<any>('/noreko-backend/api.php?action=tvattlinje&run=admin-settings', { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.settings = response.data;
          } else {
            console.error('Kunde inte ladda inställningar:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid laddning av inställningar:', error);
          this.loading = false;
        }
      });
  }

  saveSettings() {
    if (!this.settings.antal_per_dag || this.settings.antal_per_dag < 1) {
      return;
    }

    this.loading = true;
    this.http.post<any>('/noreko-backend/api.php?action=tvattlinje&run=admin-settings', this.settings, { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.showSuccess('Inställningar sparade!');
            this.settings = response.data;
          } else {
            console.error('Kunde inte spara inställningar:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid sparande av inställningar:', error);
          this.loading = false;
        }
      });
  }

  private showSuccess(message: string) {
    this.successMessage = message;
    this.showSuccessMessage = true;
    setTimeout(() => {
      this.showSuccessMessage = false;
    }, 3000);
  }
}

