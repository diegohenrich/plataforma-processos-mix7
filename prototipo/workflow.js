(function (root) {
  const stages = Object.freeze({
    briefing: "Novas demandas",
    planning: "Planejamento",
    doing: "Em produção",
    internalReview: "Revisão interna",
    clientReview: "Aguardando cliente",
    adjustments: "Ajustes",
    delivery: "Entrega e publicação",
    completed: "Concluídas",
  });

  function recordEvent(request, type, details, now) {
    request.history.push({ type, details: details || {}, at: now });
  }

  function captureAnchor(anchor) {
    if (anchor == null) return null;
    if (anchor.type === "image") {
      const x = Number(anchor.x);
      const y = Number(anchor.y);
      if (!Number.isFinite(x) || !Number.isFinite(y) || x < 0 || x > 1 || y < 0 || y > 1) {
        throw new Error("O ponto do comentário na imagem está fora dos limites.");
      }
      return { type: "image", x, y };
    }
    if (anchor.type === "video") {
      const timeSeconds = Number(anchor.timeSeconds);
      if (!Number.isFinite(timeSeconds) || timeSeconds < 0) throw new Error("O instante do comentário no vídeo é inválido.");
      return { type: "video", timeSeconds };
    }
    throw new Error("O tipo de referência do comentário é inválido.");
  }

  function currentTaskRound(request) {
    const currentVersion = Number(request.versions?.at(-1)?.number) || 1;
    return currentVersion + (request.stage === "adjustments" ? 1 : 0);
  }

  function assigneeKey(value) {
    return String(value || "").trim().toLocaleLowerCase("pt-BR");
  }

  function listAssignees(requests) {
    const assignees = new Map();
    for (const request of requests) {
      const round = currentTaskRound(request);
      for (const task of request.tasks || []) {
        const name = String(task.assignee || "").trim();
        const key = assigneeKey(name);
        const taskRound = Number(task.round) || currentVersionNumber(request);
        if (name && taskRound === round && !assignees.has(key)) assignees.set(key, name);
      }
    }
    return [...assignees.values()].sort((a, b) => a.localeCompare(b, "pt-BR"));
  }

  function currentVersionNumber(request) {
    return Number(request.versions?.at(-1)?.number) || 1;
  }

  function filterRequestsByAssignee(requests, assignee) {
    const key = assigneeKey(assignee);
    if (!key) return requests;
    return requests.filter(request => {
      const round = currentTaskRound(request);
      return (request.tasks || []).some(task => {
        const taskRound = Number(task.round) || currentVersionNumber(request);
        return taskRound === round && task.status !== "completed" && assigneeKey(task.assignee) === key;
      });
    });
  }

  function unmetTaskDependencies(request, task) {
    const tasksById = new Map((request.tasks || []).map(item => [item.id, item]));
    return (task.dependencyTaskIds || [])
      .map(id => tasksById.get(id))
      .filter(dependency => dependency && dependency.status !== "completed");
  }

  function taskBlockers(request, task) {
    const reasons = unmetTaskDependencies(request, task).map(dependency => `Aguardando: ${dependency.title}`);
    if (String(task.blockedReason || "").trim()) reasons.unshift(String(task.blockedReason).trim());
    return reasons;
  }

  function transition(request, action, payload = {}, now = new Date().toISOString()) {
    const next = structuredClone(request);
    const version = next.versions[next.versions.length - 1];

    switch (action) {
      case "briefing_ready":
        if (next.stage !== "briefing") throw new Error("A demanda não está no briefing.");
        if (!String(next.origin || "").trim() || !String(next.channel || "").trim() || !String(next.acceptanceCriteria || "").trim()) {
          throw new Error("Informe a origem, o canal ou peça e os critérios de aceite antes do planejamento.");
        }
        next.stage = "planning";
        break;
      case "briefing_details_updated": {
        if (next.stage !== "briefing") throw new Error("Os dados iniciais só podem ser editados durante o briefing.");
        const previous = {
          origin: next.origin || "",
          channel: next.channel || "",
          acceptanceCriteria: next.acceptanceCriteria || "",
          references: next.references || "",
          due: next.due || "",
        };
        next.origin = String(payload.origin || "").trim();
        next.channel = String(payload.channel || "").trim();
        next.acceptanceCriteria = String(payload.acceptanceCriteria || "").trim();
        next.references = String(payload.references || "").trim();
        next.due = String(payload.due || "").trim();
        if (!next.origin || !next.channel || !next.acceptanceCriteria) {
          throw new Error("Informe a origem, o canal ou peça e os critérios de aceite.");
        }
        recordEvent(next, action, { previous, updated: { origin: next.origin, channel: next.channel, acceptanceCriteria: next.acceptanceCriteria, references: next.references, due: next.due } }, now);
        return next;
      }
      case "plan_confirmed":
        if (next.stage !== "planning") throw new Error("A demanda não está em planejamento.");
        if (!next.tasks.some(task => task.round === version.number && task.title && task.assignee)) throw new Error("Adicione ao menos uma tarefa com responsável antes de confirmar o plano.");
        next.stage = "doing";
        if (next.planReviewRequired) {
          next.planReviewRequired = false;
          next.planReviewedAt = now;
        }
        break;
      case "briefing_revised": {
        if (next.stage !== "doing") throw new Error("O briefing só pode ser revisado durante a execução nesta demonstração.");
        const revisedBrief = String(payload.brief || "").trim();
        const reason = String(payload.reason || "").trim();
        if (!revisedBrief) throw new Error("Informe o briefing atualizado.");
        if (revisedBrief === String(next.brief || "").trim()) throw new Error("O briefing atualizado precisa ter uma alteração.");
        if (!reason) throw new Error("Explique o que mudou no briefing.");
        next.briefingRevisions ||= [];
        next.briefingRevisions.push({ previousBrief: next.brief || "", brief: revisedBrief, reason, at: now });
        next.brief = revisedBrief;
        next.planReviewRequired = true;
        next.planReviewedAt = null;
        next.stage = "planning";
        break;
      }
      case "add_task": {
        if (next.stage !== "planning" && next.stage !== "adjustments") throw new Error("Tarefas só podem ser planejadas no início ou numa rodada de ajustes.");
        const title = String(payload.title || "").trim();
        const assignee = String(payload.assignee || "").trim();
        if (!title || !assignee) throw new Error("Informe o nome da tarefa e seu responsável.");
        const round = version.number + (next.stage === "adjustments" ? 1 : 0);
        const requestedDependencies = Array.isArray(payload.dependencyTaskIds) ? payload.dependencyTaskIds : (payload.dependencyTaskId ? [payload.dependencyTaskId] : []);
        const dependencyTaskIds = [...new Set(requestedDependencies.map(String).filter(Boolean))];
        const roundTaskIds = new Set(next.tasks.filter(task => task.round === round).map(task => task.id));
        if (dependencyTaskIds.some(id => !roundTaskIds.has(id))) throw new Error("Dependências devem apontar para tarefas da mesma rodada.");
        const sourceCommentId = String(payload.sourceCommentId || "").trim();
        const sourceComment = sourceCommentId ? next.comments.find(comment => comment.id === sourceCommentId) : null;
        if (sourceCommentId && (!sourceComment || sourceComment.audience !== "client" || next.stage !== "adjustments")) {
          throw new Error("A origem da tarefa deve ser um comentário válido do cliente nesta rodada de ajustes.");
        }
        const estimateHours = Number(payload.estimateHours) > 0 ? Number(payload.estimateHours) : null;
        const task = { id: `${next.id}-task-${round}-${next.tasks.filter(task => task.round === round).length + 1}`, title, assignee, estimateHours, due: String(payload.due || ""), round, status: "pending", dependencyTaskIds, blockedReason: "", sourceCommentId: sourceComment?.id || null };
        next.tasks.push(task);
        payload = { ...payload, taskId: task.id, taskTitle: task.title, dependencyTaskIds, sourceCommentId: task.sourceCommentId, sourceVersionNumber: sourceComment ? next.versions.find(item => item.id === sourceComment.versionId)?.number : null };
        break;
      }
      case "toggle_task": {
        if (next.stage !== "doing" && next.stage !== "adjustments") throw new Error("Tarefas só podem ser atualizadas durante a execução.");
        const task = next.tasks.find(item => item.id === payload.taskId && item.round === version.number + (next.stage === "adjustments" ? 1 : 0));
        if (!task) throw new Error("Tarefa não encontrada nesta rodada.");
        if (task.status === "completed" && next.tasks.some(item => item.status === "completed" && item.dependencyTaskIds?.includes(task.id))) {
          throw new Error("Reabra as tarefas dependentes antes de reabrir esta tarefa prévia.");
        }
        if (task.status !== "completed") {
          if (task.blockedReason) throw new Error("Remova ou atualize o impedimento antes de concluir esta tarefa.");
          if (unmetTaskDependencies(next, task).length) throw new Error("Conclua as tarefas prévias antes desta tarefa.");
        }
        task.status = task.status === "completed" ? "pending" : "completed";
        task.completedAt = task.status === "completed" ? now : null;
        payload = { ...payload, taskTitle: task.title, status: task.status };
        break;
      }
      case "set_task_blocker": {
        if (next.stage !== "doing" && next.stage !== "adjustments") throw new Error("Impedimentos só podem ser registrados durante a execução.");
        const task = next.tasks.find(item => item.id === payload.taskId && item.round === version.number + (next.stage === "adjustments" ? 1 : 0));
        if (!task) throw new Error("Tarefa não encontrada nesta rodada.");
        if (task.status === "completed") throw new Error("Reabra a tarefa antes de registrar um impedimento.");
        const reason = String(payload.reason || "").trim();
        if (reason.length > 500) throw new Error("O motivo do impedimento deve ter até 500 caracteres.");
        const previousReason = task.blockedReason || "";
        task.blockedReason = reason;
        payload = { ...payload, taskTitle: task.title, previousReason, reason, blocked: Boolean(reason) };
        break;
      }
      case "submit_internal_review":
        if (next.stage !== "doing") throw new Error("A demanda não está em produção.");
        if (!version.fileKey) throw new Error("Anexe o arquivo da versão antes de enviar para revisão.");
        const roundTasks = next.tasks.filter(task => task.round === version.number);
        if (!roundTasks.length || roundTasks.some(task => task.status !== "completed")) throw new Error("Conclua as tarefas atribuídas desta versão antes de iniciar a revisão.");
        next.stage = "internalReview";
        payload = { ...payload, fileName: version.fileName };
        break;
      case "attach_file": {
        if (next.stage !== "doing") throw new Error("O arquivo só pode ser anexado durante a produção.");
        if (!String(payload.fileKey || "").trim() || !String(payload.fileName || "").trim()) throw new Error("Arquivo inválido.");
        const previousFileName = version.fileName || "";
        const previousFileKey = version.fileKey || null;
        version.fileKey = payload.fileKey;
        version.fileName = payload.fileName;
        version.createdBy = payload.author || "Equipe";
        version.createdAt = now;
        payload = { ...payload, previousFileName, previousFileKey };
        break;
      }
      case "internal_approved":
        if (next.stage !== "internalReview") throw new Error("A demanda não está em revisão interna.");
        version.sharedAt = now;
        next.stage = "clientReview";
        payload = { ...payload, result: "approved" };
        break;
      case "internal_changes":
        if (next.stage !== "internalReview") throw new Error("A demanda não está em revisão interna.");
        if (!String(payload.comment || "").trim()) throw new Error("Registre o motivo da devolução.");
        next.stage = "doing";
        const internalComment = { id: `${next.id}-comment-${next.comments.length + 1}`, versionId: version.id, author: "Revisão interna", audience: "internal", text: payload.comment.trim(), anchor: captureAnchor(payload.anchor), at: now };
        next.comments.push(internalComment);
        payload = { ...payload, commentId: internalComment.id, commentText: internalComment.text };
        break;
      case "client_approved":
        if (next.stage !== "clientReview" || version.id !== payload.versionId || !version.sharedAt) throw new Error("A versão enviada para aprovação mudou ou não está compartilhada.");
        version.decision = { result: "approved", author: payload.author || "Aprovador do cliente", at: now };
        next.stage = "delivery";
        payload = { ...payload, result: version.decision.result, author: version.decision.author };
        break;
      case "client_changes":
        if (next.stage !== "clientReview" || version.id !== payload.versionId || !version.sharedAt) throw new Error("A versão enviada para aprovação mudou ou não está compartilhada.");
        if (!String(payload.comment || "").trim()) throw new Error("Descreva as alterações solicitadas.");
        version.decision = { result: "changes_requested", author: payload.author || "Aprovador do cliente", at: now };
        const clientComment = { id: `${next.id}-comment-${next.comments.length + 1}`, versionId: version.id, author: payload.author || "Aprovador do cliente", audience: "client", text: payload.comment.trim(), anchor: captureAnchor(payload.anchor), at: now };
        next.comments.push(clientComment);
        payload = { ...payload, commentId: clientComment.id, commentText: clientComment.text, result: version.decision.result, author: version.decision.author };
        next.stage = "adjustments";
        break;
      case "new_version":
        if (next.stage !== "adjustments") throw new Error("Só é possível criar uma versão após um pedido de ajustes.");
        if (!String(payload.fileName || "").trim() || !String(payload.fileKey || "").trim()) throw new Error("Selecione um arquivo para criar uma nova versão.");
        const adjustmentTasks = next.tasks.filter(task => task.round === version.number + 1);
        if (!adjustmentTasks.length || adjustmentTasks.some(task => task.status !== "completed")) throw new Error("Adicione e conclua ao menos uma tarefa para esta rodada antes de anexar a versão.");
        const createdVersion = { id: `${next.id}-v${version.number + 1}`, number: version.number + 1, fileName: payload.fileName, fileKey: payload.fileKey, createdBy: payload.author || "Equipe", createdAt: now, sharedAt: null, decision: null };
        next.versions.push(createdVersion);
        next.stage = "internalReview";
        payload = { ...payload, versionId: createdVersion.id, versionNumber: createdVersion.number };
        break;
      case "record_delivery":
        if (next.stage !== "delivery") throw new Error("A demanda ainda não foi aprovada pelo cliente.");
        if (!["delivered", "scheduled", "published"].includes(payload.destinationType)) throw new Error("Selecione se o material foi entregue, agendado ou publicado.");
        if (!String(payload.evidence || "").trim()) throw new Error("Registre a evidência de entrega ou publicação.");
        next.delivery = { destinationType: payload.destinationType, evidence: payload.evidence.trim(), at: now, author: payload.author || "Equipe" };
        next.stage = "completed";
        break;
      case "add_comment":
        if (!String(payload.comment || "").trim()) throw new Error("Escreva um comentário antes de enviar.");
        const addedComment = { id: `${next.id}-comment-${next.comments.length + 1}`, versionId: version.id, author: payload.author || "Equipe", audience: payload.audience || "internal", text: payload.comment.trim(), anchor: captureAnchor(payload.anchor), at: now };
        next.comments.push(addedComment);
        payload = { ...payload, commentId: addedComment.id, commentText: addedComment.text, author: addedComment.author, audience: addedComment.audience };
        break;
      default:
        throw new Error("Ação de fluxo desconhecida.");
    }

    const eventDetails = payload.evidence ? { evidence: payload.evidence.trim() } : {};
    const eventVersion = action === "new_version" ? next.versions.at(-1) : version;
    if (["submit_internal_review", "attach_file", "internal_approved", "internal_changes", "client_approved", "client_changes", "new_version", "record_delivery", "add_comment"].includes(action)) {
      eventDetails.versionId = eventVersion.id;
      eventDetails.versionNumber = eventVersion.number;
    }
    if (action === "record_delivery") eventDetails.destinationType = payload.destinationType;
    if (action === "submit_internal_review") eventDetails.fileName = payload.fileName;
    if (action === "attach_file") {
      eventDetails.previousFileName = payload.previousFileName;
      eventDetails.previousFileKey = payload.previousFileKey;
      eventDetails.fileName = payload.fileName;
    }
    if (action === "internal_approved") eventDetails.result = payload.result;
    if (["internal_changes", "client_changes", "add_comment"].includes(action)) {
      eventDetails.commentId = payload.commentId;
      eventDetails.commentText = payload.commentText;
      eventDetails.author = payload.author || (action === "internal_changes" ? "Revisão interna" : "");
      if (action === "add_comment") eventDetails.audience = payload.audience;
    }
    if (["client_approved", "client_changes"].includes(action)) eventDetails.result = payload.result;
    if (action === "new_version") eventDetails.fileName = eventVersion.fileName;
    if (action === "briefing_revised") {
      const revision = next.briefingRevisions.at(-1);
      eventDetails.reason = revision.reason;
      eventDetails.planReviewRequired = true;
    }
    if (action === "briefing_ready") {
      eventDetails.origin = next.origin;
      eventDetails.channel = next.channel;
      eventDetails.acceptanceCriteria = next.acceptanceCriteria;
      eventDetails.references = next.references || "";
      eventDetails.due = next.due || "";
    }
    if (["add_comment", "internal_changes", "client_changes"].includes(action)) {
      eventDetails.versionId = eventVersion.id;
      const comment = next.comments.at(-1);
      if (comment?.anchor) eventDetails.anchor = comment.anchor;
    }
    if (["add_task", "toggle_task", "set_task_blocker"].includes(action)) {
      eventDetails.taskId = payload.taskId;
      eventDetails.taskTitle = payload.taskTitle;
      if (action === "add_task") {
        eventDetails.dependencyTaskIds = payload.dependencyTaskIds;
        eventDetails.sourceCommentId = payload.sourceCommentId;
        eventDetails.sourceVersionNumber = payload.sourceVersionNumber;
      }
      if (action === "toggle_task") eventDetails.status = payload.status;
      if (action === "set_task_blocker") {
        eventDetails.previousReason = payload.previousReason;
        eventDetails.reason = payload.reason;
        eventDetails.blocked = payload.blocked;
      }
    }
    recordEvent(next, action, eventDetails, now);
    return next;
  }

  const api = { stages, transition, listAssignees, filterRequestsByAssignee, unmetTaskDependencies, taskBlockers };
  if (typeof module !== "undefined" && module.exports) module.exports = api;
  root.Mix7Workflow = api;
})(globalThis);
