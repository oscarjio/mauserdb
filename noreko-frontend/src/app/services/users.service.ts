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
} 