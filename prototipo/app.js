const { stages, transition, listAssignees, filterRequestsByAssignee, taskBlockers, findActiveTaskTimer, validateLocalFiles, mergeLocalFiles, serializeRequestsJson, serializeRequestsCsv } = window.Mix7Workflow;
const STORAGE_KEY = "mix7.workflow.v1";
const MINIMIZED_KEY = "mix7.workflow.minimized.v1";
const MAX_FILE_BYTES = 15 * 1024 * 1024;
const drawer = document.querySelector("#detailDrawer");
const scrim = document.querySelector("#scrim");
const dialog = document.querySelector("#requestDialog");
const briefingFileInput = document.querySelector("#briefingFileInput");
const briefingFileDropZone = document.querySelector("#briefingFileDropZone");
const briefingFileStatus = document.querySelector("#briefingFileStatus");
const toast = document.querySelector("#toast");
const fileInput = document.querySelector("#versionFileInput");
let toastTimer;
let activeRequestId = null;
let selectedFileAction = null;
let activePreviewUrl = null;
let activeMediaObserver = null;
let activeCommentAnchor = null;
let imageAnchorMode = false;
let viewedVersionId = null;
let activePage = "requests";
let activeStageFilter = "";
let activeClientFilter = "";
let activeDueFilter = "";
let columnActionStage = "";
let minimizedRequestIds = loadMinimizedIds();
let state = loadState();

function formatDuration(seconds) {
  const total = Math.max(0, Math.floor(Number(seconds) || 0));
  return [Math.floor(total / 3600), Math.floor((total % 3600) / 60), total % 60]
    .map(part => String(part).padStart(2, "0")).join(":");
}

function loadMinimizedIds() {
  try {
    const value = JSON.parse(localStorage.getItem(MINIMIZED_KEY) || "[]");
    return Array.isArray(value) ? value.filter(item => typeof item === "string") : [];
  } catch {
    return [];
  }
}

function saveMinimizedIds() {
  try { localStorage.setItem(MINIMIZED_KEY, JSON.stringify(minimizedRequestIds)); } catch { /* Minimizar continua disponível até fechar esta página. */ }
}

