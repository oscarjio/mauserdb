import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { OperatorsService } from '../../services/operators.service';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';

@Component({
  standalone: true,
  selector: 'app-operators',
  imports: [CommonModule, FormsModule],
  templateUrl: './operators.html',
  styleUrl: './operators.css'
})
export class OperatorsPage implements OnInit, OnDestroy {
  operators: any[] = [];
  expanded: { [id: number]: boolean } = {};
  loading = false;
  error = '';
  showAddForm = false;
  addForm: { name: string; number: number | null } = { name: '', number: null };

  private destroy$ = new Subject<void>();

  constructor(
    private operatorsService: OperatorsService,
    private auth: AuthService,
    private router: Router,
    private toast: ToastService
  ) {}

  ngOnInit() {
    this.auth.user$.pipe(takeUntil(this.destroy$)).subscribe(user => {
      if (!user || user.role !== 'admin') {
        this.router.navigate(['/']);
      }
    });
    this.fetchOperators();
  }

  ngOnDestroy() {
    this.destroy$.next();
    this.destroy$.complete();
  }

  fetchOperators() {
    this.loading = true;
    this.operatorsService.getOperators().subscribe({
      next: (res) => {
        this.operators = res.operators || [];
        this.loading = false;
      },
      error: () => {
        this.error = 'Kunde inte hämta operatörer.';
        this.loading = false;
      }
    });
  }

  toggleExpand(id: number) {
    this.expanded[id] = !this.expanded[id];
  }

  saveOperator(op: any) {
    this.operatorsService.updateOperator({ id: op.id, name: op.name, number: op.number }).subscribe({
      next: (res) => {
        if (res.success) {
          this.expanded[op.id] = false;
          this.toast.success('Operatör sparad');
          this.fetchOperators();
        } else {
          this.toast.error('Kunde inte spara: ' + (res.message || 'Okänt fel'));
        }
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'Kunde inte spara operatör.');
      }
    });
  }

  deleteOperator(op: any) {
    if (!confirm(`Är du säker på att du vill ta bort operatören "${op.name}"?`)) {
      return;
    }
    this.operatorsService.deleteOperator(op.id).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success('Operatör borttagen');
          this.fetchOperators();
        } else {
          this.toast.error(res.message || 'Kunde inte ta bort operatör');
        }
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'Kunde inte ta bort operatör');
      }
    });
  }

  toggleActive(op: any) {
    this.operatorsService.toggleActive(op.id).subscribe({
      next: (res) => {
        if (res.success) {
          op.active = res.active;
          this.fetchOperators();
        } else {
          this.toast.error(res.message || 'Kunde inte ändra status');
        }
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'Kunde inte ändra status');
      }
    });
  }

  createOperator() {
    if (!this.addForm.name.trim() || !this.addForm.number || this.addForm.number <= 0) {
      this.toast.error('Namn och giltigt nummer krävs');
      return;
    }
    this.operatorsService.createOperator({ name: this.addForm.name.trim(), number: this.addForm.number }).subscribe({
      next: (res) => {
        if (res.success) {
          this.toast.success('Operatör skapad');
          this.addForm = { name: '', number: null };
          this.showAddForm = false;
          this.fetchOperators();
        } else {
          this.toast.error('Kunde inte skapa: ' + (res.message || 'Okänt fel'));
        }
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'Kunde inte skapa operatör.');
      }
    });
  }
}
