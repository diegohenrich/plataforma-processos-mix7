const { stages, participantTypes, transition, listAssignees, filterRequestsByAssignee, taskBlockers, findActiveTaskTimer, stopAllActiveTaskTimers, getRunnableTasks, validateLocalFiles, mergeLocalFiles, serializeRequestsJson, serializeRequestsCsv } = window.Mix7Workflow;
const { assigneeKey, weekStartFromIso, isoWeekFromDate, summarizeWeeklyCapacity } = window.Mix7Capacity;
const { knowledgeTypes, saveKnowledgeItem, setKnowledgeItemArchived, filterKnowledgeItems } = window.Mix7Knowledge;
const { createAssignment, setStepCompleted } = window.Mix7Onboarding;
const STORAGE_KEY = "mix7.workflow.v1";
const MINIMIZED_KEY = "mix7.workflow.minimized.v1";
const KNOWLEDGE_STORAGE_KEY = "mix7.knowledge.v1";
const CAPACITY_STORAGE_KEY = "mix7.capacity-preview.v1";
const ONBOARDING_STORAGE_KEY = "mix7.onboarding-assignments.v1";
const MAX_FILE_BYTES = 15 * 1024 * 1024;
const drawer = document.querySelector("#detailDrawer");
const appShell = document.querySelector(".app-shell");
const minimizedRequests = document.querySelector("#minimizedRequests");
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
let drawerReturnFocus = null;
let activePage = "requests";
let activeStageFilter = "";
let activeClientFilter = "";
let activeDueFilter = "";
let activeParticipantTypeId = participantTypes[0].id;
let columnActionStage = "";
let minimizedRequestIds = loadMinimizedIds();
const minimizedTimerTaskSelections = new Map();
let state = loadState();
let knowledgeRecords = loadKnowledgeRecords();
let onboardingAssignments = loadOnboardingAssignments();
let capacityData = loadCapacityData();
let selectedCapacityAssignee = "";
let selectedCapacityWeek = isoWeekFromDate(new Date());
let knowledgeSearch = "";
let knowledgeTypeFilter = "";
let knowledgeIncludeArchived = false;
let knowledgeEditingId = null;

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
    item.dataset.requestId = request.id;
    const activeTimer = findActiveTaskTimer(state.requests);
    const activeTaskForRequest = activeTimer?.request.id === request.id ? activeTimer.task : null;
    const runnableTasks = getRunnableTasks(request, activeTimer);
    const chip = node("button", "minimized-request", "");
    chip.type = "button";
    chip.setAttribute("aria-label", `Reabrir ${request.title}`);
    chip.append(node("strong", "", request.title), node("small", "", `${request.client} · ${stages[request.stage]}`));
    chip.addEventListener("click", () => openDrawer(request.id));
    if (activeTaskForRequest || runnableTasks.length) {
      const timerControls = node("div", "minimized-timer-controls");
      const taskSelect = node("select", "minimized-task-select");
      taskSelect.setAttribute("aria-label", `Tarefa do cronômetro para ${request.title}`);
      const availableTasks = activeTaskForRequest ? [activeTaskForRequest] : runnableTasks;
      if (availableTasks.length > 1) taskSelect.append(new Option("Escolha a tarefa…", ""));
      for (const task of availableTasks) taskSelect.add(new Option(`${task.title} · ${task.assignee}`, task.id));
      const previousSelection = minimizedTimerTaskSelections.get(request.id);
      taskSelect.value = activeTaskForRequest?.id || (availableTasks.some(task => task.id === previousSelection) ? previousSelection : availableTasks.length === 1 ? availableTasks[0].id : "");
      taskSelect.disabled = Boolean(activeTaskForRequest);

      const selectedTask = availableTasks.find(task => task.id === taskSelect.value);
      const totalSeconds = task => (task.timeEntries || []).reduce((sum, entry) => sum + (Number(entry.durationSeconds) || 0), 0);
      const elapsed = node("small", "minimized-timer-total", selectedTask
        ? `Registrado: ${formatDuration(totalSeconds(selectedTask))}`
        : "Selecione uma tarefa para ver o tempo registrado.");
      elapsed.setAttribute("role", "timer");
      elapsed.setAttribute("aria-live", "off");
      if (activeTaskForRequest) {
        elapsed.textContent = `Ativo: ${formatDuration(totalSeconds(activeTaskForRequest) + Math.floor((Date.now() - Date.parse(activeTaskForRequest.timerStartedAt)) / 1000))} · ${activeTaskForRequest.title}`;
        elapsed.dataset.timerStartedAt = activeTaskForRequest.timerStartedAt;
        elapsed.dataset.timerBaseSeconds = String(totalSeconds(activeTaskForRequest));
      }
      taskSelect.addEventListener("change", () => {
        minimizedTimerTaskSelections.set(request.id, taskSelect.value);
        const task = availableTasks.find(candidate => candidate.id === taskSelect.value);
        elapsed.textContent = task ? `Registrado: ${formatDuration(totalSeconds(task))}` : "Selecione uma tarefa para ver o tempo registrado.";
        timerButton.disabled = !activeTaskForRequest && (Boolean(activeTimer) || !task);
      });

      const timerButton = node("button", "minimized-timer-button", activeTaskForRequest ? "Pausar" : "Iniciar");
      timerButton.type = "button";
      timerButton.setAttribute("aria-label", activeTaskForRequest ? `Pausar cronômetro de ${activeTaskForRequest.title}` : `Iniciar cronômetro da tarefa selecionada em ${request.title}`);
      timerButton.disabled = !activeTaskForRequest && (activeTimer || !selectedTask);
      if (activeTimer && !activeTaskForRequest) timerButton.title = `Pause primeiro “${activeTimer.task.title}” em ${activeTimer.request.title}.`;
      timerButton.addEventListener("click", event => {
        event.stopPropagation();
        const task = activeTaskForRequest || availableTasks.find(candidate => candidate.id === taskSelect.value);
        if (!task) return;
        if (activeTaskForRequest) updateRequestFor(request.id, "stop_task_timer", { taskId: task.id }, "Sessão de tempo registrada.");
        else updateRequestFor(request.id, "start_task_timer", { taskId: task.id }, "Cronômetro iniciado.");
      });
      timerControls.append(taskSelect, elapsed, timerButton);
      if (activeTimer && !activeTaskForRequest) {
        timerControls.append(node("small", "minimized-timer-note", `Pause “${activeTimer.task.title}” em ${activeTimer.request.title} para iniciar outra tarefa.`));
      }
      item.append(timerControls);
    } else if (["doing", "adjustments"].includes(request.stage)) {
      item.append(node("small", "minimized-timer-unavailable", "Sem tarefas liberadas para cronometrar"));
    }
    const dismiss = node("button", "minimized-dismiss", "×");
    dismiss.type = "button";
    dismiss.setAttribute("aria-label", `Remover atalho minimizado de ${request.title}`);
    dismiss.addEventListener("click", event => {
      event.stopPropagation();
      minimizedRequestIds = minimizedRequestIds.filter(id => id !== request.id);
      saveMinimizedIds();
      renderMinimizedRequests();
    });
    item.prepend(chip);
    item.append(dismiss);
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

