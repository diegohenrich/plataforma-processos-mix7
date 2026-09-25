const test = require("node:test");
const assert = require("node:assert/strict");
const { participantTypes, transition, listAssignees, filterRequestsByAssignee, taskBlockers, findActiveTaskTimer, stopAllActiveTaskTimers, getRunnableTasks, validateLocalFiles, mergeLocalFiles, csvCell, serializeRequestsJson, serializeRequestsCsv } = require("../prototipo/workflow.js");

test("catálogo de participantes cobre os quatro tipos dos áudios sem inventar permissões", () => {
  assert.deepEqual(participantTypes.map(({ id }) => id), [
    "mix7_responsible",
    "manager",
    "team_professional",
    "client_approver",
  ]);
  for (const participant of participantTypes) {
    assert.ok(participant.name);
    assert.match(participant.source, /^Áudio [23] · /);
    assert.ok(participant.confirmedCapability);
    assert.ok(participant.stillToDefine);
    assert.equal(Object.hasOwn(participant, "permissions"), false);
  }
});

test("CSV export neutralizes spreadsheet formula prefixes and preserves CSV quoting", () => {
  for (const value of ["=1+1", "+1+1", "-1+1", "@SUM(1,1)", "＝1+1", "\t=1+1", "\r=1+1", "\n=1+1", "  =1+1"]) {
    assert.equal(csvCell(value), `"\t${value.replaceAll('"', '""')}"`);
  }
  assert.equal(csvCell('texto, com "aspas"'), '"texto, com ""aspas"""');
  assert.equal(csvCell("campanha normal"), '"campanha normal"');
});

test("JSON export preserves the version, timestamp, scope and full request data", () => {
  const request = { id: "d-1", title: "Peça de campanha", history: [{ type: "created" }] };
  const exported = JSON.parse(serializeRequestsJson([request], 3, "2026-09-25T12:00:00.000Z"));
  assert.deepEqual(exported, {
    schemaVersion: 3,
    exportedAt: "2026-09-25T12:00:00.000Z",
    scope: "Dados de texto; arquivos de mídia não incluídos.",
    requests: [request],
  });
});

test("CSV export includes readable task fields, UTF-8 BOM and formula protection", () => {
  const csv = serializeRequestsCsv([{
    title: "=SOMA(A1:A2)",
    client: "Cliente, Ltda",
    stage: "clientReview",
    due: "2026-10-02",
    tasks: [{ assignee: "Ana" }],
    versions: [{ decision: { result: "approved" } }],
  }]);
  assert.equal(csv.charCodeAt(0), 0xFEFF);
  assert.equal(csv.slice(1).split("\r\n")[0], '"Demanda","Cliente","Etapa","Prazo","Responsáveis","Tarefas","Decisão atual"');
  assert.equal(csv.slice(1).split("\r\n")[1], '"\t=SOMA(A1:A2)","Cliente, Ltda","Aguardando cliente","2026-10-02","Ana","1","approved"');
});

function demand() {
  return {
    id: "d-1",
    stage: "briefing",
    brief: "Criar uma peça de lançamento.",
    origin: "Cliente",
    channel: "Instagram · carrossel",
    acceptanceCriteria: "Mensagem legível e formato aprovado.",
    references: "",
    due: "",
    briefingRevisions: [],
    planReviewRequired: false,
    versions: [{ id: "d-1-v1", number: 1, fileName: "arte-v1.png", fileKey: "asset-v1", sharedAt: null, decision: null }],
    tasks: [],
    comments: [],
    history: [],
  };
}

function planRoundOne(item) {
  item = transition(item, "briefing_ready");
  item = transition(item, "add_task", { title: "Criar peça", assignee: "Designer" });
  return transition(item, "plan_confirmed");
}

function completeCurrentTasks(item) {
  for (const task of item.tasks.filter(task => task.round === item.versions.at(-1).number + (item.stage === "adjustments" ? 1 : 0))) {
    item = transition(item, "toggle_task", { taskId: task.id });
  }
  return item;
}

test("arquivos locais de briefing aceitam mídia/PDF e rejeitam tipo ou tamanho fora do limite", () => {
  const maxBytes = 15 * 1024 * 1024;
  assert.equal(validateLocalFiles([], maxBytes), "");
  assert.equal(validateLocalFiles([
    { type: "image/png", size: 120 },
    { type: "video/mp4", size: 240 },
    { type: "application/pdf", size: maxBytes },
  ], maxBytes), "");
  assert.match(validateLocalFiles([{ type: "text/plain", size: 10 }], maxBytes), /Formato não permitido/);
  assert.match(validateLocalFiles([{ type: "application/pdf", size: maxBytes + 1 }], maxBytes), /15 MB por arquivo/);
});

