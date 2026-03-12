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
    haslo VARCHAR(255) NOT NULL,
    imie VARCHAR(100) NOT NULL,
    nazwisko VARCHAR(100) NOT NULL,
    status_konta ENUM('aktywne', 'nieaktywne') NOT NULL DEFAULT 'aktywne',
    rola ENUM('admin', 'user') NOT NULL DEFAULT 'user'
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
    operacja ENUM('dodanie', 'edycja', 'usunięcie', 'wydanie', 'inwentaryzacja') NOT NULL,
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
CREATE INDEX idx_wydania_id_produktu ON wydania(id_produktu);
CREATE INDEX idx_wydania_id_uzytkownika ON wydania(id_uzytkownika);
CREATE INDEX idx_inwentaryzacje_id_uzytkownika ON inwentaryzacje(id_uzytkownika);
CREATE INDEX idx_inwentaryzacja_pozycja_id_inwentaryzacji ON inwentaryzacja_pozycja(id_inwentaryzacji);
CREATE INDEX idx_inwentaryzacja_pozycja_id_produktu ON inwentaryzacja_pozycja(id_produktu);
CREATE INDEX idx_historia_id_uzytkownika ON historia_operacji(id_uzytkownika);
CREATE INDEX idx_historia_id_produktu ON historia_operacji(id_produktu);
CREATE INDEX idx_historia_data_operacji ON historia_operacji(data_operacji);

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