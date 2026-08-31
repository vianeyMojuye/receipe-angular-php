import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';

import { RecetteService } from '../../core/recette';
import { Recette } from '../../core/models';

@Component({
  selector: 'app-recette-detail',
  standalone: true,
  imports: [],
  templateUrl: './recette-detail.html',
  styleUrl: './recette-detail.scss',
})
export class RecetteDetail {
  private readonly route = inject(ActivatedRoute);
  private readonly recetteService = inject(RecetteService);

  protected readonly recette = signal<Recette | null>(null);
  protected readonly chargement = signal(true);

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    this.recetteService.detail(id).subscribe({
      next: (recette) => {
        this.recette.set(recette);
        this.chargement.set(false);
      },
      error: () => this.chargement.set(false),
    });
  }
}
