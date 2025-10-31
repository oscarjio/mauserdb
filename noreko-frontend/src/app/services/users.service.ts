import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class UsersService {
  constructor(private http: HttpClient) {}

  getUsers(): Observable<any> {
    return this.http.get<any>('/noreko-backend/api.php?action=admin', { withCredentials: true });
  }

  updateUser(user: any): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=admin', user, { withCredentials: true });
  }

  deleteUser(id: number): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=admin', { action: 'delete', id }, { withCredentials: true });
  }

  toggleAdmin(id: number): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=admin', { action: 'toggleAdmin', id }, { withCredentials: true });
  }

  toggleActive(id: number): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=admin', { action: 'toggleActive', id }, { withCredentials: true });
  }

  createUser(user: any): Observable<any> {
    return this.http.post<any>('/noreko-backend/api.php?action=admin', { action: 'create', ...user }, { withCredentials: true });
  }
} 