function makeId() {
  return crypto.randomUUID ? crypto.randomUUID() : `d-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function showToast(message) {
  toast.textContent = message;
  toast.classList.add("show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("show"), 3200);
}

function loadState() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      const parsed = JSON.parse(saved);
      if (parsed.schemaVersion === 1 && Array.isArray(parsed.requests)) {
        for (const request of parsed.requests) {
          if (request.demo && !request.due) {
            const card = [...document.querySelectorAll(".task-card")].find(item => item.dataset.title === request.title);
            request.due = demoDueFromCard(card);
          }
          if (!Array.isArray(request.tasks)) request.tasks = [];
          for (const task of request.tasks) {
            if (!Array.isArray(task.dependencyTaskIds)) task.dependencyTaskIds = task.dependencyTaskId ? [task.dependencyTaskId] : [];
            if (typeof task.blockedReason !== "string") task.blockedReason = "";
            if (!Array.isArray(task.timeEntries)) task.timeEntries = [];
            if (typeof task.timerStartedAt !== "string") task.timerStartedAt = null;
          }
          if (!Array.isArray(request.briefingRevisions)) request.briefingRevisions = [];
          if (!Array.isArray(request.briefingFiles)) request.briefingFiles = [];
          if (typeof request.planReviewRequired !== "boolean") request.planReviewRequired = false;
          for (const field of ["origin", "channel", "acceptanceCriteria", "references"]) {
            if (typeof request[field] !== "string") request[field] = "";
          }
        }
        return parsed;
      }
    }
  } catch {
    showToast("O armazenamento local está indisponível; as alterações podem não persistir.");
  }

  const requests = [...document.querySelectorAll(".task-card")].map((card, index) => {
    const stageKey = card.closest("[data-stage]").dataset.stage;
    const stage = stageKey === "review" ? "clientReview" : stageKey;
    const featuredComments = card.classList.contains("card-featured")
      ? [...document.querySelectorAll("#commentsContainer .comment-item")].map(item => ({
          versionId: `demo-${index}-v3`,
          author: item.querySelector(".comment-meta strong")?.textContent || "Cliente demonstrativo",
          audience: "client",
          text: item.querySelector(".comment-body p")?.textContent || "",
          at: item.querySelector(".comment-meta > span")?.textContent || "",
        }))
      : [];
    if (card.classList.contains("card-featured")) {
      const note = document.querySelector("#commentsContainer .internal-note");
      if (note) featuredComments.push({ versionId: `demo-${index}-v3`, author: "Nota interna demonstrativa", audience: "internal", text: note.textContent.replace(/^✳\s*/, ""), at: "" });
    }
    return {
      id: `demo-${index}-${slug(card.dataset.title)}`,
      title: card.dataset.title,
      client: card.dataset.client,
      brief: card.querySelector(".card-description")?.textContent || card.querySelector(".creative-thumb strong")?.textContent.replace(/\s+/g, " ") || "Demanda demonstrativa.",
      type: "Peça de redes sociais",
      due: demoDueFromCard(card),
      stage,
      demo: true,
      briefingRevisions: [],
      planReviewRequired: false,
      tasks: [],
      versions: card.classList.contains("card-featured") ? [
        { id: `demo-${index}-v1`, number: 1, fileName: "Versão 01 · sem arquivo real", fileKey: null, createdBy: "Equipe Mix7 (demonstração)", createdAt: "", sharedAt: null, decision: null },
        { id: `demo-${index}-v2`, number: 2, fileName: "Versão 02 · sem arquivo real", fileKey: null, createdBy: "Equipe Mix7 (demonstração)", createdAt: "", sharedAt: null, decision: null },
        { id: `demo-${index}-v3`, number: 3, fileName: "Prévia demonstrativa · versão 03", fileKey: null, createdBy: "Equipe Mix7 (demonstração)", createdAt: "", sharedAt: stage === "clientReview" || stage === "adjustments" || stage === "delivery" ? "demo" : null, decision: null },
      ] : [{ id: `demo-${index}-v1`, number: 1, fileName: "", fileKey: null, createdBy: "Equipe Mix7 (demonstração)", createdAt: "", sharedAt: stage === "clientReview" || stage === "adjustments" || stage === "delivery" ? "demo" : null, decision: null }],
      comments: featuredComments,
      history: [{ type: "fixture_loaded", details: {}, at: new Date().toISOString() }],
      delivery: null,
    };
  });
  return { schemaVersion: 1, requests };
}

function slug(value) {
  return String(value || "demanda").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
}

function saveState() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    return true;
  } catch {
    showToast("Não foi possível salvar neste navegador. Libere espaço ou verifique as configurações de privacidade.");
    return false;
  }
}

function openAssetDatabase() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open("mix7.workflow.assets", 1);
    request.onupgradeneeded = () => request.result.createObjectStore("assets");
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error || new Error("Não foi possível abrir o armazenamento de arquivos."));
  });
}

async function storeFile(key, file) {
  const database = await openAssetDatabase();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction("assets", "readwrite");
    transaction.objectStore("assets").put(file, key);
    transaction.oncomplete = () => { database.close(); resolve(); };
    transaction.onerror = () => { database.close(); reject(transaction.error || new Error("Falha ao salvar o arquivo.")); };
    transaction.onabort = () => { database.close(); reject(transaction.error || new Error("O salvamento do arquivo foi cancelado.")); };
  });
}

async function readFile(key) {
  const database = await openAssetDatabase();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction("assets", "readonly");
    const request = transaction.objectStore("assets").get(key);
    request.onsuccess = () => { database.close(); resolve(request.result || null); };
    request.onerror = () => { database.close(); reject(request.error || new Error("Falha ao abrir o arquivo.")); };
  });
}

function node(tag, className, text) {
  const element = document.createElement(tag);
  if (className) element.className = className;
  if (text !== undefined) element.textContent = text;
  return element;
}

function formatDue(value) {
  if (!value) return "Prazo não definido";
  const date = new Date(`${value}T12:00:00`);
  return new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "short" }).format(date).replace(".", "");
}

function formatTimestamp(value) {
  if (!value) return "demonstração";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString("pt-BR");
}

function makeCard(request) {
  const card = node("article", "task-card");
  card.dataset.requestId = request.id;
  card.dataset.title = request.title;
  card.dataset.client = request.client;
  card.dataset.stageName = stages[request.stage];
  card.dataset.stage = request.stage;
  card.dataset.due = request.due || "";
  card.tabIndex = 0;
  card.setAttribute("role", "button");
  card.setAttribute("aria-label", `${request.title}, ${stages[request.stage]}`);

  const row = node("div", "card-label-row");
  const client = node("span", "client-label label-blue", request.client);
  row.append(client);
  if (request.stage === "completed" && request.delivery?.destinationType) {
    const outcomeLabels = { delivered: "✓ Entregue", scheduled: "◷ Agendado", published: "↗ Publicado" };
    row.append(node("span", "status-pill status-approved", outcomeLabels[request.delivery.destinationType] || "✓ Concluído"));
  }
  if (request.versions?.at(-1)?.decision?.result === "approved") row.append(node("span", "status-pill status-approved", "✓ Aprovado"));
  if (request.versions?.at(-1)?.decision?.result === "changes_requested") row.append(node("span", "status-pill status-changes", "↺ Ajustes"));
  card.append(row, node("h3", "", request.title), node("p", "card-description", request.brief));

  const version = request.versions?.at(-1);
  if (version?.fileName) card.append(node("p", "card-description file-note", `Arquivo: ${version.fileName}`));
  const footer = node("div", "card-footer");
  footer.append(node("span", "due-date", request.due ? `◷ ${formatDue(request.due)}` : "Prazo não definido"));
  footer.append(node("span", "card-stage-label", request.demo ? "Exemplo fictício" : "Equipe Mix7"));
  card.append(footer);
  card.addEventListener("click", () => openDrawer(request.id));
  card.addEventListener("keydown", event => { if (event.key === "Enter" || event.key === " ") { event.preventDefault(); openDrawer(request.id); } });
  return card;
}

function renderBoard() {
  const assigneeFilter = document.querySelector("#assigneeFilter");
  const selectedAssignee = assigneeFilter.value;
  const assignees = listAssignees(state.requests);
  assigneeFilter.replaceChildren(new Option("Equipe toda", ""));
  for (const assignee of assignees) assigneeFilter.add(new Option(assignee, assignee));
  if (assignees.includes(selectedAssignee)) assigneeFilter.value = selectedAssignee;
  const activeAssignee = assigneeFilter.value;
  const scopeNote = document.querySelector("#assigneeScope");
  scopeNote.hidden = !activeAssignee;
  scopeNote.textContent = activeAssignee
    ? `Filtro visual: demandas com tarefas pendentes da rodada atual atribuídas a ${activeAssignee}. Os indicadores acima são gerais; o filtro não limita acesso.`
    : "";
  const visibleRequests = filterRequestsByAssignee(state.requests, activeAssignee);

  for (const stage of Object.keys(stages)) {
    const column = document.querySelector(`.kanban-column[data-stage="${stage}"]`);
    if (!column) continue;
    const stack = column.querySelector(".card-stack");
    stack.querySelectorAll(".task-card").forEach(card => card.remove());
    const items = visibleRequests.filter(request => request.stage === stage);
    const addButton = stack.querySelector(".add-card");
    for (const request of items.slice().reverse()) stack.insertBefore(makeCard(request), addButton || null);
    const count = column.querySelector(".column-count");
    if (count) count.textContent = String(items.length);
  }
  const active = state.requests.filter(request => request.stage !== "completed").length;
  const approvals = state.requests.filter(request => request.stage === "clientReview").length;
  document.querySelector("#activeDemandCount").textContent = String(active);
  document.querySelector("#pendingApprovalCount").textContent = String(approvals);
  document.querySelector("#navDemandCount").textContent = String(active);
  renderMinimizedRequests();
  renderWorkspacePage();
}

function updateBriefingFileStatus(files = briefingFileInput.files) {
  const names = Array.from(files || []).map(file => file.name);
  briefingFileStatus.textContent = names.length
    ? `${names.length} arquivo(s): ${names.join(", ")}`
    : "Nenhum arquivo selecionado.";
}

function hasFileTransfer(event) {
  return Array.from(event.dataTransfer?.types || []).includes("Files");
}

function demoDueFromCard(card) {
  if (!card) return "";
  const text = card.querySelector(".due-date")?.textContent.trim().replace(/^◷\s*/, "").toLocaleLowerCase("pt-BR") || "";
  if (text.startsWith("hoje")) return localDateStamp();
  const match = text.match(/^(\d{1,2})\s+(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/);
  if (!match) return "";
  const months = { jan: "01", fev: "02", mar: "03", abr: "04", mai: "05", jun: "06", jul: "07", ago: "08", set: "09", out: "10", nov: "11", dez: "12" };
  const day = match[1].padStart(2, "0");
  let year = new Date().getFullYear();
  const month = months[match[2]];
  const todayMonth = String(new Date().getMonth() + 1).padStart(2, "0");
  if (month < todayMonth) year += 1;
  return `${year}-${month}-${day}`;
}

function localDateStamp(date = new Date()) {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
  return local.toISOString().slice(0, 10);
}

function renderMinimizedRequests() {
  const tray = document.querySelector("#minimizedRequests");
  const requests = minimizedRequestIds.map(id => state.requests.find(request => request.id === id)).filter(Boolean);
  minimizedRequestIds = requests.map(request => request.id);
  saveMinimizedIds();
  tray.replaceChildren();
  tray.hidden = requests.length === 0;
  if (!requests.length) return;
  tray.append(node("span", "minimized-label", "Demandas minimizadas"));
  for (const request of requests) {
    const item = node("div", "minimized-item");
    const chip = node("button", "minimized-request", "");
    chip.type = "button";
    chip.setAttribute("aria-label", `Reabrir ${request.title}`);
    chip.append(node("strong", "", request.title), node("small", "", `${request.client} · ${stages[request.stage]}`));
    chip.addEventListener("click", () => openDrawer(request.id));
    const dismiss = node("button", "minimized-dismiss", "×");
    dismiss.type = "button";
    dismiss.setAttribute("aria-label", `Remover atalho minimizado de ${request.title}`);
    dismiss.addEventListener("click", event => {
      event.stopPropagation();
      minimizedRequestIds = minimizedRequestIds.filter(id => id !== request.id);
      saveMinimizedIds();
      renderMinimizedRequests();
    });
    item.append(chip, dismiss);
    tray.append(item);
  }
}

function matchesDueFilter(request, filter) {
  if (!filter) return true;
  if (filter === "none") return !request.due;
  if (!request.due) return false;
  const due = new Date(`${request.due}T23:59:59`);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  if (filter === "overdue") return due < today;
  if (filter === "today") return request.due === localDateStamp(today);
  if (filter === "week") {
    const end = new Date(today);
    end.setDate(end.getDate() + 7);
    end.setHours(23, 59, 59, 999);
    return due >= today && due <= end;
  }
  return true;
}

function selectedRequests() {
  const query = document.querySelector("#searchInput").value.trim().toLocaleLowerCase("pt-BR");
  const selectedAssignee = document.querySelector("#assigneeFilter").value;
  return filterRequestsByAssignee(state.requests, selectedAssignee).filter(request => {
    const pageStage = activePage === "approvals" ? "clientReview" : "";
    const stage = activeStageFilter || pageStage;
    return (!stage || request.stage === stage)
      && (!activeClientFilter || request.client === activeClientFilter)
      && matchesDueFilter(request, activeDueFilter)
      && (!query || `${request.title} ${request.client} ${request.brief}`.toLocaleLowerCase("pt-BR").includes(query));
  });
}

function renderWorkspacePage() {
  const panel = document.querySelector("#workspaceView");
  const boardPage = ["overview", "requests", "approvals"].includes(activePage);
  panel.hidden = boardPage;
  document.querySelector(".kanban-board").hidden = !boardPage;
  document.querySelector("#listViewHeader").hidden = !boardPage || !document.querySelector(".kanban-board").classList.contains("list-view");
  document.querySelector(".summary-grid").hidden = !["overview", "requests"].includes(activePage);
  document.querySelector(".board-heading").hidden = !boardPage;
  document.querySelector(".board-footnote").hidden = !boardPage;
  const titles = {
    overview: ["Visão geral", "Acompanhe o fluxo e os pontos que pedem atenção."],
    requests: ["Demandas", "Organize o trabalho do briefing à conclusão."],
    approvals: ["Aprovações", "Demandas aguardando decisão do cliente."],
    team: ["Equipe", "Tarefas atribuídas e andamento nesta demonstração local."],
    clients: ["Clientes", "Clientes vinculados às demandas registradas."],
    calendar: ["Calendário", "Prazos informados em demandas e tarefas."],
    knowledge: ["Conhecimento", "Guia rápido para usar o fluxo da Mix7."],
    accesses: ["Acessos", "O que esta versão local registra sobre acesso e privacidade."],
  };
  document.querySelector("#pageTitle").textContent = titles[activePage][0];
  document.querySelector("#pageSubtitle").textContent = titles[activePage][1];
  document.querySelector("#pageBreadcrumb").textContent = titles[activePage][0];
  if (boardPage) {
    const requests = selectedRequests();
    document.querySelectorAll(".task-card").forEach(card => {
      card.hidden = !requests.some(request => request.id === card.dataset.requestId);
    });
    document.querySelectorAll(".kanban-column").forEach(column => {
      const stage = activeStageFilter || (activePage === "approvals" ? "clientReview" : "");
      column.hidden = Boolean(stage) && column.dataset.stage !== stage;
      const count = column.querySelector(".column-count");
      if (count) count.textContent = String(requests.filter(request => request.stage === column.dataset.stage).length);
    });
    renderActiveFilters();
    if (activePage === "approvals" && !requests.length) {
      // Empty stage remains visible so that the user understands there is nothing awaiting a client.
    }
    return;
  }
  renderActiveFilters();
  renderWorkspacePanel(panel, selectedRequests());
}

function panelHeading(title, text) {
  const heading = node("div", "workspace-panel-heading");
  heading.append(node("h2", "", title), node("p", "", text));
  return heading;
}

function makeOpenRequestButton(request, detail = "", beforeOpen = null) {
  const button = node("button", "workspace-request", "");
  button.type = "button";
  button.append(node("strong", "", request.title), node("span", "", `${request.client} · ${stages[request.stage]}`));
  if (detail) button.append(node("small", "", detail));
  button.addEventListener("click", () => {
    beforeOpen?.();
    openDrawer(request.id);
  });
  return button;
}

function renderWorkspacePanel(panel, requests) {
  panel.replaceChildren();
  if (activePage === "team") {
    panel.append(panelHeading("Fila de tarefas", "A lista usa os responsáveis digitados no plano; não cria contas nem permissões."));
    const rows = [];
    for (const request of requests) {
      const round = Number(request.versions?.at(-1)?.number) || 1;
      for (const task of request.tasks || []) {
        if ((Number(task.round) || round) !== round || task.status === "completed") continue;
        rows.push({ request, task, blockers: taskBlockers(request, task) });
      }
    }
    rows.sort((a, b) => (a.task.due || "9999").localeCompare(b.task.due || "9999"));
    if (!rows.length) return panel.append(emptyPanel("Nenhuma tarefa pendente", "Crie ou atribua tarefas dentro de uma demanda para vê-las aqui."));
    const list = node("div", "workspace-list");
    for (const { request, task, blockers } of rows) {
      const row = node("article", "workspace-task");
      row.append(node("div", "workspace-task-copy", ""));
      const copy = row.firstChild;
      copy.append(node("strong", "", task.title), node("span", "", `${task.assignee || "Sem responsável"} · ${request.client}`));
      if (blockers.length) copy.append(node("small", "workspace-warning", blockers.join(" · ")));
      const open = node("button", "secondary-button", "Abrir demanda");
      open.type = "button";
      open.addEventListener("click", () => openDrawer(request.id));
      row.append(open);
      list.append(row);
    }
    panel.append(list);
    return;
  }
  if (activePage === "clients") {
    panel.append(panelHeading("Clientes deste quadro", "Cada cliente reúne as demandas que têm esse nome."));
    const clients = new Map();
    for (const request of requests) clients.set(request.client, [...(clients.get(request.client) || []), request]);
    if (!clients.size) return panel.append(emptyPanel("Nenhum cliente encontrado", "Ajuste a busca ou os filtros para ver clientes."));
    const list = node("div", "workspace-list workspace-client-list");
    for (const [client, items] of [...clients].sort(([a], [b]) => a.localeCompare(b, "pt-BR"))) {
      const group = node("section", "workspace-client");
      group.append(node("h3", "", `${client} · ${items.length} demanda${items.length === 1 ? "" : "s"}`));
      items.forEach(item => group.append(makeOpenRequestButton(item)));
      list.append(group);
    }
    panel.append(list);
    return;
  }
  if (activePage === "calendar") {
    panel.append(panelHeading("Cronograma e prazos", "O Gantt usa início e prazo informados nas tarefas; sem datas, não há previsão calculada nem disponibilidade estimada."));
    const currentTasks = [];
    const undatedTasks = [];
    const events = [];
    for (const request of requests) {
      if (request.due) events.push({ date: request.due, title: request.title, detail: `${request.client} · Demanda`, request });
      const round = Number(request.versions?.at(-1)?.number) || 1;
      for (const task of request.tasks || []) {
        if ((Number(task.round) || round) !== round) continue;
        const entry = { task, request, start: task.plannedStart || task.due || "", end: task.due || task.plannedStart || "" };
        if (entry.start) currentTasks.push(entry);
        else undatedTasks.push(entry);
        if (task.due) events.push({ date: task.due, title: task.title, detail: `${task.assignee || "Sem responsável"} · ${request.client}`, request });
      }
    }
    if (currentTasks.length) {
      panel.append(node("h3", "calendar-section-title", "Cronograma de tarefas"));
      const dates = currentTasks.flatMap(entry => [entry.start, entry.end]).sort();
      const first = new Date(`${dates[0]}T12:00:00`);
      const last = new Date(`${dates.at(-1)}T12:00:00`);
      const days = Math.max(1, Math.round((last - first) / 86400000) + 1);
      const timeline = node("section", "gantt-timeline");
      timeline.setAttribute("aria-label", `Cronograma Gantt de ${formatDue(dates[0])} a ${formatDue(dates.at(-1))}`);
      const axis = node("div", "gantt-axis");
      const axisLabel = node("span", "", "Tarefa · responsável");
      const axisTrack = node("div", "gantt-axis-track");
      const tickCount = Math.min(8, days);
      for (let index = 0; index < tickCount; index++) {
        const offset = Math.round((days - 1) * index / Math.max(1, tickCount - 1));
        const date = new Date(first.getTime() + offset * 86400000);
        const tick = node("time", "gantt-tick", new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "short" }).format(date).replace(".", ""));
        tick.style.left = `${days === 1 ? 0 : offset / (days - 1) * 100}%`;
        tick.dateTime = localDateStamp(date);
        axisTrack.append(tick);
      }
      axis.append(axisLabel, axisTrack);
      timeline.append(axis);
      const rows = node("div", "gantt-rows");
      for (const entry of currentTasks) {
        const row = node("div", "gantt-row");
        const label = makeOpenRequestButton(entry.request);
        label.classList.add("gantt-task-label");
        label.replaceChildren(node("strong", "", entry.task.title), node("span", "", `${entry.task.assignee || "Sem responsável"} · ${entry.request.client}`));
        const track = node("div", "gantt-track");
        const start = new Date(`${entry.start}T12:00:00`);
        const end = new Date(`${entry.end}T12:00:00`);
        const startOffset = Math.round((start - first) / 86400000);
        const endOffset = Math.round((end - first) / 86400000);
        const bar = node("span", `gantt-bar${entry.start === entry.end ? " gantt-milestone" : ""}`, entry.start === entry.end ? "◆" : `${formatDue(entry.start)}–${formatDue(entry.end)}`);
        bar.style.left = `${days === 1 ? 0 : startOffset / days * 100}%`;
        bar.style.width = `${entry.start === entry.end ? "16px" : `${Math.max(1, (endOffset - startOffset + 1) / days * 100)}%`}`;
        bar.title = entry.start === entry.end ? `Marco em ${formatDue(entry.start)}` : `${formatDue(entry.start)} a ${formatDue(entry.end)}`;
        track.append(bar);
        row.append(label, track);
        rows.append(row);
      }
      timeline.append(rows);
      panel.append(timeline);
    } else {
      panel.append(emptyPanel("Sem tarefas com datas", "Informe início planejado ou prazo nas tarefas para montar o cronograma."));
    }
    if (undatedTasks.length) {
      const section = node("section", "gantt-undated");
      section.append(node("h3", "calendar-section-title", `Tarefas sem data (${undatedTasks.length})`));
      const list = node("div", "workspace-list");
      for (const entry of undatedTasks) list.append(makeOpenRequestButton(entry.request, `${entry.task.assignee || "Sem responsável"} · ${entry.request.client} · V${String(entry.task.round).padStart(2, "0")}`));
      section.append(list);
      panel.append(section);
    }
    panel.append(panelHeading("Prazos registrados", "Demandas e tarefas com prazo final informado. O protótipo não envia lembretes."));
    events.sort((a, b) => a.date.localeCompare(b.date));
    if (!events.length) return panel.append(emptyPanel("Nenhum prazo final registrado", "Adicione um prazo desejado à demanda ou um prazo final à tarefa para vê-lo aqui."));
    const list = node("div", "workspace-list");
    for (const event of events) {
      const row = makeOpenRequestButton(event.request, event.detail);
      row.classList.add("calendar-event");
      row.prepend(node("time", "calendar-date", formatDue(event.date)));
      list.append(row);
    }
    panel.append(list);
    return;
  }
  if (activePage === "knowledge") {
    panel.append(panelHeading("Guia do fluxo", "Uma demanda só avança quando a etapa anterior está completa."));
    const list = node("ol", "knowledge-steps");
    ["Briefing: registre origem, canal/peça e critério de aceite.", "Planejamento: crie tarefas e informe quem fará cada uma.", "Execução: conclua as tarefas; dependências e impedimentos controlam a ordem.", "Revisão interna: confira o arquivo antes de compartilhar com o cliente.", "Aprovação: decisão ou pedido de ajuste fica ligado à versão analisada.", "Conclusão: registre entrega, agendamento ou publicação e sua evidência."].forEach(text => list.append(node("li", "", text)));
    panel.append(list);
    const note = node("p", "utility-note", "Este guia descreve o fluxo-alvo aprovado e exercitado no protótipo. A rotina e os papéis reais da Mix7 ainda precisam de validação com um caso anonimizado.");
    panel.append(note);
    return;
  }
  panel.append(panelHeading("Acesso e privacidade", "Esta versão não tem login, contas ou permissões reais."));
  panel.append(node("p", "utility-note", "Os nomes de responsáveis são texto livre. O filtro por profissional só organiza a tela; qualquer pessoa com acesso ao mesmo perfil de navegador pode ver os dados locais."));
  panel.append(node("p", "utility-note", "A matriz de papéis e acessos depende de validação da operação da Mix7 e de uma futura versão com servidor. Não use dados reais de clientes nesta demonstração."));
}

function emptyPanel(title, detail) {
  const box = node("div", "workspace-empty");
  box.append(node("strong", "", title), node("p", "", detail));
  return box;
}

function renderActiveFilters() {
  const strip = document.querySelector("#filterStrip");
  const filters = [];
  if (activeStageFilter) filters.push([stages[activeStageFilter], () => { activeStageFilter = ""; }]);
  if (activeClientFilter) filters.push([activeClientFilter, () => { activeClientFilter = ""; }]);
  if (activeDueFilter) filters.push([{ overdue: "Atrasadas", today: "Vencem hoje", week: "Próximos 7 dias", none: "Sem prazo" }[activeDueFilter], () => { activeDueFilter = ""; }]);
  strip.replaceChildren();
  strip.hidden = filters.length === 0;
  if (!filters.length) return;
  strip.append(node("span", "", "Filtrando por:"));
  for (const [label, clear] of filters) {
    const chip = node("button", "filter-chip", "");
    chip.type = "button";
    chip.append(document.createTextNode(label), node("b", "", " ×"));
    chip.addEventListener("click", () => { clear(); renderBoard(); });
    strip.append(chip);
  }
  const clear = node("button", "clear-filters", "Limpar filtros");
  clear.type = "button";
  clear.addEventListener("click", resetFilters);
  strip.append(clear);
}

function resetFilters() {
  activeStageFilter = "";
  activeClientFilter = "";
  activeDueFilter = "";
  document.querySelector("#assigneeFilter").value = "";
  document.querySelector("#searchInput").value = "";
  renderBoard();
}

function currentRequest() {
  return state.requests.find(request => request.id === activeRequestId) || null;
}

function appendAction(container, label, action, className = "secondary-button") {
  const button = node("button", className, label);
  button.type = "button";
  button.addEventListener("click", action);
  container.append(button);
  return button;
}

function updateRequest(action, payload = {}, successMessage = "Alteração salva.") {
  const request = currentRequest();
  if (!request) return false;
  try {
    if (action === "start_task_timer") {
      const active = findActiveTaskTimer(state.requests);
      if (active && (active.request.id !== request.id || active.task.id !== payload.taskId)) {
        throw new Error("Pare o cronômetro atual antes de iniciar outro.");
      }
    }
    const commentActions = ["add_comment", "internal_changes", "client_changes"];
    const anchorMatchesVersion = activeCommentAnchor?.versionId === request.versions.at(-1)?.id;
    const actionPayload = commentActions.includes(action) && anchorMatchesVersion ? { ...payload, anchor: { ...activeCommentAnchor } } : payload;
    const updated = transition(request, action, actionPayload);
    state.requests = state.requests.map(item => item.id === request.id ? updated : item);
    if (action === "new_version") viewedVersionId = updated.versions.at(-1)?.id || null;
    if (commentActions.includes(action)) {
      activeCommentAnchor = null;
      imageAnchorMode = false;
    }
    if (["attach_file", "new_version"].includes(action)) {
      activeCommentAnchor = null;
      imageAnchorMode = false;
    }
    saveState();
    renderBoard();
    renderDrawer();
    showToast(successMessage);
    return true;
  } catch (error) {
    showToast(error.message);
    return false;
  }
}

function renderTasks(request) {
  const list = document.querySelector("#taskList");
  list.replaceChildren();
  const currentVersion = request.versions.at(-1).number;
  const round = currentVersion + (request.stage === "adjustments" ? 1 : 0);
  const tasks = request.tasks || [];
  document.querySelector("#taskCount").textContent = String(tasks.length);
  const form = document.querySelector("#taskForm");
  form.hidden = !["planning", "adjustments"].includes(request.stage);
  const dependencySelect = form.elements.namedItem("taskDependencies");
  const sourceHint = document.querySelector("#taskSourceHint");
  const sourceCommentField = form.elements.namedItem("taskSourceCommentId");
  form.elements.namedItem("taskDue").min = form.elements.namedItem("taskPlannedStart").value || "";
  if (request.stage !== "adjustments" && sourceCommentField.value) {
    sourceCommentField.value = "";
    sourceHint.hidden = true;
    sourceHint.textContent = "";
  }
  dependencySelect.replaceChildren(new Option("Sem dependência", ""));
  for (const task of tasks.filter(task => task.round === round)) dependencySelect.add(new Option(`${task.title} · ${task.assignee}`, task.id));
  document.querySelector("#taskHint").textContent = request.stage === "planning" ? "Inclua pelo menos uma tarefa atribuída antes de iniciar a execução." : request.stage === "adjustments" ? `Tarefas para a versão V${String(round).padStart(2, "0")}; conclua todas antes de anexá-la.` : `Tarefas da rodada V${String(round).padStart(2, "0")}. Os nomes são texto livre nesta demonstração.`;
  for (const task of tasks) {
    const blockers = taskBlockers(request, task);
    const item = node("li", `task-list-item${task.status === "completed" ? " task-done" : ""}${blockers.length ? " task-blocked" : ""}`);
    const details = node("div", "task-list-details");
    details.append(node("strong", "", `V${String(task.round).padStart(2, "0")} · ${task.title}`), node("span", "", `${task.assignee}${task.estimateHours ? ` · ${task.estimateHours} h` : ""}${task.due ? ` · ${formatDue(task.due)}` : ""}`));
    const recordedSeconds = (task.timeEntries || []).reduce((sum, entry) => sum + (Number(entry.durationSeconds) || 0), 0);
    const timerLine = node("span", "task-time-total", `Tempo registrado: ${formatDuration(recordedSeconds)}`);
    details.append(timerLine);
    if (task.timerStartedAt) {
      const runningLine = node("span", "task-time-running", `Cronômetro ativo: ${formatDuration(recordedSeconds + Math.floor((Date.now() - Date.parse(task.timerStartedAt)) / 1000))}`);
      runningLine.dataset.timerStartedAt = task.timerStartedAt;
      runningLine.dataset.timerBaseSeconds = String(recordedSeconds);
      details.append(runningLine);
    }
    const sourceComment = task.sourceCommentId && request.comments.find(comment => comment.id === task.sourceCommentId);
    if (sourceComment) {
      const sourceVersion = request.versions.find(version => version.id === sourceComment.versionId)?.number;
      details.append(node("span", "task-source-reference", `Feedback do cliente V${String(sourceVersion || "?").padStart(2, "0")}: ${historyExcerpt(sourceComment.text, 100)}`));
    }
    for (const blocker of blockers) details.append(node("span", "task-blocker-note", `Impedida: ${blocker}`));
    item.append(details);
    if (task.round === round && ["doing", "adjustments"].includes(request.stage)) {
      const activeTimer = findActiveTaskTimer(state.requests);
      const timerButton = node("button", "task-timer-button", task.timerStartedAt ? "Parar cronômetro" : "Iniciar cronômetro");
      timerButton.type = "button";
      timerButton.disabled = task.timerStartedAt ? activeTimer?.request.id !== request.id : task.status === "completed" || blockers.length > 0 || Boolean(activeTimer);
      if (activeTimer && !task.timerStartedAt) timerButton.title = `Pare primeiro o cronômetro de “${activeTimer.task.title}”.`;
      timerButton.addEventListener("click", () => {
        if (task.timerStartedAt) updateRequest("stop_task_timer", { taskId: task.id }, "Sessão de tempo registrada.");
        else updateRequest("start_task_timer", { taskId: task.id }, "Cronômetro iniciado.");
      });
      item.append(timerButton);
      const button = node("button", "task-toggle", task.status === "completed" ? "Reabrir" : "Concluir tarefa");
      button.type = "button";
      button.disabled = task.status !== "completed" && (blockers.length > 0 || Boolean(task.timerStartedAt));
      button.addEventListener("click", () => updateRequest("toggle_task", { taskId: task.id }, task.status === "completed" ? "Tarefa reaberta." : "Tarefa concluída."));
      item.append(button);
      if (task.status !== "completed" && !task.timerStartedAt) {
        const blockerForm = node("form", "task-blocker-form");
        const reason = node("input", "workflow-input");
        reason.name = "reason";
        reason.maxLength = 500;
        reason.required = true;
        reason.placeholder = "Motivo obrigatório para sinalizar impedimento";
        reason.setAttribute("aria-label", `Motivo do impedimento em ${task.title}`);
        reason.value = task.blockedReason || "";
        const submit = node("button", "secondary-button", task.blockedReason ? "Atualizar impedimento" : "Sinalizar impedimento");
        submit.type = "submit";
        blockerForm.append(reason, submit);
        blockerForm.addEventListener("submit", event => {
          event.preventDefault();
          updateRequest("set_task_blocker", { taskId: task.id, reason: reason.value }, task.blockedReason ? "Impedimento atualizado." : "Impedimento registrado.");
        });
        if (task.blockedReason) {
          const clear = node("button", "task-clear-blocker", "Remover impedimento");
          clear.type = "button";
          clear.addEventListener("click", () => updateRequest("set_task_blocker", { taskId: task.id, reason: "" }, "Impedimento removido."));
          blockerForm.append(clear);
        }
        item.append(blockerForm);
      }
    } else item.append(node("span", "task-status", blockers.length ? "Bloqueada" : task.status === "completed" ? "Concluída" : "Pendente"));
    list.append(item);
  }
  if (!tasks.length) list.append(node("li", "empty-comments", "Nenhuma tarefa cadastrada nesta rodada."));
}

function renderComments(request) {
  const container = document.querySelector("#commentsContainer");
  container.replaceChildren();
  const comments = [...request.comments].sort((a, b) => String(a.at).localeCompare(String(b.at)));
  for (const comment of comments) {
    const item = node("div", "comment-item");
    item.dataset.commentId = comment.id || "";
    item.append(node("span", `avatar avatar-small ${comment.audience === "client" ? "avatar-client" : "avatar-lilac"}`, comment.audience === "client" ? "CL" : "EQ"));
    const body = node("div", "comment-body");
    const meta = node("div", "comment-meta");
    meta.append(node("strong", "", comment.author), node("span", "", formatTimestamp(comment.at)), node("span", "client-role", `V${request.versions.find(version => version.id === comment.versionId)?.number || "?"}`));
    body.append(meta, node("p", "", comment.text));
    if (comment.anchor?.type === "image") {
      const versionNumber = request.versions.find(version => version.id === comment.versionId)?.number || "?";
      const anchor = node("button", "comment-anchor", `◉ V${String(versionNumber).padStart(2, "0")} · Imagem · x ${Math.round(comment.anchor.x * 100)}%, y ${Math.round(comment.anchor.y * 100)}%`);
      anchor.type = "button";
      anchor.title = "Abrir o ponto marcado na versão deste comentário";
      anchor.addEventListener("click", () => openCommentAnchor(comment));
      body.append(anchor);
    } else if (comment.anchor?.type === "video") {
      const versionNumber = request.versions.find(version => version.id === comment.versionId)?.number || "?";
      const anchor = node("button", "comment-anchor", `▶ V${String(versionNumber).padStart(2, "0")} · Vídeo · ${formatTimecode(comment.anchor.timeSeconds)}`);
      anchor.type = "button";
      anchor.title = "Abrir o instante marcado na versão deste comentário";
      anchor.addEventListener("click", () => openCommentAnchor(comment));
      body.append(anchor);
    }
    item.append(body);
    if (request.stage === "adjustments" && comment.audience === "client" && comment.id) {
      const linkedTasks = request.tasks.filter(task => task.sourceCommentId === comment.id);
      const sourceVersion = request.versions.find(version => version.id === comment.versionId)?.number;
      const action = node("button", "comment-anchor comment-task-action", linkedTasks.length ? `＋ Outra tarefa deste feedback (${linkedTasks.length} vinculada${linkedTasks.length === 1 ? "" : "s"})` : "＋ Planejar tarefa deste feedback");
      action.type = "button";
      action.addEventListener("click", () => {
        const form = document.querySelector("#taskForm");
        form.elements.namedItem("taskSourceCommentId").value = comment.id;
        form.elements.namedItem("taskTitle").value = `Ajustar: ${historyExcerpt(comment.text, 110 - "Ajustar: ".length)}`;
        const sourceHint = document.querySelector("#taskSourceHint");
        sourceHint.textContent = `Rascunho baseado no comentário do cliente da V${String(sourceVersion || "?").padStart(2, "0")}. Edite a tarefa e confirme o responsável antes de adicionar.`;
        sourceHint.hidden = false;
        form.scrollIntoView({ behavior: "smooth", block: "nearest" });
        form.elements.namedItem("taskAssignee").focus({ preventScroll: true });
      });
      body.append(action);
    }
    container.append(item);
  }
  if (!comments.length) container.append(node("p", "empty-comments", "Ainda não há comentários registrados."));
  document.querySelector("#commentCount").textContent = String(comments.length);
}

async function openCommentAnchor(comment) {
  const request = currentRequest();
  if (!request || !request.versions.some(version => version.id === comment.versionId)) return;
  viewedVersionId = comment.versionId;
  activeCommentAnchor = null;
  imageAnchorMode = false;
  await renderAsset(request);
  document.querySelector("#assetPreview").scrollIntoView({ block: "center", behavior: "smooth" });
  if (comment.anchor?.type === "image") {
    const marker = document.querySelector(`[data-image-comment="${CSS.escape(comment.id || "")}"]`);
    marker?.focus();
  } else if (comment.anchor?.type === "video") {
    const video = document.querySelector("#assetPreview video");
    if (!video) return;
    const seek = () => { video.currentTime = comment.anchor.timeSeconds; };
    if (video.readyState > 0) seek();
    else video.addEventListener("loadedmetadata", seek, { once: true });
  }
}

function formatTimecode(value) {
  const total = Math.max(0, Math.floor(Number(value) || 0));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = total % 60;
  return hours ? [hours, minutes, seconds].map(part => String(part).padStart(2, "0")).join(":") : [minutes, seconds].map(part => String(part).padStart(2, "0")).join(":");
}

function refreshTaskTimerDisplays() {
  document.querySelectorAll("[data-timer-started-at]").forEach(element => {
    const elapsed = Math.floor((Date.now() - Date.parse(element.dataset.timerStartedAt)) / 1000);
    element.textContent = `Cronômetro ativo: ${formatDuration(Number(element.dataset.timerBaseSeconds) + elapsed)}`;
  });
}

async function removeFile(key) {
  const database = await openAssetDatabase();
  return new Promise((resolve, reject) => {
    const transaction = database.transaction("assets", "readwrite");
    transaction.objectStore("assets").delete(key);
    transaction.oncomplete = () => { database.close(); resolve(); };
    transaction.onerror = () => { database.close(); reject(transaction.error || new Error("Falha ao remover arquivo temporário.")); };
    transaction.onabort = () => { database.close(); reject(transaction.error || new Error("A remoção do arquivo foi cancelada.")); };
  });
}

function historyExcerpt(value, maxLength = 180) {
  const text = String(value || "").replace(/\s+/g, " ").trim();
  return text.length > maxLength ? `${text.slice(0, maxLength - 1)}…` : text;
}

function historyVersionLabel(value) {
  const number = Number(value);
  return Number.isInteger(number) && number > 0 ? `V${String(number).padStart(2, "0")}` : "Versão";
}

function renderHistory(request) {
  const list = document.querySelector("#historyList");
  list.replaceChildren();
  for (const event of [...request.history].reverse()) {
    const details = event.details || {};
    const task = request.tasks.find(item => item.id === details.taskId);
    let summary = "";
    if (event.type === "add_task") {
      const dependencies = (details.dependencyTaskIds || []).map(id => request.tasks.find(item => item.id === id)?.title).filter(Boolean);
      const title = details.taskTitle || task?.title;
      const sourceComment = details.sourceCommentId && request.comments.find(comment => comment.id === details.sourceCommentId);
      const source = sourceComment ? ` · feedback ${historyVersionLabel(details.sourceVersionNumber)}: ${historyExcerpt(sourceComment.text, 100)}` : "";
      if (title) summary = `${title}${dependencies.length ? ` · após ${dependencies.join(", ")}` : ""}${source}`;
    } else if (event.type === "toggle_task") {
      const title = details.taskTitle || task?.title;
      if (title && ["completed", "pending"].includes(details.status)) summary = `${title} · ${details.status === "completed" ? "concluída" : "reaberta"}`;
    } else if (event.type === "set_task_blocker") {
      const title = details.taskTitle || task?.title;
      if (title) summary = `${title} · ${details.blocked ? `impedimento: ${historyExcerpt(details.reason, 120)}` : "impedimento removido"}`;
    } else if (event.type === "start_task_timer" || event.type === "stop_task_timer") {
      const title = details.taskTitle || task?.title;
      if (title) summary = `${title}${event.type === "stop_task_timer" ? ` · sessão ${formatDuration(details.durationSeconds)} · total ${formatDuration(details.totalSeconds)}` : " · início registrado"}`;
    } else if (event.type === "record_delivery") {
      const labels = { delivered: "entregue ao cliente", scheduled: "agendado", published: "publicado" };
      summary = `${historyVersionLabel(details.versionNumber)} · ${labels[details.destinationType] || "resultado sem tipo registrado"}${details.evidence ? ` · evidência: ${historyExcerpt(details.evidence)}` : ""}`;
    } else if (event.type === "created" && details.briefingFiles?.length) {
      summary = `Referências iniciais: ${details.briefingFiles.map(file => historyExcerpt(file.fileName, 48)).join(", ")}`;
    } else if (event.type === "briefing_revised") {
      summary = details.reason ? `Motivo: ${historyExcerpt(details.reason)}` : "";
    } else if (["submit_internal_review", "internal_approved", "internal_changes", "client_approved", "client_changes", "attach_file", "new_version", "add_comment"].includes(event.type)) {
      const versionLabel = historyVersionLabel(details.versionNumber);
      if (event.type === "submit_internal_review") summary = `${versionLabel} · ${details.fileName || "arquivo"}`;
      if (event.type === "internal_approved" || event.type === "client_approved") summary = versionLabel;
      if (event.type === "internal_changes" || event.type === "client_changes") summary = `${versionLabel}${details.commentText ? ` · ${historyExcerpt(details.commentText)}` : ""}`;
      if (event.type === "attach_file") summary = `${versionLabel} · ${details.previousFileName ? `Rascunho substituído: ${historyExcerpt(details.previousFileName, 72)} → ` : ""}${historyExcerpt(details.fileName || "arquivo", 72)}`;
      if (event.type === "new_version") summary = `${versionLabel} · ${details.fileName || "arquivo"}`;
      if (event.type === "add_comment") summary = `${versionLabel}${details.commentText ? ` · ${historyExcerpt(details.commentText)}` : ""}`;
    }
    const row = node("li", "history-event");
    row.append(node("span", "history-event-summary", `${eventLabel(event.type)}${summary ? ` · ${summary}` : ""} · ${formatTimestamp(event.at)}`));
    if (event.type === "attach_file" && details.previousFileKey && details.previousFileName) {
      const download = node("button", "history-file-download", `Baixar rascunho anterior · ${historyExcerpt(details.previousFileName, 72)}`);
      download.type = "button";
      download.addEventListener("click", () => downloadHistoricalFile(details.previousFileKey, details.previousFileName));
      row.append(download);
    }
    list.append(row);
  }
}

async function downloadHistoricalFile(fileKey, fileName) {
  return downloadLocalFile(fileKey, fileName, "O arquivo anterior não está disponível neste navegador.");
}

async function downloadLocalFile(fileKey, fileName, unavailableMessage = "O arquivo de referência não está disponível neste navegador.") {
  try {
    const file = await readFile(fileKey);
    if (!file) throw new Error(unavailableMessage);
    const url = URL.createObjectURL(file);
    const link = node("a");
    link.href = url;
    link.download = fileName;
    link.hidden = true;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) {
    showToast(error.message || "Não foi possível recuperar o rascunho anterior.");
  }
}

function eventLabel(type) {
  const names = {
    created: "Demanda criada", fixture_loaded: "Item demonstrativo carregado", briefing_ready: "Briefing liberado para planejamento", briefing_revised: "Briefing alterado; plano aguarda revisão", plan_confirmed: "Planejamento revisado e confirmado", add_task: "Tarefa atribuída", toggle_task: "Estado da tarefa alterado", set_task_blocker: "Impedimento da tarefa atualizado", start_task_timer: "Cronômetro iniciado", stop_task_timer: "Cronômetro parado", submit_internal_review: "Enviado para revisão interna", internal_approved: "Revisão interna aprovada e versão compartilhada", internal_changes: "Devolvido pela revisão interna", client_approved: "Versão aprovada pelo cliente", client_changes: "Ajustes solicitados pelo cliente", attach_file: "Arquivo anexado à versão", new_version: "Nova versão criada", record_delivery: "Entrega/publicação registrada", add_comment: "Comentário registrado",
  };
  return names[type] || type;
}

function makeTextarea(placeholder) {
  const field = node("textarea", "workflow-input");
  field.rows = 2;
  field.placeholder = placeholder;
  return field;
}

function renderActions(request) {
  const container = document.querySelector("#workflowActions");
  container.replaceChildren();
  const notice = node("span", "client-approval-note", "Demonstração local · sem login, cliente conectado ou permissões reais");
  container.append(notice);
  const buttons = node("div", "approval-buttons");
  container.append(buttons);

  if (request.stage === "briefing") {
    const missing = [
      ["origem do pedido", request.origin],
      ["canal ou peça", request.channel],
      ["critérios de aceite", request.acceptanceCriteria],
    ].filter(([, value]) => !String(value || "").trim()).map(([label]) => label);
    const ready = appendAction(buttons, "Confirmar briefing e planejar", () => updateRequest("briefing_ready", {}, "Briefing liberado para planejamento."), "approve-button");
    ready.disabled = missing.length > 0;
    if (missing.length) container.append(node("small", "workflow-hint", `Complete antes do planejamento: ${missing.join(", ")}.`));
  }
  else if (request.stage === "planning") {
    if (request.planReviewRequired) container.append(node("small", "workflow-hint", "O briefing mudou. Revise o briefing e as tarefas, ajuste o plano se necessário e confirme para retomar a execução."));
    const confirm = appendAction(buttons, request.planReviewRequired ? "Confirmar revisão do plano e retomar" : "Confirmar plano e iniciar", () => updateRequest("plan_confirmed", {}, request.planReviewRequired ? "Revisão do plano confirmada; execução retomada." : "Planejamento confirmado; demanda em produção."), "approve-button");
    confirm.disabled = !(request.tasks || []).some(task => task.round === request.versions.at(-1).number);
    if (confirm.disabled) container.append(node("small", "workflow-hint", "Adicione ao menos uma tarefa e informe o responsável."));
  }
  else if (request.stage === "doing") {
    appendAction(buttons, "Anexar arquivo", () => chooseFile("attach_file"));
    const ready = Boolean(request.versions.at(-1)?.fileKey);
    const roundTasks = (request.tasks || []).filter(task => task.round === request.versions.at(-1).number);
    const tasksReady = roundTasks.length > 0 && roundTasks.every(task => task.status === "completed");
    const review = appendAction(buttons, "Enviar para revisão interna", () => updateRequest("submit_internal_review", {}, "Enviado para revisão interna."), "approve-button");
    review.disabled = !ready || !tasksReady;
    if (!tasksReady) container.append(node("small", "workflow-hint", "Conclua todas as tarefas desta versão antes da revisão."));
    else if (!ready) container.append(node("small", "workflow-hint", "Anexe um arquivo antes de iniciar a revisão."));
  } else if (request.stage === "internalReview") {
    const reason = makeTextarea("Motivo se precisar devolver para produção…");
    reason.id = "internalReviewReason";
    container.insertBefore(reason, buttons);
    appendAction(buttons, "Devolver", () => updateRequest("internal_changes", { comment: reason.value }, "Devolvido para produção."));
    appendAction(buttons, "Aprovar e compartilhar", () => updateRequest("internal_approved", {}, "Versão compartilhada para decisão do cliente."), "approve-button");
  } else if (request.stage === "clientReview") {
    const reason = makeTextarea("Escreva os ajustes solicitados. Obrigatório para devolver.");
    reason.id = "clientDecisionReason";
    container.insertBefore(reason, buttons);
    const requestChanges = appendAction(buttons, "Solicitar ajustes", () => updateRequest("client_changes", { versionId: request.versions.at(-1).id, comment: reason.value }, "Pedido de ajustes registrado nesta versão."));
    const updateClientDecisionReady = () => { requestChanges.disabled = !reason.value.trim(); };
    reason.addEventListener("input", updateClientDecisionReady);
    updateClientDecisionReady();
    appendAction(buttons, "Aprovar versão", () => updateRequest("client_approved", { versionId: request.versions.at(-1).id }, "Versão aprovada. Falta registrar entrega/publicação."), "approve-button");
  } else if (request.stage === "adjustments") {
    container.append(node("small", "workflow-hint", "A nova versão preserva o pedido de ajuste e passa novamente pela revisão interna."));
    const nextRound = request.versions.at(-1).number + 1;
    const tasks = (request.tasks || []).filter(task => task.round === nextRound);
    const attach = appendAction(buttons, "Anexar nova versão", () => chooseFile("new_version"), "approve-button");
    attach.disabled = !tasks.length || tasks.some(task => task.status !== "completed");
  } else if (request.stage === "delivery") {
    const destinationType = node("select", "workflow-input");
    destinationType.id = "deliveryType";
    destinationType.setAttribute("aria-label", "Resultado após aprovação do cliente");
    destinationType.required = true;
    destinationType.add(new Option("Selecione o resultado após aprovação", ""));
    destinationType.add(new Option("Material entregue ao cliente", "delivered"));
    destinationType.add(new Option("Publicação agendada", "scheduled"));
    destinationType.add(new Option("Material publicado", "published"));
    const evidence = node("input", "workflow-input");
    evidence.id = "deliveryEvidence";
    evidence.setAttribute("aria-label", "Evidência de entrega, agendamento ou publicação");
    evidence.placeholder = "URL ou descrição da entrega/publicação (obrigatório)";
    const updateReady = () => { finish.disabled = !destinationType.value || !evidence.value.trim(); };
    destinationType.addEventListener("change", updateReady);
    evidence.addEventListener("input", updateReady);
    container.insertBefore(destinationType, buttons);
    container.insertBefore(evidence, buttons);
    const finish = appendAction(buttons, "Registrar resultado e concluir", () => updateRequest("record_delivery", { destinationType: destinationType.value, evidence: evidence.value }, "Resultado registrado e demanda concluída."), "approve-button");
    finish.disabled = true;
    container.append(node("small", "workflow-hint", "Escolha entrega, agendamento ou publicação e registre a evidência correspondente."));
  } else {
    const outcomes = { delivered: "Material entregue ao cliente", scheduled: "Publicação agendada", published: "Material publicado" };
    const outcome = outcomes[request.delivery?.destinationType] || "Resultado pós-aprovação não discriminado";
    container.append(node("small", "workflow-hint", `${outcome} · evidência: ${request.delivery?.evidence || "não registrada"}`));
  }
}

function chooseFile(action) {
  selectedFileAction = action;
  fileInput.value = "";
  fileInput.click();
}

function imageContentRect(image, frame) {
  const imageRect = image.getBoundingClientRect();
  const frameRect = frame.getBoundingClientRect();
  if (!image.naturalWidth || !image.naturalHeight || !imageRect.width || !imageRect.height) return null;
  const scale = Math.min(imageRect.width / image.naturalWidth, imageRect.height / image.naturalHeight);
  const width = image.naturalWidth * scale;
  const height = image.naturalHeight * scale;
  return {
    left: imageRect.left - frameRect.left + (imageRect.width - width) / 2,
    top: imageRect.top - frameRect.top + (imageRect.height - height) / 2,
    width,
    height,
  };
}

function drawImageMarkers(request, frame, image) {
  const placeMarkers = () => {
    frame.querySelectorAll(".image-comment-marker").forEach(marker => marker.remove());
    const content = imageContentRect(image, frame);
    if (!content) return;
    const markers = request.comments.filter(comment => comment.versionId === viewedVersionId && comment.anchor?.type === "image");
    markers.forEach((comment, index) => {
      const marker = node("button", "image-comment-marker", String(index + 1));
      marker.type = "button";
      marker.dataset.imageComment = comment.id || "";
      marker.setAttribute("aria-label", `Comentário ${index + 1}: ${comment.text}`);
      marker.title = comment.text;
      marker.style.left = `${content.left + comment.anchor.x * content.width}px`;
      marker.style.top = `${content.top + comment.anchor.y * content.height}px`;
      marker.addEventListener("click", event => {
        event.stopPropagation();
        const commentItem = document.querySelector(`[data-comment-id="${CSS.escape(comment.id || "")}"]`);
        document.querySelectorAll(".comment-item.selected-anchor").forEach(item => item.classList.remove("selected-anchor"));
        commentItem?.classList.add("selected-anchor");
        commentItem?.scrollIntoView({ block: "nearest", behavior: "smooth" });
      });
      frame.append(marker);
    });
  };
  image.addEventListener("load", placeMarkers, { once: true });
  if (image.complete) placeMarkers();
  if (typeof ResizeObserver !== "undefined") {
    activeMediaObserver = new ResizeObserver(placeMarkers);
    activeMediaObserver.observe(frame);
  }
}

function showAnchorStatus() {
  const status = document.querySelector("#commentAnchorStatus");
  status.replaceChildren();
  if (!activeCommentAnchor) {
    status.hidden = true;
    return;
  }
  const label = activeCommentAnchor.type === "video"
    ? `Vídeo · ${formatTimecode(activeCommentAnchor.timeSeconds)}`
    : `Imagem · x ${Math.round(activeCommentAnchor.x * 100)}%, y ${Math.round(activeCommentAnchor.y * 100)}%`;
  status.append(node("span", "", `Referência anexada: ${label}`));
  const clear = node("button", "clear-anchor", "Remover");
  clear.type = "button";
  clear.addEventListener("click", () => {
    activeCommentAnchor = null;
    imageAnchorMode = false;
    renderDrawer();
  });
  status.append(clear);
  status.hidden = false;
}

function setImageAnchor(event, image, frame, versionId) {
  if (!imageAnchorMode) return;
  const content = imageContentRect(image, frame);
  if (!content) return;
  const frameRect = frame.getBoundingClientRect();
  const x = (event.clientX - frameRect.left - content.left) / content.width;
  const y = (event.clientY - frameRect.top - content.top) / content.height;
  if (x < 0 || x > 1 || y < 0 || y > 1) return;
  activeCommentAnchor = { type: "image", x, y, versionId };
  imageAnchorMode = false;
  showAnchorStatus();
  frame.classList.remove("marking-image-comment");
  frame.querySelector(".image-anchor-draft")?.remove();
  const marker = node("span", "image-anchor-draft", "•");
  marker.setAttribute("aria-hidden", "true");
  marker.style.left = `${content.left + x * content.width}px`;
  marker.style.top = `${content.top + y * content.height}px`;
  frame.append(marker);
}

async function renderAsset(request) {
  const preview = document.querySelector("#assetPreview");
  const anchorControls = document.querySelector("#assetAnchorControls");
  anchorControls.replaceChildren();
  anchorControls.hidden = true;
  preview.classList.remove("has-uploaded-media");
  activeMediaObserver?.disconnect();
  activeMediaObserver = null;
  if (activePreviewUrl) URL.revokeObjectURL(activePreviewUrl);
  activePreviewUrl = null;
  preview.replaceChildren();
  const latestVersion = request.versions.at(-1);
  if (!request.versions.some(item => item.id === viewedVersionId)) viewedVersionId = latestVersion?.id || null;
  const version = request.versions.find(item => item.id === viewedVersionId) || latestVersion;
  const isLatestVersion = version?.id === latestVersion?.id;
  document.querySelector("#assetTitle").textContent = version?.fileName || "Nenhum arquivo anexado";
  document.querySelector("#assetMeta").textContent = `${request.type} · ${request.demo ? "material fictício" : "armazenado neste navegador"}`;
  document.querySelector("#versionTag").textContent = `V${String(version?.number || 1).padStart(2, "0")}`;
  document.querySelector("#versionMeta").textContent = !isLatestVersion
    ? `Visualização V${String(version?.number || 1).padStart(2, "0")} · novos comentários pertencem à versão atual`
    : version?.createdAt ? `Adicionada por ${version.createdBy} · ${formatTimestamp(version.createdAt)}` : (request.demo ? "Versão demonstrativa" : "Versão inicial sem arquivo");
  document.querySelector("#versionCount").textContent = `${request.versions.length} versão(ões)`;
  document.querySelector("#versionHistorySummary").textContent = `Consultar histórico de versões (${request.versions.length})`;
  const versionList = document.querySelector("#versionHistoryList");
  versionList.replaceChildren();
  for (const item of [...request.versions].reverse()) {
    const decision = item.decision?.result === "approved" ? " · aprovada" : item.decision?.result === "changes_requested" ? " · ajustes solicitados" : item.sharedAt ? " · compartilhada" : " · não compartilhada";
    const fileName = item.fileName || "sem arquivo anexado";
    const row = node("li");
    const selectVersion = node("button", `version-history-item${item.id === version?.id ? " selected" : ""}`, `V${String(item.number).padStart(2, "0")} · ${fileName}${decision}`);
    selectVersion.type = "button";
    selectVersion.setAttribute("aria-current", item.id === version?.id ? "true" : "false");
    selectVersion.addEventListener("click", () => {
      viewedVersionId = item.id;
      activeCommentAnchor = null;
      imageAnchorMode = false;
      renderAsset(request);
    });
    row.append(selectVersion);
    versionList.append(row);
  }

  if (version?.fileKey) {
    try {
      const file = await readFile(version.fileKey);
      if (!file || activeRequestId !== request.id) return;
      activePreviewUrl = URL.createObjectURL(file);
      if (file.type.startsWith("image/")) {
        preview.classList.add("has-uploaded-media");
        const frame = node("div", "asset-media-frame");
        const image = node("img", "uploaded-preview");
        image.src = activePreviewUrl;
        image.alt = `Prévia de ${version.fileName}`;
        image.addEventListener("click", event => setImageAnchor(event, image, frame, version.id));
        frame.append(image);
        preview.append(frame);
        drawImageMarkers(request, frame, image);
        if (isLatestVersion) {
          const mark = node("button", "secondary-button", imageAnchorMode ? "Clique na imagem para marcar" : "Marcar ponto na imagem");
          mark.type = "button";
          mark.setAttribute("aria-pressed", String(imageAnchorMode));
          mark.addEventListener("click", () => {
            imageAnchorMode = !imageAnchorMode;
            frame.classList.toggle("marking-image-comment", imageAnchorMode);
            mark.textContent = imageAnchorMode ? "Clique na imagem para marcar" : "Marcar ponto na imagem";
            mark.setAttribute("aria-pressed", String(imageAnchorMode));
          });
          anchorControls.append(mark, node("small", "anchor-help", "O ponto fica ligado a esta versão."));
        } else {
          const latest = node("button", "secondary-button", `Voltar à versão atual (V${String(latestVersion.number).padStart(2, "0")})`);
          latest.type = "button";
          latest.addEventListener("click", () => { viewedVersionId = latestVersion.id; renderAsset(request); });
          anchorControls.append(latest, node("small", "anchor-help", "Esta versão é somente para consulta."));
        }
        anchorControls.hidden = false;
      } else if (file.type.startsWith("video/")) {
        preview.classList.add("has-uploaded-media");
        const video = node("video", "uploaded-preview");
        video.src = activePreviewUrl;
        video.controls = true;
        video.preload = "metadata";
        preview.append(video);
        if (isLatestVersion) {
          const capture = node("button", "secondary-button", "Vincular ao instante pausado");
          capture.type = "button";
          capture.disabled = true;
          video.addEventListener("pause", () => { capture.disabled = false; });
          video.addEventListener("play", () => { capture.disabled = true; });
          capture.addEventListener("click", () => {
            if (!video.paused || !Number.isFinite(video.currentTime)) {
              showToast("Pause o vídeo no ponto que deseja comentar.");
              return;
            }
            activeCommentAnchor = { type: "video", timeSeconds: video.currentTime, versionId: version.id };
            showAnchorStatus();
          });
          anchorControls.append(capture, node("small", "anchor-help", "Pause no instante que deseja vincular ao comentário."));
        } else {
          const latest = node("button", "secondary-button", `Voltar à versão atual (V${String(latestVersion.number).padStart(2, "0")})`);
          latest.type = "button";
          latest.addEventListener("click", () => { viewedVersionId = latestVersion.id; renderAsset(request); });
          anchorControls.append(latest, node("small", "anchor-help", "Esta versão é somente para consulta."));
        }
        anchorControls.hidden = false;
      } else {
        preview.classList.add("has-uploaded-media");
        preview.append(node("div", "asset-file-placeholder", `PDF anexado: ${version.fileName}`));
      }
      showAnchorStatus();
      return;
    } catch {
      preview.classList.add("has-uploaded-media");
      preview.append(node("div", "asset-file-placeholder", "Não foi possível carregar este arquivo do armazenamento local."));
      showAnchorStatus();
      return;
    }
  }
  preview.classList.remove("has-uploaded-media");
  preview.append(node("div", "asset-file-placeholder", request.demo ? "Prévia fictícia · nenhum arquivo real" : "Anexe uma imagem, vídeo ou PDF durante a produção."));
  showAnchorStatus();
}

function renderDrawer() {
  const request = currentRequest();
  if (!request) return;
  document.querySelector("#drawerTitle").textContent = request.title;
  document.querySelector("#drawerClient").textContent = request.client;
  document.querySelector("#drawerStage").textContent = stages[request.stage].toUpperCase();
  document.querySelector("#drawerDue").textContent = request.due ? `◷ ${formatDue(request.due)}` : "Prazo não definido";
  renderBriefing(request);
  const flowIndex = ({ briefing: 0, planning: 1, doing: 2, internalReview: 2, clientReview: 3, adjustments: 2, delivery: 4, completed: 4 })[request.stage];
  document.querySelectorAll(".flow-step").forEach((step, index) => {
    step.classList.toggle("complete", index < flowIndex || request.stage === "completed");
    step.classList.toggle("current", index === flowIndex && request.stage !== "completed");
    const marker = step.querySelector("span");
    if (marker) marker.textContent = index < flowIndex || request.stage === "completed" ? "✓" : String(index + 1);
  });
  renderComments(request);
  renderHistory(request);
  renderTasks(request);
  renderActions(request);
  renderAsset(request);
}

function renderBriefing(request) {
  document.querySelector("#briefingText").textContent = request.brief || "Nenhum contexto registrado.";
  const details = document.querySelector("#briefingDetails");
  details.replaceChildren();
  const fields = [
    ["Origem do pedido", request.origin, true],
    ["Tipo de entrega", request.type, true],
    ["Canal ou peça", request.channel, true],
    ["Prazo desejado", request.due ? formatDue(request.due) : "", false],
    ["Critérios de aceite", request.acceptanceCriteria, true],
    ["Referências ou links", request.references, false],
  ];
  for (const [label, value, required] of fields) {
    const row = node("div", "briefing-detail");
    row.append(node("dt", "", label), node("dd", "", String(value || "").trim() || (required ? "Não informado · necessário antes do planejamento" : "Não informado · opcional nesta demonstração")));
    details.append(row);
  }
  const briefingFiles = document.querySelector("#briefingFiles");
  briefingFiles.replaceChildren();
  briefingFiles.hidden = !request.briefingFiles?.length;
  if (request.briefingFiles?.length) {
    briefingFiles.append(node("strong", "briefing-files-title", "Arquivos de referência"));
    const list = node("ul", "briefing-file-list");
    for (const file of request.briefingFiles) {
      const item = node("li", "briefing-file-item");
      item.append(node("span", "", `${file.fileName} · ${file.type || "tipo desconhecido"}`));
      const download = node("button", "text-action", "Baixar");
      download.type = "button";
      download.addEventListener("click", () => downloadLocalFile(file.fileKey, file.fileName));
      item.append(download);
      list.append(item);
    }
    briefingFiles.append(list);
  }
  const detailsForm = document.querySelector("#briefingDetailsForm");
  detailsForm.hidden = request.stage !== "briefing";
  for (const field of ["origin", "channel", "acceptanceCriteria", "references", "due"]) {
    detailsForm.elements.namedItem(field).value = request[field] || "";
  }
  const notice = document.querySelector("#briefingRevisionNotice");
  const revision = request.briefingRevisions?.at(-1);
  notice.replaceChildren();
  notice.hidden = !revision;
  if (revision) {
    notice.append(node("strong", "", request.planReviewRequired ? "Plano aguardando revisão humana" : "Última alteração do briefing"));
    notice.append(node("p", "", `Motivo: ${revision.reason}`));
    notice.append(node("p", "", `Antes: ${revision.previousBrief}`));
  }
  document.querySelector("#briefingRevisionForm").hidden = request.stage !== "doing";
}

function openDrawer(id) {
  minimizedRequestIds = minimizedRequestIds.filter(item => item !== id);
  saveMinimizedIds();
  renderMinimizedRequests();
  activeRequestId = id;
  const request = state.requests.find(item => item.id === id);
  viewedVersionId = request?.versions.at(-1)?.id || null;
  activeCommentAnchor = null;
  imageAnchorMode = false;
  renderDrawer();
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
  activeRequestId = null;
  viewedVersionId = null;
  if (activePreviewUrl) URL.revokeObjectURL(activePreviewUrl);
  activePreviewUrl = null;
  activeMediaObserver?.disconnect();
  activeMediaObserver = null;
}

function minimizeDrawer() {
  if (!activeRequestId) return;
  if (!minimizedRequestIds.includes(activeRequestId)) minimizedRequestIds.push(activeRequestId);
  saveMinimizedIds();
  closeDrawer();
  renderMinimizedRequests();
}

function setPage(page) {
  const pages = new Set(["overview", "requests", "approvals", "team", "clients", "calendar", "knowledge", "accesses"]);
  if (!pages.has(page)) return;
  if (page === "approvals") activeStageFilter = "";
  activePage = page;
  document.querySelectorAll("[data-page]").forEach(link => {
    const active = link.dataset.page === page;
    link.classList.toggle("active", active);
    if (active) link.setAttribute("aria-current", "page");
    else link.removeAttribute("aria-current");
  });
  renderBoard();
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function populateFilterDialog() {
  const form = document.querySelector("#filterForm");
  const stageSelect = form.elements.namedItem("stage");
  const clientSelect = form.elements.namedItem("client");
  stageSelect.replaceChildren(new Option("Todas as etapas", ""));
  clientSelect.replaceChildren(new Option("Todos os clientes", ""));
  for (const [key, label] of Object.entries(stages)) stageSelect.add(new Option(label, key));
  [...new Set(state.requests.map(request => request.client).filter(Boolean))].sort((a, b) => a.localeCompare(b, "pt-BR")).forEach(client => clientSelect.add(new Option(client, client)));
  stageSelect.value = activeStageFilter;
  clientSelect.value = activeClientFilter;
  form.elements.namedItem("due").value = activeDueFilter;
}

function renderNotifications() {
  const list = document.querySelector("#notificationList");
  list.replaceChildren();
  const attention = [];
  for (const request of state.requests) {
    if (request.stage === "clientReview") attention.push([request, "Aguardando decisão do cliente"]);
    if (request.stage === "briefing" && (!request.origin || !request.channel || !request.acceptanceCriteria)) attention.push([request, "Briefing precisa ser completado"]);
    const round = Number(request.versions?.at(-1)?.number) || 1;
    if ((request.tasks || []).some(task => (Number(task.round) || round) === round && task.blockedReason)) attention.push([request, "Há uma tarefa com impedimento"]);
  }
  if (!attention.length) return list.append(emptyPanel("Nenhuma pendência encontrada", "O quadro não tem itens que atendam aos avisos desta demonstração."));
  for (const [request, label] of attention) {
    list.append(makeOpenRequestButton(request, label, () => document.querySelector("#notificationsDialog").close()));
  }
}

function downloadData(filename, content, type) {
  const url = URL.createObjectURL(new Blob([content], { type }));
  const anchor = node("a");
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function exportRequests(format) {
  const date = new Date().toISOString().slice(0, 10);
  if (format === "json") {
    downloadData(`mix7-demandas-${date}.json`, serializeRequestsJson(state.requests, state.schemaVersion), "application/json;charset=utf-8");
    showToast("Cópia JSON das demandas baixada. Os arquivos de mídia não estão incluídos.");
    return;
  }
  downloadData(`mix7-demandas-${date}.csv`, serializeRequestsCsv(state.requests), "text/csv;charset=utf-8");
  showToast("Lista CSV das demandas baixada.");
}

function displayCurrentDate() {
  document.querySelector("#currentDateLabel").textContent = new Intl.DateTimeFormat("pt-BR", { weekday: "long", day: "2-digit", month: "long", year: "numeric" }).format(new Date()).toLocaleUpperCase("pt-BR");
}

document.querySelector("#closeDrawer").addEventListener("click", closeDrawer);
document.querySelector("#minimizeDrawer").addEventListener("click", minimizeDrawer);
document.querySelector("#copyRequestId").addEventListener("click", async () => {
  const id = currentRequest()?.id;
  if (!id) return;
  try {
    await navigator.clipboard.writeText(id);
    showToast("Código da demanda copiado.");
  } catch {
    showToast(`Código da demanda: ${id}`);
  }
});
scrim.addEventListener("click", closeDrawer);
document.addEventListener("keydown", event => { if (event.key === "Escape") { closeDrawer(); if (dialog.open) dialog.close(); } });
document.querySelector("#newRequestButton").addEventListener("click", () => dialog.showModal());
document.querySelectorAll(".add-card").forEach(button => {
  button.textContent = "＋ Nova demanda";
  button.title = "Toda demanda nova começa no briefing";
  button.setAttribute("aria-label", "Criar demanda; começa no briefing");
  button.addEventListener("click", () => { dialog.showModal(); dialog.querySelector('[name="title"]').focus(); });
});

document.querySelectorAll("[data-page]").forEach(link => link.addEventListener("click", event => { event.preventDefault(); setPage(link.dataset.page); }));
document.querySelector(".brand").addEventListener("click", event => { event.preventDefault(); setPage("overview"); });
document.querySelector("#focusSearchButton").addEventListener("click", () => { setPage("requests"); document.querySelector("#searchInput").focus(); });
document.querySelector("#workspaceButton").addEventListener("click", () => document.querySelector("#profileDialog").showModal());
document.querySelector("#profileButton").addEventListener("click", () => document.querySelector("#profileDialog").showModal());
document.querySelector("#exportProfileData").addEventListener("click", () => exportRequests("json"));
document.querySelector("#notificationsButton").addEventListener("click", () => { renderNotifications(); document.querySelector("#notificationsDialog").showModal(); });
document.querySelector("#filterButton").addEventListener("click", () => { populateFilterDialog(); document.querySelector("#filterDialog").showModal(); });
document.querySelector("#moreOptionsButton").addEventListener("click", () => document.querySelector("#optionsDialog").showModal());
document.querySelector("#exportJson").addEventListener("click", () => exportRequests("json"));
document.querySelector("#exportCsv").addEventListener("click", () => exportRequests("csv"));
document.querySelectorAll("[data-close-dialog]").forEach(button => button.addEventListener("click", () => document.querySelector(`#${button.dataset.closeDialog}`).close()));
document.querySelector("#filterForm").addEventListener("submit", event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  activeStageFilter = String(data.get("stage") || "");
  activeClientFilter = String(data.get("client") || "");
  activeDueFilter = String(data.get("due") || "");
  document.querySelector("#filterDialog").close();
  setPage("requests");
});
document.querySelector("#resetFilters").addEventListener("click", () => { resetFilters(); populateFilterDialog(); });
document.querySelectorAll("[data-column-menu]").forEach(button => button.addEventListener("click", () => {
  columnActionStage = button.dataset.columnMenu;
  document.querySelector("#columnDialogTitle").textContent = stages[columnActionStage];
  document.querySelector("#filterThisStage").textContent = activeStageFilter === columnActionStage ? "Remover filtro desta etapa" : "Ver esta etapa";
  document.querySelector("#columnDialog").showModal();
}));
document.querySelector("#filterThisStage").addEventListener("click", () => {
  activeStageFilter = activeStageFilter === columnActionStage ? "" : columnActionStage;
  document.querySelector("#columnDialog").close();
  setPage("requests");
});
document.querySelector("#sectionHistoryButton").addEventListener("click", () => document.querySelector("#historyList").scrollIntoView({ behavior: "smooth", block: "center" }));