test("arquivos arrastados se juntam à seleção e recusas preservam os arquivos válidos", () => {
  const maxBytes = 15 * 1024 * 1024;
  const selected = [{ name: "referencia.png", type: "image/png", size: 100 }];
  const incoming = [
    { name: "roteiro.mp4", type: "video/mp4", size: 200 },
    { name: "briefing.pdf", type: "application/pdf", size: maxBytes },
  ];

  const accepted = mergeLocalFiles(selected, incoming, maxBytes);
  assert.equal(accepted.error, "");
  assert.deepEqual(accepted.files.map(file => file.name), ["referencia.png", "roteiro.mp4", "briefing.pdf"]);

  const rejectedType = mergeLocalFiles(accepted.files, [{ name: "notas.txt", type: "text/plain", size: 10 }], maxBytes);
  assert.match(rejectedType.error, /Formato não permitido/);
  assert.deepEqual(rejectedType.files, accepted.files);

  const rejectedSize = mergeLocalFiles(selected, [{ name: "grande.pdf", type: "application/pdf", size: maxBytes + 1 }], maxBytes);
  assert.match(rejectedSize.error, /15 MB por arquivo/);
  assert.deepEqual(rejectedSize.files, selected);
});

test("cronômetro de tarefa registra sessões, soma o total e permite retomar", () => {
  let item = planRoundOne(demand());
  const taskId = item.tasks[0].id;
  item = transition(item, "start_task_timer", { taskId }, "2026-09-25T10:00:00.000Z");
  assert.equal(item.tasks[0].timerStartedAt, "2026-09-25T10:00:00.000Z");
  assert.equal(item.history.at(-1).type, "start_task_timer");

  item = transition(item, "stop_task_timer", { taskId }, "2026-09-25T10:01:01.900Z");
  assert.equal(item.tasks[0].timerStartedAt, null);
  assert.deepEqual(item.tasks[0].timeEntries, [{ startedAt: "2026-09-25T10:00:00.000Z", stoppedAt: "2026-09-25T10:01:01.900Z", durationSeconds: 61 }]);
  assert.equal(item.history.at(-1).details.totalSeconds, 61);

  item = transition(item, "start_task_timer", { taskId }, "2026-09-25T10:02:00.000Z");
  item = transition(item, "stop_task_timer", { taskId }, "2026-09-25T10:02:10.000Z");
  assert.equal(item.tasks[0].timeEntries.length, 2);
  assert.equal(item.history.at(-1).details.totalSeconds, 71);
});

test("cronômetro não inicia em tarefa inválida, bloqueada ou concluída, nem permite sobreposição", () => {
  let item = transition(demand(), "briefing_ready");
  item = transition(item, "add_task", { title: "Criar peça", assignee: "Designer" });
  item = transition(item, "add_task", { title: "Revisar peça", assignee: "Gestor" });
  item = transition(item, "plan_confirmed");
  const [first, second] = item.tasks;
  assert.throws(() => transition(item, "start_task_timer", { taskId: "missing" }), /Tarefa não encontrada/);
  item = transition(item, "set_task_blocker", { taskId: second.id, reason: "Aguardando material" });
  assert.throws(() => transition(item, "start_task_timer", { taskId: second.id }), /Resolva os impedimentos/);
  item = transition(item, "set_task_blocker", { taskId: second.id, reason: "" });
  item = transition(item, "start_task_timer", { taskId: first.id }, "2026-09-25T10:00:00.000Z");
  assert.throws(() => transition(item, "start_task_timer", { taskId: second.id }), /Já existe um cronômetro ativo/);
  assert.throws(() => transition(item, "toggle_task", { taskId: first.id }), /Pare o cronômetro/);
  assert.throws(() => transition(item, "set_task_blocker", { taskId: first.id, reason: "Impedido" }), /Pare o cronômetro/);
  item = transition(item, "stop_task_timer", { taskId: first.id }, "2026-09-25T10:00:01.000Z");
  item = transition(item, "toggle_task", { taskId: first.id });
  assert.throws(() => transition(item, "start_task_timer", { taskId: first.id }), /tarefa concluída/);
});

