import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { UsersService } from '../../services/users.service';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { OperatorsService } from '../../services/operators.service';

@Component({
  standalone: true,
  selector: 'app-users',
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './users.html',
  styleUrl: './users.css'
})
export class UsersPage implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  users: any[] = [];
  operators: any[] = [];
  expanded: { [id: number]: boolean } = {};
  loading = false;
  error = '';

  constructor(
    private usersService: UsersService,
    private auth: AuthService,
    private router: Router,
    private toast: ToastService,
    private operatorsService: OperatorsService
  ) {}

  ngOnInit() {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      if (user !== undefined && (!user || user.role !== 'admin')) {
        this.router.navigate(['/']);
      }
    });
    this.fetchUsers();
    this.fetchOperators();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  fetchOperators() {
    this.operatorsService.getOperators().subscribe({
      next: (res) => { this.operators = res.operators || []; },
      error: () => {}
    });
  }

  getOperatorName(num: number): string | null {
    const op = this.operators.find(o => o.number == num);
    return op ? op.name : null;
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
          this.toast.success('Användare sparad');
          this.fetchUsers();
        } else {
          this.toast.error('Kunde inte spara användare: ' + (res.message || 'Okänt fel'));
        }
      },
      error: () => {
        this.toast.error('Kunde inte spara användare.');
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
          this.toast.success('Användare borttagen');
          this.fetchUsers();
        } else {
          this.toast.error(res.message || 'Kunde inte ta bort användare');
        }
      },
      error: (error) => {
        this.toast.error(error.error?.message || 'Kunde inte ta bort användare');
      }
    });
  }

  toggleAdmin(user: any) {
    this.usersService.toggleAdmin(user.id).subscribe({
      next: (res) => {
        if (res.success) {
          user.admin = res.admin;
          user.role = res.admin === 1 ? 'admin' : 'user';
          this.fetchUsers();
        } else {
          this.toast.error(res.message || 'Kunde inte ändra admin-status');
        }
      },
      error: (error) => {
        this.toast.error(error.error?.message || 'Kunde inte ändra admin-status');
      }
    });
  }

  toggleActive(user: any) {
    this.usersService.toggleActive(user.id).subscribe({
      next: (res) => {
        if (res.success) {
          user.active = res.active;
          this.fetchUsers();
        } else {
          this.toast.error(res.message || 'Kunde inte ändra status');
        }
      },
      error: (error) => {
        this.toast.error(error.error?.message || 'Kunde inte ändra status');
      }
    });
  }
} 