document.querySelector("#briefingRevisionForm").addEventListener("submit", event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const saved = updateRequest("briefing_revised", { brief: data.get("revisedBrief"), reason: data.get("revisionReason") }, "Briefing atualizado; revise o plano antes de retomar.");
  if (saved) event.currentTarget.reset();
});

document.querySelector("#briefingDetailsForm").addEventListener("submit", event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const payload = Object.fromEntries(["origin", "channel", "acceptanceCriteria", "references", "due"].map(field => [field, data.get(field)]));
  updateRequest("briefing_details_updated", payload, "Dados do briefing e histórico atualizados.");
});

document.querySelector("#requestForm").addEventListener("submit", event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const selectedFiles = data.getAll("briefingFiles").filter(file => file instanceof File && file.size > 0);
  const fileError = validateLocalFiles(selectedFiles, MAX_FILE_BYTES);
  if (fileError) {
    showToast(fileError);
    return;
  }
  const submitButton = event.currentTarget.querySelector('button[type="submit"]');
  submitButton.disabled = true;
  void createRequestFromForm(event.currentTarget, data, selectedFiles, submitButton);
});

briefingFileInput.addEventListener("change", () => {
  const error = validateLocalFiles(briefingFileInput.files, MAX_FILE_BYTES);
  if (error) {
    briefingFileInput.value = "";
    briefingFileStatus.textContent = error;
    showToast(error);
    return;
  }
  updateBriefingFileStatus();
});