test("cronômetro rejeita horário final anterior ao início", () => {
  let item = planRoundOne(demand());
  const taskId = item.tasks[0].id;
  item = transition(item, "start_task_timer", { taskId }, "2026-09-25T10:00:00.000Z");
  assert.throws(() => transition(item, "stop_task_timer", { taskId }, "2026-09-24T10:00:00.000Z"), /horário do cronômetro é inválido/);
  assert.equal(item.tasks[0].timerStartedAt, "2026-09-25T10:00:00.000Z");
});

test("fechar o app encerra e registra as sessões ativas sem mantê-las correndo offline", () => {
  let item = planRoundOne(demand());
  const taskId = item.tasks[0].id;
  item = transition(item, "start_task_timer", { taskId }, "2026-09-25T10:00:00.000Z");
  const stopped = stopAllActiveTaskTimers([item], "2026-09-25T10:00:47.900Z")[0];
  assert.equal(stopped.tasks[0].timerStartedAt, null);
  assert.deepEqual(stopped.tasks[0].timeEntries, [{ startedAt: "2026-09-25T10:00:00.000Z", stoppedAt: "2026-09-25T10:00:47.900Z", durationSeconds: 47 }]);
  assert.equal(stopped.history.at(-1).type, "stop_task_timer");
  assert.equal(findActiveTaskTimer([stopped]), null);
  assert.equal(stopAllActiveTaskTimers([stopped], "2026-09-25T11:00:00.000Z")[0].history.length, stopped.history.length);
});

test("procura timer ativo em qualquer demanda e libera a próxima tarefa ao parar", () => {
  const first = planRoundOne(demand());
  let second = planRoundOne({ ...demand(), id: "d-2" });
  second = transition(second, "start_task_timer", { taskId: second.tasks[0].id }, "2026-09-25T10:00:00.000Z");
  assert.equal(findActiveTaskTimer([first, second]).request.id, "d-2");
  second = transition(second, "stop_task_timer", { taskId: second.tasks[0].id }, "2026-09-25T10:00:01.000Z");
  assert.equal(findActiveTaskTimer([first, second]), null);
});

test("filtro por profissional considera tarefas da rodada vigente e opções distintas", () => {
  const first = demand();
  first.stage = "doing";
  first.tasks = [
    { assignee: " Diego ", round: 1, status: "pending" },
    { assignee: "DIEGO", round: 1, status: "completed" },
    { assignee: "Ana", round: 1, status: "pending" },
  ];
  const second = { ...demand(), id: "d-2", stage: "adjustments", versions: [{ id: "d-2-v1", number: 1 }], tasks: [
    { assignee: "Diego", round: 1, status: "completed" },
    { assignee: "Diego", round: 2, status: "pending" },
  ] };
  const third = { ...demand(), id: "d-3", stage: "adjustments", versions: [{ id: "d-3-v1", number: 1 }], tasks: [
    { assignee: "Diego", round: 1, status: "completed" },
  ] };
  const fourth = { ...demand(), id: "d-4", stage: "doing", tasks: [
    { assignee: "Diego", round: 1, status: "completed" },
  ] };
  const requests = [first, second, third, fourth];

  assert.deepEqual(listAssignees(requests), ["Ana", "Diego"]);
  assert.deepEqual(filterRequestsByAssignee(requests, "dIeGo").map(item => item.id), ["d-1", "d-2"]);
  assert.deepEqual(filterRequestsByAssignee(requests, "Ana").map(item => item.id), ["d-1"]);
  assert.deepEqual(filterRequestsByAssignee(requests, ""), requests);
});

test("dependências só liberam a tarefa após conclusão e preservam a ordem ao reabrir", () => {
  let item = transition(demand(), "briefing_ready");
  item = transition(item, "add_task", { title: "Definir conceito", assignee: "Gestor" });
  const prerequisite = item.tasks[0];
  item = transition(item, "add_task", { title: "Criar peça", assignee: "Designer", dependencyTaskIds: [prerequisite.id] });
  const dependent = item.tasks[1];
  assert.throws(() => transition(item, "add_task", { title: "Outra rodada", assignee: "Designer", dependencyTaskIds: ["fora-da-rodada"] }), /mesma rodada/);
  item = transition(item, "plan_confirmed");
  assert.deepEqual(taskBlockers(item, dependent), ["Aguardando: Definir conceito"]);
  assert.throws(() => transition(item, "toggle_task", { taskId: dependent.id }), /tarefas prévias/);
  item = transition(item, "toggle_task", { taskId: prerequisite.id });
  assert.deepEqual(taskBlockers(item, dependent), []);
  item = transition(item, "toggle_task", { taskId: dependent.id });
  assert.throws(() => transition(item, "toggle_task", { taskId: prerequisite.id }), /tarefas dependentes/);
  item = transition(item, "toggle_task", { taskId: dependent.id });
  item = transition(item, "toggle_task", { taskId: prerequisite.id });
  assert.deepEqual(item.tasks.map(task => task.status), ["pending", "pending"]);
});

