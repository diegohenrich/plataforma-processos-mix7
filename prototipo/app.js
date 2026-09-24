const drawer = document.querySelector("#detailDrawer");
const scrim = document.querySelector("#scrim");
const dialog = document.querySelector("#requestDialog");
const toast = document.querySelector("#toast");
let toastTimer;

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 2800);
}

function openDrawer(card) {
  document.querySelector("#drawerTitle").textContent = card.dataset.title || "Nova demanda";
  document.querySelector("#drawerClient").textContent = card.dataset.client || "Cliente";
  document.querySelector("#drawerStage").textContent = (card.dataset.stageName || "Em andamento").toUpperCase();
  drawer.classList.add("open");
  drawer.setAttribute("aria-hidden", "false");
  scrim.hidden = false;
  document.body.style.overflow = "hidden";
  document.querySelector("#closeDrawer").focus();
}

function closeDrawer() {
  drawer.classList.remove("open");
  drawer.setAttribute("aria-hidden", "true");
  scrim.hidden = true;
  document.body.style.overflow = "";
}

document.querySelectorAll(".task-card").forEach(card => card.addEventListener("click", () => openDrawer(card)));
document.querySelector("#closeDrawer").addEventListener("click", closeDrawer);
scrim.addEventListener("click", closeDrawer);
document.addEventListener("keydown", event => { if (event.key === "Escape") { closeDrawer(); if (dialog.open) dialog.close(); } });

document.querySelector("#newRequestButton").addEventListener("click", () => dialog.showModal());
document.querySelectorAll(".add-card").forEach(button => button.addEventListener("click", () => dialog.showModal()));
dialog.addEventListener("close", () => {
  if (dialog.returnValue !== "submit") return;
});

document.querySelector("#requestForm").addEventListener("submit", event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const title = String(data.get("title")).trim();
  const client = String(data.get("client")).trim();
  const description = String(data.get("brief")).trim();
  if (!title || !client || !description) return;
  const card = document.createElement("article");
  card.className = "task-card";
  card.dataset.title = title;
  card.dataset.client = client;
  card.dataset.stageName = "Novas demandas";
  const label = document.createElement("span");
  label.className = "client-label label-blue";
  label.textContent = client;
  const heading = document.createElement("h3");
  heading.textContent = title;
  const brief = document.createElement("p");
  brief.className = "card-description";
  brief.textContent = description;
  const row = document.createElement("div");
  row.className = "card-label-row";
  row.append(label);
  const menu = document.createElement("span");
  menu.className = "card-menu";
  menu.textContent = "···";
  row.append(menu);
  const footer = document.createElement("div");
  footer.className = "card-footer";
  const pending = document.createElement("span");
  pending.className = "due-date";
  pending.textContent = "Briefing para revisar";
  footer.append(pending);
  const avatar = document.createElement("span");
  avatar.className = "avatar avatar-small avatar-teal";
  avatar.textContent = "LM";
  footer.append(avatar);
  card.append(row, heading, brief, footer);
  card.addEventListener("click", () => openDrawer(card));
  document.querySelector('[data-stage="briefing"] .card-stack').prepend(card);
  const count = document.querySelector('[data-stage="briefing"] .column-count');
  count.textContent = String(Number(count.textContent) + 1);
  event.currentTarget.reset();
  dialog.close("saved");
  showToast("Demanda registrada como briefing para revisão.");
});

document.querySelector("#approveButton").addEventListener("click", () => {
  showToast("Aprovação registrada. Próximo passo: confirmar entrega ou publicação.");
  closeDrawer();
});
document.querySelector("#requestChangesButton").addEventListener("click", () => {
  showToast("Retorno registrado. A equipe prepara uma nova versão para revisão.");
  closeDrawer();
});

document.querySelector(".send-reply").addEventListener("click", () => {
  const field = document.querySelector(".reply-box textarea");
  if (!field.value.trim()) { field.focus(); return; }
  showToast("Comentário adicionado ao histórico desta versão.");
  field.value = "";
});

document.querySelector("#filterButton").addEventListener("click", () => {
  document.querySelector("#filterStrip").hidden = !document.querySelector("#filterStrip").hidden;
});
document.querySelector("#clearFilters").addEventListener("click", () => {
  document.querySelector("#filterStrip").hidden = true;
  showToast("Filtros removidos.");
});
document.querySelector("#searchInput").addEventListener("input", event => {
  const query = event.target.value.trim().toLocaleLowerCase("pt-BR");
  document.querySelectorAll(".task-card").forEach(card => {
    card.hidden = Boolean(query) && !`${card.dataset.title} ${card.dataset.client}`.toLocaleLowerCase("pt-BR").includes(query);
  });
});

document.querySelectorAll(".view-tab").forEach(tab => tab.addEventListener("click", () => {
  document.querySelectorAll(".view-tab").forEach(item => { item.classList.remove("active"); item.setAttribute("aria-selected", "false"); });
  tab.classList.add("active");
  tab.setAttribute("aria-selected", "true");
  const listMode = tab.textContent.includes("Lista");
  document.querySelector(".kanban-board").classList.toggle("list-view", listMode);
  document.querySelector("#listViewHeader").hidden = !listMode;
}));