function renderCapacityOverviewCard() {
  const value = document.querySelector("#capacityOverviewValue");
  if (!value) return;
  const label = document.querySelector("#capacityOverviewLabel");
  const note = document.querySelector("#capacityOverviewNote");
  const bar = document.querySelector("#capacityOverviewBar");
  const legend = document.querySelector("#capacityOverviewLegend");
  const people = listAssignees(state.requests);
  value.textContent = "—";
  label.textContent = "sem configuração";
  bar.style.width = "0%";
  bar.classList.remove("is-overloaded");
  note.replaceChildren(document.createTextNode("Configure horas fictícias na "));
  const openCalendar = node("a", "capacity-overview-link", "agenda");
  openCalendar.href = "#calendario";
  openCalendar.dataset.page = "calendar";
  note.append(openCalendar, document.createTextNode("."));
  legend.textContent = "Sem dados de horas";

  if (!people.length) {
    label.textContent = "sem tarefas";
    note.replaceChildren(document.createTextNode("Atribua tarefas para configurar a "));
    note.append(openCalendar, document.createTextNode("capacidade."));
    return;
  }

  const start = weekStartFromIso(selectedCapacityWeek);
  const summaries = people.map(assignee => summarizeWeeklyCapacity({
    requests: state.requests,
    profile: findCapacityProfile(assignee, start),
    absences: capacityData.absences,
    assignee,
    weekStart: start,
  }));
  const missing = summaries.filter(item => !item.configured).length;
  if (missing) {
    label.textContent = `${missing} sem jornada`;
    note.replaceChildren(document.createTextNode(`Configure horas para ${missing} profissional${missing === 1 ? "" : "is"} na `));
    note.append(openCalendar, document.createTextNode("."));
    return;
  }

  const available = summaries.reduce((total, item) => total + item.availableHours, 0);
  const planned = summaries.reduce((total, item) => total + item.plannedHours, 0);
  const over = planned > available;
  value.textContent = `${Number(available.toFixed(2))} h`;
  label.textContent = over ? "carga acima" : "disponíveis";
  note.replaceChildren(document.createTextNode(`${Number(planned.toFixed(2))} h estimadas nesta semana · `));
  note.append(openCalendar);
  const percent = available > 0 ? Math.min(100, planned / available * 100) : (planned > 0 ? 100 : 0);
  bar.style.width = `${percent}%`;
  bar.classList.toggle("is-overloaded", over);
  legend.textContent = over
    ? `Excesso estimado: ${Number((planned - available).toFixed(2))} h`
    : `${Number(planned.toFixed(2))} h previstas de ${Number(available.toFixed(2))} h disponíveis`;
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
    knowledge: ["Conhecimento", "Consulte e organize as informações úteis da agência."],
    accesses: ["Acessos", "O que esta versão local registra sobre acesso e privacidade."],
  };
  document.querySelector("#pageTitle").textContent = titles[activePage][0];
  document.querySelector("#pageSubtitle").textContent = titles[activePage][1];
  document.querySelector("#pageBreadcrumb").textContent = titles[activePage][0];
  document.querySelector("#newRequestButton").hidden = ["knowledge", "accesses"].includes(activePage);
  renderCapacityOverviewCard();
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

function loadCapacityData() {
  try {
    const parsed = JSON.parse(localStorage.getItem(CAPACITY_STORAGE_KEY) || "{}");
    return {
      profiles: Array.isArray(parsed.profiles) ? parsed.profiles.filter(item => item && typeof item.assignee === "string" && typeof item.weekStart === "string" && Number.isFinite(Number(item.scheduledHours))) : [],
      absences: Array.isArray(parsed.absences) ? parsed.absences.filter(item => item && typeof item.id === "string" && typeof item.assignee === "string" && typeof item.date === "string" && Number.isFinite(Number(item.hours))) : [],
    };
  } catch {
    return { profiles: [], absences: [] };
  }
}

function saveCapacityData() {
  try {
    localStorage.setItem(CAPACITY_STORAGE_KEY, JSON.stringify(capacityData));
    return true;
  } catch {
    showToast("Não foi possível salvar a prévia de capacidade neste navegador.");
    return false;
  }
}

function findCapacityProfile(assignee, weekStart) {
  const key = assigneeKey(assignee);
  return capacityData.profiles.find(item => assigneeKey(item.assignee) === key && item.weekStart === weekStart) || null;
}

function renderKnowledgeResults(container) {
  container.replaceChildren();
  const results = filterKnowledgeItems(knowledgeRecords, {
    query: knowledgeSearch,
    type: knowledgeTypeFilter,
    includeArchived: knowledgeIncludeArchived,
  });
  container.setAttribute("aria-label", `${results.length} item(ns) na biblioteca`);
  if (!results.length) {
    const noRecords = knowledgeSearch || knowledgeTypeFilter || knowledgeIncludeArchived
      ? emptyPanel("Nenhum item encontrado", "Tente outra busca ou escolha outro tipo.")
      : emptyPanel("A biblioteca ainda está vazia", "Cadastre uma referência, um treinamento, um contato ou uma trilha de onboarding.");
    container.append(noRecords);
    return;
  }

  for (const record of results) {
    const card = node("article", `knowledge-card${record.archived ? " is-archived" : ""}`);
    const heading = node("div", "knowledge-card-heading");
    const typeName = knowledgeTypes.find(item => item.id === record.type)?.label || "Conteúdo";
    heading.append(node("span", "knowledge-type", typeName));
    if (record.archived) heading.append(node("span", "knowledge-archived-label", "Arquivado"));
    card.append(heading, node("h3", "", record.title), node("p", "knowledge-summary", record.summary));
    if (record.link) {
      try {
        const link = new URL(record.link);
        if (["https:", "http:"].includes(link.protocol)) {
          const anchor = node("a", "knowledge-link", "Abrir material ↗");
          anchor.href = link.href;
          anchor.target = "_blank";
          anchor.rel = "noopener noreferrer";
          card.append(anchor);
        }
      } catch { /* Ignore links changed outside the validated form. */ }
    }
    if (record.steps?.length) {
      const steps = node("ol", "knowledge-card-steps");
      record.steps.forEach(step => steps.append(node("li", "", step)));
      card.append(steps);
    }
    const meta = node("dl", "knowledge-meta");
    for (const [label, value] of [["Responsável", record.owner], ["Público", record.audience], ["Revisar em", formatDue(record.reviewDate)]]) {
      const pair = node("div", "knowledge-meta-pair");
      pair.append(node("dt", "", label), node("dd", "", value));
      meta.append(pair);
    }
    card.append(meta);
    const actions = node("div", "knowledge-card-actions");
    const edit = node("button", "secondary-button", "Editar");
    edit.type = "button";
    edit.dataset.knowledgeAction = "edit";
    edit.dataset.knowledgeId = record.id;
    edit.setAttribute("aria-label", `Editar ${record.title}`);
    const archive = node("button", "secondary-button", record.archived ? "Restaurar" : "Arquivar");
    archive.type = "button";
    archive.dataset.knowledgeAction = record.archived ? "restore" : "archive";
    archive.dataset.knowledgeId = record.id;
    archive.setAttribute("aria-label", `${record.archived ? "Restaurar" : "Arquivar"} ${record.title}`);
    actions.append(edit, archive);
    card.append(actions);
    container.append(card);
  }
}

