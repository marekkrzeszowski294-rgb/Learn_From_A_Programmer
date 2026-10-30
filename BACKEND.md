# Backend PHP i MySQL (XAMPP)

## Uruchomienie lokalne

1. Skopiuj cały folder projektu do `C:\xampp\htdocs\LearnFromAProgrammer`.
2. W panelu XAMPP uruchom **Apache** i **MySQL**.
3. Otwórz `http://localhost/phpmyadmin`, wybierz **Import**, wskaż plik `schema.sql` i zaimportuj bazę.
4. Wejdź na `http://localhost/LearnFromAProgrammer/Web.html`. Nie otwieraj plików przez `file://` — API PHP działa przez Apache.

Domyślna konfiguracja pasuje do XAMPP: host `127.0.0.1`, baza `learn_from_programmer`, użytkownik `root`, puste hasło. Można ją nadpisać zmiennymi środowiskowymi `DB_HOST`, `DB_NAME`, `DB_USER` i `DB_PASSWORD` dla Apache/PHP.

## Co zapisuje baza

- `users`: konta, role, datę akceptacji regulaminu i skrót hasła utworzony przez `password_hash()`;
- `booking_requests`: zgłoszenia z formularza rozmowy, opcjonalnie powiązane z zalogowanym kontem;
- `completed_lessons`: postęp ucznia, aktualizowany przez API i przypisany do konta.

API obsługuje rejestrację, logowanie, wylogowanie, zapis zgłoszeń, odczyt danych panelu oraz zapis postępów. Hasła nie są przechowywane w postaci jawnej. Konta mentorów zakłada się przez formularz rejestracji, wybierając rolę mentora.

To podstawowy backend demonstracyjnej strony. Zgłoszenia nie są udostępniane wszystkim mentorom: przypisywanie ich do mentora wymaga osobnego procesu administracyjnego. Umawianie terminów, płatności i zarządzanie statusem zgłoszeń również wymagają osobnych ekranów i reguł biznesowych.
