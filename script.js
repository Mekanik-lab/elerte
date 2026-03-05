const header = document.getElementsByTagName("header")[0];
const closeFormButton = document.getElementById("closeFormButton");
const formContainer = document.getElementById("formContainer");
const magazineButton = document.getElementById("magazineButton");
const issueButton = document.getElementById("issueButton");
const inventoryButton = document.getElementById("inventoryButton");
const data = document.getElementById('data');

hideForm();
renderMagazineButtons("magazyn");

function highlightMagazineTab() {
    document.getElementsByClassName("tab")[0].style.backgroundColor = "rgb(197, 128, 0)";
    document.getElementsByClassName("tab")[1].style.backgroundColor = "orange";
    document.getElementsByClassName("tab")[2].style.backgroundColor = "orange";  
}

function highlightIssueTab() {
    document.getElementsByClassName("tab")[0].style.backgroundColor = "orange";
    document.getElementsByClassName("tab")[1].style.backgroundColor = "rgb(197, 128, 0)";
    document.getElementsByClassName("tab")[2].style.backgroundColor = "orange";  
}

function highlightInventoryTab() {
    document.getElementsByClassName("tab")[0].style.backgroundColor = "orange";
    document.getElementsByClassName("tab")[1].style.backgroundColor = "orange";
    document.getElementsByClassName("tab")[2].style.backgroundColor = "rgb(197, 128, 0)";  
}

function renderMagazineButtons(sectionName) {
    header.innerHTML = 
    `<h1>Magazynek IT</h1>
    <button onclick="renderForm('magazineAddProduct')">Dodaj</button>
        <button onclick="renderForm('magazineEditProduct')">Edytuj</button>
        <button onclick="renderForm('magazineDeleteProduct')">Usuń</button>
        <button onclick="renderForm('searchProductUsingName')">Filtruj po nazwie produktu</button>`;
    highlightMagazineTab();
    loadTable(sectionName);
}

function loadTable(tableName) {
    fetch(`getTable.php?table=${tableName}`, {
        headers: {
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(res => res.text())
    .then(html => {
        console.log(html);
        data.innerHTML = html;
    });
}

function hideForm() {
    formContainer.style.display="none";
    formContainer.innerHTML=``;
}

function loadSection(sectionName) {
    switch (sectionName) {
        case "magazyn":
            renderMagazineButtons(sectionName);
            highlightMagazineTab();
            loadTable(sectionName);
            break;
        case "wydania":
            header.innerHTML = 
            `
                <h1>Magazynek IT</h1>
                <button onclick="renderForm('issue')">Wydanie</button>
            `;
            highlightIssueTab();
            magazineButton.removeEventListener("click", highlightMagazineTab);
            loadTable(sectionName);
            break;
        case "inwentaryzacja":
            header.innerHTML = 
            `
                <h1>Magazynek IT</h1>
                <button onclick="renderForm('inventory')">Inwenatyzacja</button>
            `;
            highlightInventoryTab();
            issueButton.removeEventListener("click", highlightIssueTab);
            loadTable(sectionName);
            break;
        default:
            renderMagazineButtons();
    }
}

function renderMagazineAddProduct() {
    formContainer.style.display = "flex";
    formContainer.innerHTML =
        `<button onclick="hideForm()" id="closeFormButton">&times;</button>
        <form method="POST" action="index.php">
            <input type="hidden" name="addProduct" value="addProduct">
            <label>Nazwa:</label>
            <input type="text" name="productName">
            <label>Kategoria:</label>
            <input type="text" name="productCategory">
            <label>Ilość:</label>
            <input type="number" min="1" name="productQuantity">
            <label>Lokalizacja:</label>  
            <input type="text" name="productAdress">
            <label>Uwagi:</label>
            <input type="text" name="productComments">
            <button type="submit" id="submitButton">Zatwierdź</button>
        </form>`;
}

function renderForm(formName) {
    switch (formName) {
        case "magazineAddProduct":
            renderMagazineAddProduct();
            break;
        case "magazineEditProduct":
            formContainer.style.display = "flex";
            formContainer.innerHTML = 
            `<button onclick="hideForm()" id="closeFormButton">&times;</button>
            <form method="POST" action="index.php">
                <input type="hidden" name="addProduct" value="addProduct">
                <label>ID produktu:</label>
                <input type="number" min="1" name="productId">
                <label>Nazwa:</label>
                <input type="text" name="productName">
                <label>Kategoria:</label>
                <input type="text" name="category">
                <label>Ilość:</label>
                <input type="number" min="1" name="quantity">
                <label>Lokalizacja:</label>  
                <input type="text" name="adress">
                <label>Uwagi:</label>
                <input type="text" name="productComments">
                <button type="submit" id="submitButton">Zatwierdź</button>
            </form>`;
            break;
        case "magazineDeleteProduct":
            formContainer.style.display = "flex";
            formContainer.innerHTML = 
            `<button onclick="hideForm()" id="closeFormButton">&times;</button>
            <form method="POST" action="index.php">
                <input type="hidden" name="deleteProduct" value="deleteProduct">
                <label>ID produktu:</label>
                <input type="number" name="prdouctId">
                <button type="submit" id="submitButton">Zatwierdź</button>
            </form>`;
            break;
        case "searchProductUsingName":
            formContainer.style.display = "flex";
            formContainer.innerHTML =
            `<button onclick="hideForm()" id="closeFormButton">&times;</button>
            <form method="POST" action="index.php"></form>
                <input type="hidden" name="searchProductUsingName" value="searchProductUsingName">
                <label>Nazwa produktu:</label>
                <input type="text" name="searchProductName">
                <button type="submit" id="submitButton">Zatwierdź</button>
            </form>`;
            break;
        case "issue":
            formContainer.style.display = "flex";
            formContainer.innerHTML =
            `<button onclick="hideForm()" id="closeFormButton">&times;</button>
            <form method="POST" action="index.php">
                <input type="hidden" name="issue" value="issue">
                <label>Pracownik:</label>
                <input type="text" name="employee">
                <label>Pozycja:</label>
                <input type="number" min="1" name="positionId">
                <label>Ilość:</label>
                <input type="number" min="1" name="issueQuantity">
                <label>Powód:</label>
                <input type="text" name="issueComment">
                <button type="submit" id="submitButton">Zatwierdź</button>
            </form>`;
            break;
        case "inventory":
            formContainer.style.display = "flex";
            formContainer.innerHTML =
            `<button onclick="hideForm()" id="closeFormButton">&times;</button>
            <form method="POST" action="index.php">
                <input type="hidden" name="inventory" value="inventory">
                <label>Pracownik:</label>
                <input type="text" name="inventoryEmployee">
                <label>Stan:</label>
                <input type="number" min="1" name="inventoryQuantity">
                <button type="submit" id="submitButton">Zatwierdź</button>
            </form>`;
            break;
    }
}