function formatOnboardingDate(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "short", year: "numeric" }).format(date);
}

function renderOnboardingAssignments(container) {
  const section = node("section", "onboarding-assignments");
  section.append(panelHeading("Trilhas atribuídas", "Acompanhe os passos registrados para cada pessoa nesta demonstração local."));
  const templates = knowledgeRecords.filter(item => item.type === "onboarding" && !item.archived && item.steps?.length);
  const form = node("form", "onboarding-assign-form");
  form.id = "onboardingAssignForm";
  const templateSelect = document.createElement("select");
  templateSelect.name = "templateId";
  templateSelect.required = true;
  templateSelect.add(new Option(templates.length ? "Escolha uma trilha" : "Cadastre uma trilha ativa primeiro", ""));
  templates.forEach(item => templateSelect.add(new Option(item.title, item.id)));
  templateSelect.disabled = !templates.length;
  const templateLabel = node("label", "capacity-field", "Modelo de trilha");
  templateLabel.append(templateSelect);
  const personInput = document.createElement("input");
  personInput.name = "assignee";
  personInput.required = true;
  personInput.maxLength = 100;
  personInput.placeholder = "Ex.: Pessoa fictícia";
  const personLabel = node("label", "capacity-field", "Pessoa fictícia");
  personLabel.append(personInput);
  const submit = node("button", "primary-button", "Atribuir trilha");
  submit.type = "submit";
  submit.disabled = !templates.length;
  form.append(templateLabel, personLabel, submit);
  section.append(form);
  if (!templates.length) section.append(node("p", "capacity-muted", "Crie um item do tipo Onboarding com passos e deixe-o ativo para poder atribuí-lo."));

  if (!onboardingAssignments.length) {
    section.append(emptyPanel("Nenhuma trilha atribuída", "Quando um modelo estiver pronto, atribua seus passos a uma pessoa fictícia para acompanhar o progresso."));
  } else {
    const list = node("div", "onboarding-assignment-list");
    for (const assignment of [...onboardingAssignments].reverse()) {
      const card = node("article", "onboarding-assignment-card");
      card.dataset.onboardingCard = assignment.id;
      const heading = node("div", "onboarding-assignment-heading");
      const title = node("div", "");
      title.append(node("h3", "", assignment.templateTitle), node("p", "", `${assignment.assignee} · atribuída em ${formatOnboardingDate(assignment.assignedAt)}`));
      const status = node("span", "onboarding-status", assignment.completedAt ? "Concluída" : "Em andamento");
      status.dataset.onboardingStatus = "";
      heading.append(title, status);
      card.append(heading);
      const doneCount = assignment.steps.filter(step => step.completed).length;
      const progress = node("div", "onboarding-progress-summary");
      const progressText = node("span", "", `${doneCount} de ${assignment.steps.length} passos`);
      progressText.dataset.onboardingProgressText = "";
      const progressPercent = assignment.steps.length ? Math.round(doneCount / assignment.steps.length * 100) : 0;
      const meter = node("div", "capacity-meter onboarding-meter");
      meter.setAttribute("role", "progressbar");
      meter.setAttribute("aria-label", `Progresso de ${assignment.templateTitle} para ${assignment.assignee}`);
      meter.setAttribute("aria-valuemin", "0");
      meter.setAttribute("aria-valuemax", "100");
      meter.setAttribute("aria-valuenow", String(progressPercent));
      const fill = node("i", "");
      fill.style.width = `${progressPercent}%`;
      meter.append(fill);
      progress.append(progressText, meter);
      card.append(progress);
      const steps = node("ol", "onboarding-assignment-steps");
      for (const step of assignment.steps) {
        const item = node("li", step.completed ? "is-complete" : "");
        const label = node("label", "onboarding-step-label");
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = Boolean(step.completed);
        checkbox.dataset.onboardingId = assignment.id;
        checkbox.dataset.onboardingStep = step.id;
        const text = node("span", "", step.text);
        label.append(checkbox, text);
        item.append(label);
        if (step.completedAt) item.append(node("small", "onboarding-step-date", `Concluído em ${formatOnboardingDate(step.completedAt)}`));
        steps.append(item);
      }
      card.append(steps);
      if (assignment.completedAt) card.append(node("p", "onboarding-completion-note", `Todos os passos foram marcados em ${formatOnboardingDate(assignment.completedAt)}. Registro demonstrativo, sem validação de identidade.`));
      list.append(card);
    }
    section.append(list);
  }
  container.append(section);
  form.addEventListener("submit", event => {
    event.preventDefault();
    const data = new FormData(form);
    const template = templates.find(item => item.id === data.get("templateId"));
    try {
      const next = createAssignment(onboardingAssignments, template, data.get("assignee"), { id: makeId(), now: new Date().toISOString() });
      if (persistOnboardingAssignments(next)) {
        showToast("Trilha atribuída neste navegador.");
        renderWorkspacePage();
      }
    } catch (error) { showToast(error.message || "Revise a trilha e a pessoa informadas."); }
  });
  section.addEventListener("change", event => {
    const checkbox = event.target.closest("input[data-onboarding-id][data-onboarding-step]");
    if (!checkbox) return;
    try {
      const next = setStepCompleted(onboardingAssignments, checkbox.dataset.onboardingId, checkbox.dataset.onboardingStep, checkbox.checked, new Date().toISOString());
      if (!persistOnboardingAssignments(next)) {
        checkbox.checked = !checkbox.checked;
        return;
      }
      const assignment = onboardingAssignments.find(item => item.id === checkbox.dataset.onboardingId);
      const card = checkbox.closest("[data-onboarding-card]");
      const done = assignment.steps.filter(step => step.completed).length;
      const percent = assignment.steps.length ? Math.round(done / assignment.steps.length * 100) : 0;
      card.querySelector("[data-onboarding-progress-text]").textContent = `${done} de ${assignment.steps.length} passos`;
      card.querySelector('[role="progressbar"]').setAttribute("aria-valuenow", String(percent));
      card.querySelector(".onboarding-meter i").style.width = `${percent}%`;
      card.querySelector("[data-onboarding-status]").textContent = assignment.completedAt ? "Concluída" : "Em andamento";
      checkbox.closest("li").classList.toggle("is-complete", checkbox.checked);
      showToast(checkbox.checked ? "Passo registrado como concluído." : "Passo reaberto; o histórico foi preservado.");
      renderWorkspacePage();
      document.querySelector(`[data-onboarding-card="${CSS.escape(assignment.id)}"] input[data-onboarding-step="${CSS.escape(checkbox.dataset.onboardingStep)}"]`)?.focus();
    } catch (error) { showToast(error.message || "Não foi possível atualizar este passo."); }
  });
}

