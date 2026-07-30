## Port Baltic Shipping

Nazwa robocza projektu "PBS".

## Informacje o firmie

Oferujemy kompleksowe usługi wynajmu wykwalifikowanych pracowników na potrzeby sektora portowego. Świadczymy kompleksową obsługę przeładunków statków i wagonów towarowych, zapewniając sprawną i bezpieczną realizację operacji portowych. Profesjonalna obsługa terminali, zapewniająca płynność operacji i bezpieczeństwo ładunków.

## Założenia projektu

Projekt w formie aplikacji web podzielonej na sekcje do których poszczególni użytkownicy mogą mieć nadawane uprawnienia + konto główne posiadające uprawnienia do wszystkiego. Osoba z uprawnieniami do danej sekcji może wykonywać wszystkie operacje dostępne w danej sekcji.

Lista sekcji:

1. Sekcja "Dashboard"
2. Sekcja "Pracownicy"
3. Sekcja "Sprzęt"
4. Sekcja "Terminale"
5. Sekcja "Harmonogram"
6. Sekcja "Zlecenia"
7. Sekcja "Analityka"
8. Sekcja "Raportowanie"
9. Sekcja "Ustawienia"
10. Sekcja "Awaria!"

## Sekcja "Dashboard"

Strona główna widoczna po zalogowaniu prezentująca bieżący stan operacyjny firmy. Powinna zawierać:
- Skrótowe KPI: liczba aktywnych pracowników w terenie, liczba terminali aktualnie obsługiwanych, liczba pojazdów w użyciu, liczba aktywnych awarii, **suma przepracowanych godzin dziś (wszystkie porty)**, **liczba pracowników na urlopie**, **suma wynagrodzeń za bieżący miesiąc (wszyscy pracownicy, z podziałem na okresy 1–15 i 15–23)**.
- Widżety alertów: certyfikaty/uprawnienia pracowników bliskie wygaśnięcia, zbliżające się przeglądy sprzętu, nierozwiązane awarie, **zbliżające się powroty z urlopów**.
- Skróty do najczęstszych akcji: zgłoś awarię, utwórz raport, dodaj zlecenie.
Dashboard powinien być responsywny i zoptymalizowany pod kątem szybkiego przeglądu sytuacji na urządzeniach mobilnych.

## Sekcja "Pracownicy"

Zadaniem sekcji jest zarządzanie zespołem pracowników w firmie. Powinna mieć formę tabeli zawierającej informacje takie jak imię, nazwisko, nr telefonu, email pracownika, oraz informacje do jakiego terminala jest w danej chwili skierowany i jaki sprzęt został do niego czasowo przypisany (przypisania czasowe odbywają się przez harmonogram i zlecenia, nie są to stałe przypisania). Powinna być możliwość przejścia do podstrony edycji pracownika oraz szybka edycja czasowego przypisania portu czy sprzętu. Powina być też możliwość dodawania i usuwania pracowników.

Każdy pracownik posiada **indywidualną stawkę godzinową (PLN)**, którą można edytować w dowolnym momencie. Stawka jest używana do obliczania wynagrodzenia: przepracowane godziny × stawka godzinowa = wynagrodzenie pracownika. Stawkę można zmienić przy każdym pracowniku niezależnie.

Każdy pracownik posiada zakładkę "Certyfikaty i uprawnienia" w której przechowywane są jego dokumenty takie jak uprawnienia UDT, certyfikaty BHP, orzeczenia lekarskie itp. Dla każdego dokumentu zapisujemy nazwę, numer dokumentu, datę wydania oraz datę ważności. System automatycznie oznacza dokumenty wygasłe lub te których ważność kończy się w ciągu 30 dni, a alerty o zbliżających się wygaśnięciach są wysyłane zgodnie z konfiguracją w sekcji "Ustawienia".

### Stawki godzinowe i rozliczenia

Dla każdego pracownika zapisujemy jego aktualną stawkę godzinową (PLN/h). Stawkę można w każdej chwili zmienić — system przechowuje historię zmian stawek z datą wejścia w życie, co pozwala prawidłowo rozliczać godziny przepracowane w różnych okresach przy różnych stawkach. Wynagrodzenie pracownika obliczamy jako: **suma przepracowanych godzin × stawka godzinowa obowiązująca w danym dniu**. W sekcji powinna być widoczna podsumowana liczba przepracowanych godzin i naliczone wynagrodzenie dla każdego pracownika (w wybranym okresie).

### Okresy rozliczeniowe (półmiesięczne)

