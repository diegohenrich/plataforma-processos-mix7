(function (root) {
  const knowledgeTypes = Object.freeze([
    Object.freeze({ id: "reference", label: "Referência" }),
    Object.freeze({ id: "training", label: "Treinamento" }),
    Object.freeze({ id: "contact", label: "Contato" }),
    Object.freeze({ id: "onboarding", label: "Onboarding" }),
  ]);
  const allowedTypes = new Set(knowledgeTypes.map(item => item.id));

  function normalizeSteps(value) {
    const source = Array.isArray(value) ? value : String(value || "").split(/\r?\n/u);
    return source.map(step => String(step || "").trim()).filter(Boolean);
  }

  function saveKnowledgeItem(items, payload, options = {}) {
    const type = String(payload.type || "");
    const title = String(payload.title || "").trim();
    const summary = String(payload.summary || "").trim();
    const owner = String(payload.owner || "").trim();
    const audience = String(payload.audience || "").trim();
    const reviewDate = String(payload.reviewDate || "").trim();
    const link = String(payload.link || "").trim();
    const steps = normalizeSteps(payload.steps);

    if (!allowedTypes.has(type)) throw new Error("Escolha um tipo de conteúdo.");
    if (!title) throw new Error("Informe o nome do item.");
    if (!summary) throw new Error(type === "contact" ? "Informe como encontrar este contato." : "Informe um resumo ou instrução.");
    if (!owner) throw new Error("Informe quem cuida deste conteúdo.");
    if (!audience) throw new Error("Informe para quem este conteúdo serve.");
    const parsedReviewDate = new Date(`${reviewDate}T12:00:00`);
    if (!/^\d{4}-\d{2}-\d{2}$/u.test(reviewDate) || Number.isNaN(parsedReviewDate.getTime()) || parsedReviewDate.toISOString().slice(0, 10) !== reviewDate) {
      throw new Error("Informe uma data válida para revisar este conteúdo.");
    }
    if (link) {
      let parsed;
      try { parsed = new URL(link); } catch { throw new Error("O link deve começar com https:// ou http://."); }
      if (!["https:", "http:"].includes(parsed.protocol)) throw new Error("O link deve começar com https:// ou http://.");
    }
    if (type === "onboarding" && !steps.length) throw new Error("Adicione pelo menos um passo ao onboarding.");
    if (steps.some(step => step.length > 240)) throw new Error("Cada passo deve ter até 240 caracteres.");

    const existing = Array.isArray(items) ? items : [];
    const existingItem = existing.find(item => item.id === (options.id || payload.id));
    const id = existingItem?.id || options.id || payload.id || `knowledge-${Date.now()}`;
    const now = options.now || new Date().toISOString();
    const record = {
      id,
      type,
      title,
      summary,
      owner,
      audience,
      reviewDate,
      link,
      steps,
      archived: existingItem?.archived || false,
      createdAt: existingItem?.createdAt || now,
      updatedAt: now,
    };
    if (existingItem) return existing.map(item => item.id === id ? record : item);
    return [...existing, record];
  }

  function setKnowledgeItemArchived(items, id, archived, now = new Date().toISOString()) {
    let found = false;
    const next = (items || []).map(item => {
      if (item.id !== id) return item;
      found = true;
      return { ...item, archived: Boolean(archived), updatedAt: now };
    });
    if (!found) throw new Error("O conteúdo não foi encontrado.");
    return next;
  }

  function filterKnowledgeItems(items, { query = "", type = "", includeArchived = false } = {}) {
    const normalizedQuery = String(query).trim().toLocaleLowerCase("pt-BR");
    return (items || [])
      .filter(item => includeArchived || !item.archived)
      .filter(item => !type || item.type === type)
      .filter(item => !normalizedQuery || [item.title, item.summary, item.owner, item.audience, ...(item.steps || [])]
        .some(value => String(value || "").toLocaleLowerCase("pt-BR").includes(normalizedQuery)))
      .sort((a, b) => a.title.localeCompare(b.title, "pt-BR"));
  }

  const api = { knowledgeTypes, normalizeSteps, saveKnowledgeItem, setKnowledgeItemArchived, filterKnowledgeItems };
  if (typeof module !== "undefined" && module.exports) module.exports = api;
  root.Mix7Knowledge = api;
})(globalThis);