function renderKnowledgeEditor(container, record = null) {
  container.replaceChildren();
  container.hidden = false;
  const form = node("form", "knowledge-form");
  form.id = "knowledgeForm";
  form.noValidate = true;
  const heading = node("div", "knowledge-editor-heading");
  heading.append(node("h3", "", record ? "Editar item" : "Novo item"), node("p", "", "Campos marcados com * são necessários para manter o conteúdo organizado."));
  form.append(heading);

  const typeLabel = node("label", "form-label", "Tipo de conteúdo *");
  const type = document.createElement("select");
  type.name = "type";
  type.required = true;
  knowledgeTypes.forEach(item => type.add(new Option(item.label, item.id)));
  type.value = record?.type || "reference";
  typeLabel.append(type);
  form.append(typeLabel);

  const titleLabel = node("label", "form-label", "Nome do item *");
  const title = document.createElement("input");
  title.name = "title";
  title.required = true;
  title.maxLength = 120;
  title.placeholder = "Ex.: Guia de identidade visual";
  title.value = record?.title || "";
  titleLabel.append(title);
  form.append(titleLabel);

  const summaryLabel = node("label", "form-label");
  const summaryCaption = node("span", "knowledge-summary-label");
  const summary = document.createElement("textarea");
  summary.name = "summary";
  summary.required = true;
  summary.maxLength = 1200;
  summary.rows = 3;
  summary.placeholder = "Registre o que a pessoa precisa saber.";
  summary.value = record?.summary || "";
  summaryLabel.append(summaryCaption, summary);
  form.append(summaryLabel);

  const formGrid = node("div", "knowledge-form-grid");
  for (const [field, caption, placeholder, maxLength] of [
    ["owner", "Responsável pelo conteúdo *", "Nome ou função responsável", 100],
    ["audience", "Para quem serve *", "Ex.: equipe de criação", 160],
  ]) {
    const label = node("label", "form-label", caption);
    const input = document.createElement("input");
    input.name = field;
    input.required = true;
    input.maxLength = maxLength;
    input.placeholder = placeholder;
    input.value = record?.[field] || "";
    label.append(input);
    formGrid.append(label);
  }
  const reviewLabel = node("label", "form-label", "Data de revisão *");
  const reviewDate = document.createElement("input");
  reviewDate.type = "date";
  reviewDate.name = "reviewDate";
  reviewDate.required = true;
  reviewDate.value = record?.reviewDate || "";
  reviewLabel.append(reviewDate);
  formGrid.append(reviewLabel);
  form.append(formGrid);

  const linkLabel = node("label", "form-label", "Link do material (opcional)");
  const link = document.createElement("input");
  link.type = "url";
  link.name = "link";
  link.placeholder = "https://...";
  link.value = record?.link || "";
  linkLabel.append(link);
  form.append(linkLabel);

  const stepsLabel = node("label", "form-label knowledge-steps-field", "Passos (um por linha)");
  const steps = document.createElement("textarea");
  steps.name = "steps";
  steps.rows = 4;
  steps.maxLength = 2000;
  steps.placeholder = "1. Conhecer o espaço de trabalho\n2. Consultar as referências principais";
  steps.value = (record?.steps || []).join("\n");
  stepsLabel.append(steps);
  form.append(stepsLabel);

  const notice = node("p", "knowledge-editor-note", "Demonstração local: use apenas conteúdo e contatos fictícios. Não inclua senhas nem dados de clientes.");
  form.append(notice);
  const buttons = node("div", "knowledge-editor-actions");
  const cancel = node("button", "secondary-button", "Cancelar");
  cancel.type = "button";
  cancel.dataset.knowledgeAction = "cancel-edit";
  const save = node("button", "primary-button", record ? "Salvar alterações" : "Salvar item");
  save.type = "submit";
  buttons.append(cancel, save);
  form.append(buttons);
  container.append(form);

  const updateTypeFields = () => {
    const kind = type.value;
    title.placeholder = kind === "contact" ? "Ex.: Suporte de hospedagem (fictício)" : kind === "training" ? "Ex.: Treinamento de ferramenta" : kind === "onboarding" ? "Ex.: Primeiros passos da equipe" : "Ex.: Guia de identidade visual";
    summaryCaption.textContent = kind === "contact" ? "Como encontrar este contato *" : kind === "training" ? "O que a pessoa vai aprender *" : kind === "onboarding" ? "Objetivo desta trilha *" : "Resumo ou instrução *";
    stepsLabel.hidden = !["training", "onboarding"].includes(kind);
    steps.required = kind === "onboarding";
  };
  type.addEventListener("change", updateTypeFields);
  updateTypeFields();
  form.addEventListener("submit", event => {
    event.preventDefault();
    const data = new FormData(form);
    const existing = record || knowledgeRecords.find(item => item.id === knowledgeEditingId);
    try {
      const nextRecords = saveKnowledgeItem(knowledgeRecords, Object.fromEntries(data.entries()), {
        id: existing?.id || makeId(),
        now: new Date().toISOString(),
      });
      if (!persistKnowledgeRecords(nextRecords)) return;
      knowledgeEditingId = null;
      renderBoard();
      showToast(existing ? "Item atualizado na biblioteca deste navegador." : "Item salvo na biblioteca deste navegador.");
    } catch (error) {
      showToast(error.message || "Revise os campos antes de salvar.");
    }
  });
  title.focus();
}