Wynagrodzenia są sumowane i prezentowane w **dwóch okresach rozliczeniowych w miesiącu**:
- **Okres I: dni 1–15 miesiąca**
- **Okres II: dni 15–23 miesiąca**

Dla każdego pracownika i każdego okresu widoczna jest suma przepracowanych godzin oraz naliczone wynagrodzenie. Podział ten pozwala na wypłaty półmiesięczne i lepszą kontrolę kosztów. Ponadto widoczna jest **łączna suma wynagrodzeń za cały miesiąc wszystkich pracowników** (z podziałem na okresy 1–15 i 15–23). W Analityce i Raportowaniu sumy wynagrodzeń są również prezentowane z podziałem na te dwa okresy.

### Stanowiska (role dzienne)

Przy każdym przypisaniu pracownika do zlecenia/harmonogramu należy określić jego **stanowisko (rolę) w danym dniu**. Dostępne stanowiska:

1. **Operator** — obsługa maszyny/urządzenia
2. **Brygadzista** — kierowanie zespołem na zmianie
3. **Sztauer** — obsługa ładunków (lashing/unlashing)
4. **Lukowy** — obsługa luków ładunkowych
5. **Operator żurawia** — obsługa żurawia portowego (RTG/RMG/STS)

Rola jest przypisywana na poziomie dnia i może się zmieniać z dnia na dzień dla tego samego pracownika. Rola wpływa na analitykę (np. rozkład ról w portach) i może być powiązana z różnymi stawkami.

### Urlopy pracowników

Każdy pracownik posiada zakładkę "Urlopy" w której zarządza się jego urlopami. Zapisujemy datę początku i końca urlopu, typ urlopu (wypoczynkowy, na żądanie, L4) oraz status (zaplanowany / zatwierdzony / zrealizowany). Pracownik na urlopie jest automatycznie wykluczany z dostępnych do przypisania w harmonogramie. Na liście pracowników widoczny jest status urlopowy (np. "na urlopie do 15.07"). Liczba pracowników na urlopie jest widoczna na Dashboardzie.

## Sekcja "Sprzęt"

Zadaniem sekcji jest zapanowanie nad sprzętem firmowym używanym podczas pracy. Sprzęt powinien być podzielony na 2 kategorie "pojazdy" i "inne". Na stronie prezentujemy listę sprzętu z informacją do której kategorii należy, do którego pracownika jest w danej chwili czasowo przypisany i do którego terminala jest w danej chwili czasowo przypisany (przypisania czasowe odbywają się przez harmonogram i zlecenia, nie są to stałe przypisania). Podobnie jak w przypadku sekcji "Pracownicy" powinna być możliwość szybkiego czasowego przypisania do terminala i pracownika (w przypadku przypiszemy sprzęt do pracownika który jest przypisany do jakiegoś terminala, automatycznie sprzęt przypisujemy do tego samego terminala co pracownik), a także edycja, usuwanie i dodawanie. Urządzenia z kateogrii pojazdy powinny wyświetlać dodatkowe informacje jak "ostatni zarejestrowany przebieg", "ostatni serwis olejowy", "ostatnia zgłoszona awaria", "data ostatniej oc", "wynik ostatniej oc". Przez "oc" rozumiemy obsługę codzienną maszyny. Dodatkowo powinna być możliwość wyświetlenia rekordów w formie "timeline" żeby sprawnie prześledzić historię danego sprzętu w firmie.

Dla każdego pojazdu dostępna jest zakładka "Planowanie przeglądów" w której można tworzyć harmonogram przyszłych przeglądów technicznych i serwisów (np. serwis olejowy co X km lub co X dni, przegląd UDT co rok itp.). System porównuje zaplanowane terminy z ostatnim zarejestrowanym przebiegiem i datami wykonanych przeglądów, oznaczając pojazdy wymagające wkrótce serwisu. Alerty o zbliżających się przeglądach są wysyłane zgodnie z konfiguracją w sekcji "Ustawienia".

### Przekazywanie maszyn między operatorami (zmiany)

Maszyny portowe pracują często 7 dób w tygodniu, a operatorzy się zamieniają. System rozwiązuje ten problem poprzez **protokoły przekazania zmiany**:

