import { Component, Input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

/**
 * Lekki komponent ikon SVG (liniowe, 24x24).
 * Brak zewnętrznych zależności — pasuje do konwencji Tailwind + standalone components.
 * Ikony stylizowane przez `currentColor`, więc dziedziczą kolor tekstu (np. z Tailwind text-*).
 */
@Component({
  selector: 'app-svg-icon',
  standalone: true,
  imports: [CommonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
      stroke-linecap="round"
      stroke-linejoin="round"
      [attr.aria-hidden]="true"
      focusable="false"
      [style.width]="iconSize"
      [style.height]="iconSize"
    >
      @switch (name) {
        @case ('dashboard') {
          <rect x="3" y="3" width="7" height="7" rx="1.5" />
          <rect x="14" y="3" width="7" height="7" rx="1.5" />
          <rect x="14" y="14" width="7" height="7" rx="1.5" />
          <rect x="3" y="14" width="7" height="7" rx="1.5" />
        }
        @case ('pracownicy') {
          <circle cx="9" cy="8" r="3" />
          <path d="M3.5 20c0-3 2.7-5 5.5-5s5.5 2 5.5 5" />
          <circle cx="17" cy="9" r="2.5" />
          <path d="M15 20c0-2.5 2-4 4-4" />
        }
        @case ('sprzet') {
          <path d="M3 7h11v8H3z" />
          <path d="M14 9.5h3.5L21 13v2.5h-7z" />
          <circle cx="7" cy="18" r="1.8" />
          <circle cx="17.5" cy="18" r="1.8" />
        }
        @case ('terminale') {
          <rect x="3" y="4" width="18" height="12" rx="1.5" />
          <path d="M9 20h6M12 16v4" />
        }
        @case ('harmonogram') {
          <rect x="3" y="5" width="18" height="16" rx="2" />
          <path d="M3 9.5h18M8 3v4M16 3v4" />
        }
        @case ('analytics') {
          <path d="M3 20h18" />
          <rect x="5" y="11" width="3" height="6" rx="0.5" />
          <rect x="10.5" y="7" width="3" height="10" rx="0.5" />
          <rect x="16" y="14" width="3" height="3" rx="0.5" />
        }
        @case ('reporting') {
          <path d="M7 3h6l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" />
          <path d="M13 3v4h4" />
          <path d="M9 13h6M9 16.5h6" />
        }
        @case ('settings') {
          <path d="M19.4 13c.04-.33.06-.66.06-1s-.02-.67-.06-1l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.03 7.03 0 0 0-1.73-1l-.38-2.65A.5.5 0 0 0 14.46 2h-4a.5.5 0 0 0-.49.42l-.38 2.65c-.63.26-1.2.6-1.73 1l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.5.5 0 0 0 .12.64L4.6 11c-.04.33-.06.66-.06 1s.02.67.06 1l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .61.22l2.49-1c.53.4 1.1.74 1.73 1l.38 2.65c.05.24.26.42.49.42h4c.24 0 .45-.18.49-.42l.38-2.65c.63-.26 1.2-.6 1.73-1l2.49 1c.22.09.48 0 .61-.22l2-3.46a.5.5 0 0 0-.12-.64L19.4 13Z" />
          <circle cx="12" cy="12" r="3" />
        }
        @case ('awaria') {
          <path d="M12 3.5l9 16H3z" />
          <path d="M12 10v4.5M12 17.5v.5" />
        }
        @case ('menu') {
          <path d="M4 7h16M4 12h16M4 17h16" />
        }
        @case ('close') {
          <path d="M6 6l12 12M18 6L6 18" />
        }
        @case ('trash') {
          <polyline points="3 6 5 6 21 6" />
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
          <line x1="10" y1="11" x2="10" y2="17" />
          <line x1="14" y1="11" x2="14" y2="17" />
        }
        @case ('logout') {
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <path d="M16 17l5-5-5-5" />
          <path d="M21 12H9" />
        }
        @case ('mail') {
          <rect x="3" y="5" width="18" height="14" rx="2" />
          <path d="m3 7 9 6 9-6" />
        }
        @case ('phone') {
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
        }
        @case ('lock') {
          <rect x="5" y="11" width="14" height="9" rx="2" />
          <path d="M8 11V8a4 4 0 0 1 8 0v3" />
        }
        @case ('eye') {
          <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
          <circle cx="12" cy="12" r="3" />
        }
        @case ('eye-off') {
          <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
          <path d="M3 3l18 18" />
        }
        @case ('alert') {
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7v6M12 16v.5" />
        }
        @case ('check') {
          <path d="M5 12.5l4.5 4.5L19 7" />
        }
        @case ('arrow-left') {
          <path d="M19 12H5" />
          <path d="M11 6l-6 6 6 6" />
        }
        @case ('spinner') {
          <path d="M12 3a9 9 0 1 0 9 9" />
        }
        @case ('plus') {
          <path d="M12 5v14M5 12h14" />
        }
        @case ('filter') {
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        }
        @case ('chevron-down') {
          <path d="M6 9l6 6 6-6" />
        }
        @case ('chevron-left') {
          <path d="M15 6l-6 6 6 6" />
        }
        @case ('chevron-right') {
          <path d="M9 6l6 6-6 6" />
        }
        @case ('search') {
          <circle cx="11" cy="11" r="7" />
          <path d="M21 21l-4.3-4.3" />
        }
        @case ('document') {
          <path d="M7 3h6l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" />
          <path d="M13 3v4h4" />
        }
        @case ('exchange') {
          <path d="M7 10l-4 4 4 4" />
          <path d="M3 14h14" />
          <path d="M17 6l4 4-4 4" />
          <path d="M21 10H7" />
        }
        @case ('calendar') {
          <rect x="3" y="5" width="18" height="16" rx="2" />
          <path d="M3 9.5h18M8 3v4M16 3v4" />
        }
        @case ('money') {
          <circle cx="12" cy="12" r="9" />
          <path d="M14.5 9.5c-.5-1-1.5-1.5-2.7-1.5-1.3 0-2.3.7-2.3 1.8 0 2.6 5.2 1.4 5.2 4.2 0 1.2-1.1 2.1-2.6 2.1-1.3 0-2.4-.6-2.8-1.7" />
          <path d="M12 6.5v1.5M12 16v1.5" />
        }
        @case ('dots-vertical') {
          <circle cx="12" cy="5" r="1.6" />
          <circle cx="12" cy="12" r="1.6" />
          <circle cx="12" cy="19" r="1.6" />
        }
        @case ('history') {
          <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
          <path d="M3 3v5h5" />
          <path d="M12 7v5l3 2" />
        }
        @case ('wrench') {
          <path d="M14.7 6.3a4.5 4.5 0 0 0-6 6L3 18l3 3 5.7-5.7a4.5 4.5 0 0 0 6-6L14 13l-3-3 3.7-3.7z" />
        }
        @case ('chart') {
          <path d="M3 3v18h18" />
          <path d="M7 15v-4M12 15V8M17 15v-6" />
        }
        @case ('notes') {
          <rect x="6" y="3" width="12" height="18" rx="2" />
          <path d="M9 8h6M9 12h6M9 16h4" />
        }
        @case ('pencil') {
          <path d="M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
          <path d="m15 5 4 4" />
        }
        @case ('sun') {
          <circle cx="12" cy="12" r="4" />
          <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
        }
        @case ('moon') {
          <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
        }
        @default {
          <circle cx="12" cy="12" r="9" />
        }
      }
    </svg>
  `,
  styles: [
    `
      :host {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 0;
        vertical-align: middle;
      }
    `,
  ],
})
export class SvgIconComponent {
  @Input({ required: true }) name = '';
  /** Rozmiar ikony: sm (1rem), md (1.5rem — domyślny), lg (2rem). */
  @Input() size: 'sm' | 'md' | 'lg' = 'md';

  get iconSize(): string {
    return this.size === 'sm' ? '1rem' : this.size === 'lg' ? '2rem' : '1.5rem';
  }
}