function renderKnowledgePage(panel) {
  panel.append(panelHeading("Biblioteca da agência", "Encontre referências, treinamentos, contatos e passos de onboarding."));
  const notice = node("p", "knowledge-safety-note", "Demonstração local: os itens ficam neste navegador. Use exemplos fictícios; não há contas, permissões ou conteúdo compartilhado entre pessoas.");
  panel.append(notice);
  const processGuide = document.createElement("details");
  processGuide.className = "knowledge-process-guide";
  const processSummary = document.createElement("summary");
  processSummary.textContent = "Como uma demanda avança";
  const processSteps = node("ol", "knowledge-steps");
  [
    "Briefing: registrar origem, canal ou peça e critérios de aceite.",
    "Planejamento: dividir o trabalho em tarefas e revisar o plano.",
    "Execução: acompanhar tarefas e registrar comentários e impedimentos.",
    "Revisão interna: conferir o material antes de enviar ao cliente.",
    "Aprovação do cliente: aprovar a versão ou pedir alterações.",
    "Ajustes: produzir uma nova versão mantendo o vínculo ao feedback anterior.",
    "Entrega: registrar se foi entregue, agendada ou publicada, com evidência.",
    "Conclusão: conferir o registro final antes de encerrar a demanda.",
  ].forEach(text => processSteps.append(node("li", "", text)));
  processGuide.append(processSummary, processSteps);
  panel.append(processGuide);
  renderOnboardingAssignments(panel);

  const toolbar = node("div", "knowledge-toolbar");
  const searchLabel = node("label", "knowledge-search-label", "Buscar na biblioteca");
  const search = document.createElement("input");
  search.id = "knowledgeSearch";
  search.type = "search";
  search.placeholder = "Digite um nome, assunto ou responsável";
  search.value = knowledgeSearch;
  searchLabel.append(search);
  const typeLabel = node("label", "knowledge-filter-label", "Tipo");
  const typeFilter = document.createElement("select");
  typeFilter.id = "knowledgeTypeFilter";
  typeFilter.add(new Option("Todos os tipos", ""));
  knowledgeTypes.forEach(item => typeFilter.add(new Option(item.label, item.id)));
  typeFilter.value = knowledgeTypeFilter;
  typeLabel.append(typeFilter);
  const archivedLabel = node("label", "knowledge-archived-toggle", "");
  const archivedToggle = document.createElement("input");
  archivedToggle.type = "checkbox";
  archivedToggle.checked = knowledgeIncludeArchived;
  archivedToggle.setAttribute("aria-label", "Mostrar itens arquivados");
  archivedLabel.append(archivedToggle, node("span", "", "Mostrar arquivados"));
  const add = node("button", "primary-button", "+ Novo item");
  add.type = "button";
  add.id = "newKnowledgeItem";
  toolbar.append(searchLabel, typeLabel, archivedLabel, add);
  panel.append(toolbar);

  const editor = node("section", "knowledge-editor");
  editor.id = "knowledgeEditor";
  editor.hidden = true;
  panel.append(editor);
  const results = node("section", "knowledge-results");
  results.id = "knowledgeResults";
  results.setAttribute("aria-live", "polite");
  panel.append(results);
  renderKnowledgeResults(results);

  search.addEventListener("input", () => { knowledgeSearch = search.value; renderKnowledgeResults(results); });
  typeFilter.addEventListener("change", () => { knowledgeTypeFilter = typeFilter.value; renderKnowledgeResults(results); });
  archivedToggle.addEventListener("change", () => { knowledgeIncludeArchived = archivedToggle.checked; renderKnowledgeResults(results); });
  add.addEventListener("click", () => {
    knowledgeEditingId = null;
    renderKnowledgeEditor(editor);
    editor.scrollIntoView({ behavior: "smooth", block: "start" });
  });
  results.addEventListener("click", event => {
    const button = event.target.closest("button[data-knowledge-action]");
    if (!button) return;
    const action = button.dataset.knowledgeAction;
    const id = button.dataset.knowledgeId;
    const item = knowledgeRecords.find(record => record.id === id);
    if (action === "edit" && item) {
      knowledgeEditingId = id;
      renderKnowledgeEditor(editor, item);
      editor.scrollIntoView({ behavior: "smooth", block: "start" });
    } else if (action === "archive" || action === "restore") {
      try {
        const nextRecords = setKnowledgeItemArchived(knowledgeRecords, id, action === "archive");
        if (persistKnowledgeRecords(nextRecords)) {
          renderKnowledgeResults(results);
          showToast(action === "archive" ? "Item arquivado; você pode restaurá-lo depois." : "Item restaurado na biblioteca.");
        }
      } catch (error) { showToast(error.message); }
    }
  });
  editor.addEventListener("click", event => {
    if (!event.target.closest('[data-knowledge-action="cancel-edit"]')) return;
    knowledgeEditingId = null;
    editor.replaceChildren();
    editor.hidden = true;
    add.focus();
  });
}

function capacityField(labelText, control, className = "") {
  const label = node("label", `capacity-field${className ? ` ${className}` : ""}`, labelText);
  label.append(control);
  return label;
}

