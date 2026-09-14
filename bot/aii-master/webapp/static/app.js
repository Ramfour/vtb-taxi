const tg = window.Telegram?.WebApp;
const initData = tg?.initData || "";
const dayOptions = [
  ["mon", "Понедельник"],
  ["tue", "Вторник"],
  ["wed", "Среда"],
  ["thu", "Четверг"],
  ["fri", "Пятница"],
  ["sat", "Суббота"],
  ["sun", "Воскресенье"],
];

let appState = {
  me: null,
  activeTab: "order",
  reminderSchedule: "daily",
  selectedDays: [],
};

if (tg) {
  tg.ready();
  tg.expand();
}

const content = document.querySelector("#content");
const subtitle = document.querySelector("#subtitle");
const statusBox = document.querySelector("#status");
const refreshBtn = document.querySelector("#refreshBtn");

refreshBtn.addEventListener("click", loadApp);

function showStatus(message, type = "success") {
  statusBox.textContent = message;
  statusBox.className = `status ${type}`;
  window.setTimeout(() => {
    statusBox.className = "status hidden";
  }, 4200);
}

async function api(path, options = {}) {
  const response = await fetch(path, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      "X-Telegram-Init-Data": initData,
      ...(options.headers || {}),
    },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) {
    throw new Error(data.error || data.message || "Ошибка запроса");
  }
  return data;
}

async function loadApp() {
  try {
    const me = await api("/api/me");
    appState.me = me;
    render();
  } catch (error) {
    subtitle.textContent = "Откройте приложение из Telegram";
    content.innerHTML = `
      <section class="panel">
        <p>${escapeHtml(error.message)}</p>
      </section>
    `;
  }
}

function render() {
  if (!appState.me.authorized) {
    subtitle.textContent = "Авторизация";
    renderAuth();
    return;
  }

  const employee = appState.me.employee;
  subtitle.textContent = `${employee.full_name} · ${employee.id}`;
  content.innerHTML = `
    <nav class="tabs">
      <button class="tab-button ${appState.activeTab === "order" ? "active" : ""}" data-tab="order">Заявка</button>
      <button class="tab-button ${appState.activeTab === "reminder" ? "active" : ""}" data-tab="reminder">Напоминание</button>
      <button class="tab-button ${appState.activeTab === "orders" ? "active" : ""}" data-tab="orders">История</button>
    </nav>
    <section id="tabContent"></section>
  `;
  document.querySelectorAll("[data-tab]").forEach((button) => {
    button.addEventListener("click", () => {
      appState.activeTab = button.dataset.tab;
      render();
    });
  });

  if (appState.activeTab === "order") renderOrder();
  if (appState.activeTab === "reminder") renderReminder();
  if (appState.activeTab === "orders") renderOrders();
}

function renderAuth() {
  content.innerHTML = `
    <form class="panel form-grid" id="authForm">
      <label>
        Табельный номер
        <input name="employee_id" autocomplete="off" placeholder="ТБ001" required>
      </label>
      <label>
        Адрес
        <textarea name="address" placeholder="ул. Ленина, д. 12" required></textarea>
      </label>
      <label>
        Телефон
        <input name="phone" inputmode="tel" placeholder="89131234567" required>
      </label>
      <button class="primary-button" type="submit">Авторизоваться</button>
    </form>
  `;
  document.querySelector("#authForm").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await api("/api/register", {
        method: "POST",
        body: JSON.stringify(Object.fromEntries(form.entries())),
      });
      showStatus("Авторизация завершена");
      await loadApp();
    } catch (error) {
      showStatus(error.message, "error");
    }
  });
}

