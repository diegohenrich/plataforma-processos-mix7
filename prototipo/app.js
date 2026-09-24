const { stages, transition, listAssignees, filterRequestsByAssignee } = window.Mix7Workflow;
const STORAGE_KEY = "mix7.workflow.v1";
const MAX_FILE_BYTES = 15 * 1024 * 1024;
const drawer = document.querySelector("#detailDrawer");
const scrim = document.querySelector("#scrim");
const dialog = document.querySelector("#requestDialog");
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
let state = loadState();

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
          if (!Array.isArray(request.tasks)) request.tasks = [];
          if (!Array.isArray(request.briefingRevisions)) request.briefingRevisions = [];
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
      due: "",
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
  card.dataset.title = request.title;
  card.dataset.client = request.client;
  card.dataset.stageName = stages[request.stage];
  card.tabIndex = 0;
  card.setAttribute("role", "button");
  card.setAttribute("aria-label", `${request.title}, ${stages[request.stage]}`);

  const row = node("div", "card-label-row");
  const client = node("span", "client-label label-blue", request.client);
  row.append(client);
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
  applySearch(document.querySelector("#searchInput").value);
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
  document.querySelector("#taskHint").textContent = request.stage === "planning" ? "Inclua pelo menos uma tarefa atribuída antes de iniciar a execução." : request.stage === "adjustments" ? `Tarefas para a versão V${String(round).padStart(2, "0")}; conclua todas antes de anexá-la.` : `Tarefas da rodada V${String(round).padStart(2, "0")}. Os nomes são texto livre nesta demonstração.`;
  for (const task of tasks) {
    const item = node("li", `task-list-item${task.status === "completed" ? " task-done" : ""}`);
    const details = node("div", "task-list-details");
    details.append(node("strong", "", `V${String(task.round).padStart(2, "0")} · ${task.title}`), node("span", "", `${task.assignee}${task.estimateHours ? ` · ${task.estimateHours} h` : ""}${task.due ? ` · ${formatDue(task.due)}` : ""}`));
    item.append(details);
    if (task.round === round && ["doing", "adjustments"].includes(request.stage)) {
      const button = node("button", "task-toggle", task.status === "completed" ? "Reabrir" : "Concluir tarefa");
      button.type = "button";
      button.addEventListener("click", () => updateRequest("toggle_task", { taskId: task.id }, task.status === "completed" ? "Tarefa reaberta." : "Tarefa concluída."));
      item.append(button);
    } else item.append(node("span", "task-status", task.status === "completed" ? "Concluída" : "Pendente"));
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

function renderHistory(request) {
  const list = document.querySelector("#historyList");
  list.replaceChildren();
  for (const event of [...request.history].reverse()) {
    const row = node("li", "", `${eventLabel(event.type)} · ${formatTimestamp(event.at)}`);
    list.append(row);
  }
}

function eventLabel(type) {
  const names = {
    created: "Demanda criada", fixture_loaded: "Item demonstrativo carregado", briefing_ready: "Briefing liberado para planejamento", briefing_revised: "Briefing alterado; plano aguarda revisão", plan_confirmed: "Planejamento revisado e confirmado", add_task: "Tarefa atribuída", toggle_task: "Estado da tarefa alterado", submit_internal_review: "Enviado para revisão interna", internal_approved: "Revisão interna aprovada e versão compartilhada", internal_changes: "Devolvido pela revisão interna", client_approved: "Versão aprovada pelo cliente", client_changes: "Ajustes solicitados pelo cliente", attach_file: "Arquivo anexado à versão", new_version: "Nova versão criada", record_delivery: "Entrega/publicação registrada", add_comment: "Comentário registrado",
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
    appendAction(buttons, "Solicitar ajustes", () => updateRequest("client_changes", { versionId: request.versions.at(-1).id, comment: reason.value }, "Pedido de ajustes registrado nesta versão."));
    appendAction(buttons, "Aprovar versão", () => updateRequest("client_approved", { versionId: request.versions.at(-1).id }, "Versão aprovada. Falta registrar entrega/publicação."), "approve-button");
  } else if (request.stage === "adjustments") {
    container.append(node("small", "workflow-hint", "A nova versão preserva o pedido de ajuste e passa novamente pela revisão interna."));
    const nextRound = request.versions.at(-1).number + 1;
    const tasks = (request.tasks || []).filter(task => task.round === nextRound);
    const attach = appendAction(buttons, "Anexar nova versão", () => chooseFile("new_version"), "approve-button");
    attach.disabled = !tasks.length || tasks.some(task => task.status !== "completed");
  } else if (request.stage === "delivery") {
    const evidence = node("input", "workflow-input");
    evidence.id = "deliveryEvidence";
    evidence.placeholder = "URL ou descrição da entrega/publicação (obrigatório)";
    container.insertBefore(evidence, buttons);
    appendAction(buttons, "Registrar entrega e concluir", () => updateRequest("record_delivery", { evidence: evidence.value }, "Entrega registrada e demanda concluída."), "approve-button");
  } else {
    container.append(node("small", "workflow-hint", `Concluída com evidência: ${request.delivery?.evidence || "não registrada"}`));
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

function applySearch(value) {
  const query = value.trim().toLocaleLowerCase("pt-BR");
  document.querySelectorAll(".task-card").forEach(card => {
    card.hidden = Boolean(query) && !`${card.dataset.title} ${card.dataset.client}`.toLocaleLowerCase("pt-BR").includes(query);
  });
}

document.querySelector("#closeDrawer").addEventListener("click", closeDrawer);
scrim.addEventListener("click", closeDrawer);
document.addEventListener("keydown", event => { if (event.key === "Escape") { closeDrawer(); if (dialog.open) dialog.close(); } });
document.querySelector("#newRequestButton").addEventListener("click", () => dialog.showModal());
document.querySelectorAll(".add-card").forEach(button => button.addEventListener("click", () => dialog.showModal()));

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
  const now = new Date().toISOString();
  const request = {
    id: makeId(), title: String(data.get("title")).trim(), client: String(data.get("client")).trim(), brief: String(data.get("brief")).trim(), type: String(data.get("type")), origin: String(data.get("origin")).trim(), channel: String(data.get("channel")).trim(), acceptanceCriteria: String(data.get("acceptanceCriteria")).trim(), references: String(data.get("references") || "").trim(), due: String(data.get("due") || ""), stage: "briefing", demo: false, briefingRevisions: [], planReviewRequired: false,
    versions: [{ id: "", number: 1, fileName: "", fileKey: null, createdBy: "", createdAt: now, sharedAt: null, decision: null }], tasks: [], comments: [], history: [{ type: "created", details: { origin: String(data.get("origin")).trim(), channel: String(data.get("channel")).trim(), acceptanceCriteria: String(data.get("acceptanceCriteria")).trim() }, at: now }], delivery: null,
  };
  request.versions[0].id = `${request.id}-v1`;
  state.requests.unshift(request);
  const saved = saveState();
  renderBoard();
  event.currentTarget.reset();
  dialog.close();
  showToast(saved ? "Demanda salva no navegador como briefing para revisão." : "Demanda criada nesta sessão; o navegador não confirmou o salvamento.");
});

document.querySelector("#sendComment").addEventListener("click", () => {
  const field = document.querySelector("#commentDraft");
  const saved = updateRequest("add_comment", { comment: field.value, audience: "internal" }, "Comentário interno registrado.");
  if (saved) field.value = "";
});

document.querySelector("#taskForm").addEventListener("submit", event => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const saved = updateRequest("add_task", { title: data.get("taskTitle"), assignee: data.get("taskAssignee"), estimateHours: data.get("taskEstimateHours"), due: data.get("taskDue") }, "Tarefa atribuída ao plano.");
  if (saved) event.currentTarget.reset();
});

fileInput.addEventListener("change", async () => {
  const file = fileInput.files?.[0];
  const request = currentRequest();
  if (!file || !request || !selectedFileAction) return;
  if (!(file.type.startsWith("image/") || file.type.startsWith("video/") || file.type === "application/pdf")) { showToast("Formato não permitido. Use imagem, vídeo ou PDF."); return; }
  if (file.size > MAX_FILE_BYTES) { showToast("O limite local desta demonstração é 15 MB por arquivo."); return; }
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

document.querySelector("#searchInput").addEventListener("input", event => applySearch(event.target.value));
document.querySelector("#assigneeFilter").addEventListener("change", renderBoard);

document.querySelectorAll(".view-tab").forEach(tab => tab.addEventListener("click", () => {
  document.querySelectorAll(".view-tab").forEach(item => { item.classList.remove("active"); item.setAttribute("aria-selected", "false"); });
  tab.classList.add("active");
  tab.setAttribute("aria-selected", "true");
  const listMode = tab.textContent.includes("Lista");
  document.querySelector(".kanban-board").classList.toggle("list-view", listMode);
  document.querySelector("#listViewHeader").hidden = !listMode;
}));

renderBoard();
