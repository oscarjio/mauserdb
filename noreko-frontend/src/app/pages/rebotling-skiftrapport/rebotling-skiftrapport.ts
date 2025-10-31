import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClientModule } from '@angular/common/http';
import { SkiftrapportService } from '../../services/skiftrapport.service';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-skiftrapport',
  imports: [CommonModule, FormsModule, HttpClientModule, DatePipe],
  templateUrl: './rebotling-skiftrapport.html',
  styleUrl: './rebotling-skiftrapport.css'
})
export class RebotlingSkiftrapportPage implements OnInit, OnDestroy {
  reports: any[] = [];
  selectedIds: Set<number> = new Set();
  expanded: { [id: number]: boolean } = {};
  loading = false;
  errorMessage = '';
  successMessage = '';
  showSuccessMessage = false;
  isAdmin = false;
  user: any = null;

  constructor(
    private skiftrapportService: SkiftrapportService,
    private auth: AuthService
  ) {}

  newReport = {
    datum: new Date().toISOString().split('T')[0],
    ibc_ok: 0,
    bur_ej_ok: 0,
    ibc_ej_ok: 0
  };

  ngOnInit() {
    this.auth.user$.subscribe(user => {
      this.user = user;
      this.isAdmin = user?.role === 'admin';
    });
    this.fetchReports();
  }

  ngOnDestroy() {
    // Cleanup om nödvändigt
  }

  fetchReports() {
    this.loading = true;
    this.errorMessage = '';
    this.skiftrapportService.getSkiftrapporter().subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          this.reports = res.data || [];
        } else {
          this.errorMessage = res.message || 'Kunde inte hämta skiftrapporter';
        }
      },
      error: (error) => {
        this.loading = false;
        this.errorMessage = error.error?.message || 'Ett fel uppstod vid hämtning av skiftrapporter';
      }
    });
  }

  toggleSelect(id: number) {
    if (this.selectedIds.has(id)) {
      this.selectedIds.delete(id);
    } else {
      this.selectedIds.add(id);
    }
  }

  toggleSelectAll() {
    if (this.selectedIds.size === this.reports.length) {
      this.selectedIds.clear();
    } else {
      this.reports.forEach(r => this.selectedIds.add(r.id));
    }
  }

  isSelected(id: number): boolean {
    return this.selectedIds.has(id);
  }

  toggleInlagd(report: any) {
    const newInlagd = !report.inlagd;
    this.skiftrapportService.updateInlagd(report.id, newInlagd).subscribe({
      next: (res) => {
        if (res.success) {
          report.inlagd = newInlagd ? 1 : 0;
          this.showSuccess('Status uppdaterad');
        } else {
          this.errorMessage = res.message || 'Kunde inte uppdatera status';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod';
      }
    });
  }

  bulkMarkInlagd(inlagd: boolean) {
    if (this.selectedIds.size === 0) {
      this.errorMessage = 'Inga rader valda';
      return;
    }

    const ids = Array.from(this.selectedIds);
    this.skiftrapportService.bulkUpdateInlagd(ids, inlagd).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports.forEach(r => {
            if (this.selectedIds.has(r.id)) {
              r.inlagd = inlagd ? 1 : 0;
            }
          });
          this.selectedIds.clear();
          this.showSuccess(res.message);
        } else {
          this.errorMessage = res.message || 'Kunde inte uppdatera status';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod';
      }
    });
  }

  deleteReport(id: number) {
    if (!confirm('Är du säker på att du vill ta bort denna skiftrapport?')) {
      return;
    }

    this.skiftrapportService.deleteSkiftrapport(id).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports = this.reports.filter(r => r.id !== id);
          this.selectedIds.delete(id);
          this.showSuccess('Skiftrapport borttagen');
        } else {
          this.errorMessage = res.message || 'Kunde inte ta bort skiftrapport';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod';
      }
    });
  }

  bulkDelete() {
    if (this.selectedIds.size === 0) {
      this.errorMessage = 'Inga rader valda';
      return;
    }

    if (!confirm(`Är du säker på att du vill ta bort ${this.selectedIds.size} skiftrapport(er)?`)) {
      return;
    }

    const ids = Array.from(this.selectedIds);
    this.skiftrapportService.bulkDelete(ids).subscribe({
      next: (res) => {
        if (res.success) {
          this.reports = this.reports.filter(r => !this.selectedIds.has(r.id));
          this.selectedIds.clear();
          this.showSuccess(res.message);
        } else {
          this.errorMessage = res.message || 'Kunde inte ta bort skiftrapporter';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod';
      }
    });
  }

  addReport() {
    this.errorMessage = '';
    
    if (!this.newReport.datum) {
      this.errorMessage = 'Datum är obligatoriskt';
      return;
    }

    const totalt = this.newReport.ibc_ok + this.newReport.bur_ej_ok + this.newReport.ibc_ej_ok;
    
    this.loading = true;
    this.skiftrapportService.createSkiftrapport({
      datum: this.newReport.datum,
      ibc_ok: this.newReport.ibc_ok,
      bur_ej_ok: this.newReport.bur_ej_ok,
      ibc_ej_ok: this.newReport.ibc_ej_ok,
      totalt: totalt
    }).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          this.fetchReports();
          this.newReport = {
            datum: new Date().toISOString().split('T')[0],
            ibc_ok: 0,
            bur_ej_ok: 0,
            ibc_ej_ok: 0
          };
          this.showSuccess('Skiftrapport tillagd');
        } else {
          this.errorMessage = res.message || 'Kunde inte lägga till skiftrapport';
        }
      },
      error: (error) => {
        this.loading = false;
        this.errorMessage = error.error?.message || 'Ett fel uppstod vid skapande av skiftrapport';
      }
    });
  }

  toggleExpand(id: number) {
    this.expanded[id] = !this.expanded[id];
  }

  saveReport(report: any) {
    // Säkerställ att datum är i rätt format (YYYY-MM-DD)
    let datum = report.datum;
    if (datum instanceof Date) {
      datum = datum.toISOString().split('T')[0];
    } else if (typeof datum === 'string') {
      // Om datum är en sträng, ta bort eventuella extra delar
      datum = datum.split(' ')[0];
    }
    
    this.skiftrapportService.updateSkiftrapport(report.id, {
      datum: datum,
      ibc_ok: parseInt(report.ibc_ok) || 0,
      bur_ej_ok: parseInt(report.bur_ej_ok) || 0,
      ibc_ej_ok: parseInt(report.ibc_ej_ok) || 0
    }).subscribe({
      next: (res) => {
        if (res.success) {
          // Räkna om totalt
          report.totalt = (parseInt(report.ibc_ok) || 0) + (parseInt(report.bur_ej_ok) || 0) + (parseInt(report.ibc_ej_ok) || 0);
          report.datum = datum; // Säkerställ korrekt format
          this.expanded[report.id] = false;
          this.fetchReports();
          this.showSuccess('Skiftrapport uppdaterad');
        } else {
          this.errorMessage = res.message || 'Kunde inte uppdatera skiftrapport';
        }
      },
      error: (error) => {
        this.errorMessage = error.error?.message || 'Ett fel uppstod vid uppdatering';
      }
    });
  }

  showSuccess(message: string) {
    this.successMessage = message;
    this.showSuccessMessage = true;
    setTimeout(() => {
      this.showSuccessMessage = false;
    }, 3000);
  }
}
