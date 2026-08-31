import { Routes } from '@angular/router';

import { RecetteDetail } from './features/recette-detail/recette-detail';
import { RecetteForm } from './features/recette-form/recette-form';
import { RecetteListe } from './features/recette-liste/recette-liste';

export const routes: Routes = [
  { path: '', component: RecetteListe, title: 'Recettes' },
  { path: 'recettes/nouvelle', component: RecetteForm, title: 'Nouvelle recette' },
  { path: 'recettes/:id', component: RecetteDetail, title: 'Détail recette' },
];
