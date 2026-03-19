CREATE DATABASE magazyn
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_polish_ci;

USE magazyn;

-- =========================================================
-- TABELA: UŻYTKOWNICY
-- =========================================================
CREATE TABLE uzytkownicy (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    login VARCHAR(255) NOT NULL UNIQUE,
    rfid VARCHAR(500) UNIQUE,
    haslo VARCHAR(255) NOT NULL,
    imie VARCHAR(100) NOT NULL,
    nazwisko VARCHAR(100) NOT NULL,
    status_konta ENUM('aktywne', 'nieaktywne') NOT NULL DEFAULT 'aktywne',
    rola ENUM('admin', 'user') NOT NULL DEFAULT 'user'
);

-- =========================================================
-- TABELA: SŁOWNIK KATEGORII
-- =========================================================
CREATE TABLE slownik_kategorie (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    nazwa VARCHAR(255) NOT NULL UNIQUE,
    aktywna BOOLEAN NOT NULL DEFAULT TRUE,
    data_utworzenia DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================================================
-- TABELA: SŁOWNIK LOKALIZACJI
-- =========================================================
CREATE TABLE slownik_lokalizacje (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    nazwa VARCHAR(255) NOT NULL UNIQUE,
    aktywna BOOLEAN NOT NULL DEFAULT TRUE,
    data_utworzenia DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================================================
-- TABELA: MAGAZYN
-- =========================================================
CREATE TABLE magazyn (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    id_uzytkownika INT UNSIGNED NOT NULL,
    nazwa VARCHAR(255) NOT NULL,
    kategoria VARCHAR(255) DEFAULT NULL,
    jednostka VARCHAR(20) NOT NULL,
    ilosc INT NOT NULL,
    lokalizacja VARCHAR(255) DEFAULT NULL,
    uwagi VARCHAR(500) DEFAULT NULL,
    CONSTRAINT chk_magazyn_ilosc CHECK (ilosc >= 0),
    CONSTRAINT fk_magazyn_uzytkownik
        FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- =========================================================
-- TABELA: WYDANIA
-- =========================================================
CREATE TABLE wydania (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    id_produktu INT UNSIGNED NULL,
    id_uzytkownika INT UNSIGNED NOT NULL,
    data_i_godzina TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ilosc INT NOT NULL,
    powod VARCHAR(500) NOT NULL,
    nazwa_produktu_snapshot VARCHAR(255) NOT NULL,
    jednostka_snapshot VARCHAR(20) NOT NULL,
    CONSTRAINT chk_wydania_ilosc CHECK (ilosc > 0),
    CONSTRAINT fk_wydania_uzytkownik
        FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_wydania_magazyn
        FOREIGN KEY (id_produktu) REFERENCES magazyn(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- =========================================================
-- TABELA: INWENTARYZACJE
-- =========================================================
CREATE TABLE inwentaryzacje (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    numer_inwentaryzacji VARCHAR(100) DEFAULT NULL UNIQUE,
    id_uzytkownika INT UNSIGNED NOT NULL,
    data_utworzenia DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_zatwierdzenia DATETIME DEFAULT NULL,
    zatwierdzona BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_inwentaryzacje_uzytkownik
        FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- =========================================================
-- TABELA: POZYCJE INWENTARYZACJI
-- =========================================================
CREATE TABLE inwentaryzacja_pozycja (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    id_inwentaryzacji INT UNSIGNED NOT NULL,
    id_produktu INT UNSIGNED NULL,
    id_uzytkownika INT UNSIGNED NOT NULL,
    stan INT NOT NULL,
    roznica INT DEFAULT NULL,
    zatwierdzona BOOLEAN NOT NULL DEFAULT FALSE,
    nazwa_produktu_snapshot VARCHAR(255) NOT NULL,
    jednostka_snapshot VARCHAR(20) NOT NULL,
    CONSTRAINT chk_inwentaryzacja_pozycja_stan CHECK (stan >= 0),
    CONSTRAINT fk_inwentaryzacja_magazyn
        FOREIGN KEY (id_produktu) REFERENCES magazyn(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_inwentaryzacja_inwentaryzacje
        FOREIGN KEY (id_inwentaryzacji) REFERENCES inwentaryzacje(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_inwentaryzacja_pozycja_uzytkownik
        FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT unikalny_produkt_w_inwentaryzacji
        UNIQUE (id_inwentaryzacji, id_produktu)
);

-- =========================================================
-- TABELA: HISTORIA OPERACJI
-- =========================================================
CREATE TABLE historia_operacji (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    id_uzytkownika INT UNSIGNED NOT NULL,
    id_produktu INT UNSIGNED DEFAULT NULL,
    operacja ENUM(
    'dodanie',
    'edycja',
    'usunięcie',
    'wydanie',
    'inwentaryzacja',
    'dodanie_do_słownika',
    'edycja_słownika',
    'usunięcie_z_słownika') NOT NULL,
    data_operacji TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dane_przed JSON DEFAULT NULL,
    dane_po JSON DEFAULT NULL,
    CONSTRAINT fk_historia_uzytkownik
        FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- =========================================================
-- INDEKSY POMOCNICZE
-- =========================================================
CREATE INDEX idx_magazyn_nazwa ON magazyn(nazwa);
CREATE INDEX idx_magazyn_kategoria ON magazyn(kategoria);
CREATE INDEX idx_magazyn_lokalizacja ON magazyn(lokalizacja);

CREATE INDEX idx_wydania_id_produktu ON wydania(id_produktu);
CREATE INDEX idx_wydania_id_uzytkownika ON wydania(id_uzytkownika);

CREATE INDEX idx_inwentaryzacje_id_uzytkownika ON inwentaryzacje(id_uzytkownika);
CREATE INDEX idx_inwentaryzacje_numer ON inwentaryzacje(numer_inwentaryzacji);

CREATE INDEX idx_inwentaryzacja_pozycja_id_inwentaryzacji ON inwentaryzacja_pozycja(id_inwentaryzacji);
CREATE INDEX idx_inwentaryzacja_pozycja_id_produktu ON inwentaryzacja_pozycja(id_produktu);

CREATE INDEX idx_historia_id_uzytkownika ON historia_operacji(id_uzytkownika);
CREATE INDEX idx_historia_id_produktu ON historia_operacji(id_produktu);
CREATE INDEX idx_historia_data_operacji ON historia_operacji(data_operacji);

CREATE INDEX idx_slownik_kategorie_nazwa ON slownik_kategorie(nazwa);
CREATE INDEX idx_slownik_lokalizacje_nazwa ON slownik_lokalizacje(nazwa);

-- =========================================================
-- TRIGGER: KONTROLA STANU PRZY WYDANIU
-- =========================================================
DELIMITER //

CREATE TRIGGER wydanie_kontrola_stanu
BEFORE INSERT ON wydania
FOR EACH ROW
BEGIN
    DECLARE stan_aktualny INT;

    IF NEW.id_produktu IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Nie znaleziono pozycji w magazynie';
    END IF;

    SELECT ilosc
    INTO stan_aktualny
    FROM magazyn
    WHERE id = NEW.id_produktu
    FOR UPDATE;

    IF stan_aktualny IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Nie znaleziono pozycji w magazynie';
    END IF;

    IF stan_aktualny < NEW.ilosc THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Nie można wydać więcej niż jest na stanie';
    ELSE
        UPDATE magazyn
        SET ilosc = ilosc - NEW.ilosc
        WHERE id = NEW.id_produktu;
    END IF;
END//

DELIMITER ;

-- =========================================================
-- PRZYKŁADOWY UŻYTKOWNIK ADMIN
-- login: AdminSystemu
-- hasło: admin123
-- =========================================================
INSERT INTO uzytkownicy (login, haslo, imie, nazwisko, status_konta, rola)
VALUES (
    'AdminSystemu',
    '$2y$10$0fCR/0IOOd8ae16cH1aNGeT9UrAI3II3YZOwXnWY2cZT6JUfbOpgm',
    'Admin',
    'Systemu',
    'aktywne',
    'admin'
);

-- =========================================================
-- JEDNORAZOWE UZUPEŁNIENIE SŁOWNIKÓW Z ISTNIEJĄCYCH DANYCH
-- ODPAL TYLKO JEŚLI MASZ JUŻ DANE W MAGAZYN
-- =========================================================
INSERT IGNORE INTO slownik_kategorie (nazwa)
SELECT DISTINCT kategoria
FROM magazyn
WHERE kategoria IS NOT NULL
  AND TRIM(kategoria) <> '';

INSERT IGNORE INTO slownik_lokalizacje (nazwa)
SELECT DISTINCT lokalizacja
FROM magazyn
WHERE lokalizacja IS NOT NULL
  AND TRIM(lokalizacja) <> '';

INSERT INTO `slownik_kategorie` (`id`, `nazwa`, `aktywna`, `data_utworzenia`) VALUES
(1, 'AIO', 1, '2026-03-18 13:17:32'),
(2, 'Akcesoria', 1, '2026-03-18 13:17:32'),
(3, 'Akcesoria FTTH', 1, '2026-03-18 13:17:32'),
(4, 'Akcesoria GSM', 1, '2026-03-18 13:17:32'),
(5, 'Akcesoria PC', 1, '2026-03-18 13:17:32'),
(6, 'Akcesoria POE', 1, '2026-03-18 13:17:32'),
(7, 'Alarmy', 1, '2026-03-18 13:17:32'),
(8, 'Audio', 1, '2026-03-18 13:17:32'),
(9, 'Automatyka pomieszczeń', 1, '2026-03-18 13:17:32'),
(10, 'Bęben', 1, '2026-03-18 13:17:32'),
(11, 'Desktopy', 1, '2026-03-18 13:17:32'),
(12, 'Drukarki', 1, '2026-03-18 13:17:32'),
(13, 'Dyski', 1, '2026-03-18 13:17:32'),
(14, 'Elektronika', 1, '2026-03-18 13:17:32'),
(15, 'Elektryka', 1, '2026-03-18 13:17:32'),
(16, 'Kamery', 1, '2026-03-18 13:17:32'),
(17, 'Kable do ładowarek', 1, '2026-03-18 13:17:32'),
(18, 'Kable USB', 1, '2026-03-18 13:17:32'),
(19, 'Kable UTP', 1, '2026-03-18 13:17:32'),
(20, 'Kable wideo', 1, '2026-03-18 13:17:32'),
(21, 'Kable zasilające', 1, '2026-03-18 13:17:32'),
(22, 'Karty sieciowe', 1, '2026-03-18 13:17:32'),
(23, 'Klawiatury', 1, '2026-03-18 13:17:32'),
(24, 'Laptopy', 1, '2026-03-18 13:17:32'),
(25, 'Listwy zasilające', 1, '2026-03-18 13:17:32'),
(26, 'Monitory', 1, '2026-03-18 13:17:32'),
(27, 'Myszki', 1, '2026-03-18 13:17:32'),
(28, 'Podstawki', 1, '2026-03-18 13:17:32'),
(29, 'Powerline', 1, '2026-03-18 13:17:32'),
(30, 'Przejściówki', 1, '2026-03-18 13:17:32'),
(31, 'Routery', 1, '2026-03-18 13:17:32'),
(32, 'Skanery', 1, '2026-03-18 13:17:32'),
(33, 'Switche', 1, '2026-03-18 13:17:32'),
(34, 'Tonery', 1, '2026-03-18 13:17:32'),
(35, 'UPS', 1, '2026-03-18 13:17:32'),
(36, 'Uchwyty', 1, '2026-03-18 13:17:32'),
(37, 'Wentylatory', 1, '2026-03-18 13:17:32'),
(38, 'Zasilacze', 1, '2026-03-18 13:17:32'),
(39, 'Ładowarki', 1, '2026-03-18 13:17:32');

INSERT INTO `slownik_lokalizacje` (`id`, `nazwa`, `aktywna`, `data_utworzenia`) VALUES
(1, 'INF-01-A', 1, '2026-03-18 13:17:56'),
(2, 'INF-01-B', 1, '2026-03-18 13:17:56'),
(3, 'INF-01-C', 1, '2026-03-18 13:17:56'),
(4, 'INF-01-D', 1, '2026-03-18 13:17:56'),
(5, 'INF-02-A', 1, '2026-03-18 13:17:56'),
(6, 'INF-02-B', 1, '2026-03-18 13:17:56'),
(7, 'INF-02-C', 1, '2026-03-18 13:17:56'),
(8, 'INF-02-D', 1, '2026-03-18 13:17:56'),
(9, 'INF-02-E', 1, '2026-03-18 13:17:56'),
(10, 'INF-03-A', 1, '2026-03-18 13:17:56'),
(11, 'INF-03-B', 1, '2026-03-18 13:17:56'),
(12, 'INF-03-C', 1, '2026-03-18 13:17:56'),
(13, 'INF-03-D', 1, '2026-03-18 13:17:56'),
(14, 'INF-03-E', 1, '2026-03-18 13:17:56'),
(15, 'INF-04-A', 1, '2026-03-18 13:17:56'),
(16, 'INF-04-B', 1, '2026-03-18 13:17:56'),
(17, 'INF-04-C', 1, '2026-03-18 13:17:56'),
(18, 'INF-04-D', 1, '2026-03-18 13:17:56');