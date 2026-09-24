const test = require("node:test");
const assert = require("node:assert/strict");
const { transition } = require("../prototipo/workflow.js");

function demand() {
  return {
    id: "d-1",
    stage: "briefing",
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

test("pedido de alteração é comentário e decisão imutáveis da versão enviada", () => {
  let item = demand();
  item = planRoundOne(item);
  item = completeCurrentTasks(item);
  item = transition(item, "submit_internal_review");
  item = transition(item, "internal_approved");
  item = transition(item, "client_changes", { versionId: "d-1-v1", comment: "Ajustar a chamada" });
  assert.equal(item.stage, "adjustments");
  assert.equal(item.versions[0].decision.result, "changes_requested");
  assert.equal(item.comments[0].versionId, "d-1-v1");
  item = transition(item, "add_task", { title: "Ajustar chamada", assignee: "Designer" });
  item = completeCurrentTasks(item);
  item = transition(item, "new_version", { fileName: "arte-v2.png", fileKey: "asset-v2" });
  assert.equal(item.stage, "internalReview");
  assert.equal(item.versions[1].number, 2);
  assert.equal(item.versions[0].decision.result, "changes_requested");
  assert.deepEqual(item.tasks.map(task => [task.round, task.status]), [[1, "completed"], [2, "completed"]]);
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
