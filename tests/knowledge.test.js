const test = require("node:test");
const assert = require("node:assert/strict");
const { knowledgeTypes, saveKnowledgeItem, setKnowledgeItemArchived, filterKnowledgeItems } = require("../prototipo/knowledge.js");

const validRecord = (overrides = {}) => ({
  type: "reference",
  title: "Guia de marca demonstrativo",
  summary: "Exemplo fictício para validar a biblioteca local.",
  owner: "Responsável fictício",
  audience: "Equipe (público demonstrativo)",
  reviewDate: "2026-10-15",
  link: "https://example.com/guia",
  steps: [],
  ...overrides,
});

test("catálogo usa os quatro tipos confirmados no áudio", () => {
  assert.deepEqual(knowledgeTypes.map(item => item.id), ["reference", "training", "contact", "onboarding"]);
});

test("cria conteúdo com responsável, público, data de revisão e metadados", () => {
  const records = saveKnowledgeItem([], validRecord(), { id: "k-1", now: "2026-09-25T12:00:00.000Z" });
  assert.deepEqual(records[0], {
    id: "k-1",
    ...validRecord(),
    archived: false,
    createdAt: "2026-09-25T12:00:00.000Z",
    updatedAt: "2026-09-25T12:00:00.000Z",
  });
});

test("recusa campos obrigatórios ausentes, links não http e onboarding sem passos", () => {
  assert.throws(() => saveKnowledgeItem([], validRecord({ owner: " " })), /quem cuida/);
  assert.throws(() => saveKnowledgeItem([], validRecord({ audience: "" })), /para quem/);
  assert.throws(() => saveKnowledgeItem([], validRecord({ reviewDate: "2026-02-31" })), /data válida/);
  assert.throws(() => saveKnowledgeItem([], validRecord({ link: "javascript:alert(1)" })), /http/);
  assert.throws(() => saveKnowledgeItem([], validRecord({ type: "onboarding", steps: [] })), /pelo menos um passo/);
});

test("edita conteúdo sem trocar ID ou data de criação e mantém arquivo como restauração possível", () => {
  const created = saveKnowledgeItem([], validRecord(), { id: "k-1", now: "2026-09-25T12:00:00.000Z" });
  const archived = setKnowledgeItemArchived(created, "k-1", true, "2026-09-26T12:00:00.000Z");
  const updated = saveKnowledgeItem(archived, validRecord({ title: "Guia atualizado" }), { id: "k-1", now: "2026-09-27T12:00:00.000Z" });
  assert.equal(updated[0].id, "k-1");
  assert.equal(updated[0].createdAt, "2026-09-25T12:00:00.000Z");
  assert.equal(updated[0].updatedAt, "2026-09-27T12:00:00.000Z");
  assert.equal(updated[0].archived, true);
  const restored = setKnowledgeItemArchived(updated, "k-1", false, "2026-09-28T12:00:00.000Z");
  assert.equal(restored[0].archived, false);
});

test("filtra pelo texto visível, tipo e estado de arquivamento", () => {
  let records = saveKnowledgeItem([], validRecord(), { id: "k-ref" });
  records = saveKnowledgeItem(records, validRecord({ type: "onboarding", title: "Primeiros passos", summary: "Trilha interna", link: "", steps: ["Conhecer o espaço", "Abrir o quadro"] }), { id: "k-onb" });
  records = setKnowledgeItemArchived(records, "k-ref", true);
  assert.deepEqual(filterKnowledgeItems(records, {}).map(item => item.id), ["k-onb"]);
  assert.deepEqual(filterKnowledgeItems(records, { query: "quadro", type: "onboarding" }).map(item => item.id), ["k-onb"]);
  assert.deepEqual(filterKnowledgeItems(records, { query: "guia", includeArchived: true }).map(item => item.id), ["k-ref"]);
});
