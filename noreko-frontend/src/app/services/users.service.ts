import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class UsersService {
  private base = `${environment.apiUrl}?action=admin`;

  constructor(private http: HttpClient) {}

  getUsers(): Observable<any> {
    return this.http.get<any>(this.base, { withCredentials: true }).pipe(
      timeout(15000),
      retry(1),
      catchError(() => of({ success: false, data: [] }))
    );
  }

  updateUser(user: any): Observable<any> {
    return this.http.post<any>(this.base, user, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(err => of({ success: false, message: err?.error?.error || 'Anslutningsfel' }))
    );
  }

  deleteUser(id: number): Observable<any> {
    return this.http.post<any>(this.base, { action: 'delete', id }, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(err => of({ success: false, message: err?.error?.error || 'Anslutningsfel' }))
    );
  }

  toggleAdmin(id: number): Observable<any> {
    return this.http.post<any>(this.base, { action: 'toggleAdmin', id }, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(err => of({ success: false, message: err?.error?.error || 'Anslutningsfel' }))
    );
  }

  toggleActive(id: number): Observable<any> {
    return this.http.post<any>(this.base, { action: 'toggleActive', id }, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(err => of({ success: false, message: err?.error?.error || 'Anslutningsfel' }))
    );
  }

  createUser(user: any): Observable<any> {
    return this.http.post<any>(this.base, { action: 'create', ...user }, { withCredentials: true }).pipe(
      timeout(15000),
      catchError(err => of({ success: false, message: err?.error?.error || 'Anslutningsfel' }))
    );
  }
} 