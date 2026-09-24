const test = require("node:test");
const assert = require("node:assert/strict");
const { transition } = require("../prototipo/workflow.js");

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

test("fluxo feliz só conclui após aprovação e evidência de entrega", () => {
  let item = demand();
  item = planRoundOne(item);
  item = completeCurrentTasks(item);
  item = transition(item, "submit_internal_review");
  item = transition(item, "internal_approved");
  assert.equal(item.stage, "clientReview");
  item = transition(item, "client_approved", { versionId: "d-1-v1" });
  assert.equal(item.stage, "delivery");
  assert.throws(() => transition(item, "record_delivery", { evidence: " " }), /evidência/);
  item = transition(item, "record_delivery", { evidence: "URL de publicação" });
  assert.equal(item.stage, "completed");
  assert.equal(item.delivery.evidence, "URL de publicação");
  assert.equal(item.history.length, 8);
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
  assert.deepEqual(item.history[0].details, { versionId: "d-1-v1", anchor: { type: "video", timeSeconds: 12.75 } });
  assert.throws(() => transition(demand(), "add_comment", { comment: "Ponto inválido", anchor: { type: "image", x: 1.1, y: 0.5 } }), /fora dos limites/);
  assert.throws(() => transition(demand(), "add_comment", { comment: "Tempo inválido", anchor: { type: "video", timeSeconds: -1 } }), /instante.*inválido/);
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
