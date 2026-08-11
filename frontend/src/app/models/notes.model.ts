/**
 * Modele danych szybkich notatek to-do (Etap 19).
 */

export interface Note {
  id: number;
  user_id: number;
  tresc: string;
  is_done: boolean;
  kolejnosc: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface NoteListResponse {
  data: Note[];
}

export interface CreateNoteRequest {
  tresc: string;
  kolejnosc?: number;
}

export interface UpdateNoteRequest {
  tresc: string;
}

export interface ClearNotesResponse {
  success: boolean;
  deleted: number;
}