function renderWeeklyCapacityPanel(panel) {
  const section = node("section", "capacity-planner");
  section.append(panelHeading("Disponibilidade e carga estimada", "Configure horas de demonstração por pessoa e registre ausências. Não existe jornada padrão neste protótipo."));
  const people = listAssignees(state.requests);
  if (!people.length) {
    section.append(emptyPanel("Nenhuma pessoa com tarefa atribuída", "Crie uma tarefa e informe um responsável para experimentar a prévia de capacidade."));
    panel.append(section);
    return;
  }
  if (!people.some(name => assigneeKey(name) === assigneeKey(selectedCapacityAssignee))) selectedCapacityAssignee = people[0];

  const controls = node("div", "capacity-controls");
  const weekInput = node("input", "");
  weekInput.type = "week";
  weekInput.name = "week";
  weekInput.value = selectedCapacityWeek;
  controls.append(capacityField("Semana (segunda a domingo)", weekInput));
  const assigneeSelect = node("select", "");
  assigneeSelect.name = "assignee";
  for (const name of people) assigneeSelect.add(new Option(name, name));
  assigneeSelect.value = selectedCapacityAssignee;
  controls.append(capacityField("Profissional demonstrativo", assigneeSelect));
  section.append(controls);

  let weekStart;
  try {
    weekStart = weekStartFromIso(selectedCapacityWeek);
  } catch {
    weekStart = weekStartFromIso(isoWeekFromDate(new Date()));
    selectedCapacityWeek = isoWeekFromDate(new Date());
  }
  const profile = findCapacityProfile(selectedCapacityAssignee, weekStart);
  const weekEnd = new Date(`${weekStart}T00:00:00Z`);
  weekEnd.setUTCDate(weekEnd.getUTCDate() + 6);
  const weekEndStamp = weekEnd.toISOString().slice(0, 10);
  const absenceHours = capacityData.absences
    .filter(item => assigneeKey(item.assignee) === assigneeKey(selectedCapacityAssignee) && item.date >= weekStart && item.date <= weekEndStamp)
    .reduce((total, item) => total + (Number(item.hours) || 0), 0);
  const absencesForWeek = capacityData.absences.filter(item => assigneeKey(item.assignee) === assigneeKey(selectedCapacityAssignee) && item.date >= weekStart && item.date <= weekEndStamp);

  const scheduleForm = node("form", "capacity-form");
  scheduleForm.id = "capacityScheduleForm";
  const hoursInput = node("input", "");
  hoursInput.type = "number";
  hoursInput.name = "scheduledHours";
  hoursInput.min = "0";
  hoursInput.max = "168";
  hoursInput.step = "0.25";
  hoursInput.required = true;
  hoursInput.value = profile ? String(profile.scheduledHours) : "";
  hoursInput.placeholder = "Informe as horas";
  const scheduleFields = node("div", "capacity-form-fields");
  scheduleFields.append(capacityField("Horas previstas de trabalho nesta semana", hoursInput));
  const saveSchedule = node("button", "secondary-button", profile ? "Atualizar horas" : "Salvar horas");
  saveSchedule.type = "submit";
  scheduleForm.append(scheduleFields, saveSchedule);
  section.append(scheduleForm);

  if (!profile) {
    section.append(emptyPanel("Disponibilidade não configurada", "Digite a quantidade de horas prevista para esta pessoa nesta semana. O protótipo não preenche uma jornada sozinho."));
    panel.append(section);
    return;
  }

  const summary = summarizeWeeklyCapacity({
    requests: state.requests,
    profile,
    absences: capacityData.absences,
    assignee: selectedCapacityAssignee,
    weekStart,
  });
  const metrics = node("dl", "capacity-metrics");
  for (const [label, value] of [
    ["Horas previstas", summary.scheduledHours],
    ["Ausências", summary.absenceHours],
    ["Disponíveis", summary.availableHours],
    ["Estimativa com prazo nesta semana", summary.plannedHours],
  ]) {
    const pair = node("div", "capacity-metric");
    pair.append(node("dt", "", label), node("dd", "", `${Number(value.toFixed(2))} h`));
    metrics.append(pair);
  }
  section.append(metrics);

  const overCapacity = summary.plannedHours > summary.availableHours;
  const utilization = summary.availableHours > 0
    ? Math.min(100, summary.plannedHours / summary.availableHours * 100)
    : (summary.plannedHours > 0 ? 100 : 0);
  const balance = node("p", `capacity-balance${overCapacity ? " is-overloaded" : ""}`, "");
  balance.textContent = overCapacity
    ? `Excesso estimado: ${Number((summary.plannedHours - summary.availableHours).toFixed(2))} h`
    : `Saldo após as estimativas: ${Number((summary.availableHours - summary.plannedHours).toFixed(2))} h`;
  const meter = node("div", "capacity-meter");
  meter.setAttribute("role", "progressbar");
  meter.setAttribute("aria-label", "Estimativa comparada com a disponibilidade configurada");
  meter.setAttribute("aria-valuemin", "0");
  meter.setAttribute("aria-valuemax", "100");
  meter.setAttribute("aria-valuenow", String(Math.round(utilization)));
  const fill = node("i", overCapacity ? "is-overloaded" : "");
  fill.style.width = `${utilization}%`;
  meter.append(fill);
  section.append(balance, meter, node("p", "capacity-method-note", "Prévia: soma estimativas de tarefas abertas com prazo nesta semana. Não distribui horas por dia e não desconta tempo já registrado."));

  const absenceSection = node("section", "capacity-absence-section");
  absenceSection.append(node("h3", "calendar-section-title", "Ausências nesta semana"));
  const absenceForm = node("form", "capacity-absence-form");
  absenceForm.id = "capacityAbsenceForm";
  const dateInput = node("input", "");
  dateInput.type = "date";
  dateInput.name = "date";
  dateInput.min = weekStart;
  dateInput.max = weekEndStamp;
  dateInput.required = true;
  const absenceInput = node("input", "");
  absenceInput.type = "number";
  absenceInput.name = "hours";
  absenceInput.min = "0.25";
  absenceInput.step = "0.25";
  absenceInput.max = String(Math.max(0, profile.scheduledHours - absenceHours));
  absenceInput.required = true;
  absenceInput.placeholder = "Horas";
  const addAbsence = node("button", "secondary-button", "Registrar ausência");
  addAbsence.type = "submit";
  absenceForm.append(capacityField("Dia", dateInput), capacityField("Horas fora", absenceInput), addAbsence);
  absenceSection.append(absenceForm);
  if (absencesForWeek.length) {
    const list = node("ul", "capacity-absence-list");
    for (const absence of absencesForWeek) {
      const item = node("li", "capacity-absence-item");
      const date = new Date(`${absence.date}T12:00:00`);
      const dateLabel = new Intl.DateTimeFormat("pt-BR", { weekday: "short", day: "2-digit", month: "short" }).format(date);
      item.append(node("span", "", `${dateLabel} · ${Number(absence.hours)} h`));
      const remove = node("button", "text-action", "Remover");
      remove.type = "button";
      remove.dataset.capacityRemoveAbsence = absence.id;
      item.append(remove);
      list.append(item);
    }
    absenceSection.append(list);
  } else {
    absenceSection.append(node("p", "capacity-muted", "Nenhuma ausência registrada."));
  }
  section.append(absenceSection);

  const taskSection = node("section", "capacity-task-section");
  taskSection.append(node("h3", "calendar-section-title", `Tarefas com prazo nesta semana (${summary.plannedTasks.length})`));
  if (summary.plannedTasks.length) {
    const list = node("div", "workspace-list");
    for (const item of summary.plannedTasks) {
      const detail = `${item.task.title} · ${item.estimateHours == null ? "sem estimativa" : `${Number(item.estimateHours)} h estimadas`} · prazo ${formatDue(item.task.due)}`;
      list.append(makeOpenRequestButton(item.request, detail));
    }
    taskSection.append(list);
  } else {
    taskSection.append(node("p", "capacity-muted", "Nenhuma tarefa aberta desta pessoa tem prazo nesta semana."));
  }
  if (summary.undatedTasks.length) {
    taskSection.append(node("p", "capacity-muted", `${summary.undatedTasks.length} tarefa${summary.undatedTasks.length === 1 ? "" : "s"} atribuída${summary.undatedTasks.length === 1 ? "" : "s"} sem prazo não entra${summary.undatedTasks.length === 1 ? "" : "m"} no cálculo.`));
  }
  if (summary.missingEstimateTasks.length) {
    taskSection.append(node("p", "capacity-muted", `${summary.missingEstimateTasks.length} tarefa${summary.missingEstimateTasks.length === 1 ? "" : "s"} com prazo não tem estimativa; confira a lista antes de interpretar o total.`));
  }
  section.append(taskSection);
  panel.append(section);
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
    renderWeeklyCapacityPanel(panel);
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
    renderKnowledgePage(panel);
    return;
  }
  panel.append(panelHeading("Tipos de usuário", "Quatro categorias citadas nos áudios, com responsabilidades confirmadas e decisões de acesso ainda abertas."));
  const roleGrid = node("div", "participant-role-grid");
  for (const participant of participantTypes) {
    const choice = node("button", `participant-role-choice${participant.id === activeParticipantTypeId ? " is-selected" : ""}`, "");
    choice.type = "button";
    choice.setAttribute("aria-pressed", String(participant.id === activeParticipantTypeId));
    choice.append(node("strong", "", participant.name), node("small", "", participant.source));
    choice.addEventListener("click", () => {
      activeParticipantTypeId = participant.id;
      renderWorkspacePage();
    });
    roleGrid.append(choice);
  }
  panel.append(roleGrid);
  const selectedParticipant = participantTypes.find(participant => participant.id === activeParticipantTypeId) || participantTypes[0];
  const roleDetails = node("section", "participant-role-details");
  roleDetails.setAttribute("aria-live", "polite");
  roleDetails.setAttribute("aria-labelledby", "participantRoleTitle");
  roleDetails.append(node("h3", "", selectedParticipant.name));
  roleDetails.lastChild.id = "participantRoleTitle";
  const capability = node("p", "utility-note", "");
  capability.append(node("strong", "", "O áudio confirma"), node("span", "", selectedParticipant.confirmedCapability));
  const pending = node("p", "utility-note participant-role-pending", "");
  pending.append(node("strong", "", "Ainda precisa ser definido"), node("span", "", selectedParticipant.stillToDefine));
  roleDetails.append(capability, pending);
  panel.append(roleDetails);
  panel.append(node("p", "utility-note", "Estes são tipos de usuário de referência, não contas. Esta demonstração não aplica restrições de acesso: todos os dados continuam visíveis a quem abrir o mesmo navegador."));
  panel.append(node("p", "utility-note", "Não há login nem cadastro de pessoas nesta versão. Antes de criar contas ou proteger dados, é preciso validar a matriz de permissões, o isolamento dos clientes e quem administrará os acessos. Não use dados reais de clientes nesta demonstração."));
}