test("impedimento exige motivo, bloqueia conclusão, pode ser atualizado e removido com histórico", () => {
  let item = planRoundOne(demand());
  const task = item.tasks[0];
  assert.throws(() => transition(item, "set_task_blocker", { taskId: "missing", reason: "Aguardando arquivo" }), /não encontrada/);
  item = transition(item, "set_task_blocker", { taskId: task.id, reason: "Aguardando material do cliente" }, "2026-09-24T17:00:00.000Z");
  assert.equal(item.tasks[0].blockedReason, "Aguardando material do cliente");
  assert.deepEqual(taskBlockers(item, item.tasks[0]), ["Aguardando material do cliente"]);
  assert.deepEqual(item.history.at(-1).details, { taskId: task.id, taskTitle: task.title, previousReason: "", reason: "Aguardando material do cliente", blocked: true });
  assert.throws(() => transition(item, "toggle_task", { taskId: task.id }), /impedimento/);
  assert.throws(() => transition(item, "set_task_blocker", { taskId: task.id, reason: "x".repeat(501) }), /500 caracteres/);
  item = transition(item, "set_task_blocker", { taskId: task.id, reason: "" });
  assert.equal(item.tasks[0].blockedReason, "");
  assert.deepEqual(taskBlockers(item, item.tasks[0]), []);
  assert.deepEqual(item.history.at(-1).details, { taskId: task.id, taskTitle: task.title, previousReason: "Aguardando material do cliente", reason: "", blocked: false });
  item = transition(item, "toggle_task", { taskId: task.id });
  assert.throws(() => transition(item, "set_task_blocker", { taskId: task.id, reason: "Bloqueada novamente" }), /Reabra/);
});

test("fluxo feliz só conclui após aprovação e evidência de entrega", () => {
  let item = demand();
  item = planRoundOne(item);
  item = completeCurrentTasks(item);
  item = transition(item, "attach_file", { fileKey: "asset-v1a", fileName: "arte-original.png" });
  item = transition(item, "attach_file", { fileKey: "asset-v1b", fileName: "arte-final.png" });
  assert.deepEqual(item.history.at(-1).details, { versionId: "d-1-v1", versionNumber: 1, previousFileName: "arte-original.png", previousFileKey: "asset-v1a", fileName: "arte-final.png" });
  item = transition(item, "submit_internal_review");
  assert.deepEqual(item.history.at(-1).details, { versionId: "d-1-v1", versionNumber: 1, fileName: "arte-final.png" });
  item = transition(item, "internal_approved");
  assert.deepEqual(item.history.at(-1).details, { versionId: "d-1-v1", versionNumber: 1, result: "approved" });
  assert.equal(item.stage, "clientReview");
  item = transition(item, "client_approved", { versionId: "d-1-v1" });
  assert.equal(item.stage, "delivery");
  assert.throws(() => transition(item, "record_delivery", { evidence: "URL de publicação" }), /Selecione se o material/);
  assert.throws(() => transition(item, "record_delivery", { destinationType: "published", evidence: " " }), /evidência/);
  item = transition(item, "record_delivery", { destinationType: "published", evidence: "URL de publicação" });
  assert.equal(item.stage, "completed");
  assert.equal(item.delivery.destinationType, "published");
  assert.equal(item.delivery.evidence, "URL de publicação");
  assert.deepEqual(item.history.at(-1).details, { evidence: "URL de publicação", versionId: "d-1-v1", versionNumber: 1, destinationType: "published" });
  assert.equal(item.history.length, 10);
});

