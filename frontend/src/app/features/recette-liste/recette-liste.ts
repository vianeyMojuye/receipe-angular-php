import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { RecetteService } from '../../core/recette';
import { Recette } from '../../core/models';

@Component({
  selector: 'app-recette-liste',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './recette-liste.html',
  styleUrl: './recette-liste.scss',
})
export class RecetteListe {
  private readonly recetteService = inject(RecetteService);

  protected readonly recettes = signal<Recette[]>([]);
  protected readonly chargement = signal(true);
  protected readonly erreur = signal<string | null>(null);

  constructor() {
    this.recetteService.liste().subscribe({
      next: (recettes) => {
        this.recettes.set(recettes);
        this.chargement.set(false);
      },
      error: () => {
        this.erreur.set("Impossible de charger les recettes. L'API est-elle démarrée ?");
        this.chargement.set(false);
      },
    });
  }
}
