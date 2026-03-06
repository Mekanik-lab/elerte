DROP DATABASE IF EXISTS magazyn;
CREATE DATABASE magazyn CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
USE magazyn;

CREATE TABLE uzytkownicy (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(500) NOT NULL,
    haslo VARCHAR(255) NOT NULL,
    imie VARCHAR(250) NOT NULL,
    nazwisko VARCHAR(250) NOT NULL,
    status_konta ENUM("aktywne", "nieaktywne") NOT NULL DEFAULT 'aktywne',
    rola ENUM("admin", "user") DEFAULT 'user'
);

CREATE TABLE magazyn (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_uzytkownika INT UNSIGNED NOT NULL,
    nazwa VARCHAR(500) NOT NULL,
    kategoria VARCHAR(500),
    jednostka VARCHAR(10),
    ilosc INT NOT NULL CHECK (ilosc >= 0),
    lokalizacja VARCHAR(500),
    uwagi VARCHAR(500),
    CONSTRAINT fk_magazyn_uzytkownik
        FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE wydania (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_produktu INT UNSIGNED NOT NULL,
    id_uzytkownika INT UNSIGNED NOT NULL,
    data_i_godzina TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ilosc INT NOT NULL CHECK (ilosc > 0),
    powod VARCHAR(500),
    CONSTRAINT fk_wydania_uzytkownik
        FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_wydania_magazyn
        FOREIGN KEY (id_produktu) REFERENCES magazyn(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE inwentaryzacja_sesja (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_uzytkownika INT UNSIGNED NOT NULL,
    data_sesji DATETIME NOT NULL,
    CONSTRAINT fk_inwentaryzacja_sesja_uzytkownik
        FOREIGN KEY (id_uzytkownika) REFERENCES uzytkownicy(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE inwentaryzacja (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_sesji INT UNSIGNED NOT NULL,
    id_produktu INT UNSIGNED NOT NULL,
    stan INT NOT NULL CHECK (stan >= 0),
    roznica INT,
    CONSTRAINT fk_inw_sesja
        FOREIGN KEY (id_sesji) REFERENCES inwentaryzacja_sesja(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_inw_magazyn
        FOREIGN KEY (id_produktu) REFERENCES magazyn(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

DELIMITER //

CREATE TRIGGER wydanie_kontrola_stanu
BEFORE INSERT ON wydania
FOR EACH ROW
BEGIN
    DECLARE stan_aktualny INT;

    SELECT ilosc INTO stan_aktualny
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

CREATE TRIGGER policz_roznice_przy_inwentaryzacji
BEFORE INSERT ON inwentaryzacja
FOR EACH ROW
BEGIN
    DECLARE stan_magazynowy INT;

    SELECT ilosc INTO stan_magazynowy
    FROM magazyn
    WHERE id = NEW.id_produktu;

    IF stan_magazynowy IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Nie znaleziono produktu w magazynie';
    END IF;

    SET NEW.roznica = NEW.stan - stan_magazynowy;
END//

CREATE TRIGGER zastosuj_inwentaryzacje_od_razu
AFTER INSERT ON inwentaryzacja
FOR EACH ROW
BEGIN
    UPDATE magazyn
    SET ilosc = NEW.stan
    WHERE id = NEW.id_produktu;
END//

DELIMITER ;