briefingFileDropZone.addEventListener("dragenter", event => {
  if (!hasFileTransfer(event)) return;
  event.preventDefault();
  briefingFileDropZone.classList.add("is-dragging");
  briefingFileStatus.textContent = "Solte para adicionar os arquivos ao briefing.";
});

briefingFileDropZone.addEventListener("dragover", event => {
  if (!hasFileTransfer(event)) return;
  event.preventDefault();
  if (event.dataTransfer) event.dataTransfer.dropEffect = "copy";
});

briefingFileDropZone.addEventListener("dragleave", event => {
  if (!hasFileTransfer(event)) return;
  if (event.relatedTarget instanceof Node && briefingFileDropZone.contains(event.relatedTarget)) return;
  briefingFileDropZone.classList.remove("is-dragging");
  updateBriefingFileStatus();
});

briefingFileDropZone.addEventListener("drop", event => {
  if (!hasFileTransfer(event)) return;
  event.preventDefault();
  briefingFileDropZone.classList.remove("is-dragging");
  const incomingFiles = Array.from(event.dataTransfer?.files || []);
  if (!incomingFiles.length) {
    briefingFileStatus.textContent = "Nenhum arquivo foi recebido. Use o botão para selecionar.";
    return;
  }

  const result = mergeLocalFiles(briefingFileInput.files, incomingFiles, MAX_FILE_BYTES);
  if (result.error) {
    briefingFileStatus.textContent = result.error;
    showToast(result.error);
    return;
  }

  try {
    const transfer = new DataTransfer();
    for (const file of result.files) transfer.items.add(file);
    briefingFileInput.files = transfer.files;
    updateBriefingFileStatus();
  } catch {
    briefingFileStatus.textContent = "Não foi possível adicionar pelo arraste. Use o botão para selecionar.";
    showToast("Não foi possível adicionar pelo arraste. Use o botão para selecionar.");
  }
});

