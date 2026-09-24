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

  function transition(request, action, payload = {}, now = new Date().toISOString()) {
    const next = structuredClone(request);
    const version = next.versions[next.versions.length - 1];

    switch (action) {
      case "briefing_ready":
        if (next.stage !== "briefing") throw new Error("A demanda não está no briefing.");
        next.stage = "planning";
        break;
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
        const estimateHours = Number(payload.estimateHours) > 0 ? Number(payload.estimateHours) : null;
        next.tasks.push({ id: `${next.id}-task-${round}-${next.tasks.filter(task => task.round === round).length + 1}`, title, assignee, estimateHours, due: String(payload.due || ""), round, status: "pending" });
        break;
      }
      case "toggle_task": {
        if (next.stage !== "doing" && next.stage !== "adjustments") throw new Error("Tarefas só podem ser atualizadas durante a execução.");
        const task = next.tasks.find(item => item.id === payload.taskId && item.round === version.number + (next.stage === "adjustments" ? 1 : 0));
        if (!task) throw new Error("Tarefa não encontrada nesta rodada.");
        task.status = task.status === "completed" ? "pending" : "completed";
        task.completedAt = task.status === "completed" ? now : null;
        break;
      }
      case "submit_internal_review":
        if (next.stage !== "doing") throw new Error("A demanda não está em produção.");
        if (!version.fileKey) throw new Error("Anexe o arquivo da versão antes de enviar para revisão.");
        const roundTasks = next.tasks.filter(task => task.round === version.number);
        if (!roundTasks.length || roundTasks.some(task => task.status !== "completed")) throw new Error("Conclua as tarefas atribuídas desta versão antes de iniciar a revisão.");
        next.stage = "internalReview";
        break;
      case "attach_file":
        if (next.stage !== "doing") throw new Error("O arquivo só pode ser anexado durante a produção.");
        if (!String(payload.fileKey || "").trim() || !String(payload.fileName || "").trim()) throw new Error("Arquivo inválido.");
        version.fileKey = payload.fileKey;
        version.fileName = payload.fileName;
        version.createdBy = payload.author || "Equipe";
        version.createdAt = now;
        break;
      case "internal_approved":
        if (next.stage !== "internalReview") throw new Error("A demanda não está em revisão interna.");
        version.sharedAt = now;
        next.stage = "clientReview";
        break;
      case "internal_changes":
        if (next.stage !== "internalReview") throw new Error("A demanda não está em revisão interna.");
        if (!String(payload.comment || "").trim()) throw new Error("Registre o motivo da devolução.");
        next.stage = "doing";
        next.comments.push({ id: `${next.id}-comment-${next.comments.length + 1}`, versionId: version.id, author: "Revisão interna", audience: "internal", text: payload.comment.trim(), anchor: captureAnchor(payload.anchor), at: now });
        break;
      case "client_approved":
        if (next.stage !== "clientReview" || version.id !== payload.versionId || !version.sharedAt) throw new Error("A versão enviada para aprovação mudou ou não está compartilhada.");
        version.decision = { result: "approved", author: payload.author || "Aprovador do cliente", at: now };
        next.stage = "delivery";
        break;
      case "client_changes":
        if (next.stage !== "clientReview" || version.id !== payload.versionId || !version.sharedAt) throw new Error("A versão enviada para aprovação mudou ou não está compartilhada.");
        if (!String(payload.comment || "").trim()) throw new Error("Descreva as alterações solicitadas.");
        version.decision = { result: "changes_requested", author: payload.author || "Aprovador do cliente", at: now };
        next.comments.push({ id: `${next.id}-comment-${next.comments.length + 1}`, versionId: version.id, author: payload.author || "Aprovador do cliente", audience: "client", text: payload.comment.trim(), anchor: captureAnchor(payload.anchor), at: now });
        next.stage = "adjustments";
        break;
      case "new_version":
        if (next.stage !== "adjustments") throw new Error("Só é possível criar uma versão após um pedido de ajustes.");
        if (!String(payload.fileName || "").trim() || !String(payload.fileKey || "").trim()) throw new Error("Selecione um arquivo para criar uma nova versão.");
        const adjustmentTasks = next.tasks.filter(task => task.round === version.number + 1);
        if (!adjustmentTasks.length || adjustmentTasks.some(task => task.status !== "completed")) throw new Error("Adicione e conclua ao menos uma tarefa para esta rodada antes de anexar a versão.");
        next.versions.push({ id: `${next.id}-v${version.number + 1}`, number: version.number + 1, fileName: payload.fileName, fileKey: payload.fileKey, createdBy: payload.author || "Equipe", createdAt: now, sharedAt: null, decision: null });
        next.stage = "internalReview";
        break;
      case "record_delivery":
        if (next.stage !== "delivery") throw new Error("A demanda ainda não foi aprovada pelo cliente.");
        if (!String(payload.evidence || "").trim()) throw new Error("Registre a evidência de entrega ou publicação.");
        next.delivery = { evidence: payload.evidence.trim(), at: now, author: payload.author || "Equipe" };
        next.stage = "completed";
        break;
      case "add_comment":
        if (!String(payload.comment || "").trim()) throw new Error("Escreva um comentário antes de enviar.");
        next.comments.push({ id: `${next.id}-comment-${next.comments.length + 1}`, versionId: version.id, author: payload.author || "Equipe", audience: payload.audience || "internal", text: payload.comment.trim(), anchor: captureAnchor(payload.anchor), at: now });
        break;
      default:
        throw new Error("Ação de fluxo desconhecida.");
    }

    const eventDetails = payload.evidence ? { evidence: payload.evidence.trim() } : {};
    if (action === "briefing_revised") {
      const revision = next.briefingRevisions.at(-1);
      eventDetails.reason = revision.reason;
      eventDetails.planReviewRequired = true;
    }
    if (["add_comment", "internal_changes", "client_changes"].includes(action)) {
      eventDetails.versionId = version.id;
      const comment = next.comments.at(-1);
      if (comment?.anchor) eventDetails.anchor = comment.anchor;
    }
    recordEvent(next, action, eventDetails, now);
    return next;
  }

  const api = { stages, transition };
  if (typeof module !== "undefined" && module.exports) module.exports = api;
  root.Mix7Workflow = api;
})(globalThis);
