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
  products: any[] = [];
  selectedIds: Set<number> = new Set();
  expanded: { [id: number]: boolean } = {};
  loading = false;
  errorMessage = '';
  successMessage = '';
  showSuccessMessage = false;
  isAdmin = false;
  user: any = null;
  showAddReportForm = false;
  loggedIn = false;
  private updateInterval: any = null;

  constructor(
    private skiftrapportService: SkiftrapportService,
    private auth: AuthService
  ) {}

  newReport = {
    datum: new Date().toISOString().split('T')[0],
    product_id: null as number | null,
    ibc_ok: 0,
    bur_ej_ok: 0,
    ibc_ej_ok: 0
  };

  ngOnInit() {
    this.auth.loggedIn$.subscribe(val => this.loggedIn = val);
    this.auth.user$.subscribe(user => {
      this.user = user;
      this.isAdmin = user?.role === 'admin';
    });
    this.fetchReports();
    this.fetchProducts();
    
    // Uppdatera tabellen var 10:e sekund
    this.updateInterval = setInterval(() => {
      this.fetchReports(true); // true = sömlös uppdatering
    }, 10000);
  }

  fetchProducts() {
    this.skiftrapportService.getProducts().subscribe({
      next: (res) => {
        if (res.success) {
          this.products = res.data || [];
        }
      },
      error: (error) => {
        console.error('Error fetching products:', error);
      }
    });
  }

  ngOnDestroy() {
    // Rensa interval när komponenten förstörs
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
    }
  }

  fetchReports(silent: boolean = false) {
    // Visa inte loading-spinner vid automatiska uppdateringar
    if (!silent) {
      this.loading = true;
    }
    this.errorMessage = '';
    
    // Spara scroll-position och befintliga rader för sömlös uppdatering
    const tableContainer = document.querySelector('.table-responsive');
    const scrollTop = tableContainer ? tableContainer.scrollTop : 0;
    const oldReportIds = new Set(this.reports.map(r => r.id));
    
    this.skiftrapportService.getSkiftrapporter().subscribe({
      next: (res) => {
        if (!silent) {
          this.loading = false;
        }
        if (res.success) {
          const newReports = res.data || [];
          
          // Om det är en sömlös uppdatering, behåll expanderade rader och val
          if (silent) {
            // Behåll expanderade rader
            const expandedCopy = { ...this.expanded };
            // Behåll valda rader
            const selectedIdsCopy = new Set(this.selectedIds);
            
            // Uppdatera rapporterna
            this.reports = newReports;
            
            // Återställ expanderade rader
            this.expanded = expandedCopy;
            // Återställ valda rader (bara de som fortfarande finns)
            this.selectedIds = new Set(
              Array.from(selectedIdsCopy).filter(id => 
                newReports.some((r: any) => r.id === id)
              )
            );
            
            // Återställ scroll-position
            if (tableContainer) {
              setTimeout(() => {
                tableContainer.scrollTop = scrollTop;
              }, 0);
            }
          } else {
            // Normal uppdatering - ersätt allt
            this.reports = newReports;
          }
        } else {
          this.errorMessage = res.message || 'Kunde inte hämta skiftrapporter';
        }
      },
      error: (error) => {
        if (!silent) {
          this.loading = false;
        }
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

  isOwner(report: any): boolean {
    return this.user && report.user_id === this.user.id;
  }

  canEdit(report: any): boolean {
    return this.isAdmin || this.isOwner(report);
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

    if (!this.newReport.product_id) {
      this.errorMessage = 'Produkt måste väljas';
      return;
    }

    const totalt = this.newReport.ibc_ok + this.newReport.bur_ej_ok + this.newReport.ibc_ej_ok;
    
    this.loading = true;
    this.skiftrapportService.createSkiftrapport({
      datum: this.newReport.datum,
      product_id: this.newReport.product_id,
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
            product_id: null,
            ibc_ok: 0,
            bur_ej_ok: 0,
            ibc_ej_ok: 0
          };
          this.showAddReportForm = false; // Hide form after adding
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
      product_id: report.product_id,
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
