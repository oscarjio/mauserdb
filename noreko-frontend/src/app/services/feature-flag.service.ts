import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from './auth.service';
import { timeout, catchError, retry } from 'rxjs/operators';
import { of, firstValueFrom } from 'rxjs';
import { environment } from '../../environments/environment';

interface FeatureFlagResponse {
  success: boolean;
  data: Array<{
    feature_key: string;
    label: string;
    category: string;
    min_role: string;
    enabled: number | boolean;
  }>;
}

@Injectable({ providedIn: 'root' })
export class FeatureFlagService {
  private flags = new Map<string, string>();
  private loaded = false;

  constructor(
    private http: HttpClient,
    private auth: AuthService
  ) {}

  /** Hämta feature flags från backend */
  loadFlags(): Promise<void> {
    return firstValueFrom(
      this.http.get<FeatureFlagResponse>(
        `${environment.apiUrl}?action=feature-flags&run=list`,
        { withCredentials: true }
      ).pipe(
        timeout(15000),
        retry(1),
        catchError(() => of(null))
      )
    ).then(res => {
      if (res?.success && Array.isArray(res.data)) {
        this.flags.clear();
        for (const flag of res.data) {
          if (flag.enabled) {
            this.flags.set(flag.feature_key, flag.min_role);
          }
        }
      }
      this.loaded = true;
    });
  }

  /** Kontrollera om aktuell användare har åtkomst till en feature */
  canAccess(featureKey: string): boolean {
    const userRole = this.auth.user$.value?.role ?? 'public';
    const minRole = this.flags.get(featureKey) ?? 'developer';
    return this.roleLevel(userRole) >= this.roleLevel(minRole);
  }

  /** Är flaggorna laddade? */
  isLoaded(): boolean {
    return this.loaded;
  }

  private roleLevel(role: string): number {
    switch (role) {
      case 'developer': return 3;
      case 'admin': return 2;
      case 'user': return 1;
      case 'public': return 0;
      default: return 0;
    }
  }
}
