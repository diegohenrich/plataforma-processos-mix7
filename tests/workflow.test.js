const test = require("node:test");
const assert = require("node:assert/strict");
const { transition, listAssignees, filterRequestsByAssignee, taskBlockers } = require("../prototipo/workflow.js");

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
  assert.deepEqual(item.history.at(-1).details, { versionId: "d-1-v1", versionNumber: 1, previousFileName: "arte-original.png", fileName: "arte-final.png" });
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