test("conclusão distingue entrega, agendamento e publicação, sempre com evidência", () => {
  for (const destinationType of ["delivered", "scheduled", "published"]) {
    let item = demand();
    item = planRoundOne(item);
    item = completeCurrentTasks(item);
    item = transition(item, "submit_internal_review");
    item = transition(item, "internal_approved");
    item = transition(item, "client_approved", { versionId: item.versions[0].id });
    item = transition(item, "record_delivery", { destinationType, evidence: `Registro ${destinationType}` });
    assert.equal(item.delivery.destinationType, destinationType);
    assert.throws(() => transition({ ...item, stage: "delivery" }, "record_delivery", { destinationType }), /evidência/);
  }
});

test("briefing sem origem, canal ou critérios não avança e as correções ficam no histórico", () => {
  let item = demand();
  item.origin = "";
  assert.throws(() => transition(item, "briefing_ready"), /origem.*canal.*critérios/);
  item = transition(item, "briefing_details_updated", {
    origin: "Planejamento de conteúdo",
    channel: "Instagram · carrossel",
    acceptanceCriteria: "Texto revisado e identidade visual aprovada.",
    references: "https://example.invalid/referencia",
    due: "2026-10-02",
  }, "2026-09-24T15:00:00.000Z");
  assert.equal(item.stage, "briefing");
  assert.equal(item.origin, "Planejamento de conteúdo");
  assert.equal(item.history.at(-1).type, "briefing_details_updated");
  assert.equal(item.history.at(-1).details.previous.origin, "");
  assert.equal(item.history.at(-1).details.updated.acceptanceCriteria, "Texto revisado e identidade visual aprovada.");
  item = transition(item, "briefing_ready");
  assert.equal(item.stage, "planning");
  assert.equal(item.history.at(-1).details.channel, "Instagram · carrossel");
});

test("pedido de alteração é comentário ancorado e decisão imutáveis da versão enviada", () => {
  let item = demand();
  item = planRoundOne(item);
  item = completeCurrentTasks(item);
  item = transition(item, "submit_internal_review");
  item = transition(item, "internal_approved");
  item = transition(item, "client_changes", { versionId: "d-1-v1", comment: "Ajustar a chamada", anchor: { type: "image", x: 0.6, y: 0.25 } });
  assert.equal(item.stage, "adjustments");
  assert.equal(item.versions[0].decision.result, "changes_requested");
  assert.equal(item.comments[0].versionId, "d-1-v1");
  assert.deepEqual(item.comments[0].anchor, { type: "image", x: 0.6, y: 0.25 });
  assert.deepEqual(item.history.at(-1).details, {
    versionId: "d-1-v1",
    versionNumber: 1,
    commentId: "d-1-comment-1",
    commentText: "Ajustar a chamada",
    author: "Aprovador do cliente",
    result: "changes_requested",
    anchor: { type: "image", x: 0.6, y: 0.25 },
  });
  item = transition(item, "add_task", { title: "Ajustar chamada", assignee: "Designer" });
  item = completeCurrentTasks(item);
  item = transition(item, "new_version", { fileName: "arte-v2.png", fileKey: "asset-v2" });
  assert.equal(item.stage, "internalReview");
  assert.equal(item.versions[1].number, 2);
  assert.equal(item.versions[0].decision.result, "changes_requested");
  assert.deepEqual(item.tasks.map(task => [task.round, task.status]), [[1, "completed"], [2, "completed"]]);
});

test("bandeja minimizada só libera tarefas executáveis da rodada e identifica o cronômetro ativo", () => {
  let item = transition(demand(), "briefing_ready");
  item = transition(item, "add_task", { title: "Montar site", assignee: "Profissional" });
  item = transition(item, "add_task", { title: "Revisar conteúdo", assignee: "Gestor", dependencyTaskId: item.tasks[0].id });
  item = transition(item, "plan_confirmed");
  assert.deepEqual(getRunnableTasks(item).map(task => task.title), ["Montar site"]);
  item = transition(item, "start_task_timer", { taskId: item.tasks[0].id }, "2026-09-25T10:00:00.000Z");
  const active = findActiveTaskTimer([item]);
  assert.deepEqual(getRunnableTasks(item, active).map(task => task.title), ["Montar site"]);
  assert.deepEqual(getRunnableTasks(item), []);
  item = transition(item, "stop_task_timer", { taskId: item.tasks[0].id }, "2026-09-25T10:01:00.000Z");
  item = transition(item, "toggle_task", { taskId: item.tasks[0].id });
  assert.deepEqual(getRunnableTasks(item).map(task => task.title), ["Revisar conteúdo"]);
  item.stage = "clientReview";
  assert.deepEqual(getRunnableTasks(item), []);
});