dialog.addEventListener("close", () => {
  briefingFileDropZone.classList.remove("is-dragging");
  updateBriefingFileStatus();
});

async function createRequestFromForm(form, data, selectedFiles, submitButton) {
  const now = new Date().toISOString();
  const id = makeId();
  const briefingFiles = selectedFiles.map(file => ({ id: makeId(), fileKey: `${id}-brief-${makeId()}`, fileName: file.name, type: file.type, size: file.size }));
  const storedKeys = [];
  try {
    for (let index = 0; index < selectedFiles.length; index += 1) {
      await storeFile(briefingFiles[index].fileKey, selectedFiles[index]);
      storedKeys.push(briefingFiles[index].fileKey);
    }
  } catch (error) {
    await Promise.allSettled(storedKeys.map(removeFile));
    showToast(error.message || "Não foi possível salvar os arquivos de referência neste navegador.");
    submitButton.disabled = false;
    return;
  }
  const request = {
    id, title: String(data.get("title")).trim(), client: String(data.get("client")).trim(), brief: String(data.get("brief")).trim(), type: String(data.get("type")), origin: String(data.get("origin")).trim(), channel: String(data.get("channel")).trim(), acceptanceCriteria: String(data.get("acceptanceCriteria")).trim(), references: String(data.get("references") || "").trim(), due: String(data.get("due") || ""), stage: "briefing", demo: false, briefingRevisions: [], briefingFiles, planReviewRequired: false,
    versions: [{ id: "", number: 1, fileName: "", fileKey: null, createdBy: "", createdAt: now, sharedAt: null, decision: null }], tasks: [], comments: [], history: [{ type: "created", details: { origin: String(data.get("origin")).trim(), channel: String(data.get("channel")).trim(), acceptanceCriteria: String(data.get("acceptanceCriteria")).trim(), briefingFiles: briefingFiles.map(({ fileName, type, size }) => ({ fileName, type, size })) }, at: now }], delivery: null,
  };
  request.versions[0].id = `${request.id}-v1`;
  state.requests.unshift(request);
  const saved = saveState();
  form.reset();
  dialog.close();
  activeStageFilter = "";
  activeClientFilter = "";
  activeDueFilter = "";
  document.querySelector("#assigneeFilter").value = "";
  document.querySelector("#searchInput").value = "";
  setPage("requests");
  showToast(saved ? `Demanda salva no navegador como briefing para revisão${briefingFiles.length ? ` com ${briefingFiles.length} arquivo(s) de referência` : ""}.` : "Demanda criada nesta sessão; o navegador não confirmou o salvamento.");
  submitButton.disabled = false;
}

