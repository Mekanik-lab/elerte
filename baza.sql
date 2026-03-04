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
    FOREIGN KEY (id_pozycji) REFERENCES magazyn(id)
);

CREATE TABLE inwentaryzacja_sesja (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_sesji DATETIME NOT NULL,
    stworzone_przez VARCHAR(500),
    zatwierdzone BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE inwentaryzacja (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_sesji INT UNSIGNED NOT NULL,
    id_produktu INT UNSIGNED NOT NULL,
    stan INT NOT NULL CHECK (stan >= 0),
    roznica INT,
    FOREIGN KEY (id_sesji) REFERENCES inwentaryzacja_sesja(id),
    FOREIGN KEY (id_produktu) REFERENCES magazyn(id)
);

DELIMITER //

CREATE TRIGGER wydanie_kontrola_stanu
BEFORE INSERT ON wydania
FOR EACH ROW
BEGIN
    DECLARE stan INT;

    SELECT ilosc INTO stan
    FROM magazyn
    WHERE id = NEW.id_pozycji
    FOR UPDATE;

    IF stan IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Nie znaleziono pozycji w magazynie';
    END IF;

    IF stan < NEW.ilosc THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Nie można wydać więcej niż jest na stanie';
    ELSE
        UPDATE magazyn
        SET ilosc = ilosc - NEW.ilosc
        WHERE id = NEW.id_pozycji;
    END IF;
END//

DELIMITER ;

DELIMITER //

CREATE TRIGGER policz_roznice_przy_inwentaryzacji
BEFORE INSERT ON inwentaryzacja
FOR EACH ROW
BEGIN
    DECLARE stan INT;

    SELECT ilosc INTO stan
    FROM magazyn
    WHERE id = NEW.id_produktu;

    SET NEW.roznica = NEW.stan - stan;
END//

DELIMITER ;

DELIMITER //;
CREATE TRIGGER zatwierdz_inwentaryzacje
AFTER UPDATE ON inwentaryzacja_sesja
FOR EACH ROW
BEGIN
    IF NEW.zatwierdzone = TRUE AND OLD.zatwierdzone = FALSE THEN
        UPDATE magazyn 
        JOIN inwentaryzacja ON inwentaryzacja.id_produktu = magazyn.id
        SET magazyn.ilosc = inwentaryzacja.stan
        WHERE inwentaryzacja.id_sesji = NEW.id;
    END IF;
END //
DELIMITER ;