function renderOrder() {
  const contact = appState.me.contact || {};
  const tab = document.querySelector("#tabContent");
  tab.innerHTML = `
    <form class="panel form-grid" id="orderForm">
      <label>
        Время подачи
        <input name="time" type="time" required>
      </label>
      <label class="check-row">
        <input name="use_saved" type="checkbox" ${contact.address ? "checked" : ""}>
        Использовать сохранённые данные
      </label>
      <label data-contact-field>
        Адрес
        <textarea name="address">${escapeHtml(contact.address || "")}</textarea>
      </label>
      <label data-contact-field>
        Телефон
        <input name="phone" inputmode="tel" value="${escapeHtml(contact.phone || "")}">
      </label>
      <button class="primary-button" type="submit">Сохранить заявку</button>
    </form>
  `;
  const form = document.querySelector("#orderForm");
  const saved = form.elements.use_saved;
  const toggleFields = () => {
    document.querySelectorAll("[data-contact-field]").forEach((field) => {
      field.classList.toggle("hidden", saved.checked);
    });
  };
  saved.addEventListener("change", toggleFields);
  toggleFields();

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    if (saved.checked) {
      data.address = "";
      data.phone = "";
    }
    try {
      await api("/api/orders", {
        method: "POST",
        body: JSON.stringify(data),
      });
      showStatus("Заявка сохранена");
      await loadApp();
    } catch (error) {
      showStatus(error.message, "error");
    }
  });
}

function renderReminder() {
  const reminder = appState.me.reminder || {};
  appState.reminderSchedule = reminder.schedule_type || appState.reminderSchedule || "daily";
  appState.selectedDays = reminder.days || appState.selectedDays || [];
  const tab = document.querySelector("#tabContent");
  tab.innerHTML = `
    <form class="panel form-grid" id="reminderForm">
      <label>
        Время напоминания
        <input name="time" type="time" value="${escapeHtml(reminder.time || "")}" required>
      </label>
      <div class="segmented" id="scheduleButtons">
        <button type="button" data-schedule="daily">Каждый день</button>
        <button type="button" data-schedule="weekdays">5/2</button>
        <button type="button" data-schedule="custom">Выбрать дни</button>
      </div>
      <div class="days-grid ${appState.reminderSchedule === "custom" ? "" : "hidden"}" id="daysGrid">
        ${dayOptions.map(([code, label]) => `
          <button class="day-chip" type="button" data-day="${code}">${label}</button>
        `).join("")}
      </div>
      <button class="primary-button" type="submit">Сохранить напоминание</button>
    </form>
  `;
  updateScheduleButtons();
  document.querySelectorAll("[data-schedule]").forEach((button) => {
    button.addEventListener("click", () => {
      appState.reminderSchedule = button.dataset.schedule;
      updateScheduleButtons();
    });
  });
  document.querySelectorAll("[data-day]").forEach((button) => {
    button.addEventListener("click", () => {
      const day = button.dataset.day;
      if (appState.selectedDays.includes(day)) {
        appState.selectedDays = appState.selectedDays.filter((item) => item !== day);
      } else {
        appState.selectedDays = [...appState.selectedDays, day];
      }
      updateScheduleButtons();
    });
  });
  document.querySelector("#reminderForm").addEventListener("submit", async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await api("/api/reminder", {
        method: "POST",
        body: JSON.stringify({
          time: form.get("time"),
          schedule_type: appState.reminderSchedule,
          days: appState.selectedDays,
        }),
      });
      showStatus("Напоминание сохранено");
      await loadApp();
    } catch (error) {
      showStatus(error.message, "error");
    }
  });
}

function updateScheduleButtons() {
  document.querySelectorAll("[data-schedule]").forEach((button) => {
    button.classList.toggle("active", button.dataset.schedule === appState.reminderSchedule);
  });
  const daysGrid = document.querySelector("#daysGrid");
  if (daysGrid) {
    daysGrid.classList.toggle("hidden", appState.reminderSchedule !== "custom");
  }
  document.querySelectorAll("[data-day]").forEach((button) => {
    button.classList.toggle("active", appState.selectedDays.includes(button.dataset.day));
  });
}

function renderOrders() {
  const orders = [...(appState.me.orders || [])].reverse();
  const tab = document.querySelector("#tabContent");
  tab.innerHTML = `
    <section class="panel">
      <div class="list">
        ${orders.length ? orders.map((order) => `
          <article class="list-item">
            <strong>${escapeHtml(order.status_text || "Заявка")}</strong>
            <br>
            ${escapeHtml(order.text)}
            <div class="meta">${escapeHtml(order.timestamp || order.date || "")}</div>
            ${order.manager_comment ? `<div class="meta">Комментарий: ${escapeHtml(order.manager_comment)}</div>` : ""}
          </article>
        `).join("") : "<p>Заявок пока нет.</p>"}
      </div>
    </section>
  `;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

loadApp();
