import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';

@Component({
  selector: 'app-not-found',
  standalone: true,
  imports: [RouterModule],
  template: `
    <div class="not-found-page">
      <div class="not-found-content">
        <div class="error-code">404</div>
        <h2>Sidan hittades inte</h2>
        <p>Sidan du letar efter finns inte eller har flyttats.</p>
        <a routerLink="/" class="btn btn-outline-info btn-lg mt-3">
          <i class="fas fa-home me-2"></i>Till startsidan
        </a>
      </div>
    </div>
  `,
  styles: [`
    .not-found-page {
      min-height: 70vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 2rem;
    }
    .error-code {
      font-size: 8rem;
      font-weight: 900;
      color: #4a5568;
      line-height: 1;
      margin-bottom: 0.5rem;
    }
    h2 { color: #e2e8f0; font-size: 1.8rem; }
    p { color: #718096; font-size: 1.1rem; }
  `]
})
export class NotFoundPage {}
