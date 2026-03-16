import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { timeout, catchError } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class UsersService {
  private base = `${environment.apiUrl}?action=admin`;

  constructor(private http: HttpClient) {}

  getUsers(): Observable<any> {
    return this.http.get<any>(this.base, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, data: [] }))
    );
  }

  updateUser(user: any): Observable<any> {
    return this.http.post<any>(this.base, user, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, message: 'Anslutningsfel' }))
    );
  }

  deleteUser(id: number): Observable<any> {
    return this.http.post<any>(this.base, { action: 'delete', id }, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, message: 'Anslutningsfel' }))
    );
  }

  toggleAdmin(id: number): Observable<any> {
    return this.http.post<any>(this.base, { action: 'toggleAdmin', id }, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, message: 'Anslutningsfel' }))
    );
  }

  toggleActive(id: number): Observable<any> {
    return this.http.post<any>(this.base, { action: 'toggleActive', id }, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, message: 'Anslutningsfel' }))
    );
  }

  createUser(user: any): Observable<any> {
    return this.http.post<any>(this.base, { action: 'create', ...user }, { withCredentials: true }).pipe(
      timeout(10000),
      catchError(() => of({ success: false, message: 'Anslutningsfel' }))
    );
  }
} 