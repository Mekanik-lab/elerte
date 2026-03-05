document.addEventListener("DOMContentLoaded", () => {

const headerContent = document.getElementById("headerContent");
const data = document.getElementById("data");
const formContainer = document.getElementById("formContainer");
const formModalEl = document.getElementById("formModal");
const formModalTitle = document.getElementById("formModalTitle");

const formModal = new bootstrap.Modal(formModalEl);

init();

function init() {
  document.querySelectorAll(".tab-btn").forEach(btn => {
    btn.addEventListener("click", () => loadSection(btn.dataset.section));
  });
  loadSection("magazyn");
}

function setActiveTab(sectionName) {
  document.querySelectorAll(".tab-btn").forEach(btn => {
    const isActive = btn.dataset.section === sectionName;
    btn.classList.toggle("btn-light", isActive);
    btn.classList.toggle("text-dark", isActive);

    btn.classList.toggle("btn-outline-light", !isActive);
    btn.classList.toggle("text-white", !isActive);
  });
}

function loadTable(tableName) {
  fetch(`getTable.php?table=${encodeURIComponent(tableName)}`, {
    headers: { "X-Requested-With": "XMLHttpRequest" }
  })
    .then(res => res.text())
    .then(html => {
      data.innerHTML = html;
    });
}

function loadSection(sectionName) {
  setActiveTab(sectionName);

  switch (sectionName) {
    case "magazyn":
      headerContent.innerHTML = `
        <h1 class="h4 m-0">Magazynek IT</h1>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-light" data-form="magazineAddProduct">Dodaj</button>
          <button class="btn btn-light" data-form="magazineEditProduct">Edytuj</button>
          <button class="btn btn-danger" data-form="magazineDeleteProduct">Usuń</button>
          <button class="btn btn-dark" data-form="searchProductByName">Filtruj po nazwie</button>
        </div>
      `;
      bindHeaderButtons();
      loadTable(sectionName);
      break;

    case "wydania":
      headerContent.innerHTML = `
        <h1 class="h4 m-0">Magazynek IT</h1>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-light" data-form="issue">Wydanie</button>
        </div>
      `;
      bindHeaderButtons();
      loadTable(sectionName);
      break;

    case "inwentaryzacja":
      headerContent.innerHTML = `
        <h1 class="h4 m-0">Magazynek IT</h1>
        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-light" data-form="inventory">Inwentaryzacja</button>
        </div>
      `;
      bindHeaderButtons();
      loadTable(sectionName);
      break;

    case "inwentaryzacja_sesja":
      headerContent.innerHTML = `
        <h1 class="h4 m-0">Magazynek IT</h1>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge text-bg-dark">Podgląd sesji</span>
        </div>
      `;
      bindHeaderButtons();
      loadTable(sectionName);
      break;
  }
}

function bindHeaderButtons() {
  headerContent.querySelectorAll("button[data-form]").forEach(btn => {
    btn.addEventListener("click", () => renderForm(btn.dataset.form));
  });
}

function hideForm() {
  formContainer.innerHTML = "";
  formModal.hide();
}

function showForm(title, html) {
  formModalTitle.textContent = title;
  formContainer.innerHTML = html;
  formModal.show();

  const filterForm = formContainer.querySelector("form[data-filter='1']");
  if (filterForm) filterForm.addEventListener("submit", submitFilter);
}

function submitFilter(e) {
  e.preventDefault();
  const form = e.target;

  fetch("getTable.php", {
    method: "POST",
    headers: { "X-Requested-With": "XMLHttpRequest" },
    body: new FormData(form)
  })
    .then(res => res.text())
    .then(html => {
      data.innerHTML = html;
      hideForm();
    });
}

function renderForm(formName) {
  switch (formName) {
    case "magazineAddProduct":
      showForm("Dodaj produkt", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="addProduct" value="addProduct">

          <div><label class="form-label">Nazwa</label>
            <input type="text" name="productName" class="form-control" required></div>

          <div><label class="form-label">Kategoria</label>
            <input type="text" name="productCategory" class="form-control"></div>

          <div><label class="form-label">Ilość</label>
            <input type="number" min="0" name="productQuantity" class="form-control" required></div>

          <div><label class="form-label">Lokalizacja</label>
            <input type="text" name="productAdress" class="form-control"></div>

          <div><label class="form-label">Uwagi</label>
            <input type="text" name="productComments" class="form-control"></div>

          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
      `);
      break;

    case "magazineEditProduct":
      showForm("Edytuj produkt", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="editProduct" value="editProduct">

          <div><label class="form-label">ID produktu</label>
            <input type="number" min="1" name="productId" class="form-control" required></div>

          <div><label class="form-label">Nazwa</label>
            <input type="text" name="productName" class="form-control" required></div>

          <div><label class="form-label">Kategoria</label>
            <input type="text" name="productCategory" class="form-control"></div>

          <div><label class="form-label">Ilość</label>
            <input type="number" min="0" name="productQuantity" class="form-control" required></div>

          <div><label class="form-label">Lokalizacja</label>
            <input type="text" name="productAdress" class="form-control"></div>

          <div><label class="form-label">Uwagi</label>
            <input type="text" name="productComments" class="form-control"></div>

          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
      `);
      break;

    case "magazineDeleteProduct":
      showForm("Usuń produkt", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="deleteProduct" value="deleteProduct">

          <div>
            <label class="form-label">ID produktu</label>
            <input type="number" min="1" name="productId" class="form-control" required>
            <div class="form-text">Uwaga: usuwa produkt oraz powiązane rekordy (wydania / inwentaryzacja).</div>
          </div>

          <button type="submit" class="btn btn-danger">Usuń</button>
        </form>
      `);
      break;

    case "searchProductByName":
      showForm("Filtruj po nazwie", `
        <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
          <input type="hidden" name="searchProductByName" value="searchProductByName">

          <div><label class="form-label">Nazwa produktu (dokładnie)</label>
            <input type="text" name="productName" class="form-control" required></div>

          <button type="submit" class="btn btn-dark">Szukaj</button>
        </form>
      `);
      break;

    case "issue":
      showForm("Wydanie", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="issue" value="issue">

          <div><label class="form-label">Pracownik</label>
            <input type="text" name="employee" class="form-control" required></div>

          <div><label class="form-label">ID pozycji</label>
            <input type="number" min="1" name="positionId" class="form-control" required></div>

          <div><label class="form-label">Ilość</label>
            <input type="number" min="1" name="issueQuantity" class="form-control" required></div>

          <div><label class="form-label">Powód</label>
            <input type="text" name="issueComment" class="form-control"></div>

          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
      `);
      break;

    case "inventory":
      showForm("Inwentaryzacja (nowa sesja)", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="inventory" value="inventory">

          <div><label class="form-label">Pracownik</label>
            <input type="text" name="inventoryEmployee" class="form-control" required></div>

          <div><label class="form-label">ID produktu</label>
            <input type="number" min="1" name="inventoryProductId" class="form-control" required></div>

          <div><label class="form-label">Stan</label>
            <input type="number" min="0" name="inventoryQuantity" class="form-control" required></div>

          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
      `);
      break;
  }
}});