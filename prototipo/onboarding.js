(function (root) {
  function createAssignment(assignments, template, assignee, options = {}) {
    const name = String(assignee || "").trim();
    if (!template || template.type !== "onboarding" || template.archived || !Array.isArray(template.steps) || !template.steps.length) {
      throw new Error("Escolha uma trilha de onboarding ativa com pelo menos um passo.");
    }
    if (!name) throw new Error("Informe o nome demonstrativo da pessoa.");
    if (name.length > 100) throw new Error("O nome deve ter até 100 caracteres.");
    const id = options.id || `onboarding-${Date.now()}`;
    if ((assignments || []).some(item => item.id === id)) throw new Error("Esta atribuição já existe.");
    const now = options.now || new Date().toISOString();
    const steps = template.steps.map((text, index) => ({
      id: `${id}-step-${index + 1}`,
      text: String(text).trim(),
      completed: false,
      completedAt: null,
    }));
    const assignment = {
      id,
      templateId: template.id,
      templateTitle: template.title,
      assignee: name,
      assignedAt: now,
      updatedAt: now,
      completedAt: null,
      steps,
      history: [{ type: "assigned", at: now, templateId: template.id, templateTitle: template.title, stepCount: steps.length }],
    };
    return [...(assignments || []), assignment];
  }

  function setStepCompleted(assignments, assignmentId, stepId, completed, now = new Date().toISOString()) {
    let foundAssignment = false;
    let foundStep = false;
    const next = (assignments || []).map(assignment => {
      if (assignment.id !== assignmentId) return assignment;
      foundAssignment = true;
      const steps = assignment.steps.map(step => {
        if (step.id !== stepId) return step;
        foundStep = true;
        if (step.completed === Boolean(completed)) return step;
        return { ...step, completed: Boolean(completed), completedAt: completed ? now : null };
      });
      if (!foundStep) return assignment;
      const changedStep = steps.find(step => step.id === stepId);
      const allComplete = steps.length > 0 && steps.every(step => step.completed);
      const history = changedStep.completed
        ? [...assignment.history, { type: "step_completed", at: now, stepId, stepText: changedStep.text }]
        : [...assignment.history, { type: "step_reopened", at: now, stepId, stepText: changedStep.text }];
      return {
        ...assignment,
        steps,
        updatedAt: now,
        completedAt: allComplete ? (assignment.completedAt || now) : null,
        history,
      };
    });
    if (!foundAssignment) throw new Error("A trilha atribuída não foi encontrada.");
    if (!foundStep) throw new Error("O passo não foi encontrado nesta trilha.");
    return next;
  }

  const api = Object.freeze({ createAssignment, setStepCompleted });
  root.Mix7Onboarding = api;
  if (typeof module !== "undefined" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