test("bandeja mantém tarefas visíveis porém bloqueadas enquanto outra demanda está cronometrando", () => {
  let first = planRoundOne(demand());
  let second = planRoundOne({ ...demand(), id: "d-2" });
  second = transition(second, "start_task_timer", { taskId: second.tasks[0].id }, "2026-09-25T10:00:00.000Z");
  const active = findActiveTaskTimer([first, second]);
  assert.equal(getRunnableTasks(first, active).length, 1);
  assert.deepEqual(getRunnableTasks(second, active).map(task => task.id), [second.tasks[0].id]);
  second = transition(second, "stop_task_timer", { taskId: second.tasks[0].id }, "2026-09-25T10:00:03.000Z");
  assert.equal(getRunnableTasks(first, findActiveTaskTimer([first, second])).length, 1);
});

test("pedido de ajustes vazio ou só com espaços não altera a decisão do cliente", () => {
  let item = planRoundOne(demand());
  item = completeCurrentTasks(item);
  item = transition(item, "submit_internal_review");
  item = transition(item, "internal_approved");

  for (const comment of ["", "   "]) {
    assert.throws(() => transition(item, "client_changes", { versionId: "d-1-v1", comment }), /Descreva as alterações solicitadas/);
    assert.equal(item.stage, "clientReview");
    assert.equal(item.versions[0].decision, null);
    assert.deepEqual(item.comments, []);
  }
});

test("comentário de vídeo preserva o instante e a versão e valida a âncora", () => {
  const item = transition(demand(), "add_comment", {
    comment: "Rever esta fala",
    anchor: { type: "video", timeSeconds: 12.75 },
  }, "2026-09-24T15:00:00.000Z");
  assert.equal(item.comments[0].versionId, "d-1-v1");
  assert.deepEqual(item.comments[0].anchor, { type: "video", timeSeconds: 12.75 });
  assert.deepEqual(item.history[0].details, {
    versionId: "d-1-v1",
    versionNumber: 1,
    commentId: "d-1-comment-1",
    commentText: "Rever esta fala",
    author: "Equipe",
    audience: "internal",
    anchor: { type: "video", timeSeconds: 12.75 },
  });
  assert.throws(() => transition(demand(), "add_comment", { comment: "Ponto inválido", anchor: { type: "image", x: 1.1, y: 0.5 } }), /fora dos limites/);
  assert.throws(() => transition(demand(), "add_comment", { comment: "Tempo inválido", anchor: { type: "video", timeSeconds: -1 } }), /instante.*inválido/);
});

test("tarefa de ajuste mantém vínculo verificável com feedback do cliente", () => {
  const item = demand();
  item.stage = "adjustments";
  const withClientComment = transition(item, "add_comment", {
    comment: "Aumentar a chamada na arte.",
    audience: "client",
    author: "Cliente Mix7",
  }, "2026-09-24T15:00:00.000Z");
  const commentId = withClientComment.comments[0].id;
  const updated = transition(withClientComment, "add_task", {
    title: "Aumentar chamada",
    assignee: "Designer",
    sourceCommentId: commentId,
  });

  assert.equal(updated.tasks[0].sourceCommentId, commentId);
  assert.equal(updated.tasks[0].round, 2);
  assert.equal(updated.history.at(-1).details.sourceCommentId, commentId);
  assert.equal(updated.history.at(-1).details.sourceVersionNumber, 1);
  assert.throws(() => transition(withClientComment, "add_task", {
    title: "Tarefa com origem inválida",
    assignee: "Designer",
    sourceCommentId: "ausente",
  }), /comentário válido do cliente/);

  const internalComment = transition(demand(), "add_comment", { comment: "Nota interna" });
  internalComment.stage = "adjustments";
  assert.throws(() => transition(internalComment, "add_task", {
    title: "Tarefa baseada em nota interna",
    assignee: "Designer",
    sourceCommentId: internalComment.comments[0].id,
  }), /comentário válido do cliente/);
});

test("bloqueia aprovação de versão antiga ou ainda não enviada", () => {
  const item = demand();
  assert.throws(() => transition(item, "client_approved", { versionId: "d-1-v1" }), /versão/);
});

