<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magazynek IT</title>
    <link rel="shortcut icon" href="./images/favico.png" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
    </header>
    <main>
        <aside>
        <section class="tab">
            <button onclick="loadSection('magazyn')" id="magazineButton">Magazyn</button>
        </section>
        <section class="tab">
           <button onclick="loadSection('wydania')" id="issueButton">Wydania</button> 
        </section>
        <section class="tab">
            <button onclick="loadSection('inwentaryzacja')" id="inventoryButton">Inwentaryzacja</button>
        </section>
    </aside>
    <section id="data">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nazwa produktu</th>
                <th>Kategoria</th>
                <th>Ilość</th>
                <th>Lokalizacja</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>001</td>
                <td>Klawiatura Logitech K120</td>
                <td>Peripherals</td>
                <td>34</td>
                <td>Regał A1</td>
            </tr>
            <tr>
                <td>002</td>
                <td>Myszka Razer DeathAdder</td>
                <td>Peripherals</td>
                <td>12</td>
                <td>Regał A2</td>
            </tr>
            <tr>
                <td>002</td>
                <td>Myszka Razer DeathAdder</td>
                <td>Peripherals</td>
                <td>12</td>
                <td>Regał A2</td>
            </tr>
            <tr>
                <td>002</td>
                <td>Myszka Razer DeathAdder</td>
                <td>Peripherals</td>
                <td>12</td>
                <td>Regał A2</td>
            </tr>
            <tr>
                <td>002</td>
                <td>Myszka Razer DeathAdder</td>
                <td>Peripherals</td>
                <td>12</td>
                <td>Regał A2</td>
            </tr>
            <tr>
                <td>002</td>
                <td>Myszka Razer DeathAdder</td>
                <td>Peripherals</td>
                <td>12</td>
                <td>Regał A2</td>
            </tr>
            
        </tbody>
    </table>
    </section>
    </main>
    <aside id="formContainer">
    </aside>
    <script src="script.js" defer></script>
</body>
</html>