- Każda maszyna ma **aktualnego operatora** i **historię przekazań**.
- Przy zmianie operatora (np. koniec zmiany 6–14, początek 14–22) operator przyjmujący wypełnia **protokół przekazania**: stan maszyny, przebieg, uwagi, wynik OC.
- Protokół jest elektroniczny (z telefonu) i zawiera: datę/czas przekazania, operatora zdającego, operatora przyjmującego, stan licznika, uwagi o uszkodzeniach.
- W przypadku wykrycia uszkodzenia przy przejęciu — operator przyjmujący może od razu zgłosić awarię z poziomu protokołu.
- Historia przekazań jest widoczna w timeline sprzętu (kto, kiedy, w jakim stanie przejął maszynę).
- W harmonogramie przy przypisaniu operatora do maszyny widać, kto przekazał i w jakim stanie.

Protokoły przekazania są punktem wyjścia do raportów OC i rozliczenia czasu pracy maszyny (doba maszyny vs. doba operatora).

## Sekcja "Terminale"

Zadaniem sekcji jest stworzenie bazy terminali portowych (portów, na których firma pracuje) przypisywanych potem do nich pracowników i sprzętu. Na liście powinny się znaleźć takie informacje jak Adres terminala, operator terminala, dane kontaktowe do operatora. Sekcja powinna też prezentować **sumę przepracowanych godzin w danym porcie** (w wybranym okresie) — godziny z danego portu sumują się osobno, co pozwala szybko ocenić obciążenie i zaangażowanie w każdy z portów. Dla każdego terminala widoczna jest liczba obsłużonych zleceń, liczba przypisanych pracowników i sprzętu oraz suma godzin przepracowanych przez wszystkich pracowników w tym porcie.

## Sekcja "Harmonogram"

Zadaniem sekcji jest zarządzanie zleceniami od klientów portowych oraz planowanie przydziału pracowników i sprzętu. Widok główny to siatka tygodniowa lub kalendarz z możliwością przełączania na widok dzienny i miesięczny. Każde zlecenie zawiera: numer zlecenia, dane klienta, terminal, datę i godziny realizacji, zakres prac (np. rozładunek 200 TEU), wartość zlecenia (PLN), status (nowe / w realizacji / zakończone), listę przypisanych pracowników (z **rolą dzienną**: operator / brygadzista / sztauer / lukowy / operator żurawia) oraz przypisany sprzęt. Użytkownik widzi dostępnych pracowników (pracownicy na urlopie są wykluczeni) i może bezpośrednio przypisywać ich do zleceń. Powinna być możliwość tworzenia, edycji i usuwania zleceń a także kopiowania całego tygodnia jako szablon na kolejny tydzień. Harmonogram jest punktem wyjścia dla raportów dziennych — przy tworzeniu raportu dla terminala dane o przypisanych pracownikach, sprzęcie i zleceniach pobierane są właśnie z harmonogramu.

Przy przypisaniu pracownika do zlecenia system oblicza jego **liczbę godzin** (na podstawie godzin realizacji) i mnoży przez jego aktualną **stawkę godzinową**, co daje naliczone wynagrodzenie. Suma godzin danego pracownika w danym dniu i danym porcie jest widoczna w widoku szczegółowym zlecenia oraz sumuje się do statystyk per port i per pracownik.

Przy przypisaniu operatora do maszyny (pojazdu) w harmonogramie, jeśli maszyna pracuje na kilka zmian, system automatycznie generuje **okno przekazania zmiany** — operator przyjmujący dostaje powiadomienie z prośbą o wypełnienie protokołu przekazania (patrz sekcja "Sprzęt" → Przekazywanie maszyn).

## ~~Sekcja "Zlecenia"~~ (ZINTEGROWANA Z HARMONOGRAMEM)

**UWAGA:** Funkcjonalność zleceń została zintegrowana z sekcją "Harmonogram" dla uproszczenia workflow. Użytkownik teraz w jednym miejscu widzi dostępność pracowników, tworzy zlecenia i przypisuje zasoby. Sekcja "Zlecenia" może zostać przekształcona w przyszłości w archiwum historycznych zleceń lub panel raportowy.

## Sekcja "Analityka"

Zadaniem sekcji jest przygotowanie analityki danych z dokumentu excel w formie wykresów i określonego wzoru danych. Sekcja ta powinna też wyświetlać wykresy łaczące wszystkie dane ze wszystkich pozostałych sekcji, takie jakie które terminala najczęściej w danym zakresie czasu były obsługiwane, jacy pracownicy najczęściej zostają przypisani, jaki sprzęt i wszelkie relacje i statystyki pomiędzy pracownikami, sprzętem i terminalami z opcją wybrania okresu czasowego dla wszystkich wykresów jednocześnie (domyślnie 30 dni).

