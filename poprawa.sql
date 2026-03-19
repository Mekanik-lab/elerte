ALTER TABLE historia_operacji
MODIFY operacja ENUM(
    'dodanie',
    'edycja',
    'usunięcie',
    'wydanie',
    'inwentaryzacja',
    'dodanie_do_słownika',
    'edycja_słownika',
    'usunięcie_z_słownika'
) NOT NULL;


CREATE TABLE slownik_lokalizacje (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    nazwa VARCHAR(255) NOT NULL UNIQUE,
    aktywna BOOLEAN NOT NULL DEFAULT TRUE,
    data_utworzenia DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE slownik_kategorie (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    nazwa VARCHAR(255) NOT NULL UNIQUE,
    aktywna BOOLEAN NOT NULL DEFAULT TRUE,
    data_utworzenia DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_slownik_kategorie_nazwa ON slownik_kategorie(nazwa);
CREATE INDEX idx_slownik_lokalizacje_nazwa ON slownik_lokalizacje(nazwa);

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