function loadKnowledgeRecords() {
  try {
    const value = JSON.parse(localStorage.getItem(KNOWLEDGE_STORAGE_KEY) || "[]");
    return Array.isArray(value) ? value.filter(item => item && typeof item.id === "string" && typeof item.title === "string") : [];
  } catch {
    showToast("A biblioteca local não pôde ser lida neste navegador.");
    return [];
  }
}

function persistKnowledgeRecords(nextRecords) {
  try {
    localStorage.setItem(KNOWLEDGE_STORAGE_KEY, JSON.stringify(nextRecords));
    knowledgeRecords = nextRecords;
    return true;
  } catch {
    showToast("Não foi possível salvar a biblioteca neste navegador. Libere espaço ou verifique as configurações de privacidade.");
    return false;
  }
}

function loadOnboardingAssignments() {
  try {
    const value = JSON.parse(localStorage.getItem(ONBOARDING_STORAGE_KEY) || "[]");
    return Array.isArray(value) ? value.filter(item => item && typeof item.id === "string" && typeof item.assignee === "string" && Array.isArray(item.steps) && Array.isArray(item.history)) : [];
  } catch {
    showToast("As trilhas atribuídas não puderam ser lidas neste navegador.");
    return [];
  }
}

function persistOnboardingAssignments(nextAssignments) {
  try {
    localStorage.setItem(ONBOARDING_STORAGE_KEY, JSON.stringify(nextAssignments));
    onboardingAssignments = nextAssignments;
    return true;
  } catch {
    showToast("Não foi possível salvar o progresso das trilhas neste navegador.");
    return false;
  }
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

function updateRequestFor(requestId, action, payload = {}, successMessage = "Alteração salva.") {
  const request = state.requests.find(item => item.id === requestId);
  if (!request) return false;
  try {
    if (action === "start_task_timer") {
      const active = findActiveTaskTimer(state.requests);
      if (active && (active.request.id !== request.id || active.task.id !== payload.taskId)) {
        throw new Error("Pare o cronômetro atual antes de iniciar outro.");
      }
    }
    const commentActions = ["add_comment", "internal_changes", "client_changes"];
    const anchorMatchesVersion = request.id === activeRequestId && activeCommentAnchor?.versionId === request.versions.at(-1)?.id;
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
    if (request.id === activeRequestId) renderDrawer();
    if (minimizedRequestIds.includes(request.id)) {
      [...minimizedRequests.querySelectorAll(".minimized-item")]
        .find(item => item.dataset.requestId === request.id)
        ?.querySelector(".minimized-timer-button")?.focus();
    }
    showToast(successMessage);
    return true;
  } catch (error) {
    showToast(error.message);
    return false;
  }
}

function updateRequest(action, payload = {}, successMessage = "Alteração salva.") {
  const request = currentRequest();
  return request ? updateRequestFor(request.id, action, payload, successMessage) : false;
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
    if (element.classList.contains("minimized-timer-total")) {
      const requestId = element.closest(".minimized-item")?.dataset.requestId;
      const request = state.requests.find(item => item.id === requestId);
      const active = findActiveTaskTimer([request].filter(Boolean));
      if (active) element.textContent = `Ativo: ${formatDuration(Number(element.dataset.timerBaseSeconds) + elapsed)} · ${active.task.title}`;
    } else element.textContent = `Cronômetro ativo: ${formatDuration(Number(element.dataset.timerBaseSeconds) + elapsed)}`;
  });
}

function stopRunningTimersWhenAppCloses() {
  if (!findActiveTaskTimer(state.requests)) return;
  state.requests = stopAllActiveTaskTimers(state.requests, new Date().toISOString());
  saveState();
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
  if (document.activeElement !== document.body && !drawer.contains(document.activeElement) && !minimizedRequests.contains(document.activeElement)) {
    drawerReturnFocus = document.activeElement;
  }
  minimizedRequestIds = minimizedRequestIds.filter(item => item !== id);
  saveMinimizedIds();
  renderMinimizedRequests();
  activeRequestId = id;
  const request = state.requests.find(item => item.id === id);
  viewedVersionId = request?.versions.at(-1)?.id || null;
  activeCommentAnchor = null;
  imageAnchorMode = false;
  renderDrawer();
  drawer.inert = false;
  appShell.inert = true;
  minimizedRequests.inert = true;
  drawer.classList.add("open");
  drawer.setAttribute("aria-hidden", "false");
  scrim.hidden = false;
  document.body.style.overflow = "hidden";
  document.querySelector("#closeDrawer").focus();
}