test("revisão interna exige motivo ao devolver e não compartilha material", () => {
  let item = demand();
  item = transition(item, "briefing_ready");
  assert.throws(() => transition(item, "plan_confirmed"), /tarefa/);
  assert.throws(() => transition(item, "add_task", { title: "Criar peça" }), /responsável/);
  item = transition(item, "add_task", { title: "Criar peça", assignee: "Designer" });
  item = transition(item, "plan_confirmed");
  assert.throws(() => transition(item, "submit_internal_review"), /Conclua as tarefas/);
  item = completeCurrentTasks(item);
  item = transition(item, "attach_file", { fileKey: "a", fileName: "arte.png" });
  item = transition(item, "submit_internal_review");
  assert.throws(() => transition(item, "internal_changes"), /motivo/);
  item = transition(item, "internal_changes", { comment: "Corrigir contraste" });
  assert.equal(item.stage, "doing");
  assert.equal(item.versions[0].sharedAt, null);
  assert.equal(item.comments[0].audience, "internal");
  assert.equal(item.history.at(-1).details.commentText, "Corrigir contraste");
  assert.equal(item.history.at(-1).details.versionNumber, 1);
});

test("cronograma aceita início e prazo e bloqueia prazo anterior ao início", () => {
  let item = transition(demand(), "briefing_ready");
  assert.throws(() => transition(item, "add_task", {
    title: "Criar peça", assignee: "Designer", plannedStart: "2026-10-10", due: "2026-10-09",
  }), /prazo não pode ser anterior/);
  assert.equal(item.tasks.length, 0);
  item = transition(item, "add_task", {
    title: "Criar peça", assignee: "Designer", plannedStart: "2026-10-10", due: "2026-10-12",
  });
  assert.equal(item.tasks[0].plannedStart, "2026-10-10");
  assert.equal(item.tasks[0].due, "2026-10-12");
  assert.equal(item.history.at(-1).details.plannedStart, "2026-10-10");
  assert.equal(item.history.at(-1).details.due, "2026-10-12");
});

test("nova versão continua a numeração informada mesmo com histórico parcial", () => {
  let item = demand();
  item.stage = "adjustments";
  item.versions[0].number = 3;
  item = transition(item, "add_task", { title: "Ajustar", assignee: "Designer" });
  assert.throws(() => transition(item, "new_version", { fileName: "arte-v4.png", fileKey: "asset-v4" }), /conclua/);
  item = completeCurrentTasks(item);
  const updated = transition(item, "new_version", { fileName: "arte-v4.png", fileKey: "asset-v4" });
  assert.equal(updated.versions.at(-1).number, 4);
  assert.equal(updated.versions.at(-1).id, "d-1-v4");
});

test("alterar briefing na execução pausa o trabalho e exige revisão humana do plano", () => {
  let item = planRoundOne(demand());
  const oldBrief = item.brief;
  item = transition(item, "briefing_revised", {
    brief: "Criar uma peça de lançamento com foco na linha infantil.",
    reason: "O cliente alterou o público da campanha.",
  }, "2026-09-24T16:00:00.000Z");

  assert.equal(item.stage, "planning");
  assert.equal(item.brief, "Criar uma peça de lançamento com foco na linha infantil.");
  assert.equal(item.planReviewRequired, true);
  assert.deepEqual(item.briefingRevisions[0], {
    previousBrief: oldBrief,
    brief: item.brief,
    reason: "O cliente alterou o público da campanha.",
    at: "2026-09-24T16:00:00.000Z",
  });
  assert.deepEqual(item.history.at(-1).details, {
    reason: "O cliente alterou o público da campanha.",
    planReviewRequired: true,
  });
  assert.throws(() => transition(item, "toggle_task", { taskId: item.tasks[0].id }), /execução/);

  item = transition(item, "plan_confirmed", {}, "2026-09-24T16:05:00.000Z");
  assert.equal(item.stage, "doing");
  assert.equal(item.planReviewRequired, false);
  assert.equal(item.planReviewedAt, "2026-09-24T16:05:00.000Z");
});

test("revisar briefing exige texto novo e motivo sem alterar a demanda em caso de erro", () => {
  const item = planRoundOne(demand());
  assert.throws(() => transition(item, "briefing_revised", { brief: "Outro briefing" }), /Explique/);
  assert.throws(() => transition(item, "briefing_revised", { brief: item.brief, reason: "Teste" }), /precisa ter uma alteração/);
  assert.equal(item.stage, "doing");
  assert.equal(item.briefingRevisions.length, 0);
});
