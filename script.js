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
        bindRowButtons();
      });
  }

  function loadSettings() {
    data.innerHTML = `
    <div class="d-flex flex-wrap gap-2">
      <a href="deactivation.php" id="deactivateBtn" class="btn btn-dark text-start fw-semibold mt-0">Dezaktywuj konto</a>
      <button class="btn btn-dark fw-semibold" data-form="zmien_login">Zmień login</button>
      <button class="btn btn-dark fw-semibold" data-form="zmien_haslo">Zmień hasło</button>
    </div>
    `;

    const deactivateBtn = document.getElementById("deactivateBtn");
    deactivateBtn.addEventListener("click", function(e) {
    if (!confirm("Czy na pewno chcesz nieodwracalnie dezaktywować konto? Po dezaktywacji logowanie będzie niemożliwe.")) {
      e.preventDefault();
    }
  });

  data.querySelectorAll("button[data-form]").forEach(btn => {
    btn.addEventListener("click", () => renderForm(btn.dataset.form));
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
            <button class="btn btn-light" data-form="searchProductByName">Filtruj po nazwie</button>
          </div>
        `;
        bindHeaderButtons();
        loadTable(sectionName);
        break;

      case "wydania":
        headerContent.innerHTML = `<h1 class="h4 m-0">Wydania</h1>`;
        loadTable(sectionName);
        break;

      case "inwentaryzacja":
        headerContent.innerHTML = `<h1 class="h4 m-0">Inwentaryzacja</h1>`;
        loadTable(sectionName);
        break;

      case "inwentaryzacja_sesja":
        headerContent.innerHTML = `<h1 class="h4 m-0">Inwentaryzacja sesja</h1>`;
        loadTable(sectionName);
        break;

      case "uzytkownicy":
        headerContent.innerHTML = `<h1 class="h4 m-0">Użytkownicy</h1>`;
        loadTable(sectionName);
        break;
      
      case "ustawienia_konta":
        headerContent.innerHTML = `<h1 class="h4 m-0">Ustawienia konta</h1>`;
        loadSettings();
    }
  }

  function bindHeaderButtons() {
    headerContent.querySelectorAll("button[data-form]").forEach(btn => {
      btn.addEventListener("click", () => renderForm(btn.dataset.form));
    });
  }

  function bindRowButtons() {
    document.querySelectorAll("tr[data-id]").forEach(row => {
      const id = row.dataset.id;

      const cells = Array.from(row.querySelectorAll("td")).map(td =>
        td.textContent.trim()
      );

      row.querySelectorAll("button").forEach(btn => {
        btn.addEventListener("click", () => {
          const action = btn.classList.contains("editBtn") ? "edit" :
                         btn.classList.contains("deleteBtn") ? "delete" :
                         btn.classList.contains("issueBtn") ? "issue" :
                         btn.classList.contains("inventoryBtn") ? "inventory" : null;

          if (!action) return;
          renderRowForm(action, id, cells);
        });
      });
    });
  }

  function showForm(title, html) {
    formModalTitle.textContent = title;
    formContainer.innerHTML = html;
    formModal.show();

    const filterForm = formContainer.querySelector("form[data-filter='1']");
    if (filterForm) {
      filterForm.addEventListener("submit", submitFilter);
    }
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
        formModal.hide();
        formContainer.innerHTML = "";
      });
  }

  function renderRowForm(action, id, cells) {

    if (action === "edit") {

      const productName = cells[3];
      const category = cells[4];
      const unit = cells[5];
      const quantity = cells[6];
      const location = cells[7];
      const comments = cells[8];

      showForm("Edytuj produkt", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="editProduct" value="editProduct">
          <input type="hidden" name="productId" value="${id}">

          <div>
            <label class="form-label">Nazwa</label>
            <input type="text" name="productName" class="form-control" value="${productName}" required>
          </div>

          <div>
            <label class="form-label">Kategoria</label>
            <input type="text" name="productCategory" class="form-control" value="${category}">
          </div>

          <div>
            <label class="form-label">Ilość</label>
            <input type="number" min="0" name="productQuantity" class="form-control" value="${quantity}" required>
          </div>

          <div>
            <label class="form-label">Jednostka</label>
            <input type="text" name="productUnit" class="form-control" value="${unit}" required>
          </div>

          <div>
            <label class="form-label">Lokalizacja</label>
            <input type="text" name="productAdress" class="form-control" value="${location}">
          </div>

          <div>
            <label class="form-label">Uwagi</label>
            <input type="text" name="productComments" class="form-control" value="${comments}">
          </div>

          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
      `);

      return;
    }

    if (action === "delete") {
      showForm("Usuń produkt", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="deleteProduct" value="deleteProduct">
          <input type="hidden" name="productId" value="${id}">
          <p>Czy na pewno chcesz usunąć produkt o ID: <strong>${id}</strong>?</p>
          <button type="submit" class="btn btn-danger">Usuń</button>
        </form>
      `);
      return;
    }

    if (action === "issue") {
      showForm("Wydanie", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="issue" value="issue">
          <input type="hidden" name="productId" value="${id}">

          <div>
            <label class="form-label">Ilość</label>
            <input type="number" min="1" name="issueQuantity" class="form-control" required>
          </div>

          <div>
            <label class="form-label">Powód</label>
            <input type="text" name="issueComment" class="form-control">
          </div>

          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
      `);
      return;
    }

    if (action === "inventory") {
      let quantity = cells[6];
      showForm("Inwentaryzacja", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="inventory" value="inventory">
          <input type="hidden" name="inventoryProductId" value="${id}">

          <div>
            <label class="form-label">Stan</label>
            <input type="number" min="0" name="inventoryQuantity" class="form-control" value="${quantity}" required>
          </div>

          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
      `);
      return;
    }
  }

  function renderForm(formName) {
    switch (formName) {

      case "magazineAddProduct":
        showForm("Dodaj produkt", `
          <form method="POST" action="index.php" class="d-grid gap-2">
            <input type="hidden" name="addProduct" value="addProduct">

            <div>
              <label class="form-label">Nazwa</label>
              <input type="text" name="productName" class="form-control" required>
            </div>

            <div>
              <label class="form-label">Kategoria</label>
              <input type="text" name="productCategory" class="form-control">
            </div>

            <div>
              <label class="form-label">Ilość</label>
              <input type="number" min="0" name="productQuantity" class="form-control" required>
            </div>

            <div>
              <label class="form-label">Jednostka</label>
              <input type="text" name="productUnit" class="form-control" required>
            </div>

            <div>
              <label class="form-label">Lokalizacja</label>
              <input type="text" name="productAdress" class="form-control">
            </div>

            <div>
              <label class="form-label">Uwagi</label>
              <input type="text" name="productComments" class="form-control">
            </div>

            <button type="submit" class="btn btn-warning">Zatwierdź</button>
          </form>
        `);
        break;

      case "searchProductByName":
        showForm("Filtruj po nazwie", `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="searchProductByName" value="searchProductByName">

            <div>
              <label class="form-label">Nazwa produktu</label>
              <input type="text" name="productName" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-warning">Szukaj</button>
          </form>
        `);
        break;

        case "zmien_login":
      showForm("Zmień login", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="changeLogin" value="1">
          <div>
            <label class="form-label">Nowy login</label>
            <input type="text" name="newLogin" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-warning">Zmień login</button>
        </form>
      `);
      break;

    case "zmien_haslo":
      showForm("Zmień hasło", `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="changePassword" value="1">
          <div>
            <label class="form-label">Nowe hasło</label>
            <input type="password" name="newPassword" class="form-control" required>
          </div>
          <div>
            <label class="form-label">Potwierdź hasło</label>
            <input type="password" name="confirmPassword" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-warning">Zmień hasło</button>
        </form>
      `);
      break;
    }
  }
});
