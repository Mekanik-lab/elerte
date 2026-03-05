DROP DATABASE IF EXISTS magazyn;
CREATE DATABASE magazyn CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci;
USE magazyn;

CREATE TABLE magazyn (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nazwa VARCHAR(500) NOT NULL,
    kategoria VARCHAR(500),
    ilosc INT NOT NULL CHECK (ilosc >= 0),
    lokalizacja VARCHAR(500),
    uwagi VARCHAR(500)
);

CREATE TABLE wydania (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_i_godzina TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pracownik VARCHAR(250) NOT NULL,
    id_pozycji INT UNSIGNED NOT NULL,
    ilosc INT NOT NULL CHECK (ilosc > 0),
    powod VARCHAR(500),
    CONSTRAINT fk_wydania_magazyn
      FOREIGN KEY (id_pozycji) REFERENCES magazyn(id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE TABLE inwentaryzacja_sesja (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_sesji DATETIME NOT NULL,
    stworzone_przez VARCHAR(500)
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

DROP TRIGGER IF EXISTS wydanie_kontrola_stanu//
CREATE TRIGGER wydanie_kontrola_stanu
BEFORE INSERT ON wydania
FOR EACH ROW
BEGIN
    DECLARE stan_aktualny INT;

    SELECT ilosc INTO stan_aktualny
    FROM magazyn
    WHERE id = NEW.id_pozycji
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
        WHERE id = NEW.id_pozycji;
    END IF;
END//

DROP TRIGGER IF EXISTS policz_roznice_przy_inwentaryzacji//
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

/* Po dodaniu wpisu inwentaryzacji od razu nadpisz stan magazynu */
DROP TRIGGER IF EXISTS zastosuj_inwentaryzacje_od_razu//
CREATE TRIGGER zastosuj_inwentaryzacje_od_razu
AFTER INSERT ON inwentaryzacja
FOR EACH ROW
BEGIN
    UPDATE magazyn
    SET ilosc = NEW.stan
    WHERE id = NEW.id_produktu;
END//

DELIMITER ;