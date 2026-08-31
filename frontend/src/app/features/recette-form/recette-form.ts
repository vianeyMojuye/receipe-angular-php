import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';

import { RecetteService } from '../../core/recette';
import { Categorie, Personne } from '../../core/models';

@Component({
  selector: 'app-recette-form',
  standalone: true,
  imports: [ReactiveFormsModule],
  templateUrl: './recette-form.html',
  styleUrl: './recette-form.scss',
})
export class RecetteForm {
  private readonly fb = inject(FormBuilder);
  private readonly recetteService = inject(RecetteService);
  private readonly router = inject(Router);

  protected readonly categoriesDisponibles = signal<Categorie[]>([]);
  protected readonly personnesDisponibles = signal<Personne[]>([]);
  protected readonly envoiEnCours = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    titre: ['', Validators.required],
    description: [''],
    instructions: [''],
    temps_preparation: [15],
    temps_cuisson: [20],
    difficulte: ['facile' as const],
    nb_portions: [4],
    categories: this.fb.nonNullable.array<boolean>([]),
    personnes: this.fb.nonNullable.array<boolean>([]),
  });

  constructor() {
    this.recetteService.categories().subscribe((categories) => {
      this.categoriesDisponibles.set(categories);
      const control = this.form.controls.categories;
      categories.forEach(() => control.push(this.fb.nonNullable.control(false)));
    });

    this.recetteService.personnes().subscribe((personnes) => {
      this.personnesDisponibles.set(personnes);
      const control = this.form.controls.personnes;
      personnes.forEach(() => control.push(this.fb.nonNullable.control(false)));
    });
  }

  protected soumettre(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const valeurs = this.form.getRawValue();
    const categories = this.categoriesDisponibles()
      .filter((_, i) => valeurs.categories[i])
      .map((c) => c.id);
    const personnes = this.personnesDisponibles()
      .filter((_, i) => valeurs.personnes[i])
      .map((p) => p.id);

    this.envoiEnCours.set(true);

    this.recetteService
      .creer({
        titre: valeurs.titre,
        description: valeurs.description,
        instructions: valeurs.instructions,
        temps_preparation: valeurs.temps_preparation,
        temps_cuisson: valeurs.temps_cuisson,
        difficulte: valeurs.difficulte,
        nb_portions: valeurs.nb_portions,
        categories,
        personnes,
      })
      .subscribe({
        next: (recette) => this.router.navigate(['/recettes', recette.id]),
        error: () => this.envoiEnCours.set(false),
      });
  }
}
