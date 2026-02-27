export type SessionStatus =
  | "setup"
  | "discovering"
  | "interrogating"
  | "summarizing"
  | "planning"
  | "build_rules"
  | "build_tasks"
  | "build_executing"
  | "completed"
  | "failed"
  | "paused";

export type PhaseId = 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9;

export interface Phase {
  id: PhaseId;
  label: string;
  status: "completed" | "active" | "future";
}

export interface TechStackEntry {
  id: string;
  name: string;
  url: string;
}

export interface QAItem {
  id: number;
  category: string;
  categoryColor: string;
  question: string;
  reasoning?: string;
  answer?: string;
  skipped?: boolean;
  skipReason?: string;
  options?: string[];
}

export interface BuildTask {
  id: string;
  title: string;
  description: string;
  instructions: string;
  status: "queued" | "running" | "completed" | "failed";
  startedAt?: string;
  completedAt?: string;
  log?: string;
}

export interface WizardState {
  sessionName: string;
  projectDir: string;
  sessionStatus: SessionStatus;
  currentPhase: PhaseId;
  // Phase 1
  providerConnected: boolean;
  providerTeam: string;
  providerProject: string;
  // Phase 2
  techStack: TechStackEntry[];
  // Phase 3
  discoveryStatus: "idle" | "running" | "completed" | "failed";
  discoveryLog: string[];
  // Phase 4
  qaItems: QAItem[];
  currentQuestionIdx: number;
  // Phase 5
  summaryContent: string;
  summaryStatus: "idle" | "generating" | "ready";
  summaryLog: string[];
  // Phase 6
  planContent: string;
  planStatus: "idle" | "generating" | "ready" | "approved";
  planLog: string[];
  // Phase 7
  rulesContent: string;
  ruleEntries: RuleEntry[];
  // Phase 8
  buildTasks: BuildTask[];
  tasksApproved: boolean;
  // Phase 9
  buildStatus: "idle" | "running" | "paused" | "completed" | "failed";
  buildProgress: number;
  // Stats
  stats: {
    questions: number;
    answers: number;
    elapsed: string;
    progress: number;
    categories: string[];
  };
}

export interface RuleEntry {
  id: string;
  text: string;
}