function closeDrawer() {
  const closedRequestId = activeRequestId;
  drawer.classList.remove("open");
  drawer.setAttribute("aria-hidden", "true");
  drawer.inert = true;
  appShell.inert = false;
  minimizedRequests.inert = false;
  scrim.hidden = true;
  document.body.style.overflow = "";
  activeRequestId = null;
  viewedVersionId = null;
  if (activePreviewUrl) URL.revokeObjectURL(activePreviewUrl);
  activePreviewUrl = null;
  activeMediaObserver?.disconnect();
  activeMediaObserver = null;
  if (closedRequestId) {
    const isUsableFocusTarget = element => element?.isConnected && element.getClientRects().length > 0 && !element.closest("[hidden], [inert], dialog:not([open])");
    const requestCard = [...document.querySelectorAll(".task-card[data-request-id]")].find(element => element.dataset.requestId === closedRequestId && isUsableFocusTarget(element));
    const focusTarget = isUsableFocusTarget(drawerReturnFocus) ? drawerReturnFocus : requestCard || document.querySelector("#newRequestButton");
    focusTarget?.focus();
  }
  drawerReturnFocus = null;
}

function minimizeDrawer() {
  if (!activeRequestId) return;
  if (!minimizedRequestIds.includes(activeRequestId)) minimizedRequestIds.push(activeRequestId);
  saveMinimizedIds();
  closeDrawer();
  renderMinimizedRequests();
  minimizedRequests.querySelector(".minimized-item:last-child .minimized-request")?.focus();
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
  renderWorkspacePage();
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
document.addEventListener("keydown", event => {
  if (event.key !== "Tab" || drawer.inert) return;
  const focusable = [...drawer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
    .filter(element => !element.closest("[hidden]") && element.getClientRects().length > 0);
  if (!focusable.length) {
    event.preventDefault();
    return;
  }
  const first = focusable[0];
  const last = focusable.at(-1);
  if (event.shiftKey && (document.activeElement === first || !drawer.contains(document.activeElement))) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && (document.activeElement === last || !drawer.contains(document.activeElement))) {
    event.preventDefault();
    first.focus();
  }
});
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
document.querySelector("#workspaceView").addEventListener("change", event => {
  if (event.target.name === "week" && event.target.type === "week") {
    selectedCapacityWeek = event.target.value || isoWeekFromDate(new Date());
    renderWorkspacePage();
  }
  if (event.target.name === "assignee" && event.target.tagName === "SELECT") {
    selectedCapacityAssignee = event.target.value;
    renderWorkspacePage();
  }
});
document.querySelector("#workspaceView").addEventListener("submit", event => {
  if (!(["capacityScheduleForm", "capacityAbsenceForm"].includes(event.target.id))) return;
  event.preventDefault();
  const data = new FormData(event.target);
  const assignee = selectedCapacityAssignee;
  const weekStart = weekStartFromIso(selectedCapacityWeek);
  if (event.target.id === "capacityScheduleForm") {
    const scheduledHours = Number(data.get("scheduledHours"));
    if (!Number.isFinite(scheduledHours) || scheduledHours < 0 || scheduledHours > 168 || Math.round(scheduledHours * 4) !== scheduledHours * 4) {
      showToast("Informe horas entre 0 e 168, em intervalos de 15 minutos.");
      return;
    }
    const weekEnd = new Date(`${weekStart}T00:00:00Z`);
    weekEnd.setUTCDate(weekEnd.getUTCDate() + 6);
    const absenceHours = capacityData.absences
      .filter(item => assigneeKey(item.assignee) === assigneeKey(assignee) && item.date >= weekStart && item.date <= weekEnd.toISOString().slice(0, 10))
      .reduce((total, item) => total + Number(item.hours || 0), 0);
    if (absenceHours > scheduledHours) {
      showToast("As horas previstas não podem ficar abaixo das ausências já registradas.");
      return;
    }
    const previous = capacityData;
    const profile = { assignee, weekStart, scheduledHours };
    capacityData = {
      ...capacityData,
      profiles: [...capacityData.profiles.filter(item => !(assigneeKey(item.assignee) === assigneeKey(assignee) && item.weekStart === weekStart)), profile],
    };
    if (!saveCapacityData()) capacityData = previous;
    else {
      showToast("Horas previstas salvas neste navegador.");
      renderWorkspacePage();
    }
    return;
  }

  const profile = findCapacityProfile(assignee, weekStart);
  const date = String(data.get("date") || "");
  const hours = Number(data.get("hours"));
  const weekEnd = new Date(`${weekStart}T00:00:00Z`);
  weekEnd.setUTCDate(weekEnd.getUTCDate() + 6);
  const weekEndStamp = weekEnd.toISOString().slice(0, 10);
  if (!profile) return showToast("Configure as horas da semana antes de registrar uma ausência.");
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date < weekStart || date > weekEndStamp) return showToast("Escolha uma data dentro da semana selecionada.");
  if (!Number.isFinite(hours) || hours <= 0 || hours > 168 || Math.round(hours * 4) !== hours * 4) return showToast("Informe horas de ausência em intervalos de 15 minutos.");
  const existingAbsences = capacityData.absences
    .filter(item => assigneeKey(item.assignee) === assigneeKey(assignee) && item.date >= weekStart && item.date <= weekEndStamp)
    .reduce((total, item) => total + Number(item.hours || 0), 0);
  if (existingAbsences + hours > Number(profile.scheduledHours)) return showToast("As ausências não podem superar as horas previstas nesta semana.");
  const previous = capacityData;
  capacityData = { ...capacityData, absences: [...capacityData.absences, { id: makeId(), assignee, date, hours }] };
  if (!saveCapacityData()) capacityData = previous;
  else {
    showToast("Ausência registrada para esta semana.");
    renderWorkspacePage();
  }
});
document.querySelector("#workspaceView").addEventListener("click", event => {
  const removeButton = event.target.closest("[data-capacity-remove-absence]");
  if (!removeButton) return;
  const previous = capacityData;
  capacityData = { ...capacityData, absences: capacityData.absences.filter(item => item.id !== removeButton.dataset.capacityRemoveAbsence) };
  if (!saveCapacityData()) capacityData = previous;
  else {
    showToast("Ausência removida.");
    renderWorkspacePage();
  }
});
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
  renderBoard();
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
window.addEventListener("pagehide", stopRunningTimersWhenAppCloses);
window.setInterval(refreshTaskTimerDisplays, 1000);