Dodatkowo sekcja powinna prezentować:
- **Sumę wszystkich przepracowanych godzin we wszystkich portach** (łącznie) w wybranym okresie.
- **Sumę godzin per port** (każdy port sumuje się osobno) w formie wykresu słupkowego.
- **Sumę godzin i naliczone wynagrodzenie per pracownik** (godziny × stawka godzinowa) w formie tabeli.
- **Rozkład stanowisk** (operator / brygadzista / sztauer / lukowy / operator żurawia) w danym okresie — ile godzin na każdym stanowisku, per pracownik i per port.
- **Suma wynagrodzeń** wszystkich pracowników w wybranym okresie (łącznie) **z podziałem na okresy rozliczeniowe 1–15 i 15–23**.
- **Suma wynagrodzeń za cały miesiąc** wszystkich pracowników — łączna kwota wypłat dla całego zespołu w danym miesiącu, z podziałem na okresy rozliczeniowe 1–15 i 15–23.

## Sekcja "Raportowanie"

Zadaniem sekcji ma być umożliwienie poszczególnym osobom zarządzającym tworzenie raportów dziennych z prac i sytuacji w porcie. Raporty powinny dzielić się na te dotyczące sytuacji w terminalu i na te dotyczące pojazdów. W przypadku pojazdów podczas tworzenia raportu powinna być opcja wybrania którego pojadzu dotyczy raport, jaki jest aktualny przebieg i jak przebiegło oc danej maszyny. W przypadku raportu dotyczącego sytuacji w porcie, automatycznie powinny się do niego załacąć informacje o pracownikach i sprzęcie obecnych w porcie danego dnia, **w tym liczba godzin przepracowanych przez każdego pracownika, jego stanowisko danego dnia (operator / brygadzista / sztauer / lukowy / operator żurawia), stawka godzinowa oraz naliczone wynagrodzenie**. W raporcie z terminala powinna być też widoczna suma godzin wszystkich pracowników w danym porcie danego dnia oraz **podział wynagrodzeń na okresy rozliczeniowe 1–15 i 15–23** oraz **łączna suma wynagrodzeń za cały miesiąc wszystkich pracowników**.

## Sekcja "Ustawienia"

Zadaniem sekcji będzie zarządzanie ustawieniami całej aplikacji. Domyślnie będzie miał tu dostęp tylko główny administrator strony. Ustawienia które powinny się to znaleźć:
1. Zarządzanie użytkownikami i ich uprawnieniami (w przypadku tworzenia nowego użytkownika powinien być wysyłany do niego email z linkiem do podstrony w której sam ustawi sobie hasło).
2. Zarządzanie alertami na której będzie można określić na jaki email mają być wysyłane alerty i czego mają dotyczyć (np brak raportu oc do określonej godziny, zgłoszenie awarii itd).

## Sekcja "Awaria!"

Zadaniem sekcji jest wyświetlanie raportów z awarii sprzętów lub innych problemów związanych z rozładunkiem. W przypadku kiedy user będzie miał uprawnienie "zgłaszanie awarii" bedzie mógł tutaj zgłosić awarię w formie maksymalnie uproszczonego formularza składającego się tylko z informacji czego dotyczy zdarzenie z opcjami sprzęt / inne i jeśli wybierze sprzęt to z listy powinien mieć opcję wybrania sprzętu i opisania sytuacji.

Każda awaria przechodzi przez lifecycle statusów: **zgłoszona → w trakcie naprawy → naprawiona / zamknięta**. Osoba z uprawnieniami do sekcji może zmieniać status awarii, dodawać komentarze i oznaczać datę zakończenia naprawy. Historia zmian statusu jest widoczna w widoku szczegółowym zgłoszenia. Czas przestoju sprzętu (od zgłoszenia do zamknięcia) jest dostępny w analityce.

## Założenia techniczne aplikacji

1. Mobile first.
2. Frontend: Angular + Tailwind.
3. Backend: PHP.
4. W przypadku jakichkolwiek list: filtrowanie.
5. W przypadku jakichkolwiek selectów: autocomplete.
6. Ograniczenie do minimum dostępu do danych osobowych pracowników.
7. Zastosowanie wszystkich możliwych zasad bezpieczeństwa kodu i dobrych praktyk security i szyfrowania.
8. Baza danych będzie przechowywana na serwerze, dostępy (DATABASE_HOST, DATABASE_NAME, DATABASE_LOGIN, DATABASE_PASSWORD).