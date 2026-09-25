const test = require("node:test");
const assert = require("node:assert/strict");
const onboarding = require("../prototipo/onboarding.js");

const template = {
  id: "trilha-1",
  type: "onboarding",
  title: "Primeira semana",
  archived: false,
  steps: ["Conhecer as ferramentas", "Ler o guia da equipe"],
};

test("atribui uma trilha ativa e guarda uma cópia independente dos passos", () => {
  const assignments = onboarding.createAssignment([], template, "Pessoa fictícia", { id: "atribuicao-1", now: "2026-09-25T12:00:00.000Z" });
  assert.equal(assignments.length, 1);
  assert.equal(assignments[0].assignee, "Pessoa fictícia");
  assert.equal(assignments[0].templateTitle, "Primeira semana");
  assert.deepEqual(assignments[0].steps.map(step => [step.text, step.completed]), [
    ["Conhecer as ferramentas", false],
    ["Ler o guia da equipe", false],
  ]);
  assert.equal(assignments[0].history[0].type, "assigned");
  template.steps[0] = "Modelo atualizado";
  assert.equal(assignments[0].steps[0].text, "Conhecer as ferramentas");
});

test("não atribui trilha vazia, arquivada, de outro tipo ou sem nome", () => {
  assert.throws(() => onboarding.createAssignment([], { ...template, steps: [] }, "Pessoa"), /ativa/);
  assert.throws(() => onboarding.createAssignment([], { ...template, archived: true }, "Pessoa"), /ativa/);
  assert.throws(() => onboarding.createAssignment([], { ...template, type: "training" }, "Pessoa"), /ativa/);
  assert.throws(() => onboarding.createAssignment([], template, "  "), /nome demonstrativo/);
});

test("progresso registra conclusão geral após o último passo e permite reabrir preservando histórico", () => {
  const assigned = onboarding.createAssignment([], template, "Pessoa", { id: "atribuicao-2", now: "2026-09-25T12:00:00.000Z" });
  const first = onboarding.setStepCompleted(assigned, "atribuicao-2", "atribuicao-2-step-1", true, "2026-09-25T13:00:00.000Z");
  assert.equal(first[0].completedAt, null);
  const complete = onboarding.setStepCompleted(first, "atribuicao-2", "atribuicao-2-step-2", true, "2026-09-25T14:00:00.000Z");
  assert.equal(complete[0].completedAt, "2026-09-25T14:00:00.000Z");
  assert.equal(complete[0].history.filter(event => event.type === "step_completed").length, 2);
  const reopened = onboarding.setStepCompleted(complete, "atribuicao-2", "atribuicao-2-step-1", false, "2026-09-25T15:00:00.000Z");
  assert.equal(reopened[0].completedAt, null);
  assert.equal(reopened[0].steps[0].completed, false);
  assert.equal(reopened[0].history.at(-1).type, "step_reopened");
});

test("rejeita atribuição e passo que não existem", () => {
  assert.throws(() => onboarding.createAssignment([{ id: "repetido" }], template, "Pessoa", { id: "repetido" }), /já existe/);
  assert.throws(() => onboarding.setStepCompleted([], "ausente", "passo", true), /não foi encontrada/);
  const assignments = onboarding.createAssignment([], template, "Pessoa", { id: "atribuicao-3" });
  assert.throws(() => onboarding.setStepCompleted(assignments, "atribuicao-3", "ausente", true), /passo não foi encontrado/);
});
