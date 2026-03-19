document.addEventListener("DOMContentLoaded", () => {
  const body = document.body;
  const headerContent = document.getElementById("headerContent");
  const data = document.getElementById("data");
  const formContainer = document.getElementById("formContainer");
  const formModalEl = document.getElementById("formModal");
  const formModalTitle = document.getElementById("formModalTitle");
  const formModal = new bootstrap.Modal(formModalEl);

  const welcomeName = (body.dataset.userFullname || "").trim();
  const userLogin = (body.dataset.userLogin || "").trim();
  const userRole = (body.dataset.userRole || "").trim();

  const userStorageSuffix = userLogin || "guest";

  const lastSectionStorageKey = `lastSection_${userStorageSuffix}`;
  const currentInventoryStorageKey = `currentInventoryId_${userStorageSuffix}`;
  const filtersStorageKey = `sectionFilters_${userStorageSuffix}`;

  const globalLastSectionStorageKey = "lastSection_global";
  const globalCurrentInventoryStorageKey = "currentInventoryId_global";
  const globalFiltersStorageKey = "sectionFilters_global";

  let currentSection =
    localStorage.getItem(lastSectionStorageKey) ||
    localStorage.getItem(globalLastSectionStorageKey) ||
    "magazyn";

  let currentInventoryId =
    localStorage.getItem(currentInventoryStorageKey) ||
    localStorage.getItem(globalCurrentInventoryStorageKey) ||
    "";

  let sectionFilters = safeJsonParse(
    localStorage.getItem(filtersStorageKey) ||
      localStorage.getItem(globalFiltersStorageKey) ||
      "{}"
  );

  const INACTIVITY_LIMIT = 5 * 60 * 1000;
  let inactivityTimer = null;

  init();

  function init() {
    bindTabButtons();
    bindModalEvents();
    makeModalDraggable();
    startInactivityWatcher();
    loadSection(currentSection);
  }

  function safeJsonParse(value) {
    try {
      return JSON.parse(value);
    } catch {
      return {};
    }
  }

  function startInactivityWatcher() {
    const resetTimer = () => {
      clearTimeout(inactivityTimer);
      inactivityTimer = setTimeout(() => {
        window.location.href = "logout.php";
      }, INACTIVITY_LIMIT);
    };

    ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "click"].forEach(eventName => {
      document.addEventListener(eventName, resetTimer, true);
    });

    resetTimer();
  }

  function bindTabButtons() {
    document.querySelectorAll(".tab-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const section = btn.dataset.section;
        if (!section) return;

        currentSection = section;

        if (currentSection !== "inwentaryzacja_pozycja") {
          clearCurrentInventory();
        }

        localStorage.setItem(lastSectionStorageKey, currentSection);
        localStorage.setItem(globalLastSectionStorageKey, currentSection);

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
        localStorage.setItem(lastSectionStorageKey, "uzytkownicy");
        localStorage.setItem(globalLastSectionStorageKey, "uzytkownicy");
      });
    }
  }

  function setCurrentInventory(inventoryId) {
    currentInventoryId = String(inventoryId || "").trim();

    if (currentInventoryId) {
      localStorage.setItem(currentInventoryStorageKey, currentInventoryId);
      localStorage.setItem(globalCurrentInventoryStorageKey, currentInventoryId);
      data.dataset.currentInventoryId = currentInventoryId;
      headerContent.dataset.currentInventoryId = currentInventoryId;
    }
  }

  function clearCurrentInventory() {
    currentInventoryId = "";
    localStorage.removeItem(currentInventoryStorageKey);
    localStorage.removeItem(globalCurrentInventoryStorageKey);
    delete data.dataset.currentInventoryId;
    delete headerContent.dataset.currentInventoryId;
  }

  function getCurrentInventoryId() {
    return (
      currentInventoryId ||
      data.dataset.currentInventoryId ||
      headerContent.dataset.currentInventoryId ||
      localStorage.getItem(currentInventoryStorageKey) ||
      localStorage.getItem(globalCurrentInventoryStorageKey) ||
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
    localStorage.setItem(globalFiltersStorageKey, JSON.stringify(sectionFilters));
  }

  function getSectionFilter(sectionName) {
    return sectionFilters[sectionName] || null;
  }

  function clearSectionFilter(sectionName) {
    delete sectionFilters[sectionName];
    localStorage.setItem(filtersStorageKey, JSON.stringify(sectionFilters));
    localStorage.setItem(globalFiltersStorageKey, JSON.stringify(sectionFilters));
  }

  function getFilterValue(sectionName, key, fallback = "") {
    const filter = getSectionFilter(sectionName);
    if (!filter) return fallback;
    return String(filter[key] ?? fallback);
  }

  function buildFilterParams(sectionName, page = 1) {
    let savedFilter = null;

    if (sectionName === "slowniki") {
      savedFilter = {
        ...(getSectionFilter("slowniki_kategorie") || {}),
        ...(getSectionFilter("slowniki_lokalizacje") || {}),
        table: "slowniki",
        page: String(page)
      };
    } else {
      savedFilter = getSectionFilter(sectionName);
    }

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

    if (sectionName === "slowniki" && !params.get("table")) {
      params.set("table", "slowniki");
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

  async function fetchTableHtml(params, usePost = true) {
    if (usePost) {
      const res = await fetch("getTable.php", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: params
      });

      const html = await res.text();
      return { res, html };
    }

    const res = await fetch(`getTable.php?${params.toString()}`, {
      headers: { "X-Requested-With": "XMLHttpRequest" }
    });

    const html = await res.text();
    return { res, html };
  }

  function handleAjaxSessionExpired(res, html) {
    if (res.status === 440 || /Sesja wygasła/i.test(html)) {
      window.location.href = "login.php?timeout=1";
      return true;
    }
    return false;
  }

  async function loadCurrentSectionWithRememberedFilter(page = 1) {
    if (currentSection === "inwentaryzacja_pozycja") {
      const inventoryId = getCurrentInventoryId();
      if (!inventoryId) return;

      const params = buildFilterParams("inwentaryzacja_pozycja", page);

      if (params) {
        try {
          const { res, html } = await fetchTableHtml(params, true);

          if (handleAjaxSessionExpired(res, html)) return;

          if (!res.ok) {
            throw new Error(html || "Nie udało się pobrać danych.");
          }

          data.innerHTML = html;
          afterTableRender();
        } catch (err) {
          data.innerHTML = `<div class="alert alert-danger">Błąd ładowania pozycji inwentaryzacji: ${escapeHtml(err.message || String(err))}</div>`;
        }

        return;
      }

      loadInventoryPositions(inventoryId, page);
      return;
    }

    const params = buildFilterParams(currentSection, page);

    if (params) {
      try {
        const { res, html } = await fetchTableHtml(params, true);

        if (handleAjaxSessionExpired(res, html)) return;

        if (res.status === 403) {
          data.innerHTML = `<div class="alert alert-warning">Brak uprawnień do tej sekcji.</div>`;
          return;
        }

        if (!res.ok) {
          throw new Error(html || "Nie udało się pobrać danych.");
        }

        data.innerHTML = html;
        afterTableRender();
      } catch (err) {
        data.innerHTML = `<div class="alert alert-danger">Błąd ładowania tabeli: ${escapeHtml(err.message || String(err))}</div>`;
      }

      return;
    }

    loadTable(currentSection, page);
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
        loadCurrentSectionWithRememberedFilter(1);
        break;

      case "wydania":
        setHeader(
          "Wydania",
          `<button class="btn btn-light" data-form="issuesFilter">Filtruj</button>`
        );
        bindHeaderButtons();
        loadCurrentSectionWithRememberedFilter(1);
        break;

      case "inwentaryzacje":
        clearCurrentInventory();
        setHeader(
          "Inwentaryzacje",
          `<button class="btn btn-light" data-form="inventoriesFilter">Filtruj</button>`
        );
        bindHeaderButtons();
        loadCurrentSectionWithRememberedFilter(1);
        break;

      case "inwentaryzacja_pozycja": {
        const inventoryId = getCurrentInventoryId();

        if (!inventoryId) {
          currentSection = "inwentaryzacje";
          localStorage.setItem(lastSectionStorageKey, currentSection);
          localStorage.setItem(globalLastSectionStorageKey, currentSection);
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
        loadCurrentSectionWithRememberedFilter(1);
        break;
      }

      case "uzytkownicy":
        setHeader(
          "Użytkownicy",
          `
          <a href="register.php" id="goToRegister" class="btn btn-light text-start fw-semibold">
            Rejestruj użytkownika
          </a>
          <button class="btn btn-light" data-form="usersFilter">Filtruj</button>
          `
        );
        bindHeaderButtons();
        bindRegisterButton();
        loadCurrentSectionWithRememberedFilter(1);
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
        loadCurrentSectionWithRememberedFilter(1);
        break;

      case "slowniki":
        setHeader(
          "Słowniki",
          `
            <button class="btn btn-light" data-form="addCategoryDictionary">Dodaj kategorię</button>
            <button class="btn btn-light" data-form="addLocationDictionary">Dodaj lokalizację</button>
          `
        );
        bindHeaderButtons();
        loadCurrentSectionWithRememberedFilter(1);
        break;

      default:
        currentSection = "magazyn";
        localStorage.setItem(lastSectionStorageKey, currentSection);
        localStorage.setItem(globalLastSectionStorageKey, currentSection);
        setHeader(
          "Magazynek IT",
          `
            <button class="btn btn-light" data-form="magazineAddProduct">Dodaj</button>
            <button class="btn btn-light" data-form="searchProduct">Filtruj</button>
          `
        );
        bindHeaderButtons();
        loadCurrentSectionWithRememberedFilter(1);
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
        localStorage.setItem(lastSectionStorageKey, currentSection);
        localStorage.setItem(globalLastSectionStorageKey, currentSection);
        clearCurrentInventory();
        loadSection("inwentaryzacje");
      });
    }
  }

  async function loadTable(tableName, page = 1) {
    const params = new URLSearchParams({
      table: tableName,
      page: String(page)
    });

    try {
      const { res, html } = await fetchTableHtml(params, false);

      if (handleAjaxSessionExpired(res, html)) return;

      if (res.status === 403) {
        currentSection = "magazyn";
        localStorage.setItem(lastSectionStorageKey, currentSection);
        localStorage.setItem(globalLastSectionStorageKey, currentSection);
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
    } catch (err) {
      data.innerHTML = `<div class="alert alert-danger">Błąd ładowania tabeli: ${escapeHtml(err.message || String(err))}</div>`;
    }
  }

  async function loadInventoryPositions(inventoryId, page = 1) {
    setCurrentInventory(inventoryId);

    try {
      const { res, html } = await fetchTableHtml(
        new URLSearchParams({
          table: "inwentaryzacja_pozycja",
          inventoryId: inventoryId,
          page: String(page)
        }),
        true
      );

      if (handleAjaxSessionExpired(res, html)) return;

      if (res.status === 403) {
        currentSection = "inwentaryzacje";
        localStorage.setItem(lastSectionStorageKey, currentSection);
        localStorage.setItem(globalLastSectionStorageKey, currentSection);
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
    } catch (err) {
      data.innerHTML = `<div class="alert alert-danger">Błąd ładowania pozycji inwentaryzacji: ${escapeHtml(err.message || String(err))}</div>`;
    }
  }

  function afterTableRender() {
    bindRowButtons();
    bindInventoryRows();
    bindPaginationButtons();
    bindDictionaryButtons();
    bindDictionaryFilterButtons();

    if (currentSection === "historia_operacji") {
      showJSONInHistory();
    }
  }

  function bindPaginationButtons() {
    document.querySelectorAll(".pagination-btn").forEach(btn => {
      btn.addEventListener("click", async () => {
        const page = btn.dataset.page;

        if (currentSection === "inwentaryzacja_pozycja") {
          const inventoryId = getCurrentInventoryId();
          if (!inventoryId) return;

          const params = buildFilterParams("inwentaryzacja_pozycja", page);

          if (params) {
            try {
              const { res, html } = await fetchTableHtml(params, true);

              if (handleAjaxSessionExpired(res, html)) return;

              if (!res.ok) {
                throw new Error(html || "Nie udało się pobrać danych.");
              }

              data.innerHTML = html;
              afterTableRender();
            } catch (err) {
              data.innerHTML = `<div class="alert alert-danger">Błąd ładowania pozycji inwentaryzacji: ${escapeHtml(err.message || String(err))}</div>`;
            }
          } else {
            loadInventoryPositions(inventoryId, page);
          }

          return;
        }

        const params = buildFilterParams(currentSection, page);

        if (params) {
          try {
            const { res, html } = await fetchTableHtml(params, true);

            if (handleAjaxSessionExpired(res, html)) return;

            if (res.status === 403) {
              data.innerHTML = `<div class="alert alert-warning">Brak uprawnień do tej sekcji.</div>`;
              return;
            }

            if (!res.ok) {
              throw new Error(html || "Nie udało się pobrać danych.");
            }

            data.innerHTML = html;
            afterTableRender();
          } catch (err) {
            data.innerHTML = `<div class="alert alert-danger">Błąd ładowania tabeli: ${escapeHtml(err.message || String(err))}</div>`;
          }
        } else {
          loadTable(currentSection, page);
        }
      });
    });
  }

  function loadSettings() {
    let html = `
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-dark fw-semibold" data-form="changePassword">
          Zmień hasło
        </button>
    `;

    if (userLogin === "AdminSystemu") {
      html += `
        <button class="btn btn-dark fw-semibold" data-form="changeRfid">
          Zmień RFID
        </button>
      `;
    }

    html += `</div>`;

    data.innerHTML = html;

    data.querySelectorAll("button[data-form]").forEach(btn => {
      btn.addEventListener("click", () => renderForm(btn.dataset.form));
    });
  }

  function showJSONInHistory() {
    const rows = document.querySelectorAll("tr[data-id]");

    rows.forEach(row => {
      const cells = Array.from(row.querySelectorAll("td"));
      const beforeCell = cells[8];
      const afterCell = cells[9];

      if (!beforeCell || !afterCell) return;

      let beforeRaw = beforeCell.textContent.trim();
      let afterRaw = afterCell.textContent.trim();

      const invalid = txt => !txt || txt === "null" || txt === "-" || txt.trim() === "";

      if (invalid(beforeRaw)) beforeCell.textContent = "-";
      if (invalid(afterRaw)) afterCell.textContent = "-";

      if (!invalid(beforeRaw)) {
        beforeCell.textContent = beforeRaw.length > 40 ? `${beforeRaw.slice(0, 40)}...` : beforeRaw;
        beforeCell.dataset.fullJson = beforeRaw;
        beforeCell.dataset.otherJson = invalid(afterRaw) ? "{}" : afterRaw;
        beforeCell.style.cursor = "pointer";
        beforeCell.title = "Kliknij, aby zobaczyć dane PRZED";
        beforeCell.addEventListener("click", () => {
          openJSONModal(beforeCell.dataset.fullJson, beforeCell.dataset.otherJson, "before");
        });
      }

      if (!invalid(afterRaw)) {
        afterCell.textContent = afterRaw.length > 40 ? `${afterRaw.slice(0, 40)}...` : afterRaw;
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

    try {
      before = JSON.parse(beforeJson);
    } catch {}

    try {
      after = JSON.parse(afterJson);
    } catch {}

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
                      : btn.classList.contains("changeUserRfidBtn")
                        ? "changeUserRfid"
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
        localStorage.setItem(lastSectionStorageKey, currentSection);
        localStorage.setItem(globalLastSectionStorageKey, currentSection);
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
        loadCurrentSectionWithRememberedFilter(1);
      });
    });
  }

  function bindDictionaryButtons() {
    document.querySelectorAll("tr[data-dictionary-type]").forEach(row => {
      const id = row.dataset.id;
      const dictionaryType = row.dataset.dictionaryType;
      const cells = Array.from(row.querySelectorAll("td")).map(td => td.textContent.trim());

      row.querySelectorAll("button").forEach(btn => {
        btn.addEventListener("click", e => {
          e.stopPropagation();

          if (btn.classList.contains("dictionaryEditBtn")) {
            const currentValue = cells[1] || "";

            showForm(
              dictionaryType === "kategorie" ? "Edytuj kategorię" : "Edytuj lokalizację",
              `
              <form method="POST" action="index.php" class="d-grid gap-2">
                <input type="hidden" name="editDictionary" value="1">
                <input type="hidden" name="dictionaryType" value="${escapeHtml(dictionaryType)}">
                <input type="hidden" name="dictionaryId" value="${escapeHtml(id)}">
                <div>
                  <label class="form-label">Nowa nazwa</label>
                  <input type="text" name="dictionaryValue" class="form-control" value="${escapeHtml(currentValue)}" required>
                </div>
                <button type="submit" class="btn btn-warning">Zapisz</button>
              </form>
              `
            );
          }

          if (btn.classList.contains("dictionaryDeleteBtn")) {
            showForm(
              dictionaryType === "kategorie" ? "Usuń kategorię" : "Usuń lokalizację",
              `
              <form method="POST" action="index.php" class="d-grid gap-2">
                <input type="hidden" name="deleteDictionary" value="1">
                <input type="hidden" name="dictionaryType" value="${escapeHtml(dictionaryType)}">
                <input type="hidden" name="dictionaryId" value="${escapeHtml(id)}">
                <p>Czy na pewno chcesz usunąć tę pozycję słownika?</p>
                <button type="submit" class="btn btn-danger">Usuń</button>
              </form>
              `
            );
          }
        });
      });
    });
  }

  function bindDictionaryFilterButtons() {
    document.querySelectorAll(".dictionary-filter-btn").forEach(btn => {
      btn.addEventListener("click", () => {
        const formName = btn.dataset.form;
        if (formName) {
          renderForm(formName);
        }
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
            <select name="productCategory" id="editProductCategory" class="form-select">
              <option value="">-- wybierz --</option>
            </select>
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
            <select name="productAdress" id="editProductLocation" class="form-select">
              <option value="">-- wybierz --</option>
            </select>
          </div>
          <div>
            <label class="form-label">Uwagi</label>
            <input type="text" name="productComments" class="form-control" value="${escapeHtml(comments)}">
          </div>
          <button type="submit" class="btn btn-warning">Zatwierdź</button>
        </form>
        `
      );

      fillSelects([
        ["editProductCategory", "kategorie", category],
        ["editProductLocation", "lokalizacje", location]
      ]);
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

    if (action === "changeUserRfid") {
      showForm(
        "Zmień RFID użytkownika",
        `
        <form method="POST" action="index.php" class="d-grid gap-2">
          <input type="hidden" name="adminChangeUserRfid" value="1">
          <input type="hidden" name="targetUserId" value="${escapeHtml(id)}">
          <div>
            <label class="form-label">Nowy RFID</label>
            <input type="text" name="newRfid" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-warning">Zmień RFID</button>
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

    const clearFilterBtn = formContainer.querySelector("[data-clear-filter]");
    if (clearFilterBtn) {
      clearFilterBtn.addEventListener("click", () => {
        const sectionName = clearFilterBtn.dataset.clearFilter || currentSection;
        clearSectionFilter(sectionName);
        formModal.hide();

        if (sectionName === "inwentaryzacja_pozycja") {
          const inventoryId = getCurrentInventoryId();
          if (inventoryId) {
            loadInventoryPositions(inventoryId, 1);
          }
          return;
        }

        if (sectionName === "slowniki_kategorie" || sectionName === "slowniki_lokalizacje" || sectionName === "slowniki") {
          loadTable("slowniki", 1);
          return;
        }

        if (currentSection === sectionName) {
          loadTable(sectionName, 1);
        } else {
          currentSection = sectionName;
          localStorage.setItem(lastSectionStorageKey, currentSection);
          localStorage.setItem(globalLastSectionStorageKey, currentSection);
          loadSection(sectionName);
        }
      });
    }
  }

  async function submitFilter(e) {
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

    if (tableName === "slowniki") {
      const dictionaryFilterType = formData.get("dictionaryFilterType") || "";

      if (dictionaryFilterType === "kategorie") {
        saveSectionFilter("slowniki_kategorie", formData);
      } else if (dictionaryFilterType === "lokalizacje") {
        saveSectionFilter("slowniki_lokalizacje", formData);
      } else {
        saveSectionFilter("slowniki", formData);
      }
    } else {
      saveSectionFilter(tableName, formData);
    }

    try {
      const res = await fetch("getTable.php", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: formData
      });

      const html = await res.text();

      if (handleAjaxSessionExpired(res, html)) return;

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
    } catch (err) {
      data.innerHTML = `<div class="alert alert-danger">Błąd filtrowania: ${escapeHtml(err.message || String(err))}</div>`;
    }
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
              <select name="login" id="historyLoginSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Imię i nazwisko</label>
              <select name="fullName" id="historyFullNameSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Typ obiektu</label>
              <select name="objectType" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="produkt" ${getFilterValue("historia_operacji", "objectType") === "produkt" ? "selected" : ""}>Produkt</option>
                <option value="kategorie" ${getFilterValue("historia_operacji", "objectType") === "kategorie" ? "selected" : ""}>Kategorie</option>
                <option value="lokalizacje" ${getFilterValue("historia_operacji", "objectType") === "lokalizacje" ? "selected" : ""}>Lokalizacje</option>
              </select>
            </div>
            <div>
              <label class="form-label">Nazwa obiektu</label>
              <select name="objectName" id="historyObjectNameSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Operacja</label>
              <select name="operationName" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="dodanie" ${getFilterValue("historia_operacji", "operationName") === "dodanie" ? "selected" : ""}>Dodanie</option>
                <option value="edycja" ${getFilterValue("historia_operacji", "operationName") === "edycja" ? "selected" : ""}>Edycja</option>
                <option value="usunięcie" ${getFilterValue("historia_operacji", "operationName") === "usunięcie" ? "selected" : ""}>Usunięcie</option>
                <option value="wydanie" ${getFilterValue("historia_operacji", "operationName") === "wydanie" ? "selected" : ""}>Wydanie</option>
                <option value="inwentaryzacja" ${getFilterValue("historia_operacji", "operationName") === "inwentaryzacja" ? "selected" : ""}>Inwentaryzacja</option>
                <option value="dodanie_słownika" ${getFilterValue("historia_operacji", "operationName") === "dodanie_słownika" ? "selected" : ""}>Dodanie słownika</option>
                <option value="edycja_słownika" ${getFilterValue("historia_operacji", "operationName") === "edycja_słownika" ? "selected" : ""}>Edycja słownika</option>
                <option value="usunięcie_słownika" ${getFilterValue("historia_operacji", "operationName") === "usunięcie_słownika" ? "selected" : ""}>Usunięcie słownika</option>
              </select>
            </div>
            <div>
              <label class="form-label">Data</label>
              <input type="date" name="operationDate" class="form-control" value="${escapeHtml(getFilterValue("historia_operacji", "operationDate"))}">
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" ${getFilterValue("historia_operacji", "sortColumn", "Id") === "Id" ? "selected" : ""}>Id</option>
                <option value="Login" ${getFilterValue("historia_operacji", "sortColumn") === "Login" ? "selected" : ""}>Login</option>
                <option value="Id produktu" ${getFilterValue("historia_operacji", "sortColumn") === "Id produktu" ? "selected" : ""}>Id produktu</option>
                <option value="Imię i nazwisko" ${getFilterValue("historia_operacji", "sortColumn") === "Imię i nazwisko" ? "selected" : ""}>Imię i nazwisko</option>
                <option value="Typ obiektu" ${getFilterValue("historia_operacji", "sortColumn") === "Typ obiektu" ? "selected" : ""}>Typ obiektu</option>
                <option value="Nazwa obiektu" ${getFilterValue("historia_operacji", "sortColumn") === "Nazwa obiektu" ? "selected" : ""}>Nazwa obiektu</option>
                <option value="Operacja" ${getFilterValue("historia_operacji", "sortColumn") === "Operacja" ? "selected" : ""}>Operacja</option>
                <option value="Data operacji" ${getFilterValue("historia_operacji", "sortColumn") === "Data operacji" ? "selected" : ""}>Data operacji</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" ${getFilterValue("historia_operacji", "sortOrder", "ASC") === "ASC" ? "selected" : ""}>Rosnąco</option>
                <option value="DESC" ${getFilterValue("historia_operacji", "sortOrder") === "DESC" ? "selected" : ""}>Malejąco</option>
              </select>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
              <button type="button" class="btn btn-secondary mt-2" data-clear-filter="historia_operacji">Wyczyść filtry</button>
            </div>
          </form>
          `
        );

        fillSelects([
          ["historyLoginSelect", "uzytkownicy_login", getFilterValue("historia_operacji", "login")],
          ["historyFullNameSelect", "uzytkownicy_fullname", getFilterValue("historia_operacji", "fullName")],
          ["historyObjectNameSelect", "historia_nazwa_produktu", getFilterValue("historia_operacji", "objectName")]
        ]);
        break;

      case "issuesFilter":
        showForm(
          "Filtruj wydania",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="wydania">
            <input type="hidden" name="page" value="1">
            <div>
              <label class="form-label">Wydał</label>
              <select name="issuedBy" id="issuesIssuedBySelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Nazwa produktu</label>
              <select name="productName" id="issuesProductNameSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Powód</label>
              <input type="text" name="reason" class="form-control" value="${escapeHtml(getFilterValue("wydania", "reason"))}">
            </div>
            <div>
              <label class="form-label">Data</label>
              <input type="date" name="issueDate" class="form-control" value="${escapeHtml(getFilterValue("wydania", "issueDate"))}">
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Ilość od</label>
                <input type="number" min="0" name="quantityFrom" class="form-control" value="${escapeHtml(getFilterValue("wydania", "quantityFrom"))}">
              </div>
              <div class="col">
                <label class="form-label">Ilość do</label>
                <input type="number" min="0" name="quantityTo" class="form-control" value="${escapeHtml(getFilterValue("wydania", "quantityTo"))}">
              </div>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" ${getFilterValue("wydania", "sortColumn", "Id") === "Id" ? "selected" : ""}>Id</option>
                <option value="Wydał" ${getFilterValue("wydania", "sortColumn") === "Wydał" ? "selected" : ""}>Wydał</option>
                <option value="Data i godzina" ${getFilterValue("wydania", "sortColumn") === "Data i godzina" ? "selected" : ""}>Data i godzina</option>
                <option value="Nazwa produktu" ${getFilterValue("wydania", "sortColumn") === "Nazwa produktu" ? "selected" : ""}>Nazwa produktu</option>
                <option value="Jednostka" ${getFilterValue("wydania", "sortColumn") === "Jednostka" ? "selected" : ""}>Jednostka</option>
                <option value="Ilość" ${getFilterValue("wydania", "sortColumn") === "Ilość" ? "selected" : ""}>Ilość</option>
                <option value="Powód" ${getFilterValue("wydania", "sortColumn") === "Powód" ? "selected" : ""}>Powód</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" ${getFilterValue("wydania", "sortOrder", "ASC") === "ASC" ? "selected" : ""}>Rosnąco</option>
                <option value="DESC" ${getFilterValue("wydania", "sortOrder") === "DESC" ? "selected" : ""}>Malejąco</option>
              </select>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
              <button type="button" class="btn btn-secondary mt-2" data-clear-filter="wydania">Wyczyść filtry</button>
            </div>
          </form>
          `
        );

        fillSelects([
          ["issuesIssuedBySelect", "wydania_wydal", getFilterValue("wydania", "issuedBy")],
          ["issuesProductNameSelect", "wydania_nazwa_produktu", getFilterValue("wydania", "productName")]
        ]);
        break;

      case "inventoriesFilter":
        showForm(
          "Filtruj inwentaryzacje",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="inwentaryzacje">
            <input type="hidden" name="page" value="1">
            <div>
              <label class="form-label">Zinwentaryzował</label>
              <select name="inventoriedBy" id="inventoriesBySelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Numer inwentaryzacji</label>
              <select name="inventoryNumber" id="inventoriesNumberSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Data utworzenia</label>
              <input type="date" name="createdDate" class="form-control" value="${escapeHtml(getFilterValue("inwentaryzacje", "createdDate"))}">
            </div>
            <div>
              <label class="form-label">Data zatwierdzenia</label>
              <input type="date" name="approvedDate" class="form-control" value="${escapeHtml(getFilterValue("inwentaryzacje", "approvedDate"))}">
            </div>
            <div>
              <label class="form-label">Zatwierdzona</label>
              <select name="approved" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="1" ${getFilterValue("inwentaryzacje", "approved") === "1" ? "selected" : ""}>Tak</option>
                <option value="0" ${getFilterValue("inwentaryzacje", "approved") === "0" ? "selected" : ""}>Nie</option>
              </select>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" ${getFilterValue("inwentaryzacje", "sortColumn", "Id") === "Id" ? "selected" : ""}>Id</option>
                <option value="Numer inwentaryzacji" ${getFilterValue("inwentaryzacje", "sortColumn") === "Numer inwentaryzacji" ? "selected" : ""}>Numer inwentaryzacji</option>
                <option value="Zinwentaryzował" ${getFilterValue("inwentaryzacje", "sortColumn") === "Zinwentaryzował" ? "selected" : ""}>Zinwentaryzował</option>
                <option value="Data utworzenia" ${getFilterValue("inwentaryzacje", "sortColumn") === "Data utworzenia" ? "selected" : ""}>Data utworzenia</option>
                <option value="Data zatwierdzenia" ${getFilterValue("inwentaryzacje", "sortColumn") === "Data zatwierdzenia" ? "selected" : ""}>Data zatwierdzenia</option>
                <option value="Zatwierdzona" ${getFilterValue("inwentaryzacje", "sortColumn") === "Zatwierdzona" ? "selected" : ""}>Zatwierdzona</option>
                <option value="Liczba pozycji" ${getFilterValue("inwentaryzacje", "sortColumn") === "Liczba pozycji" ? "selected" : ""}>Liczba pozycji</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" ${getFilterValue("inwentaryzacje", "sortOrder", "ASC") === "ASC" ? "selected" : ""}>Rosnąco</option>
                <option value="DESC" ${getFilterValue("inwentaryzacje", "sortOrder") === "DESC" ? "selected" : ""}>Malejąco</option>
              </select>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
              <button type="button" class="btn btn-secondary mt-2" data-clear-filter="inwentaryzacje">Wyczyść filtry</button>
            </div>
          </form>
          `
        );

        fillSelects([
          ["inventoriesBySelect", "inwentaryzacje_zinwentaryzowal", getFilterValue("inwentaryzacje", "inventoriedBy")],
          ["inventoriesNumberSelect", "inwentaryzacje_numer", getFilterValue("inwentaryzacje", "inventoryNumber")]
        ]);
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
              <label class="form-label">Nazwa produktu</label>
              <select name="productName" id="inventoryPositionsProductSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Stan od</label>
                <input type="number" name="stateFrom" class="form-control" value="${escapeHtml(getFilterValue("inwentaryzacja_pozycja", "stateFrom"))}">
              </div>
              <div class="col">
                <label class="form-label">Stan do</label>
                <input type="number" name="stateTo" class="form-control" value="${escapeHtml(getFilterValue("inwentaryzacja_pozycja", "stateTo"))}">
              </div>
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Różnica od</label>
                <input type="number" name="differenceFrom" class="form-control" value="${escapeHtml(getFilterValue("inwentaryzacja_pozycja", "differenceFrom"))}">
              </div>
              <div class="col">
                <label class="form-label">Różnica do</label>
                <input type="number" name="differenceTo" class="form-control" value="${escapeHtml(getFilterValue("inwentaryzacja_pozycja", "differenceTo"))}">
              </div>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" ${getFilterValue("inwentaryzacja_pozycja", "sortColumn", "Id") === "Id" ? "selected" : ""}>Id</option>
                <option value="Nazwa produktu" ${getFilterValue("inwentaryzacja_pozycja", "sortColumn") === "Nazwa produktu" ? "selected" : ""}>Nazwa produktu</option>
                <option value="Jednostka" ${getFilterValue("inwentaryzacja_pozycja", "sortColumn") === "Jednostka" ? "selected" : ""}>Jednostka</option>
                <option value="Stan" ${getFilterValue("inwentaryzacja_pozycja", "sortColumn") === "Stan" ? "selected" : ""}>Stan</option>
                <option value="Różnica" ${getFilterValue("inwentaryzacja_pozycja", "sortColumn") === "Różnica" ? "selected" : ""}>Różnica</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" ${getFilterValue("inwentaryzacja_pozycja", "sortOrder", "ASC") === "ASC" ? "selected" : ""}>Rosnąco</option>
                <option value="DESC" ${getFilterValue("inwentaryzacja_pozycja", "sortOrder") === "DESC" ? "selected" : ""}>Malejąco</option>
              </select>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
              <button type="button" class="btn btn-secondary mt-2" data-clear-filter="inwentaryzacja_pozycja">Wyczyść filtry</button>
            </div>
          </form>
          `
        );

        fillSelects([
          ["inventoryPositionsProductSelect", "inwentaryzacja_pozycja_nazwa_produktu", getFilterValue("inwentaryzacja_pozycja", "productName")]
        ]);
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
              <label class="form-label">Login</label>
              <select name="login" id="usersLoginSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Imię i nazwisko</label>
              <select name="fullName" id="usersFullNameSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Status konta</label>
              <select name="status" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="aktywne" ${getFilterValue("uzytkownicy", "status") === "aktywne" ? "selected" : ""}>Aktywne</option>
                <option value="nieaktywne" ${getFilterValue("uzytkownicy", "status") === "nieaktywne" ? "selected" : ""}>Nieaktywne</option>
              </select>
            </div>
            <div>
              <label class="form-label">Rola</label>
              <select name="role" class="form-select">
                <option value="">-- wszystkie --</option>
                <option value="admin" ${getFilterValue("uzytkownicy", "role") === "admin" ? "selected" : ""}>Admin</option>
                <option value="user" ${getFilterValue("uzytkownicy", "role") === "user" ? "selected" : ""}>User</option>
              </select>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" ${getFilterValue("uzytkownicy", "sortColumn", "Id") === "Id" ? "selected" : ""}>Id</option>
                <option value="Login" ${getFilterValue("uzytkownicy", "sortColumn") === "Login" ? "selected" : ""}>Login</option>
                <option value="Imię i nazwisko" ${getFilterValue("uzytkownicy", "sortColumn") === "Imię i nazwisko" ? "selected" : ""}>Imię i nazwisko</option>
                <option value="Status konta" ${getFilterValue("uzytkownicy", "sortColumn") === "Status konta" ? "selected" : ""}>Status konta</option>
                <option value="Rola" ${getFilterValue("uzytkownicy", "sortColumn") === "Rola" ? "selected" : ""}>Rola</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" ${getFilterValue("uzytkownicy", "sortOrder", "ASC") === "ASC" ? "selected" : ""}>Rosnąco</option>
                <option value="DESC" ${getFilterValue("uzytkownicy", "sortOrder") === "DESC" ? "selected" : ""}>Malejąco</option>
              </select>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
              <button type="button" class="btn btn-secondary mt-2" data-clear-filter="uzytkownicy">Wyczyść filtry</button>
            </div>
          </form>
          `
        );

        fillSelects([
          ["usersLoginSelect", "uzytkownicy_login", getFilterValue("uzytkownicy", "login")],
          ["usersFullNameSelect", "uzytkownicy_fullname", getFilterValue("uzytkownicy", "fullName")]
        ]);
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
              <label class="form-label">Dodał</label>
              <select name="addedBy" id="magazineAddedBySelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Nazwa produktu</label>
              <select name="productName" id="magazineProductNameSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kategoria</label>
              <select name="productCategory" id="magazineCategorySelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Lokalizacja</label>
              <select name="productAdress" id="magazineLocationSelect" class="form-select">
                <option value="">-- wszystkie --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Uwagi</label>
              <input type="text" name="productComments" class="form-control" value="${escapeHtml(getFilterValue("magazyn", "productComments"))}">
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Ilość od</label>
                <input type="number" min="0" name="quantityFrom" class="form-control" value="${escapeHtml(getFilterValue("magazyn", "quantityFrom"))}">
              </div>
              <div class="col">
                <label class="form-label">Ilość do</label>
                <input type="number" min="0" name="quantityTo" class="form-control" value="${escapeHtml(getFilterValue("magazyn", "quantityTo"))}">
              </div>
            </div>
            <div>
              <label class="form-label">Sortuj po kolumnie</label>
              <select name="sortColumn" class="form-select">
                <option value="Id" ${getFilterValue("magazyn", "sortColumn", "Id") === "Id" ? "selected" : ""}>Id</option>
                <option value="Dodał" ${getFilterValue("magazyn", "sortColumn") === "Dodał" ? "selected" : ""}>Dodał</option>
                <option value="Nazwa produktu" ${getFilterValue("magazyn", "sortColumn") === "Nazwa produktu" ? "selected" : ""}>Nazwa produktu</option>
                <option value="Kategoria" ${getFilterValue("magazyn", "sortColumn") === "Kategoria" ? "selected" : ""}>Kategoria</option>
                <option value="Ilość" ${getFilterValue("magazyn", "sortColumn") === "Ilość" ? "selected" : ""}>Ilość</option>
                <option value="Jednostka" ${getFilterValue("magazyn", "sortColumn") === "Jednostka" ? "selected" : ""}>Jednostka</option>
                <option value="Lokalizacja" ${getFilterValue("magazyn", "sortColumn") === "Lokalizacja" ? "selected" : ""}>Lokalizacja</option>
                <option value="Uwagi" ${getFilterValue("magazyn", "sortColumn") === "Uwagi" ? "selected" : ""}>Uwagi</option>
              </select>
            </div>
            <div>
              <label class="form-label">Kierunek sortowania</label>
              <select name="sortOrder" class="form-select">
                <option value="ASC" ${getFilterValue("magazyn", "sortOrder", "ASC") === "ASC" ? "selected" : ""}>Rosnąco</option>
                <option value="DESC" ${getFilterValue("magazyn", "sortOrder") === "DESC" ? "selected" : ""}>Malejąco</option>
              </select>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
              <button type="button" class="btn btn-secondary mt-2" data-clear-filter="magazyn">Wyczyść filtry</button>
            </div>
          </form>
          `
        );

        fillSelects([
          ["magazineAddedBySelect", "magazyn_dodal", getFilterValue("magazyn", "addedBy")],
          ["magazineProductNameSelect", "magazyn_nazwa_produktu", getFilterValue("magazyn", "productName")],
          ["magazineCategorySelect", "kategorie", getFilterValue("magazyn", "productCategory")],
          ["magazineLocationSelect", "lokalizacje", getFilterValue("magazyn", "productAdress")]
        ]);
        break;

      case "dictionaryCategoriesFilter":
        showForm(
          "Filtruj kategorie",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="slowniki">
            <input type="hidden" name="dictionaryFilterType" value="kategorie">
            <input type="hidden" name="page" value="1">

            <div>
              <label class="form-label">Id</label>
              <input type="number" name="categoryId" class="form-control" value="${escapeHtml(getFilterValue("slowniki_kategorie", "categoryId"))}">
            </div>

            <div>
              <label class="form-label">Nazwa</label>
              <input type="text" name="categoryName" class="form-control" value="${escapeHtml(getFilterValue("slowniki_kategorie", "categoryName"))}">
            </div>

            <div>
              <label class="form-label">Data utworzenia</label>
              <input type="date" name="categoryCreatedDate" class="form-control" value="${escapeHtml(getFilterValue("slowniki_kategorie", "categoryCreatedDate"))}">
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
              <button type="button" class="btn btn-secondary mt-2" data-clear-filter="slowniki_kategorie">Wyczyść filtry</button>
            </div>
          </form>
          `
        );
        break;

      case "dictionaryLocationsFilter":
        showForm(
          "Filtruj lokalizacje",
          `
          <form method="POST" action="getTable.php" data-filter="1" class="d-grid gap-2">
            <input type="hidden" name="table" value="slowniki">
            <input type="hidden" name="dictionaryFilterType" value="lokalizacje">
            <input type="hidden" name="page" value="1">

            <div>
              <label class="form-label">Id</label>
              <input type="number" name="locationId" class="form-control" value="${escapeHtml(getFilterValue("slowniki_lokalizacje", "locationId"))}">
            </div>

            <div>
              <label class="form-label">Nazwa</label>
              <input type="text" name="locationName" class="form-control" value="${escapeHtml(getFilterValue("slowniki_lokalizacje", "locationName"))}">
            </div>

            <div>
              <label class="form-label">Data utworzenia</label>
              <input type="date" name="locationCreatedDate" class="form-control" value="${escapeHtml(getFilterValue("slowniki_lokalizacje", "locationCreatedDate"))}">
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning mt-2">Filtruj</button>
              <button type="button" class="btn btn-secondary mt-2" data-clear-filter="slowniki_lokalizacje">Wyczyść filtry</button>
            </div>
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
              <select name="productCategory" id="selectProductCategory" class="form-select">
                <option value="">-- wybierz --</option>
              </select>
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
              <select name="productAdress" id="selectProductLocation" class="form-select">
                <option value="">-- wybierz --</option>
              </select>
            </div>
            <div>
              <label class="form-label">Uwagi</label>
              <input type="text" name="productComments" class="form-control">
            </div>
            <button type="submit" class="btn btn-warning">Zatwierdź</button>
          </form>
          `
        );

        fillSelects([
          ["selectProductCategory", "kategorie"],
          ["selectProductLocation", "lokalizacje"]
        ]);
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

      case "changeRfid":
        showForm(
          "Zmień RFID",
          `
          <form method="POST" action="index.php" class="d-grid gap-2">
            <input type="hidden" name="changeRfid" value="1">
            <div>
              <label class="form-label">Nowy RFID</label>
              <input type="text" name="newRfid" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-warning">Zmień RFID</button>
          </form>
          `
        );
        break;

      case "addCategoryDictionary":
        showForm(
          "Dodaj kategorię do słownika",
          `
          <form method="POST" action="index.php" class="d-grid gap-2">
            <input type="hidden" name="addCategoryDictionary" value="1">
            <div>
              <label class="form-label">Nazwa kategorii</label>
              <input type="text" name="dictionaryValue" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-warning">Dodaj kategorię</button>
          </form>
          `
        );
        break;

      case "addLocationDictionary":
        showForm(
          "Dodaj lokalizację do słownika",
          `
          <form method="POST" action="index.php" class="d-grid gap-2">
            <input type="hidden" name="addLocationDictionary" value="1">
            <div>
              <label class="form-label">Nazwa lokalizacji</label>
              <input type="text" name="dictionaryValue" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-warning">Dodaj lokalizację</button>
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

  async function fillSelect(selectId, dictionaryType, selectedValue = "", includeEmpty = true) {
    const select = document.getElementById(selectId);
    if (!select) return;

    try {
      const res = await fetch(`getDictionaries.php?type=${encodeURIComponent(dictionaryType)}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" }
      });

      if (!res.ok) {
        throw new Error("Nie udało się pobrać słownika.");
      }

      const items = await res.json();

      let html = "";

      if (includeEmpty) {
        html += `<option value="">-- wybierz --</option>`;
      }

      if (Array.isArray(items)) {
        html += items
          .map(item => {
            const value = String(item ?? "");
            const selected = value === String(selectedValue ?? "") ? "selected" : "";
            return `<option value="${escapeHtml(value)}" ${selected}>${escapeHtml(value)}</option>`;
          })
          .join("");
      }

      select.innerHTML = html;
    } catch (err) {
      console.error("Błąd ładowania słownika:", err);
      select.innerHTML = includeEmpty ? `<option value="">-- wybierz --</option>` : "";
    }
  }

  function fillSelects(configs) {
    configs.forEach(([id, type, selectedValue = "", includeEmpty = true]) => {
      fillSelect(id, type, selectedValue, includeEmpty);
    });
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }
});