import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { Subject, of } from 'rxjs';
import { takeUntil, timeout, catchError, filter, switchMap } from 'rxjs/operators';
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

  savingUser = false;

  // Sök, sortering, filter
  searchText = '';
  private searchTimer: any = null;
  sortColumn: 'username' | 'email' | 'last_login' | 'admin' = 'username';
  sortDirection: 'asc' | 'desc' = 'asc';
  statusFilter: 'alla' | 'aktiva' | 'admin' | 'inaktiva' = 'alla';

  constructor(
    private usersService: UsersService,
    private auth: AuthService,
    private router: Router,
    private toast: ToastService,
    private operatorsService: OperatorsService
  ) {}

  ngOnInit() {
    this.auth.initialized$.pipe(
      filter(init => init === true),
      switchMap(() => this.auth.user$),
      takeUntil(this.destroy$)
    ).subscribe(user => {
      if (!user || user.role !== 'admin') {
        this.router.navigate(['/']);
      }
    });
    this.fetchUsers();
    this.fetchOperators();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
    clearTimeout(this.searchTimer);
  }

  fetchOperators() {
    this.operatorsService.getOperators().pipe(
      timeout(8000),
      catchError(() => of({ operators: [] })), takeUntil(this.destroy$)
    ).subscribe({
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
    this.usersService.getUsers().pipe(
      timeout(8000),
      catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (!res) {
          this.error = 'Kunde inte hämta användare.';
          this.loading = false;
          return;
        }
        this.users = res.users || [];
        this.loading = false;
      },
      error: () => {
        this.error = 'Kunde inte hämta användare.';
        this.loading = false;
      }
    });
  }

  // --- Sök med debounce ---
  onSearchInput() {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      // Trigga ändring (filteredUsers getter körs automatiskt)
      this.searchText = this.searchText; // force change detection if needed
    }, 350);
  }

  // --- Sortering ---
  toggleSort(column: 'username' | 'email' | 'last_login' | 'admin') {
    if (this.sortColumn === column) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortColumn = column;
      this.sortDirection = 'asc';
    }
  }

  getSortIcon(column: string): string {
    if (this.sortColumn !== column) return '▲▼';
    return this.sortDirection === 'asc' ? '▲' : '▼';
  }

  // --- Statusfilter ---
  setStatusFilter(filter: 'alla' | 'aktiva' | 'admin' | 'inaktiva') {
    this.statusFilter = filter;
  }

  // --- Filtrerad & sorterad lista ---
  get filteredUsers(): any[] {
    let result = [...this.users];

    // Statusfilter
    if (this.statusFilter === 'aktiva') {
      result = result.filter(u => u.active === 1);
    } else if (this.statusFilter === 'inaktiva') {
      result = result.filter(u => u.active === 0);
    } else if (this.statusFilter === 'admin') {
      result = result.filter(u => u.admin === 1);
    }

    // Söktext
    if (this.searchText.trim()) {
      const q = this.searchText.trim().toLowerCase();
      result = result.filter(u =>
        (u.username || '').toLowerCase().includes(q) ||
        (u.email || '').toLowerCase().includes(q) ||
        (u.phone || '').toLowerCase().includes(q)
      );
    }

    // Sortering
    result.sort((a, b) => {
      let valA: any;
      let valB: any;

      switch (this.sortColumn) {
        case 'username':
          valA = (a.username || '').toLowerCase();
          valB = (b.username || '').toLowerCase();
          break;
        case 'email':
          valA = (a.email || '').toLowerCase();
          valB = (b.email || '').toLowerCase();
          break;
        case 'last_login':
          valA = a.last_login || '';
          valB = b.last_login || '';
          break;
        case 'admin':
          valA = a.admin || 0;
          valB = b.admin || 0;
          break;
      }

      if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
      if (valA > valB) return this.sortDirection === 'asc' ? 1 : -1;
      return 0;
    });

    return result;
  }

  toggleExpand(id: number) {
    this.expanded[id] = !this.expanded[id];
  }

  saveUser(user: any) {
    if (this.savingUser) return;
    if (!user.username?.trim()) {
      this.toast.error('Användarnamn krävs.');
      return;
    }
    this.savingUser = true;
    this.usersService.updateUser(user).pipe(
      timeout(8000),
      catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        this.savingUser = false;
        if (!res) { this.toast.error('Kunde inte spara användare.'); return; }
        if (res.success) {
          this.expanded[user.id] = false;
          this.toast.success('Användare sparad');
          this.fetchUsers();
        } else {
          this.toast.error('Kunde inte spara användare: ' + (res.error || 'Okänt fel'));
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

    this.usersService.deleteUser(user.id).pipe(
      timeout(8000),
      catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (!res) { this.toast.error('Kunde inte ta bort användare'); return; }
        if (res.success) {
          this.toast.success('Användare borttagen');
          this.fetchUsers();
        } else {
          this.toast.error(res.error || 'Kunde inte ta bort användare');
        }
      },
      error: (error) => {
        this.toast.error(error.error?.error || 'Kunde inte ta bort användare');
      }
    });
  }

  toggleAdmin(user: any) {
    this.usersService.toggleAdmin(user.id).pipe(
      timeout(8000),
      catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (!res) { this.toast.error('Kunde inte ändra admin-status'); return; }
        if (res.success) {
          user.admin = res.admin;
          user.role = res.admin === 1 ? 'admin' : 'user';
          this.fetchUsers();
        } else {
          this.toast.error(res.error || 'Kunde inte ändra admin-status');
        }
      },
      error: (error) => {
        this.toast.error(error.error?.error || 'Kunde inte ändra admin-status');
      }
    });
  }

  toggleActive(user: any) {
    this.usersService.toggleActive(user.id).pipe(
      timeout(8000),
      catchError(() => of(null)), takeUntil(this.destroy$)
    ).subscribe({
      next: (res) => {
        if (!res) { this.toast.error('Kunde inte ändra status'); return; }
        if (res.success) {
          user.active = res.active;
          this.fetchUsers();
        } else {
          this.toast.error(res.error || 'Kunde inte ändra status');
        }
      },
      error: (error) => {
        this.toast.error(error.error?.error || 'Kunde inte ändra status');
      }
    });
  }
  trackByIndex(index: number, item: any): any { return item?.id ?? index; }
}
