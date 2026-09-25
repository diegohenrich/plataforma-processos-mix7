(function (root) {
  function assigneeKey(value) {
    return String(value || "").trim().toLocaleLowerCase("pt-BR");
  }

  function weekStartFromIso(value) {
    const match = /^(\d{4})-W(\d{2})$/.exec(String(value || ""));
    if (!match) throw new Error("Selecione uma semana válida.");
    const year = Number(match[1]);
    const week = Number(match[2]);
    if (week < 1 || week > 53) throw new Error("A semana selecionada é inválida.");
    const jan4 = new Date(Date.UTC(year, 0, 4));
    const monday = new Date(jan4);
    monday.setUTCDate(jan4.getUTCDate() - ((jan4.getUTCDay() + 6) % 7) + (week - 1) * 7);
    const isoYear = new Date(monday);
    isoYear.setUTCDate(monday.getUTCDate() + 3);
    if (isoYear.getUTCFullYear() !== year) throw new Error("A semana selecionada é inválida.");
    return monday.toISOString().slice(0, 10);
  }

  function isoWeekFromDate(date = new Date()) {
    const normalized = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    normalized.setUTCDate(normalized.getUTCDate() + 3 - ((normalized.getUTCDay() + 6) % 7));
    const isoYear = normalized.getUTCFullYear();
    const firstThursday = new Date(Date.UTC(isoYear, 0, 4));
    firstThursday.setUTCDate(firstThursday.getUTCDate() + 3 - ((firstThursday.getUTCDay() + 6) % 7));
    const week = 1 + Math.round((normalized - firstThursday) / 604800000);
    return `${isoYear}-W${String(week).padStart(2, "0")}`;
  }

  function weekEndFromStart(start) {
    const date = new Date(`${start}T00:00:00Z`);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(start) || Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== start) throw new Error("O início da semana é inválido.");
    date.setUTCDate(date.getUTCDate() + 6);
    return date.toISOString().slice(0, 10);
  }

  function estimateValue(task) {
    const estimate = Number(task?.estimateHours);
    return Number.isFinite(estimate) && estimate > 0 ? estimate : null;
  }

  function currentRound(request) {
    const latestVersion = Number(request.versions?.at(-1)?.number) || 1;
    return latestVersion + (request.stage === "adjustments" ? 1 : 0);
  }

  function summarizeWeeklyCapacity({ requests = [], profile, absences = [], assignee, weekStart }) {
    const weekEnd = weekEndFromStart(weekStart);
    const selectedAssignee = assigneeKey(assignee);
    const plannedTasks = [];
    const undatedTasks = [];

    for (const request of requests) {
      if (request.stage === "completed") continue;
      const round = currentRound(request);
      for (const task of request.tasks || []) {
        if ((Number(task.round) || (Number(request.versions?.at(-1)?.number) || 1)) !== round || task.status === "completed") continue;
        if (assigneeKey(task.assignee) !== selectedAssignee) continue;
        if (!task.due) {
          undatedTasks.push({ request, task });
          continue;
        }
        if (task.due < weekStart || task.due > weekEnd) continue;
        plannedTasks.push({ request, task, estimateHours: estimateValue(task) });
      }
    }

    const missingEstimateTasks = plannedTasks.filter(item => item.estimateHours == null);
    const absenceHours = absences
      .filter(item => assigneeKey(item.assignee) === selectedAssignee && item.date >= weekStart && item.date <= weekEnd)
      .reduce((total, item) => total + (Number(item.hours) || 0), 0);
    const scheduledHours = profile && assigneeKey(profile.assignee) === selectedAssignee && profile.weekStart === weekStart
      ? Number(profile.scheduledHours)
      : null;
    const configured = Number.isFinite(scheduledHours) && scheduledHours >= 0 && scheduledHours <= 168;
    const availableHours = configured ? scheduledHours - absenceHours : null;
    const plannedHours = plannedTasks.reduce((total, item) => total + (item.estimateHours || 0), 0);

    return {
      assignee,
      weekStart,
      weekEnd,
      configured,
      scheduledHours: configured ? scheduledHours : null,
      absenceHours,
      availableHours,
      plannedHours,
      balanceHours: configured ? availableHours - plannedHours : null,
      plannedTasks,
      missingEstimateTasks,
      undatedTasks,
    };
  }

  const api = Object.freeze({ assigneeKey, weekStartFromIso, isoWeekFromDate, weekEndFromStart, summarizeWeeklyCapacity });
  root.Mix7Capacity = api;
  if (typeof module !== "undefined" && module.exports) module.exports = api;
})(typeof window !== "undefined" ? window : globalThis);
