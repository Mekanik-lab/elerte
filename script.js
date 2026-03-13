document.addEventListener("DOMContentLoaded", () => {
  const body = document.body;
  const headerContent = document.getElementById("headerContent");
  const data = document.getElementById("data");
  const formContainer = document.getElementById("formContainer");
  const formModalEl = document.getElementById("formModal");
  const formModalTitle = document.getElementById("formModalTitle");
  const formModal = new bootstrap.Modal(formModalEl);
  const welcomeName = (body.dataset.userFullname || "").trim();

  let currentSection = localStorage.getItem("lastSection") || "magazyn";
  let currentInventoryId = localStorage.getItem("currentInventoryId") || "";
  const filtersStorageKey = "sectionFilters";
  let sectionFilters = JSON.parse(localStorage.getItem(filtersStorageKey) || "{}");

  init();

  function init() {
    bindTabButtons();
    bindModalEvents();
    makeModalDraggable();
    loadSection(currentSection);
  }

  function bindTabButtons() {
    document.querySelectorAll(".tab-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const section = btn.dataset.section;
        if (!section) return;

        currentSection = section;
        clearSectionFilter(currentSection);

        if (currentSection !== "inwentaryzacja_pozycja") {
          clearCurrentInventory();
        }

        localStorage.setItem("lastSection", currentSection);
        loadSection(currentSection);
      });
    });
  }

  function bindModalEvents() {
    formModalEl.addEventListener("hidden.bs.modal", () => {
      formContainer.innerHTML = "";
    });
  }

  function makeModalDraggable() {
    const dialog = formModalEl.querySelector(".modal-dialog");
    const header = formModalEl.querySelector(".modal-header");

    if (!dialog || !header) return;

    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let initialLeft = 0;
    let initialTop = 0;
    let hasInitializedPosition = false;

    header.style.touchAction = "none";
    header.style.cursor = "move";

    header.addEventListener("pointerdown", e => {
      if (e.target.closest(".btn-close")) return;

        const rect = dialog.getBoundingClientRect();

        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;

        if (!hasInitializedPosition) {
          initialLeft = rect.left;
          initialTop = rect.top;

          dialog.style.position = "fixed";
          dialog.style.margin = "0";
          dialog.style.left = `${initialLeft}px`;
          dialog.style.top = `${initialTop}px`;
          dialog.style.transform = "none";

          hasInitializedPosition = true;
        } else {
          initialLeft = parseFloat(dialog.style.left) || rect.left;
          initialTop = parseFloat(dialog.style.top) || rect.top;
        }

        header.setPointerCapture?.(e.pointerId);
      });

      header.addEventListener("pointermove", e => {
        if (!isDragging) return;

        const nextLeft = initialLeft + (e.clientX - startX);
        const nextTop = initialTop + (e.clientY - startY);

        dialog.style.left = `${Math.max(0, nextLeft)}px`;
        dialog.style.top = `${Math.max(0, nextTop)}px`;
        dialog.style.transform = "none";
      });

      const stopDragging = e => {
        isDragging = false;
        if (e?.pointerId != null) {
          header.releasePointerCapture?.(e.pointerId);
        }
      };

      header.addEventListener("pointerup", stopDragging);
      header.addEventListener("pointercancel", stopDragging);
  }

  function setHeader(title, actionsHtml = "") {
    headerContent.innerHTML = `
      <div class="header-title-wrap">
        <h1 class="h4 m-0 fw-bold">${title}</h1>
      </div>
      <div class="header-user-wrap flex-grow-1 text-center">
        ${welcomeName ? `Witaj, ${escapeHtml(welcomeName)}` : ""}
      </div>
      <div class="header-actions-wrap d-flex justify-content-end">
        <div class="d-flex flex-wrap gap-2">
          ${actionsHtml}
        </div>
      </div>
    `;
  }

  function bindRegisterButton() {
    const registerBtn = document.getElementById("goToRegister");
    if (registerBtn) {
      registerBtn.addEventListener("click", () => {
        localStorage.setItem("lastSection", "uzytkownicy");
      });
    }
  }

  function setCurrentInventory(inventoryId) {
    currentInventoryId = String(inventoryId || "").trim();

    if (currentInventoryId) {
      localStorage.setItem("currentInventoryId", currentInventoryId);
      data.dataset.currentInventoryId = currentInventoryId;
      headerContent.dataset.currentInventoryId = currentInventoryId;
    }
  }

  function clearCurrentInventory() {
    currentInventoryId = "";
    localStorage.removeItem("currentInventoryId");
    delete data.dataset.currentInventoryId;
    delete headerContent.dataset.currentInventoryId;
  }

  function getCurrentInventoryId() {
    return (
      currentInventoryId ||
      data.dataset.currentInventoryId ||
      headerContent.dataset.currentInventoryId ||
      localStorage.getItem("currentInventoryId") ||
      ""
    );
  }

  function saveSectionFilter(sectionName, formData) {
    const filterObject = {};

    for (const [key, value] of formData.entries()) {
      filterObject[key] = value;
    }

    sectionFilters[sectionName] = filterObject;
    localStorage.setItem(filtersStorageKey, JSON.stringify(sectionFilters));
  }

  function getSectionFilter(sectionName) {
    return sectionFilters[sectionName] || null;
  }

  function clearSectionFilter(sectionName) {
    delete sectionFilters[sectionName];
    localStorage.setItem(filtersStorageKey, JSON.stringify(sectionFilters));
  }

  function buildFilterParams(sectionName, page = 1) {
    const savedFilter = getSectionFilter(sectionName);

    if (!savedFilter) {
      return null;
    }

    const params = new URLSearchParams();

    Object.entries(savedFilter).forEach(([key, value]) => {
      params.append(key, value);
    });

    params.set("page", String(page));

    if (sectionName === "inwentaryzacja_pozycja") {
      const inventoryId = getCurrentInventoryId();
      if (inventoryId) {
        params.set("inventoryId", inventoryId);
      }
    }

    if (sectionName === "magazyn" && !params.get("table")) {
      params.set("table", "magazyn");
    }

    return params;
  }

  function setActiveTab(sectionName) {
    document.querySelectorAll(".tab-btn").forEach(btn => {
      const isActive = btn.dataset.section === sectionName;

      btn.classList.toggle("btn-light", isActive);
      btn.classList.toggle("text-dark", isActive);
      btn.classList.toggle("btn-outline-light", !isActive);
    });
  }

  function loadSection(sectionName) {
    setActiveTab(sectionName);

    switch (sectionName) {
      case "magazyn":
        setHeader(
          "Magazynek IT",
          `
            <button class="btn btn-light" data-form="magazineAddProduct">Dodaj</button>
            <button class="btn btn-light" data-form="searchProduct">Filtruj</button>
          `
        );
        bindHeaderButtons();
        loadTable(sectionName, 1);
        break;

      case "wydania":
        setHeader(
          "Wydania",
          `<button class="btn btn-light" data-form="issuesFilter">Filtruj</button>`
        );
        bindHeaderButtons();
        loadTable(sectionName, 1);
        break;

      case "inwentaryzacje":
        clearCurrentInventory();
        setHeader(
          "Inwentaryzacje",
          `<button class="btn btn-light" data-form="inventoriesFilter">Filtruj</button>`
        );
        bindHeaderButtons();
        loadTable(sectionName, 1);
        break;

      case "inwentaryzacja_pozycja": {
        const inventoryId = getCurrentInventoryId();

        if (!inventoryId) {
          currentSection = "inwentaryzacje";
          localStorage.setItem("lastSection", currentSection);
          clearCurrentInventory();
          loadSection("inwentaryzacje");
          return;
        }

        setCurrentInventory(inventoryId);

        setHeader(
          "Inwentaryzacja",
          `
            <button class="btn btn-light" data-form="approveInventory">Zatwierdź inwentaryzację</button>
            <button class="btn btn-light" data-form="inventoryPositionsFilter">Filtruj</button>
            <button class="btn btn-light" id="backToInventories">Powrót</button>
          `
        );
        bindHeaderButtons();
        bindBackButton();
        loadInventoryPositions(inventoryId, 1);
        break;
      }

      case "uzytkownicy":
        setHeader(
          "Użytkownicy",
          `
            <button class="btn btn-light" data-form="usersFilter">Filtruj</button>
            <a href="register.php" id="goToRegister" class="btn btn-light text-start fw-semibold">
              Rejestruj użytkownika
            </a>
          `
        );
        bindHeaderButtons();
        bindRegisterButton();
        loadTable(sectionName, 1);
        break;

      case "ustawienia_konta":
        setHeader("Ustawienia konta");
        loadSettings();
        break;

      case "historia_operacji":
        setHeader(
          "Historia operacji",
          `<button class="btn btn-light" data-form="historyFilter">Filtruj</button>`
        );
        bindHeaderButtons();
        loadTable(sectionName, 1);
        break;

      default:
        setHeader(
          "Magazynek IT",
          `
            <button class="btn btn-light" data-form="magazineAddProduct">Dodaj</button>
            <button class="btn btn-light" data-form="searchProduct">Filtruj</button>
          `
        );
        bindHeaderButtons();
        loadTable("magazyn", 1);
        break;
    }
  }

  function bindHeaderButtons() {
    headerContent.querySelectorAll("button[data-form]").forEach(btn => {
      btn.addEventListener("click", () => renderForm(btn.dataset.form));
    });
  }

  function bindBackButton() {
    const backBtn = document.getElementById("backToInventories");
    if (backBtn) {
      backBtn.addEventListener("click", () => {
        currentSection = "inwentaryzacje";
        localStorage.setItem("lastSection", currentSection);
        clearCurrentInventory();
        loadSection("inwentaryzacje");
      });
    }
  }

  function loadTable(tableName, page = 1) {
    fetch(`getTable.php?table=${encodeURIComponent(tableName)}&page=${encodeURIComponent(page)}`, {
      headers: { "X-Requested-With": "XMLHttpRequest" }
    })
      .then(async res => {
        const html = await res.text();

        if (res.status === 403) {
          currentSection = "magazyn";
          localStorage.setItem("lastSection", currentSection);
          clearCurrentInventory();

          setHeader(
            "Magazynek IT",
            `
              <button class="btn btn-light" data-form="magazineAddProduct">Dodaj</button>
              <button class="btn btn-light" data-form="searchProduct">Filtruj</button>
            `
          );
          bindHeaderButtons();

          data.innerHTML = `<div class="alert alert-warning">Brak uprawnień do tej sekcji.</div>`;
          return;
        }

        if (!res.ok) {
          throw new Error(html || "Nie udało się pobrać danych.");
        }

        data.innerHTML = html;
        afterTableRender();
      })
      .catch(err => {
        data.innerHTML = `<div class="alert alert-danger">Błąd ładowania tabeli: ${escapeHtml(err.message || String(err))}</div>`;
      });
  }

  function loadInventoryPositions(inventoryId, page = 1) {
    setCurrentInventory(inventoryId);

    fetch("getTable.php", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: new URLSearchParams({
        table: "inwentaryzacja_pozycja",
        inventoryId: inventoryId,
        page: page
      })
    })
      .then(async res => {
        const html = await res.text();

        if (res.status === 403) {
          currentSection = "inwentaryzacje";
          localStorage.setItem("lastSection", currentSection);
          clearCurrentInventory();

          setHeader("Inwentaryzacje");
          data.innerHTML = `<div class="alert alert-warning">Brak uprawnień do tej sekcji.</div>`;
          return;
        }

        if (!res.ok) {
          throw new Error(html || "Nie udało się pobrać danych.");
        }

        data.innerHTML = html;
        afterTableRender();
      })
      .catch(err => {
        data.innerHTML = `<div class="alert alert-danger">Błąd ładowania pozycji inwentaryzacji: ${escapeHtml(err.message || String(err))}</div>`;
      });
  }

  function afterTableRender() {
    bindRowButtons();
    bindInventoryRows();
    bindPaginationButtons();

    if (currentSection === "historia_operacji") {
      showJSONInHistory();
    }
  }

  function bindPaginationButtons() {
    document.querySelectorAll(".pagination-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const page = btn.dataset.page;

        if (currentSection === "inwentaryzacja_pozycja") {
          const inventoryId = getCurrentInventoryId();
          if (!inventoryId) return;

          const params = buildFilterParams("inwentaryzacja_pozycja", page);

          if (params) {
            fetch("getTable.php", {
              method: "POST",
              headers: { "X-Requested-With": "XMLHttpRequest" },
              body: params
            })
              .then(async res => {
                const html = await res.text();

                if (!res.ok) {
                  throw new Error(html || "Nie udało się pobrać danych.");
                }

                data.innerHTML = html;
                afterTableRender();
              })
              .catch(err => {
                data.innerHTML = `<div class="alert alert-danger">Błąd ładowania pozycji inwentaryzacji: ${escapeHtml(err.message || String(err))}</div>`;
              });
          } else {
            loadInventoryPositions(inventoryId, page);
          }

          return;
        }

        const params = buildFilterParams(currentSection, page);

        if (params) {
          fetch("getTable.php", {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body: params
          })
            .then(async res => {
              const html = await res.text();

              if (res.status === 403) {
                data.innerHTML = `<div class="alert alert-warning">Brak uprawnień do tej sekcji.</div>`;
                return;
              }

              if (!res.ok) {
                throw new Error(html || "Nie udało się pobrać danych.");
              }

              data.innerHTML = html;
              afterTableRender();
            })
            .catch(err => {
              data.innerHTML = `<div class="alert alert-danger">Błąd ładowania tabeli: ${escapeHtml(err.message || String(err))}</div>`;
            });
        } else {
          loadTable(currentSection, page);
        }
      });
    });
  }

  function loadSettings() {
    data.innerHTML = `
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-dark fw-semibold" data-form="changePassword">Zmień hasło</button>
      </div>
    `;

    data.querySelectorAll("button[data-form]").forEach(btn => {
      btn.addEventListener("click", () => renderForm(btn.dataset.form));
    });
  }

  function showJSONInHistory() {
    const rows = document.querySelectorAll("tr[data-id]");

    rows.forEach(row => {
      const cells = Array.from(row.querySelectorAll("td"));
      const beforeCell = cells[7];
      const afterCell = cells[8];

      if (!beforeCell || !afterCell) return;

      let beforeRaw = beforeCell.textContent.trim();
      let afterRaw = afterCell.textContent.trim();

      const invalid = txt => !txt || txt === "null" || txt === "-" || txt.trim() === "";

      if (invalid(beforeRaw)) beforeCell.textContent = "-";
      if (invalid(afterRaw)) afterCell.textContent = "-";

      if (!invalid(beforeRaw)) {
        beforeCell.textContent = beforeRaw.length > 40 ? beforeRaw.slice(0, 40) + "..." : beforeRaw;
        beforeCell.dataset.fullJson = beforeRaw;
        beforeCell.dataset.otherJson = invalid(afterRaw) ? "{}" : afterRaw;
        beforeCell.style.cursor = "pointer";
        beforeCell.title = "Kliknij, aby zobaczyć dane PRZED";
        beforeCell.addEventListener("click", () => {
          openJSONModal(beforeCell.dataset.fullJson, beforeCell.dataset.otherJson, "before");
        });
      }

      if (!invalid(afterRaw)) {
        afterCell.textContent = afterRaw.length > 40 ? afterRaw.slice(0, 40) + "..." : afterRaw;
        afterCell.dataset.fullJson = afterRaw;
        afterCell.dataset.otherJson = invalid(beforeRaw) ? "{}" : beforeRaw;
        afterCell.style.cursor = "pointer";
        afterCell.title = "Kliknij, aby zobaczyć dane PO";
        afterCell.addEventListener("click", () => {
          openJSONModal(afterCell.dataset.otherJson, afterCell.dataset.fullJson, "after");
        });
      }
    });
  }

  function openJSONModal(beforeJson, afterJson, mode) {
    let before = {};
    let after = {};

    try { before = JSON.parse(beforeJson); } catch {}
    try { after = JSON.parse(afterJson); } catch {}

    const diffHtml = buildDiffTable(before, after, mode);

    formModalTitle.textContent = mode === "before" ? "Dane przed operacją" : "Dane po operacji";
    formContainer.innerHTML = diffHtml;
    formModal.show();
  }

  function buildDiffTable(before, after, mode) {
    const keys = new Set([...Object.keys(before), ...Object.keys(after)]);
    let rows = "";

    keys.forEach(key => {
      const oldVal = before[key];
      const newVal = after[key];
      let style = "";
      const value = mode === "before" ? oldVal : newVal;

      if (JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
        style = "background:#fff3cd";
      }

      rows += `
        <tr>
          <td class="text-center align-middle p-2"><strong>${escapeHtml(key)}</strong></td>
          <td class="text-center align-middle p-2" style="${style}">
            ${escapeHtml(formatValue(value))}
          </td>
        </tr>
      `;
    });

    return `
      <table class="table table-bordered table-sm mb-0" style="border-color: grey;">
        <thead>
          <tr>
            <th class="text-center align-middle p-2">Pole</th>
            <th class="text-center align-middle p-2">${mode === "before" ? "Przed" : "Po"}</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    `;
  }

  function formatValue(val) {
    if (val === null || val === undefined) return "-";
    if (typeof val === "object") return Object.values(val).join(", ");
    return String(val);
  }

  function bindRowButtons() {
    document.querySelectorAll("tr[data-id]").forEach(row => {
      const id = row.dataset.id;
      const cells = Array.from(row.querySelectorAll("td")).map(td => td.textContent.trim());

      row.querySelectorAll("button").forEach(btn => {
        btn.addEventListener("click", e => {
          e.stopPropagation();

          const action = btn.classList.contains("editBtn")
            ? "edit"
            : btn.classList.contains("deleteBtn")
            ? "delete"
            : btn.classList.contains("issueBtn")
            ? "issue"
            : btn.classList.contains("inventoryBtn")
            ? "inventory"
            : btn.classList.contains("changeUserLoginBtn")
            ? "changeUserLogin"
            : btn.classList.contains("changeUserPasswordBtn")
            ? "changeUserPassword"
            : btn.classList.contains("deactivateUserBtn")
            ? "deactivateUser"
            : btn.classList.contains("deleteInventoryPositionBtn")
            ? "deleteInventoryPosition"
            : null;

          if (!action) return;

          renderRowForm(action, id, cells, row);
        });
      });
    });
  }

  function bindInventoryRows() {
    document.querySelectorAll("tr[data-section='inwentaryzacja_pozycja']").forEach(row => {
      row.addEventListener("click", e => {
        if (e.target.closest("button")) return;

        const inventoryId = row.dataset.id;
        if (!inventoryId) return;

        currentSection = "inwentaryzacja_pozycja";
        localStorage.setItem("lastSection", currentSection);
        setCurrentInventory(inventoryId);

        setHeader(
          "Inwentaryzacja",
          `
            <button class="btn btn-light" data-form="approveInventory">Zatwierdź inwentaryzację</button>
            <button class="btn btn-light" data-form="inventoryPositionsFilter">Filtruj</button>
            <button class="btn btn-light" id="backToInventories">Powrót</button>
          `
        );

        bindHeaderButtons();
        bindBackButton();
        loadInventoryPositions(inventoryId, 1);
      });
    });
  }

  function renderRowForm(action, id, cells, row) {
    if (action === "edit") {
      const productName = cells[2] || "";
      const category = cells[3] || "";
      const unit = cells[4] || "";
      const quantity = cells[5] || "";
      const location = cells[6] || "";
      const comments = cells[7] || "";

      showForm(
        "Edytuj produkt",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="editProduct" value="editProduct">
          <input type="hidden" name="productId" value="${escapeHtml(id)}">
          <div>
            <label class="form-label">Nazwa</label>
            <input type="text" name="productName" class="form-control" value="${escapeHtml(productName)}" required>
          </div>
          <div>
            <label class="form-label">Kategoria</label>
            <input type="text" name="productCategory" class="form-control" value="${escapeHtml(category)}">
          </div>
          <div>
            <label class="form-label">Ilość</label>
            <input type="number" min="0" name="productQuantity" class="form-control" value="${escapeHtml(quantity)}" required>
          </div>
          <div>
            <label class="form-label">Jednostka</label>
            <input type="text" name="productUnit" class="form-control" value="${escapeHtml(unit)}" required>
          </div>
          <div>
            <label class="form-label">Lokalizacja</label>
            <input type="text" name="productAdress" class="form-control" value="${escapeHtml(location)}">
          </div>
          <div>
            <label class="form-label">Uwagi</label>
            <input type="text" name="productComments" class="form-control" value="${escapeHtml(comments)}">
          </div>
          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
        `
      );
      return;
    }

    if (action === "delete") {
      showForm(
        "Usuń produkt",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="deleteProduct" value="deleteProduct">
          <input type="hidden" name="productId" value="${escapeHtml(id)}">
          <p>Czy na pewno chcesz usunąć produkt o ID: <strong>${escapeHtml(id)}</strong>?</p>
          <button type="submit" class="btn btn-danger">Usuń</button>
        </form>
        `
      );
      return;
    }

    if (action === "issue") {
      showForm(
        "Wydanie",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="issue" value="issue">
          <input type="hidden" name="productId" value="${escapeHtml(id)}">
          <div>
            <label class="form-label">Ilość</label>
            <input type="number" min="1" name="issueQuantity" class="form-control" required>
          </div>
          <div>
            <label class="form-label">Powód</label>
            <input type="text" name="issueComment" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
        `
      );
      return;
    }

    if (action === "inventory") {
      const quantity = cells[5] || "";

      showForm(
        "Inwentaryzacja",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="inventory" value="inventory">
          <input type="hidden" name="inventoryProductId" value="${escapeHtml(id)}">
          <div>
            <label class="form-label">Stan</label>
            <input type="number" min="0" name="inventoryQuantity" class="form-control" value="${escapeHtml(quantity)}" required>
          </div>
          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
        `
      );
      return;
    }

    if (action === "deleteInventoryPosition") {
      showForm(
        "Usuń pozycję inwentaryzacji",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="deleteInventoryPosition" value="1">
          <input type="hidden" name="inventoryPositionId" value="${escapeHtml(id)}">
          <p>Czy na pewno chcesz usunąć tę pozycję z inwentaryzacji?</p>
          <button type="submit" class="btn btn-danger">Usuń</button>
        </form>
        `
      );
      return;
    }

    if (action === "changeUserLogin") {
      const login = row.querySelector("td[data-login]")?.dataset.login || cells[1] || "";

      showForm(
        "Zmień login użytkownika",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="adminChangeUserLogin" value="1">
          <input type="hidden" name="targetUserId" value="${escapeHtml(id)}">
          <div>
            <label class="form-label">Aktualny login</label>
            <input type="text" class="form-control" value="${escapeHtml(login)}" readonly>
          </div>
          <div>
            <label class="form-label">Nowy login</label>
            <input type="text" name="newLogin" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-warning">Zmień login</button>
        </form>
        `
      );
      return;
    }

    if (action === "changeUserPassword") {
      showForm(
        "Zmień hasło użytkownika",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="adminChangeUserPassword" value="1">
          <input type="hidden" name="targetUserId" value="${escapeHtml(id)}">
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
        `
      );
      return;
    }

    if (action === "deactivateUser") {
      showForm(
        "Dezaktywuj użytkownika",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="deactivateUser" value="1">
          <input type="hidden" name="targetUserId" value="${escapeHtml(id)}">
          <p>Czy na pewno chcesz dezaktywować to konto?</p>
          <button type="submit" class="btn btn-danger">Dezaktywuj</button>
        </form>
        `
      );
    }
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

    const formData = new FormData(form);
    if (!formData.get("page")) {
      formData.append("page", "1");
    }

    let tableName = formData.get("table") || currentSection;

    if (!formData.get("table") && formData.get("searchProduct") === "magazyn") {
      tableName = "magazyn";
      formData.append("table", "magazyn");
    }

    saveSectionFilter(tableName, formData);

    fetch("getTable.php", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: formData
    })
      .then(async res => {
        const html = await res.text();

        if (res.status === 403) {
          data.innerHTML = `<div class="alert alert-warning">Brak uprawnień do tej sekcji.</div>`;
          formModal.hide();
          return;
        }

        if (!res.ok) {
          throw new Error(html || "Nie udało się przefiltrować danych.");
        }

        data.innerHTML = html;
        afterTableRender();
        formModal.hide();
      })
      .catch(err => {
        data.innerHTML = `<div class="alert alert-danger">Błąd filtrowania: ${escapeHtml(err.message || String(err))}</div>`;
      });
  }

  function renderForm(formName) {
    switch (formName) {
      case "historyFilter":
        showForm(
          "Filtruj historię",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="historia_operacji">
            <input type="hidden" name="page" value="1">
            <div>
              <label class="form-label">Login</label>
              <input type="text" name="login" class="form-control">
            </div>
            <div>
              <label class="form-label">Nazwa produktu</label>
              <input type="text" name="productName" class="form-control">
            </div>
            <div>
              <label class="form-label">Operacja</label>
              <select name="operationName" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="dodanie">Dodanie</option>
                <option value="edycja">Edycja</option>
                <option value="usunięcie">Usunięcie</option>
                <option value="wydanie">Wydanie</option>
                <option value="inwentaryzacja">Inwentaryzacja</option>
              </select>
            </div>
            <div>
              <label class="form-label">Data</label>
              <input type="date" name="operationDate" class="form-control">
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" selected>Id</option>
                <option value="Login">Login</option>
                <option value="Id produktu">Id produktu</option>
                <option value="Imię i nazwisko">Imię i nazwisko</option>
                <option value="Nazwa produktu">Nazwa produktu</option>
                <option value="Operacja">Operacja</option>
                <option value="Data operacji">Data operacji</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" selected>Rosnąco</option>
                <option value="DESC">Malejąco</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
          </form>
          `
        );
        break;

      case "issuesFilter":
        showForm(
          "Filtruj wydania",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="wydania">
            <input type="hidden" name="page" value="1">
            <div>
              <label class="form-label">Nazwisko</label>
              <input type="text" name="surname" class="form-control">
            </div>
            <div>
              <label class="form-label">Nazwa produktu</label>
              <input type="text" name="productName" class="form-control">
            </div>
            <div>
              <label class="form-label">Powód</label>
              <input type="text" name="reason" class="form-control">
            </div>
            <div>
              <label class="form-label">Data</label>
              <input type="date" name="issueDate" class="form-control">
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Ilość od</label>
                <input type="number" min="0" name="quantityFrom" class="form-control">
              </div>
              <div class="col">
                <label class="form-label">Ilość do</label>
                <input type="number" min="0" name="quantityTo" class="form-control">
              </div>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" selected>Id</option>
                <option value="Wydał">Wydał</option>
                <option value="Data i godzina">Data i godzina</option>
                <option value="Nazwa produktu">Nazwa produktu</option>
                <option value="Jednostka">Jednostka</option>
                <option value="Ilość">Ilość</option>
                <option value="Powód">Powód</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" selected>Rosnąco</option>
                <option value="DESC">Malejąco</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
          </form>
          `
        );
        break;

      case "inventoriesFilter":
        showForm(
          "Filtruj inwentaryzacje",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="inwentaryzacje">
            <input type="hidden" name="page" value="1">
            <div>
              <label class="form-label">Numer inwentaryzacji</label>
              <input type="text" name="inventoryNumber" class="form-control">
            </div>
            <div>
              <label class="form-label">Nazwisko</label>
              <input type="text" name="surname" class="form-control">
            </div>
            <div>
              <label class="form-label">Data utworzenia</label>
              <input type="date" name="createdDate" class="form-control">
            </div>
            <div>
              <label class="form-label">Data zatwierdzenia</label>
              <input type="date" name="approvedDate" class="form-control">
            </div>
            <div>
              <label class="form-label">Zatwierdzona</label>
              <select name="approved" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="1">Tak</option>
                <option value="0">Nie</option>
              </select>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" selected>Id</option>
                <option value="Numer inwentaryzacji">Numer inwentaryzacji</option>
                <option value="Zinwentaryzował">Zinwentaryzował</option>
                <option value="Data utworzenia">Data utworzenia</option>
                <option value="Data zatwierdzenia">Data zatwierdzenia</option>
                <option value="Zatwierdzona">Zatwierdzona</option>
                <option value="Liczba pozycji">Liczba pozycji</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" selected>Rosnąco</option>
                <option value="DESC">Malejąco</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
          </form>
          `
        );
        break;

      case "inventoryPositionsFilter": {
        const inventoryId = getCurrentInventoryId();

        showForm(
          "Filtruj pozycje inwentaryzacji",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="inwentaryzacja_pozycja">
            <input type="hidden" name="inventoryId" value="${escapeHtml(inventoryId)}">
            <input type="hidden" name="page" value="1">
            <div>
              <label class="form-label">Nazwisko</label>
              <input type="text" name="surname" class="form-control">
            </div>
            <div>
              <label class="form-label">Nazwa produktu</label>
              <input type="text" name="productName" class="form-control">
            </div>
            <div>
              <label class="form-label">Zatwierdzona</label>
              <select name="approved" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="1">Tak</option>
                <option value="0">Nie</option>
              </select>
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Stan od</label>
                <input type="number" name="stateFrom" class="form-control">
              </div>
              <div class="col">
                <label class="form-label">Stan do</label>
                <input type="number" name="stateTo" class="form-control">
              </div>
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Różnica od</label>
                <input type="number" name="differenceFrom" class="form-control">
              </div>
              <div class="col">
                <label class="form-label">Różnica do</label>
                <input type="number" name="differenceTo" class="form-control">
              </div>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" selected>Id</option>
                <option value="Id inwentaryzacji">Id inwentaryzacji</option>
                <option value="Zinwentaryzował">Zinwentaryzował</option>
                <option value="Nazwa produktu">Nazwa produktu</option>
                <option value="Jednostka">Jednostka</option>
                <option value="Stan">Stan</option>
                <option value="Różnica">Różnica</option>
                <option value="Zatwierdzona">Zatwierdzona</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" selected>Rosnąco</option>
                <option value="DESC">Malejąco</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
          </form>
          `
        );
        break;
      }

      case "usersFilter":
        showForm(
          "Filtruj użytkowników",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="uzytkownicy">
            <input type="hidden" name="page" value="1">
            <div>
              <label class="form-label">Imię</label>
              <input type="text" name="name" class="form-control">
            </div>
            <div>
              <label class="form-label">Nazwisko</label>
              <input type="text" name="surname" class="form-control">
            </div>
            <div>
              <label class="form-label">Login</label>
              <input type="text" name="login" class="form-control">
            </div>
            <div>
              <label class="form-label">Status konta</label>
              <select name="status" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="aktywne">Aktywne</option>
                <option value="nieaktywne">Nieaktywne</option>
              </select>
            </div>
            <div>
              <label class="form-label">Rola</label>
              <select name="role" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="admin">Admin</option>
                <option value="user">User</option>
              </select>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" selected>Id</option>
                <option value="Login">Login</option>
                <option value="Imię">Imię</option>
                <option value="Nazwisko">Nazwisko</option>
                <option value="Imię i nazwisko">Imię i nazwisko</option>
                <option value="Status konta">Status konta</option>
                <option value="Rola">Rola</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" selected>Rosnąco</option>
                <option value="DESC">Malejąco</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
          </form>
          `
        );
        break;

      case "searchProduct":
        showForm(
          "Filtruj produkty",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="magazyn">
            <input type="hidden" name="searchProduct" value="magazyn">
            <input type="hidden" name="page" value="1">
            <div>
              <label class="form-label">Nazwa produktu</label>
              <input type="text" name="productName" class="form-control">
            </div>
            <div>
              <label class="form-label">Dodał</label>
              <input type="text" name="addedBy" class="form-control">
            </div>
            <div>
              <label class="form-label">Kategoria</label>
              <input type="text" name="productCategory" class="form-control">
            </div>
            <div>
              <label class="form-label">Lokalizacja</label>
              <input type="text" name="productAdress" class="form-control">
            </div>
            <div>
              <label class="form-label">Uwagi</label>
              <input type="text" name="productComments" class="form-control">
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Ilość od</label>
                <input type="number" min="0" name="quantityFrom" class="form-control">
              </div>
              <div class="col">
                <label class="form-label">Ilość do</label>
                <input type="number" min="0" name="quantityTo" class="form-control">
              </div>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" selected>Id</option>
                <option value="Dodał">Dodał</option>
                <option value="Nazwa produktu">Nazwa produktu</option>
                <option value="Kategoria">Kategoria</option>
                <option value="Ilość">Ilość</option>
                <option value="Jednostka">Jednostka</option>
                <option value="Lokalizacja">Lokalizacja</option>
                <option value="Uwagi">Uwagi</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" selected>Rosnąco</option>
                <option value="DESC">Malejąco</option>
              </select>
            </div>
            <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
          </form>
          `
        );
        break;

      case "magazineAddProduct":
        showForm(
          "Dodaj produkt",
          `
          <form method="POST" action="index.php" class="d-grid gap-2">
            <input type="hidden" name="addProduct" value="1">
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
          `
        );
        break;

      case "changePassword":
        showForm(
          "Zmień hasło",
          `
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
          `
        );
        break;

      case "approveInventory": {
        const inventoryId = getCurrentInventoryId();

        showForm(
          "Zatwierdź inwentaryzację",
          `
          <form method="POST" action="index.php" class="d-grid gap-2">
            <input type="hidden" name="approveInventory" value="1">
            <input type="hidden" name="inventoryId" value="${escapeHtml(inventoryId)}">
            <p>Czy na pewno chcesz zatwierdzić tę inwentaryzację?</p>
            <button type="submit" class="btn btn-warning" ${inventoryId ? "" : "disabled"}>
              Zatwierdź inwentaryzację
            </button>
          </form>
          `
        );
        break;
      }
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }
});