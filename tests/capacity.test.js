const test = require("node:test");
const assert = require("node:assert/strict");
const capacity = require("../prototipo/capacity.js");

function request({ id = "d-1", stage = "doing", tasks = [], versions = [{ number: 1 }] } = {}) {
  return { id, stage, versions, tasks };
}

test("ISO week dates use Monday through Sunday, including year boundaries", () => {
  assert.equal(capacity.weekStartFromIso("2026-W01"), "2025-12-29");
  assert.equal(capacity.weekEndFromStart("2025-12-29"), "2026-01-04");
  assert.equal(capacity.isoWeekFromDate(new Date(2026, 0, 1)), "2026-W01");
  assert.throws(() => capacity.weekStartFromIso("2026-01"), /semana válida/);
});

test("weekly capacity compares configured hours with estimated open tasks due in that week", () => {
  const requests = [
    request({ tasks: [
      { id: "t1", assignee: "Diego", due: "2026-09-21", estimateHours: "5", round: 1, status: "pending" },
      { id: "t2", assignee: "DIEGO", due: "2026-09-27", estimateHours: 3, round: 1, status: "in_progress" },
      { id: "t3", assignee: "Diego", due: "2026-09-22", round: 1, status: "pending" },
      { id: "t4", assignee: "Diego", due: "2026-09-22", estimateHours: 12, round: 1, status: "completed" },
      { id: "t5", assignee: "Diego", due: "2026-09-28", estimateHours: 20, round: 1, status: "pending" },
      { id: "t6", assignee: "Diego", due: "2026-09-24", estimateHours: 20, round: 2, status: "pending" },
      { id: "t7", assignee: "Diego", estimateHours: 2, round: 1, status: "pending" },
    ] }),
    request({ id: "d-done", stage: "completed", tasks: [{ id: "t8", assignee: "Diego", due: "2026-09-23", estimateHours: 99, round: 1 }] }),
  ];
  const result = capacity.summarizeWeeklyCapacity({
    requests,
    profile: { assignee: "Diego", weekStart: "2026-09-21", scheduledHours: 10 },
    absences: [{ assignee: "Diego", date: "2026-09-22", hours: 2 }],
    assignee: " diego ",
    weekStart: "2026-09-21",
  });

  assert.equal(result.weekEnd, "2026-09-27");
  assert.equal(result.configured, true);
  assert.equal(result.scheduledHours, 10);
  assert.equal(result.absenceHours, 2);
  assert.equal(result.availableHours, 8);
  assert.equal(result.plannedHours, 8);
  assert.equal(result.balanceHours, 0);
  assert.deepEqual(result.plannedTasks.map(item => item.task.id), ["t1", "t2", "t3"]);
  assert.deepEqual(result.missingEstimateTasks.map(item => item.task.id), ["t3"]);
  assert.deepEqual(result.undatedTasks.map(item => item.task.id), ["t7"]);
});

test("missing schedule remains unconfigured and does not invent availability", () => {
  const result = capacity.summarizeWeeklyCapacity({
    requests: [request({ tasks: [{ id: "t1", assignee: "Diego", due: "2026-09-22", estimateHours: 3, round: 1 }] })],
    assignee: "Diego",
    weekStart: "2026-09-21",
  });

  assert.equal(result.configured, false);
  assert.equal(result.scheduledHours, null);
  assert.equal(result.availableHours, null);
  assert.equal(result.balanceHours, null);
  assert.equal(result.plannedHours, 3);
});

test("rejects dates and hours that cannot describe a real planning week", () => {
  assert.throws(() => capacity.weekStartFromIso("2026-W54"), /inválida/);
  assert.throws(() => capacity.weekEndFromStart("2026-02-31"), /inválido/);
});
