export interface Categorie {
  id: number;
  nom: string;
  description?: string | null;
}

export interface Auteur {
  id: number;
  nom: string;
  prenom: string;
  role: string;
}

export interface Personne {
  id: number;
  nom: string;
  prenom: string;
  email: string;
}

export type Difficulte = 'facile' | 'moyen' | 'difficile';

export interface Recette {
  id: number;
  titre: string;
  description: string | null;
  instructions: string | null;
  temps_preparation: number | null;
  temps_cuisson: number | null;
  difficulte: Difficulte;
  nb_portions: number;
  date_creation: string;
  date_modification: string;
  categories?: Categorie[];
  auteurs?: Auteur[];
}

export interface RecetteFormPayload {
  titre: string;
  description?: string;
  instructions?: string;
  temps_preparation?: number;
  temps_cuisson?: number;
  difficulte: Difficulte;
  nb_portions: number;
  personnes: number[];
  categories: number[];
}
