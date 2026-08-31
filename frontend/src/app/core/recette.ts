import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { API_BASE_URL } from './api.config';
import { Categorie, Personne, Recette, RecetteFormPayload } from './models';

@Injectable({
  providedIn: 'root',
})
export class RecetteService {
  private readonly http = inject(HttpClient);

  liste(filtres?: { categorie?: number; personne?: number }): Observable<Recette[]> {
    const params: Record<string, string> = {};
    if (filtres?.categorie) params['categorie'] = String(filtres.categorie);
    if (filtres?.personne) params['personne'] = String(filtres.personne);

    return this.http.get<Recette[]>(`${API_BASE_URL}/recettes`, { params });
  }

  detail(id: number): Observable<Recette> {
    return this.http.get<Recette>(`${API_BASE_URL}/recettes/${id}`);
  }

  creer(payload: RecetteFormPayload): Observable<Recette> {
    return this.http.post<Recette>(`${API_BASE_URL}/recettes`, payload);
  }

  modifier(id: number, payload: RecetteFormPayload): Observable<Recette> {
    return this.http.put<Recette>(`${API_BASE_URL}/recettes/${id}`, payload);
  }

  supprimer(id: number): Observable<void> {
    return this.http.delete<void>(`${API_BASE_URL}/recettes/${id}`);
  }

  categories(): Observable<Categorie[]> {
    return this.http.get<Categorie[]>(`${API_BASE_URL}/categories`);
  }

  personnes(): Observable<Personne[]> {
    return this.http.get<Personne[]>(`${API_BASE_URL}/personnes`);
  }
}
