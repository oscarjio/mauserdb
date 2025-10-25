import { Component, OnInit } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';

@Component({
  standalone: true,
  selector: 'app-rebotling-admin',
  imports: [CommonModule, FormsModule, DatePipe],
  templateUrl: './rebotling-admin.html',
  styleUrl: './rebotling-admin.css'
})
export class RebotlingAdminPage implements OnInit {
  loggedIn = false;
  user: any = null;
  isAdmin = false;
  currentTime = new Date();


  // Product management
  products: any[] = [];
  newProduct: any = {
    name: '',
    cycle_time_minutes: null
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
    // Load products when component initializes
    this.loadProducts();
  }

  private loadProducts() {
    this.loading = true;
    this.http.get<any>('/noreko-backend/api.php?action=product', { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.products = response.data.map((product: any) => ({
              ...product,
              editing: false,
              originalName: product.name,
              originalCycleTime: product.cycle_time_minutes
            }));
          } else {
            console.error('Kunde inte ladda produkter:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid laddning av produkter:', error);
          this.loading = false;
        }
      });
  }



  // Product management methods
  addProduct() {
    if (!this.newProduct.name || !this.newProduct.cycle_time_minutes) {
      return;
    }

    this.loading = true;
    this.http.post<any>('/noreko-backend/api.php?action=product', this.newProduct, { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.loadProducts(); // Reload products
            this.newProduct = { name: '', cycle_time_minutes: null }; // Reset form
            console.log('Produkt tillagd');
            this.showSuccess('Produkt tillagd!');
          } else {
            console.error('Kunde inte lägga till produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid tillägg av produkt:', error);
          this.loading = false;
        }
      });
  }

  editProduct(product: any) {
    // Cancel any other editing
    this.products.forEach(p => {
      if (p.id !== product.id) {
        p.editing = false;
        p.name = p.originalName;
        p.cycle_time_minutes = p.originalCycleTime;
      }
    });
    
    product.editing = true;
    product.originalName = product.name;
    product.originalCycleTime = product.cycle_time_minutes;
  }

  saveProduct(product: any) {
    if (!product.name || !product.cycle_time_minutes) {
      return;
    }

    this.loading = true;
    const updateData = {
      id: product.id,
      name: product.name,
      cycle_time_minutes: product.cycle_time_minutes
    };

    this.http.put<any>('/noreko-backend/api.php?action=product', updateData, { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response.success) {
            product.editing = false;
            product.originalName = product.name;
            product.originalCycleTime = product.cycle_time_minutes;
            console.log('Produkt uppdaterad');
            this.showSuccess('Produkt uppdaterad!');
          } else {
            console.error('Kunde inte uppdatera produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid uppdatering av produkt:', error);
          this.loading = false;
        }
      });
  }

  cancelEdit(product: any) {
    product.editing = false;
    product.name = product.originalName;
    product.cycle_time_minutes = product.originalCycleTime;
  }

  deleteProduct(product: any) {
    if (!confirm(`Är du säker på att du vill ta bort produkten "${product.name}"?`)) {
      return;
    }

    this.loading = true;
    // Use POST with delete action instead of DELETE method
    this.http.post<any>('/noreko-backend/api.php?action=product&run=delete', { id: product.id }, { withCredentials: true })
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.loadProducts(); // Reload products
            console.log('Produkt borttagen');
            this.showSuccess('Produkt borttagen!');
          } else {
            console.error('Kunde inte ta bort produkt:', response.error);
          }
          this.loading = false;
        },
        error: (error) => {
          console.error('Fel vid borttagning av produkt:', error);
          this.loading = false;
        }
      });
  }

  trackByProductId(index: number, product: any): number {
    return product.id;
  }

  private showSuccess(message: string) {
    this.successMessage = message;
    this.showSuccessMessage = true;
    setTimeout(() => {
      this.showSuccessMessage = false;
    }, 3000);
  }
}