document.querySelector("#sendComment").addEventListener("click", () => {
  const field = document.querySelector("#commentDraft");
  const saved = updateRequest("add_comment", { comment: field.value, audience: "internal" }, "Comentário interno registrado.");
  if (saved) field.value = "";
});

document.querySelector("#taskForm").addEventListener("submit", event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const saved = updateRequest("add_task", { title: data.get("taskTitle"), assignee: data.get("taskAssignee"), estimateHours: data.get("taskEstimateHours"), plannedStart: data.get("taskPlannedStart"), due: data.get("taskDue"), dependencyTaskIds: data.getAll("taskDependencies").filter(Boolean), sourceCommentId: data.get("taskSourceCommentId") }, "Tarefa adicionada ao plano.");
  if (saved) {
    event.currentTarget.reset();
    const sourceHint = document.querySelector("#taskSourceHint");
    sourceHint.hidden = true;
    sourceHint.textContent = "";
  }
});

fileInput.addEventListener("change", async () => {
  const file = fileInput.files?.[0];
  const request = currentRequest();
  if (!file || !request || !selectedFileAction) return;
  const fileError = validateLocalFiles([file], MAX_FILE_BYTES);
  if (fileError) { showToast(fileError); return; }
  const fileKey = `${request.id}-${makeId()}`;
  try {
    await storeFile(fileKey, file);
    updateRequest(selectedFileAction, { fileKey, fileName: file.name, author: "Equipe Mix7" }, selectedFileAction === "new_version" ? "Nova versão anexada e enviada à revisão interna." : "Arquivo anexado à versão atual.");
  } catch (error) {
    showToast(error.message || "Não foi possível salvar o arquivo neste navegador.");
  } finally {
    selectedFileAction = null;
  }
});

document.querySelector("#searchInput").addEventListener("input", renderBoard);
document.querySelector("#assigneeFilter").addEventListener("change", renderBoard);

document.querySelectorAll(".view-tab").forEach(tab => tab.addEventListener("click", () => {
  document.querySelectorAll(".view-tab").forEach(item => { item.classList.remove("active"); item.setAttribute("aria-selected", "false"); });
  tab.classList.add("active");
  tab.setAttribute("aria-selected", "true");
  const listMode = tab.textContent.includes("Lista");
  document.querySelector(".kanban-board").classList.toggle("list-view", listMode);
  document.querySelector("#listViewHeader").hidden = !listMode;
  renderBoard();
}));

displayCurrentDate();
renderBoard();
window.setInterval(refreshTaskTimerDisplays, 1000);
