import { useState, useEffect, useRef } from "react";
import { Link } from "react-router";
import {
  ArrowLeft,
  Settings,
  Pause,
  Play,
  Trash2,
  RotateCcw,
  RefreshCw,
  Pencil,
  Plus,
  X,
  ChevronDown,
  ChevronUp,
  Download,
  Upload,
  Check,
  AlertTriangle,
  Loader2,
  SkipForward,
  ExternalLink,
  Terminal,
} from "lucide-react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Textarea } from "../ui/textarea";
import { Card, CardContent } from "../ui/card";
import { Badge } from "../ui/badge";
import { Progress } from "../ui/progress";
import { Skeleton } from "../ui/skeleton";
import { RadioGroup, RadioGroupItem } from "../ui/radio-group";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "../ui/alert-dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "../ui/dropdown-menu";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetTrigger,
  SheetFooter,
} from "../ui/sheet";
import { Separator } from "../ui/separator";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "../ui/accordion";
import { PhaseStepper } from "./phase-stepper";
import { StatsPanel } from "./stats-panel";
import type { Phase, PhaseId, WizardState, TechStackEntry, QAItem, BuildTask, RuleEntry } from "./types";
import {
  mockTechStack,
  mockDiscoveryLog,
  mockQAItems,
  mockSummary,
  mockSummaryLog,
  mockPlan,
  mockPlanLog,
  mockRules,
  mockBuildTasks,
} from "./mock-data";

// ─── Helpers ─────────────────────────────────────────────

function getPhases(currentPhase: PhaseId): Phase[] {
  const labels = [
    "Setup",
    "Tech Stack",
    "Discovery",
    "Interrogation",
    "Summary",
    "Planning",
    "Rules",
    "Tasks",
    "Build",
  ];
  return labels.map((label, i) => ({
    id: (i + 1) as PhaseId,
    label,
    status:
      i + 1 < currentPhase
        ? "completed"
        : i + 1 === currentPhase
        ? "active"
        : "future",
  }));
}

// ─── Main Component ──────────────────────────────────────

// Discovery Wizard — 9-phase AI-driven requirements discovery
export function DiscoveryWizardPage() {
  const [state, setState] = useState<WizardState>({
    sessionName: "Agent Org Layer (AI Workforce)",
    projectDir: "/Users/garethdaine/Code/agent",
    sessionStatus: "setup",
    currentPhase: 1,
    providerConnected: false,
    providerTeam: "",
    providerProject: "",
    techStack: [],
    discoveryStatus: "idle",
    discoveryLog: [],
    qaItems: [],
    currentQuestionIdx: 0,
    summaryContent: "",
    summaryStatus: "idle",
    summaryLog: [],
    planContent: "",
    planStatus: "idle",
    planLog: [],
    rulesContent: "",
    ruleEntries: [],
    buildTasks: [],
    tasksApproved: false,
    buildStatus: "idle",
    buildProgress: 0,
    stats: {
      questions: 0,
      answers: 0,
      elapsed: "Not started",
      progress: 0,
      categories: [],
    },
  });

  const [isPaused, setIsPaused] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [errorBanner, setErrorBanner] = useState("");

  const update = (partial: Partial<WizardState>) =>
    setState((prev) => ({ ...prev, ...partial }));

  const goToPhase = (phase: PhaseId) => {
    if (phase <= state.currentPhase) {
      update({ currentPhase: phase });
    }
  };

  const advancePhase = () => {
    if (state.currentPhase < 9) {
      const next = (state.currentPhase + 1) as PhaseId;
      update({ currentPhase: next });
    }
  };

  const phases = getPhases(state.currentPhase);

  // Determine if QA sidebar is shown
  const showQASidebar = state.currentPhase === 4 || state.currentPhase === 5;

  return (
    <div>
      {/* ─── Header Bar ─────────────────────────────── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div>
          <h1>{state.sessionName}</h1>
          <p className="text-muted-foreground font-mono" style={{ fontSize: "12px" }}>
            {state.projectDir}
          </p>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          <Badge
            variant="outline"
            className="text-muted-foreground border-border"
          >
            {state.sessionStatus}
          </Badge>

          {state.sessionStatus === "failed" && (
            <Button variant="outline" size="sm" className="h-8 gap-1.5" style={{ fontSize: "12px" }}>
              <RotateCcw className="w-3.5 h-3.5" /> Retry
            </Button>
          )}

          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button variant="outline" size="sm" className="h-8 gap-1.5 text-destructive border-destructive/30" style={{ fontSize: "12px" }}>
                <RefreshCw className="w-3.5 h-3.5" /> Restart Fresh
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Restart session?</AlertDialogTitle>
                <AlertDialogDescription>
                  This will discard all progress and restart from Phase 1. This action cannot be undone.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                  Yes, restart
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>

          <Button variant="outline" size="sm" className="h-8 gap-1.5" style={{ fontSize: "12px" }}>
            <Pencil className="w-3.5 h-3.5" /> Rename
          </Button>

          <Button
            variant="outline"
            size="sm"
            className="h-8 gap-1.5"
            style={{ fontSize: "12px" }}
            onClick={() => setIsPaused(!isPaused)}
          >
            {isPaused ? (
              <>
                <Play className="w-3.5 h-3.5" /> Resume
              </>
            ) : (
              <>
                <Pause className="w-3.5 h-3.5" /> Pause
              </>
            )}
          </Button>

          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button variant="ghost" size="sm" className="h-8 gap-1.5 text-destructive" style={{ fontSize: "12px" }}>
                <Trash2 className="w-3.5 h-3.5" /> Delete
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Delete session?</AlertDialogTitle>
                <AlertDialogDescription>
                  This will permanently delete this discovery session and all associated data.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                  Delete
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>

          <Sheet open={settingsOpen} onOpenChange={setSettingsOpen}>
            <SheetTrigger asChild>
              <Button variant="outline" size="sm" className="h-8 gap-1.5" style={{ fontSize: "12px" }}>
                <Settings className="w-3.5 h-3.5" /> Session Settings
              </Button>
            </SheetTrigger>
            <SheetContent side="right" className="w-full sm:max-w-md overflow-y-auto">
              <SheetHeader className="px-0">
                <SheetTitle>Session Settings</SheetTitle>
                <SheetDescription>Configure session parameters</SheetDescription>
              </SheetHeader>
              <SessionSettingsContent state={state} update={update} />
              <SheetFooter className="px-0">
                <Button className="w-full" onClick={() => setSettingsOpen(false)}>
                  Save Settings
                </Button>
              </SheetFooter>
            </SheetContent>
          </Sheet>

          <Link to="/tools/discovery">
            <Button variant="ghost" size="sm" className="h-8 gap-1.5" style={{ fontSize: "12px" }}>
              <ArrowLeft className="w-3.5 h-3.5" /> Back
            </Button>
          </Link>
        </div>
      </div>

      {/* ─── Error Banner ────────────────────────────── */}
      {errorBanner && (
        <div className="flex items-center gap-2 px-4 py-2.5 mb-4 rounded-lg bg-destructive/10 border border-destructive/20 text-destructive" style={{ fontSize: "13px" }}>
          <AlertTriangle className="w-4 h-4 shrink-0" />
          {errorBanner}
          <button onClick={() => setErrorBanner("")} className="ml-auto">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Paused Banner */}
      {isPaused && (
        <div className="flex items-center gap-2 px-4 py-2.5 mb-4 rounded-lg bg-warning/10 border border-warning/20 text-warning" style={{ fontSize: "13px" }}>
          <Pause className="w-4 h-4 shrink-0" />
          Session is paused. Click Resume to continue.
        </div>
      )}

      {/* ─── Phase Stepper ───────────────────────────── */}
      <PhaseStepper phases={phases} onPhaseClick={goToPhase} />

      {/* ─── Content Area ────────────────────────────── */}
      <div className={`grid gap-4 ${showQASidebar ? "grid-cols-1 lg:grid-cols-[240px_1fr_220px]" : "grid-cols-1 lg:grid-cols-[1fr_220px]"}`}>
        {/* QA Sidebar (phases 4-5 only) */}
        {showQASidebar && (
          <QAHistoryPanel
            items={state.qaItems}
            currentIdx={state.currentQuestionIdx}
            onSelect={(idx) => update({ currentQuestionIdx: idx })}
          />
        )}

        {/* Main Content */}
        <div className="min-w-0">
          <PhaseContent state={state} update={update} advancePhase={advancePhase} setErrorBanner={setErrorBanner} />
        </div>

        {/* Stats Sidebar */}
        <div className="hidden lg:block">
          <StatsPanel
            questions={state.stats.questions}
            answers={state.stats.answers}
            elapsed={state.stats.elapsed}
            status={state.sessionStatus}
            progress={state.stats.progress}
            categories={state.stats.categories}
          />
        </div>
      </div>
    </div>
  );
}

// ─── Session Settings Sheet ──────────────────────────────

function SessionSettingsContent({
  state,
  update,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
}) {
  return (
    <div className="space-y-5 py-4">
      <div>
        <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
          Session Name
        </label>
        <Input
          value={state.sessionName}
          onChange={(e) => update({ sessionName: e.target.value })}
          className="h-9 bg-input-background"
        />
      </div>
      <div>
        <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
          Project Directory
        </label>
        <Input
          value={state.projectDir}
          onChange={(e) => update({ projectDir: e.target.value })}
          className="h-9 bg-input-background font-mono"
          style={{ fontSize: "13px" }}
        />
      </div>
      <Separator />
      <div>
        <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
          Feature Brief
        </label>
        <Textarea
          placeholder="Describe the feature scope..."
          className="min-h-[100px] bg-input-background"
          style={{ fontSize: "13px" }}
        />
      </div>
      <Separator />
      <div>
        <h4 style={{ fontSize: "13px", fontWeight: 500 }} className="mb-2">
          Tech Stack ({state.techStack.length} entries)
        </h4>
        {state.techStack.map((entry) => (
          <div key={entry.id} className="flex items-center gap-2 mb-2">
            <Badge variant="secondary" className="gap-1.5">
              {entry.name}
              <button onClick={() =>
                update({
                  techStack: state.techStack.filter((e) => e.id !== entry.id),
                })
              }>
                <X className="w-3 h-3" />
              </button>
            </Badge>
            <a href={entry.url} target="_blank" rel="noreferrer" className="text-primary" style={{ fontSize: "11px" }}>
              <ExternalLink className="w-3 h-3" />
            </a>
          </div>
        ))}
      </div>
    </div>
  );
}

// ─── QA History Sidebar ──────────────────────────────────

function QAHistoryPanel({
  items,
  currentIdx,
  onSelect,
}: {
  items: QAItem[];
  currentIdx: number;
  onSelect: (idx: number) => void;
}) {
  const unanswered = items.filter((q) => !q.answer && !q.skipped).length;

  return (
    <div className="hidden lg:block">
      <Card className="border border-border shadow-none">
        <CardContent className="p-3">
          <div className="flex items-center justify-between mb-3">
            <span style={{ fontSize: "13px", fontWeight: 600 }}>Q&A History</span>
            {unanswered > 0 && (
              <Badge className="bg-warning/10 text-warning border-warning/20">
                {unanswered} unanswered
              </Badge>
            )}
          </div>
          <div className="space-y-1 max-h-[500px] overflow-y-auto">
            {items.length === 0 ? (
              <p className="text-muted-foreground text-center py-4" style={{ fontSize: "12px" }}>
                No questions yet
              </p>
            ) : (
              items.map((item, idx) => (
                <button
                  key={item.id}
                  onClick={() => onSelect(idx)}
                  className={`w-full text-left p-2 rounded-md transition-colors ${
                    idx === currentIdx
                      ? "bg-primary/8 border-l-2 border-primary"
                      : "hover:bg-muted/50"
                  }`}
                >
                  <div className="flex items-center gap-1.5 mb-0.5">
                    <span className="text-muted-foreground" style={{ fontSize: "11px", fontWeight: 600 }}>
                      #{item.id}
                    </span>
                    <span
                      className={`px-1.5 py-0 rounded ${item.categoryColor}`}
                      style={{ fontSize: "9px", fontWeight: 600 }}
                    >
                      {item.category}
                    </span>
                  </div>
                  <p className="text-foreground truncate" style={{ fontSize: "11px" }}>
                    {item.question.slice(0, 60)}...
                  </p>
                  {item.answer ? (
                    <p className="text-muted-foreground truncate mt-0.5" style={{ fontSize: "10px" }}>
                      {item.answer.slice(0, 40)}...
                    </p>
                  ) : item.skipped ? (
                    <Badge variant="outline" className="mt-0.5 text-muted-foreground" style={{ fontSize: "9px" }}>
                      Skipped
                    </Badge>
                  ) : (
                    <Badge className="mt-0.5 bg-warning/10 text-warning border-warning/20" style={{ fontSize: "9px" }}>
                      Unanswered
                    </Badge>
                  )}
                </button>
              ))
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

// ─── Phase Content Router ────────────────────────────────

function PhaseContent({
  state,
  update,
  advancePhase,
  setErrorBanner,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
  setErrorBanner: (msg: string) => void;
}) {
  switch (state.currentPhase) {
    case 1:
      return <Phase1Setup state={state} update={update} advancePhase={advancePhase} />;
    case 2:
      return <Phase2TechStack state={state} update={update} advancePhase={advancePhase} />;
    case 3:
      return <Phase3Discovery state={state} update={update} advancePhase={advancePhase} />;
    case 4:
      return <Phase4Interrogation state={state} update={update} advancePhase={advancePhase} />;
    case 5:
      return <Phase5Summary state={state} update={update} advancePhase={advancePhase} />;
    case 6:
      return <Phase6Planning state={state} update={update} advancePhase={advancePhase} />;
    case 7:
      return <Phase7Rules state={state} update={update} advancePhase={advancePhase} />;
    case 8:
      return <Phase8Tasks state={state} update={update} advancePhase={advancePhase} />;
    case 9:
      return <Phase9Build state={state} update={update} />;
    default:
      return null;
  }
}

// ─── Phase 1: Setup ──────────────────────────────────────

function Phase1Setup({
  state,
  update,
  advancePhase,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
}) {
  return (
    <div className="space-y-4">
      <Card className="border border-border shadow-none">
        <CardContent className="p-5">
          <h3 className="uppercase tracking-wider text-muted-foreground mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>
            Pre-Discovery Setup
          </h3>
          <h2 className="mb-1" style={{ fontSize: "17px", fontWeight: 600 }}>Setup</h2>
          <p className="text-muted-foreground mb-5" style={{ fontSize: "13px" }}>
            Task provider is optional. You can connect Linear now or skip it and continue to tech stack setup.
          </p>

          {/* Task Provider Card */}
          <div className="border border-border rounded-lg p-4 mb-4">
            <h4 className="uppercase tracking-wider text-muted-foreground mb-2" style={{ fontSize: "11px", fontWeight: 600 }}>
              Task Provider (Optional)
            </h4>
            {state.providerConnected ? (
              <div className="space-y-3">
                <p style={{ fontSize: "13px" }}>
                  Connected to Linear (Gareth Daine) · Team: Agent Orchestration
                </p>
                <Button
                  variant="outline"
                  size="sm"
                  className="h-8"
                  style={{ fontSize: "12px" }}
                  onClick={() => update({ providerConnected: false, providerTeam: "", providerProject: "" })}
                >
                  Disconnect Linear
                </Button>

                {/* Team Selector */}
                <div className="flex items-center gap-2 mt-3">
                  <Select value={state.providerTeam} onValueChange={(v) => update({ providerTeam: v })}>
                    <SelectTrigger className="h-9 flex-1 bg-input-background">
                      <SelectValue placeholder="Select team..." />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="agent-orchestration">Agent Orchestration</SelectItem>
                      <SelectItem value="platform">Platform</SelectItem>
                      <SelectItem value="infrastructure">Infrastructure</SelectItem>
                    </SelectContent>
                  </Select>
                  <Button variant="outline" size="sm" className="h-9" style={{ fontSize: "12px" }}>
                    Refresh
                  </Button>
                </div>

                <div className="flex justify-end">
                  <Button size="sm" className="h-8" style={{ fontSize: "12px" }}>
                    Save Linear Team
                  </Button>
                </div>
              </div>
            ) : (
              <div>
                <p className="text-muted-foreground mb-3" style={{ fontSize: "13px" }}>
                  Connect a task management provider to sync generated build tasks. You can skip this and connect later.
                </p>
                <Button
                  size="sm"
                  className="h-8"
                  style={{ fontSize: "12px" }}
                  onClick={() => update({ providerConnected: true })}
                >
                  Connect Linear
                </Button>
              </div>
            )}
          </div>

          <div className="flex items-center justify-between pt-2">
            <button
              onClick={advancePhase}
              className="text-muted-foreground hover:text-foreground transition-colors"
              style={{ fontSize: "13px" }}
            >
              Skip provider setup
            </button>
            <Button className="h-9" onClick={advancePhase}>
              Continue to Tech Stack →
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

// ─── Phase 2: Tech Stack ─────────────────────────────────

function Phase2TechStack({
  state,
  update,
  advancePhase,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
}) {
  const [newName, setNewName] = useState("");
  const [newUrl, setNewUrl] = useState("");

  const addEntry = () => {
    if (!newName.trim()) return;
    const entry: TechStackEntry = {
      id: Date.now().toString(),
      name: newName.trim(),
      url: newUrl.trim(),
    };
    update({ techStack: [...state.techStack, entry] });
    setNewName("");
    setNewUrl("");
  };

  const removeEntry = (id: string) => {
    update({ techStack: state.techStack.filter((e) => e.id !== id) });
  };

  const loadMock = () => {
    update({ techStack: mockTechStack });
  };

  return (
    <Card className="border border-border shadow-none">
      <CardContent className="p-5">
        <h2 className="mb-1" style={{ fontSize: "17px", fontWeight: 600 }}>Tech Stack</h2>
        <p className="text-muted-foreground mb-5" style={{ fontSize: "13px" }}>
          Add your project's technology stack with documentation URLs. These are used as context during discovery, planning, and build phases.
        </p>

        {/* Add form */}
        <div className="flex flex-col sm:flex-row gap-2 mb-4">
          <Input
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            placeholder="Stack name (e.g. Laravel 12)"
            className="h-9 bg-input-background flex-1"
            style={{ fontSize: "13px" }}
          />
          <Input
            value={newUrl}
            onChange={(e) => setNewUrl(e.target.value)}
            placeholder="Documentation URL"
            className="h-9 bg-input-background flex-1"
            style={{ fontSize: "13px" }}
          />
          <Button size="sm" className="h-9 gap-1.5 shrink-0" onClick={addEntry} disabled={!newName.trim()}>
            <Plus className="w-3.5 h-3.5" /> Add
          </Button>
        </div>

        {/* Stack list */}
        {state.techStack.length === 0 ? (
          <div className="text-center py-8 border border-dashed border-border rounded-lg">
            <p className="text-muted-foreground mb-2" style={{ fontSize: "13px" }}>
              No tech stack entries added yet.
            </p>
            <Button variant="outline" size="sm" className="h-8" style={{ fontSize: "12px" }} onClick={loadMock}>
              Load sample stack
            </Button>
          </div>
        ) : (
          <div className="space-y-2 mb-4">
            {state.techStack.map((entry) => (
              <div key={entry.id} className="flex items-center gap-2 p-2.5 bg-muted/50 rounded-lg">
                <Badge variant="secondary">{entry.name}</Badge>
                {entry.url && (
                  <a
                    href={entry.url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-muted-foreground hover:text-primary truncate"
                    style={{ fontSize: "12px" }}
                  >
                    {entry.url}
                  </a>
                )}
                <button
                  onClick={() => removeEntry(entry.id)}
                  className="ml-auto text-muted-foreground hover:text-destructive transition-colors shrink-0"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            ))}
          </div>
        )}

        <div className="flex items-center justify-between pt-2">
          {state.techStack.length === 0 && (
            <span className="text-muted-foreground" style={{ fontSize: "12px" }}>
              Tech stack is optional — you can add entries later.
            </span>
          )}
          <Button
            className="h-9 ml-auto"
            onClick={() => {
              update({
                sessionStatus: "discovering",
                discoveryStatus: "running",
                discoveryLog: [],
              });
              advancePhase();
            }}
          >
            Start Discovery →
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

// ─── Phase 3: Discovery ──────────────────────────────────

function Phase3Discovery({
  state,
  update,
  advancePhase,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
}) {
  const logRef = useRef<HTMLDivElement>(null);

  const startedRef = useRef(false);

  // Auto-start discovery when entering the phase
  useEffect(() => {
    if (state.discoveryStatus === "running" && !startedRef.current) {
      startedRef.current = true;
      let idx = 0;
      const interval = setInterval(() => {
        if (idx < mockDiscoveryLog.length) {
          update({
            discoveryLog: mockDiscoveryLog.slice(0, idx + 1),
            stats: {
              ...state.stats,
              elapsed: `${idx * 3}s`,
              progress: Math.round(((idx + 1) / mockDiscoveryLog.length) * 33),
            },
          });
          idx++;
        } else {
          clearInterval(interval);
          update({
            discoveryStatus: "completed",
            sessionStatus: "discovering",
            stats: {
              ...state.stats,
              elapsed: "36s",
              progress: 33,
            },
          });
        }
      }, 400);
      return () => clearInterval(interval);
    }
  }, [state.discoveryStatus]);

  useEffect(() => {
    if (logRef.current) {
      logRef.current.scrollTop = logRef.current.scrollHeight;
    }
  }, [state.discoveryLog]);

  const isRunning = state.discoveryStatus === "running";
  const isCompleted = state.discoveryStatus === "completed";

  return (
    <Card
      className={`border shadow-none transition-all ${
        isRunning ? "border-primary/40" : "border-border"
      }`}
    >
      <CardContent className="p-5">
        <div className="flex items-center justify-between mb-4">
          <h2 style={{ fontSize: "17px", fontWeight: 600 }}>Repository Discovery</h2>
          <Badge
            className={
              isRunning
                ? "bg-primary/10 text-primary"
                : isCompleted
                ? "bg-success/10 text-success"
                : "bg-muted text-muted-foreground"
            }
          >
            {isRunning && <Loader2 className="w-3 h-3 animate-spin mr-1" />}
            {isRunning ? "Analyzing..." : isCompleted ? "Complete" : "Ready"}
          </Badge>
        </div>

        {state.discoveryStatus === "idle" ? (
          <div className="text-center py-12">
            <div className="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
              <Terminal className="w-7 h-7 text-primary" />
            </div>
            <p className="text-muted-foreground mb-4" style={{ fontSize: "13px" }}>
              Ready to analyze your repository. Click Start Discovery in the previous step to begin.
            </p>
          </div>
        ) : (
          <>
            {/* Log output */}
            <div
              ref={logRef}
              className="bg-[#0c0c0c] rounded-lg p-4 max-h-[320px] overflow-y-auto mb-4"
            >
              {state.discoveryLog.map((line, i) => (
                <div key={i} className="font-mono text-[#a1a1aa]" style={{ fontSize: "12px", lineHeight: "1.8" }}>
                  {line}
                </div>
              ))}
              {isRunning && (
                <div className="font-mono text-primary flex items-center gap-2 mt-1" style={{ fontSize: "12px" }}>
                  <Loader2 className="w-3 h-3 animate-spin" />
                  Processing...
                </div>
              )}
            </div>

            {isCompleted && (
              <div className="flex items-center justify-between pt-2">
                <p className="text-muted-foreground" style={{ fontSize: "13px" }}>
                  Analysis complete. {mockDiscoveryLog.length} entries discovered.
                </p>
                <Button
                  className="h-9"
                  onClick={() => {
                    update({
                      sessionStatus: "interrogating",
                      qaItems: mockQAItems,
                      stats: {
                        ...state.stats,
                        questions: mockQAItems.length,
                        answers: mockQAItems.filter((q) => q.answer).length,
                        categories: ["architecture", "security", "performance", "integration"],
                        progress: 33,
                      },
                    });
                    advancePhase();
                  }}
                >
                  Continue to Interrogation →
                </Button>
              </div>
            )}
          </>
        )}
      </CardContent>
    </Card>
  );
}

// ─── Phase 4: Interrogation ──────────────────────────────

function Phase4Interrogation({
  state,
  update,
  advancePhase,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
}) {
  const [answerText, setAnswerText] = useState("");
  const [selectedOption, setSelectedOption] = useState("");
  const [showReasoning, setShowReasoning] = useState(false);
  const [freeTextMode, setFreeTextMode] = useState(false);

  const currentQ = state.qaItems[state.currentQuestionIdx];
  if (!currentQ) {
    return (
      <Card className="border border-border shadow-none">
        <CardContent className="p-5 text-center py-12">
          <div className="w-14 h-14 rounded-full bg-success/10 flex items-center justify-center mx-auto mb-4">
            <Check className="w-7 h-7 text-success" />
          </div>
          <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>All questions answered!</h3>
          <p className="text-muted-foreground mb-4" style={{ fontSize: "13px" }}>
            You've completed all interrogation questions. Continue to review the summary.
          </p>
          <Button
            className="h-9"
            onClick={() => {
              update({
                sessionStatus: "summarizing",
                summaryStatus: "generating",
                summaryLog: [],
                stats: { ...state.stats, progress: 55 },
              });
              advancePhase();
            }}
          >
            Continue to Summary →
          </Button>
        </CardContent>
      </Card>
    );
  }

  const hasOptions = currentQ.options && currentQ.options.length > 0;
  const showOptions = hasOptions && !freeTextMode;
  const totalQ = state.qaItems.length;
  const answeredQ = state.qaItems.filter((q) => q.answer || q.skipped).length;
  const qProgress = Math.round((answeredQ / totalQ) * 100);

  const submitAnswer = (answer: string) => {
    const newItems = [...state.qaItems];
    newItems[state.currentQuestionIdx] = { ...currentQ, answer };
    const newAnswers = newItems.filter((q) => q.answer).length;

    // Move to next unanswered, or show completion card if all handled
    let nextIdx = state.currentQuestionIdx + 1;
    while (nextIdx < newItems.length && (newItems[nextIdx].answer || newItems[nextIdx].skipped)) {
      nextIdx++;
    }
    const allHandled = newItems.every((q) => q.answer || q.skipped);

    update({
      qaItems: newItems,
      currentQuestionIdx: allHandled ? newItems.length : (nextIdx < newItems.length ? nextIdx : state.currentQuestionIdx),
      stats: {
        ...state.stats,
        answers: newAnswers,
        progress: 33 + Math.round((newAnswers / totalQ) * 22),
      },
    });
    setAnswerText("");
    setSelectedOption("");
    setFreeTextMode(false);
    setShowReasoning(false);
  };

  const skipQuestion = (reason: string) => {
    const newItems = [...state.qaItems];
    newItems[state.currentQuestionIdx] = { ...currentQ, skipped: true, skipReason: reason };

    let nextIdx = state.currentQuestionIdx + 1;
    while (nextIdx < newItems.length && (newItems[nextIdx].answer || newItems[nextIdx].skipped)) {
      nextIdx++;
    }
    const allHandled = newItems.every((q) => q.answer || q.skipped);

    update({
      qaItems: newItems,
      currentQuestionIdx: allHandled ? newItems.length : (nextIdx < newItems.length ? nextIdx : state.currentQuestionIdx),
    });
    setAnswerText("");
    setSelectedOption("");
    setShowReasoning(false);
  };

  return (
    <div className="space-y-4">
      {/* Question Card */}
      <Card className="border border-border shadow-none">
        <CardContent className="p-5">
          {/* Category + progress */}
          <div className="flex items-center justify-between mb-3">
            <span
              className={`inline-flex items-center px-2 py-0.5 rounded ${currentQ.categoryColor}`}
              style={{ fontSize: "11px", fontWeight: 600 }}
            >
              {currentQ.category}
            </span>
            <span className="text-muted-foreground" style={{ fontSize: "12px" }}>
              Question {state.currentQuestionIdx + 1} of ~{totalQ}
            </span>
          </div>
          <Progress value={qProgress} className="h-1 mb-4" />

          {/* Question text */}
          <p className="text-foreground mb-3" style={{ fontSize: "14px", lineHeight: "1.6" }}>
            {currentQ.question}
          </p>

          {/* Reasoning toggle */}
          {currentQ.reasoning && (
            <div className="mb-4">
              <button
                onClick={() => setShowReasoning(!showReasoning)}
                className="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors"
                style={{ fontSize: "12px" }}
              >
                {showReasoning ? (
                  <>Hide reasoning <ChevronUp className="w-3 h-3" /></>
                ) : (
                  <>Show reasoning <ChevronDown className="w-3 h-3" /></>
                )}
              </button>
              {showReasoning && (
                <div className="mt-2 p-3 bg-muted/50 rounded-lg text-muted-foreground" style={{ fontSize: "13px", lineHeight: "1.6" }}>
                  {currentQ.reasoning}
                </div>
              )}
            </div>
          )}

          {/* Already answered */}
          {currentQ.answer && (
            <div className="p-3 bg-success/5 border border-success/20 rounded-lg mb-4">
              <div className="flex items-center gap-1.5 mb-1">
                <Check className="w-3.5 h-3.5 text-success" />
                <span className="text-success" style={{ fontSize: "12px", fontWeight: 500 }}>Answered</span>
              </div>
              <p className="text-foreground" style={{ fontSize: "13px" }}>{currentQ.answer}</p>
            </div>
          )}

          {/* Answer area (only if not yet answered) */}
          {!currentQ.answer && !currentQ.skipped && (
            <>
              {showOptions ? (
                <div className="space-y-2 mb-4">
                  <RadioGroup value={selectedOption} onValueChange={setSelectedOption}>
                    {currentQ.options!.map((opt, i) => (
                      <label
                        key={i}
                        className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                          selectedOption === opt
                            ? "border-primary bg-primary/5"
                            : "border-border hover:bg-muted/30"
                        }`}
                      >
                        <RadioGroupItem value={opt} />
                        <span style={{ fontSize: "13px" }}>{opt}</span>
                      </label>
                    ))}
                  </RadioGroup>
                  <div className="flex items-center justify-between pt-2">
                    <button
                      onClick={() => setFreeTextMode(true)}
                      className="text-primary hover:underline"
                      style={{ fontSize: "13px" }}
                    >
                      Something else...
                    </button>
                    <Button
                      className="h-9"
                      disabled={!selectedOption}
                      onClick={() => submitAnswer(selectedOption)}
                    >
                      Confirm Selection
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="space-y-3 mb-4">
                  <Textarea
                    value={answerText}
                    onChange={(e) => setAnswerText(e.target.value)}
                    placeholder="Type your answer..."
                    className="min-h-[100px] bg-input-background"
                    style={{ fontSize: "13px" }}
                  />
                  <div className="flex items-center justify-between">
                    {hasOptions && (
                      <button
                        onClick={() => setFreeTextMode(false)}
                        className="text-primary hover:underline"
                        style={{ fontSize: "13px" }}
                      >
                        Back to options
                      </button>
                    )}
                    <div className="flex items-center gap-2 ml-auto">
                      <Button
                        className="h-9"
                        disabled={!answerText.trim()}
                        onClick={() => submitAnswer(answerText)}
                      >
                        Submit Answer
                      </Button>
                    </div>
                  </div>
                </div>
              )}

              {/* Skip controls */}
              <Separator className="my-3" />
              <div className="flex items-center justify-between">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="sm" className="h-8 gap-1.5 text-muted-foreground" style={{ fontSize: "12px" }}>
                      <SkipForward className="w-3.5 h-3.5" /> Skip
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    <DropdownMenuItem onClick={() => skipQuestion("Skip for now")}>Skip for now</DropdownMenuItem>
                    <DropdownMenuItem onClick={() => skipQuestion("I don't know yet")}>I don't know yet</DropdownMenuItem>
                    <DropdownMenuItem onClick={() => skipQuestion("Need to research first")}>Need to research first</DropdownMenuItem>
                    <DropdownMenuItem onClick={() => skipQuestion("Not applicable")}>Not applicable</DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                {answeredQ >= totalQ - 1 && (
                  <Button
                    variant="outline"
                    className="h-8"
                    style={{ fontSize: "12px" }}
                    onClick={() => {
                      update({
                        sessionStatus: "summarizing",
                        summaryStatus: "generating",
                        summaryLog: [],
                        stats: { ...state.stats, progress: 55 },
                      });
                      advancePhase();
                    }}
                  >
                    Finish & Continue →
                  </Button>
                )}
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

// ─── Phase 5: Summary ────────────────────────────────────

function Phase5Summary({
  state,
  update,
  advancePhase,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
}) {
  const [showRevision, setShowRevision] = useState(false);
  const [revisionAction, setRevisionAction] = useState("expand");
  const [revisionNotes, setRevisionNotes] = useState("");
  const summaryLogRef = useRef<HTMLDivElement>(null);
  const summaryStartedRef = useRef(false);

  // Auto-start summary generation with streaming log
  useEffect(() => {
    if (state.summaryStatus === "generating" && !summaryStartedRef.current) {
      summaryStartedRef.current = true;
      let idx = 0;
      const interval = setInterval(() => {
        if (idx < mockSummaryLog.length) {
          update({
            summaryLog: mockSummaryLog.slice(0, idx + 1),
          });
          idx++;
        } else {
          clearInterval(interval);
          update({
            summaryContent: mockSummary,
            summaryStatus: "ready",
            summaryLog: mockSummaryLog,
            stats: { ...state.stats, progress: 55 },
          });
        }
      }, 350);
      return () => clearInterval(interval);
    }
  }, [state.summaryStatus]);

  useEffect(() => {
    if (summaryLogRef.current) {
      summaryLogRef.current.scrollTop = summaryLogRef.current.scrollHeight;
    }
  }, [state.summaryLog]);

  if (state.summaryStatus === "generating") {
    return (
      <Card className="border border-primary/40 shadow-none transition-all">
        <CardContent className="p-5">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Loader2 className="w-4 h-4 animate-spin text-primary" />
              <span style={{ fontSize: "14px", fontWeight: 500 }}>Generating summary...</span>
            </div>
            <Badge className="bg-primary/10 text-primary">
              <Loader2 className="w-3 h-3 animate-spin mr-1" />
              Summarizing
            </Badge>
          </div>

          {/* Streaming log */}
          <div
            ref={summaryLogRef}
            className="bg-[#0c0c0c] rounded-lg p-4 max-h-[240px] overflow-y-auto mb-4"
          >
            {state.summaryLog.map((line, i) => (
              <div key={i} className="font-mono text-[#a1a1aa] flex items-start gap-2" style={{ fontSize: "12px", lineHeight: "1.8" }}>
                <span className="text-primary shrink-0">▸</span>
                {line}
              </div>
            ))}
            <div className="font-mono text-primary flex items-center gap-2 mt-1" style={{ fontSize: "12px" }}>
              <Loader2 className="w-3 h-3 animate-spin" />
              Processing...
            </div>
          </div>

          {/* Skeleton preview */}
          <div className="space-y-3">
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-4 w-5/6" />
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-4 w-2/3" />
          </div>
        </CardContent>
      </Card>
    );
  }

  if (state.summaryStatus === "idle") {
    return (
      <Card className="border border-border shadow-none">
        <CardContent className="p-5 text-center py-12">
          <div className="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
            <Download className="w-7 h-7 text-primary" />
          </div>
          <h2 className="mb-2" style={{ fontSize: "17px", fontWeight: 600 }}>Discovery Summary</h2>
          <p className="text-muted-foreground mb-4" style={{ fontSize: "13px" }}>
            Complete the interrogation phase to generate the summary.
          </p>
        </CardContent>
      </Card>
    );
  }

  // Parse summary into sections
  const parseSummarySections = (content: string) => {
    const sections: { title: string; lines: string[] }[] = [];
    let currentSection: { title: string; lines: string[] } | null = null;

    content.split("\n").forEach((line) => {
      if (line.startsWith("## ")) {
        if (currentSection) sections.push(currentSection);
        currentSection = { title: line.replace("## ", ""), lines: [] };
      } else if (currentSection) {
        currentSection.lines.push(line);
      }
    });
    if (currentSection) sections.push(currentSection);
    return sections;
  };

  const sections = parseSummarySections(state.summaryContent);
  const accordionSections = ["Goals", "Constraints", "Acceptance Criteria", "Open Questions", "Private Notes"];
  const inlineSections = sections.filter((s) => !accordionSections.includes(s.title));
  const collapsibleSections = sections.filter((s) => accordionSections.includes(s.title));

  const renderSectionContent = (lines: string[]) => (
    <div>
      {lines.map((line, i) => {
        if (line.startsWith("- ")) {
          const text = line.replace("- ", "");
          return (
            <div key={i} className="flex items-baseline gap-2 ml-2 mb-1.5">
              <span className="text-primary shrink-0" style={{ fontSize: "8px" }}>●</span>
              <span
                className="text-foreground"
                style={{ fontSize: "13px", lineHeight: "1.6" }}
                dangerouslySetInnerHTML={{
                  __html: text
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/`(.*?)`/g, '<code class="px-1.5 py-0.5 rounded bg-muted font-mono" style="font-size:12px">$1</code>')
                }}
              />
            </div>
          );
        }
        if (line.trim() === "") return <div key={i} className="h-2" />;
        return (
          <p
            key={i}
            className="text-foreground mb-1"
            style={{ fontSize: "13px", lineHeight: "1.6" }}
            dangerouslySetInnerHTML={{
              __html: line
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/`(.*?)`/g, '<code class="px-1.5 py-0.5 rounded bg-muted font-mono" style="font-size:12px">$1</code>')
            }}
          />
        );
      })}
    </div>
  );

  const countItems = (lines: string[]) => lines.filter((l) => l.startsWith("- ")).length;

  // Count open questions for warning
  const openQuestionsSection = collapsibleSections.find((s) => s.title === "Open Questions");
  const openQuestionCount = openQuestionsSection ? countItems(openQuestionsSection.lines) : 0;

  return (
    <Card className="border border-border shadow-none">
      <CardContent className="p-5">
        <h2 className="mb-4" style={{ fontSize: "17px", fontWeight: 600 }}>Summary</h2>

        {/* Inline sections (Overview, Architecture, etc.) — scrollable box */}
        <div className="max-h-[380px] overflow-y-auto rounded-lg border border-border bg-muted/20 p-4 mb-4">
          <div className="prose-sm max-w-none">
            {inlineSections.map((section, si) => (
              <div key={si}>
                <h3 className={`mb-2 text-foreground ${si > 0 ? "mt-4" : ""}`} style={{ fontSize: "15px", fontWeight: 600 }}>
                  {section.title}
                </h3>
                {renderSectionContent(section.lines)}
              </div>
            ))}
          </div>
        </div>

        {/* Collapsible accordion sections */}
        {collapsibleSections.length > 0 && (
          <Accordion type="multiple" className="mb-4">
            {collapsibleSections.map((section) => (
              <AccordionItem key={section.title} value={section.title} className="border border-border rounded-lg mb-2 last:mb-0 overflow-hidden">
                <AccordionTrigger className="px-4 py-3 hover:no-underline hover:bg-muted/30">
                  <div className="flex items-center gap-2">
                    <span style={{ fontSize: "14px", fontWeight: 500 }}>{section.title}</span>
                    <Badge variant="secondary" style={{ fontSize: "11px" }}>
                      {countItems(section.lines)}
                    </Badge>
                  </div>
                </AccordionTrigger>
                <AccordionContent className="px-4 pb-3">
                  {renderSectionContent(section.lines)}
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        )}

        {/* Open questions warning */}
        {openQuestionCount > 0 && (
          <div className="flex items-center gap-2 px-4 py-2.5 mb-4 rounded-lg bg-warning/10 border border-warning/20 text-warning" style={{ fontSize: "13px" }}>
            <AlertTriangle className="w-4 h-4 shrink-0" />
            Open questions remain ({openQuestionCount}). Resolve them before confirming this summary.
          </div>
        )}

        {/* Revision form */}
        {showRevision && (
          <div className="border border-border rounded-lg p-4 mb-4 bg-muted/30">
            <h4 className="mb-3" style={{ fontSize: "14px", fontWeight: 500 }}>Request Revision</h4>
            <RadioGroup value={revisionAction} onValueChange={setRevisionAction} className="mb-3">
              {["expand", "clarify", "focus", "refocus"].map((action) => (
                <label key={action} className="flex items-center gap-2">
                  <RadioGroupItem value={action} />
                  <span className="capitalize" style={{ fontSize: "13px" }}>{action}</span>
                </label>
              ))}
            </RadioGroup>
            <Textarea
              value={revisionNotes}
              onChange={(e) => setRevisionNotes(e.target.value)}
              placeholder="What should be changed?"
              className="min-h-[80px] bg-input-background mb-3"
              style={{ fontSize: "13px" }}
            />
            <div className="flex gap-2">
              <Button size="sm" className="h-8" style={{ fontSize: "12px" }}>
                Submit Revision
              </Button>
              <Button variant="ghost" size="sm" className="h-8" style={{ fontSize: "12px" }} onClick={() => setShowRevision(false)}>
                Cancel
              </Button>
            </div>
          </div>
        )}

        {/* Action bar */}
        <Separator className="my-4" />
        <div className="flex flex-wrap items-center gap-2">
          <Button
            className="h-9 bg-success hover:bg-success/90 text-success-foreground"
            onClick={() => {
              update({
                sessionStatus: "planning",
                planStatus: "generating",
                planLog: [],
                stats: { ...state.stats, progress: 60 },
              });
              advancePhase();
            }}
          >
            Confirm Summary
          </Button>
          <Button variant="outline" className="h-9" style={{ fontSize: "13px" }} onClick={() => setShowRevision(true)}>
            Revise Summary
          </Button>
          <Button
            variant="outline"
            className="h-9"
            style={{ fontSize: "13px" }}
            onClick={() => update({ currentPhase: 4, sessionStatus: "interrogating" })}
          >
            Continue Interrogation
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

// ─── Phase 6: Planning ───────────────────────────────────

function Phase6Planning({
  state,
  update,
  advancePhase,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
}) {
  const [showRevision, setShowRevision] = useState(false);
  const [revisionAction, setRevisionAction] = useState("expand");
  const [revisionNotes, setRevisionNotes] = useState("");

  const planLogRef = useRef<HTMLDivElement>(null);
  const planStartedRef = useRef(false);

  const generatePlan = () => {
    planStartedRef.current = false;
    update({ planStatus: "generating", planLog: [] });
  };

  // Auto-start plan generation with streaming log
  useEffect(() => {
    if (state.planStatus === "generating" && !planStartedRef.current) {
      planStartedRef.current = true;
      let idx = 0;
      const interval = setInterval(() => {
        if (idx < mockPlanLog.length) {
          update({
            planLog: mockPlanLog.slice(0, idx + 1),
          });
          idx++;
        } else {
          clearInterval(interval);
          update({
            planContent: mockPlan,
            planStatus: "ready",
            planLog: mockPlanLog,
            stats: { ...state.stats, progress: 67 },
          });
        }
      }, 350);
      return () => clearInterval(interval);
    }
  }, [state.planStatus]);

  useEffect(() => {
    if (planLogRef.current) {
      planLogRef.current.scrollTop = planLogRef.current.scrollHeight;
    }
  }, [state.planLog]);

  if (state.planStatus === "idle") {
    return (
      <Card className="border border-border shadow-none">
        <CardContent className="p-5 text-center py-12">
          <div className="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
            <Download className="w-7 h-7 text-primary" />
          </div>
          <h2 className="mb-2" style={{ fontSize: "17px", fontWeight: 600 }}>Implementation Plan</h2>
          <p className="text-muted-foreground mb-4" style={{ fontSize: "13px" }}>
            Confirm the summary in the previous step to begin plan generation.
          </p>
        </CardContent>
      </Card>
    );
  }

  if (state.planStatus === "generating") {
    return (
      <Card className="border border-primary/40 shadow-none transition-all">
        <CardContent className="p-5">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Loader2 className="w-4 h-4 animate-spin text-primary" />
              <span style={{ fontSize: "14px", fontWeight: 500 }}>Generating implementation plan...</span>
            </div>
            <Badge className="bg-primary/10 text-primary">
              <Loader2 className="w-3 h-3 animate-spin mr-1" />
              Planning
            </Badge>
          </div>

          {/* Streaming log */}
          <div
            ref={planLogRef}
            className="bg-[#0c0c0c] rounded-lg p-4 max-h-[280px] overflow-y-auto mb-4"
          >
            {state.planLog.map((line, i) => (
              <div key={i} className="font-mono text-[#a1a1aa] flex items-start gap-2" style={{ fontSize: "12px", lineHeight: "1.8" }}>
                <span className="text-primary shrink-0">▸</span>
                {line}
              </div>
            ))}
            <div className="font-mono text-primary flex items-center gap-2 mt-1" style={{ fontSize: "12px" }}>
              <Loader2 className="w-3 h-3 animate-spin" />
              Processing...
            </div>
          </div>

          {/* Skeleton preview */}
          <div className="space-y-3">
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-4/5" />
            <Skeleton className="h-4 w-3/4" />
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="border border-border shadow-none">
      <CardContent className="p-5">
        <h2 className="mb-4" style={{ fontSize: "17px", fontWeight: 600 }}>Implementation Plan</h2>

        {/* Plan content — scrollable box + accordion sections */}
        {(() => {
          // Parse plan into main vs collapsible sections (Risks, Assumptions)
          const planAccordionTitles = ["Risks", "Assumptions"];
          const planSections: { title: string; lines: string[] }[] = [];
          let curSec: { title: string; lines: string[] } | null = null;

          state.planContent.split("\n").forEach((line) => {
            if (line.startsWith("## ")) {
              if (curSec) planSections.push(curSec);
              curSec = { title: line.replace("## ", ""), lines: [] };
            } else if (curSec) {
              curSec.lines.push(line);
            }
          });
          if (curSec) planSections.push(curSec);

          const mainPlanSections = planSections.filter((s) => !planAccordionTitles.includes(s.title));
          const collapsiblePlanSections = planSections.filter((s) => planAccordionTitles.includes(s.title));

          const renderPlanLines = (lines: string[]) =>
            lines.map((line, i) => {
              if (line.startsWith("### ")) {
                return (
                  <h4 key={i} className="mt-3 mb-1.5 text-foreground" style={{ fontSize: "14px", fontWeight: 600 }}>
                    {line.replace("### ", "")}
                  </h4>
                );
              }
              if (line.startsWith("- ")) {
                const text = line.replace("- ", "");
                return (
                  <div key={i} className="flex items-baseline gap-2 ml-2 mb-1">
                    <span className="text-primary shrink-0" style={{ fontSize: "8px" }}>●</span>
                    <span className="text-foreground" style={{ fontSize: "13px", lineHeight: "1.6" }}
                      dangerouslySetInnerHTML={{ __html: text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') }}
                    />
                  </div>
                );
              }
              if (/^\d+\./.test(line)) {
                return (
                  <div key={i} className="flex items-start gap-2 ml-2 mb-1">
                    <span className="text-muted-foreground shrink-0" style={{ fontSize: "13px" }}>
                      {line.match(/^\d+/)?.[0]}.
                    </span>
                    <span className="text-foreground" style={{ fontSize: "13px", lineHeight: "1.6" }}>
                      {line.replace(/^\d+\.\s*/, "")}
                    </span>
                  </div>
                );
              }
              if (line.trim() === "") return <div key={i} className="h-2" />;
              return (
                <p key={i} className="text-foreground mb-1" style={{ fontSize: "13px", lineHeight: "1.6" }}
                  dangerouslySetInnerHTML={{ __html: line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') }}
                />
              );
            });

          const countPlanItems = (lines: string[]) => {
            const bullets = lines.filter((l) => l.startsWith("- ")).length;
            return bullets || lines.filter((l) => l.trim()).length;
          };

          return (
            <>
              <div className="max-h-[380px] overflow-y-auto rounded-lg border border-border bg-muted/20 p-4 mb-4">
                <div className="prose-sm max-w-none">
                  {mainPlanSections.map((section, si) => (
                    <div key={si}>
                      <h3 className={`mb-2 text-foreground ${si > 0 ? "mt-5" : ""}`} style={{ fontSize: "15px", fontWeight: 600 }}>
                        {section.title}
                      </h3>
                      {renderPlanLines(section.lines)}
                    </div>
                  ))}
                </div>
              </div>

              {collapsiblePlanSections.length > 0 && (
                <Accordion type="multiple" className="mb-4">
                  {collapsiblePlanSections.map((section) => (
                    <AccordionItem key={section.title} value={section.title} className="border border-border rounded-lg mb-2 last:mb-0 overflow-hidden">
                      <AccordionTrigger className="px-4 py-3 hover:no-underline hover:bg-muted/30">
                        <div className="flex items-center gap-2">
                          <span style={{ fontSize: "14px", fontWeight: 500 }}>{section.title}</span>
                          <Badge variant="secondary" style={{ fontSize: "11px" }}>
                            {countPlanItems(section.lines)}
                          </Badge>
                        </div>
                      </AccordionTrigger>
                      <AccordionContent className="px-4 pb-3">
                        {renderPlanLines(section.lines)}
                      </AccordionContent>
                    </AccordionItem>
                  ))}
                </Accordion>
              )}
            </>
          );
        })()}

        {/* Revision form */}
        {showRevision && (
          <div className="border border-border rounded-lg p-4 mb-4 bg-muted/30">
            <h4 className="mb-3" style={{ fontSize: "14px", fontWeight: 500 }}>Request Plan Revision</h4>
            <RadioGroup value={revisionAction} onValueChange={setRevisionAction} className="mb-3">
              {["expand", "clarify", "reorganize", "focus"].map((action) => (
                <label key={action} className="flex items-center gap-2">
                  <RadioGroupItem value={action} />
                  <span className="capitalize" style={{ fontSize: "13px" }}>{action}</span>
                </label>
              ))}
            </RadioGroup>
            <Textarea
              value={revisionNotes}
              onChange={(e) => setRevisionNotes(e.target.value)}
              placeholder="Revision notes..."
              className="min-h-[80px] bg-input-background mb-3"
              style={{ fontSize: "13px" }}
            />
            <div className="flex gap-2">
              <Button size="sm" className="h-8" style={{ fontSize: "12px" }}>
                Request Revision
              </Button>
              <Button variant="ghost" size="sm" className="h-8" style={{ fontSize: "12px" }} onClick={() => setShowRevision(false)}>
                Cancel
              </Button>
            </div>
          </div>
        )}

        <Separator className="my-4" />
        <div className="flex flex-wrap items-center gap-2">
          <Button
            className="h-9 bg-success hover:bg-success/90 text-success-foreground"
            onClick={() => {
              update({
                planStatus: "approved",
                sessionStatus: "build_rules",
                stats: { ...state.stats, progress: 72 },
              });
              advancePhase();
            }}
          >
            Approve Plan
          </Button>
          <Button variant="outline" className="h-9" style={{ fontSize: "13px" }} onClick={() => setShowRevision(true)}>
            Revise Plan
          </Button>
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button variant="outline" className="h-9 text-destructive border-destructive/30" style={{ fontSize: "13px" }}>
                Regenerate Plan
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Regenerate plan?</AlertDialogTitle>
                <AlertDialogDescription>
                  This will discard the current plan and generate a new one. Continue?
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction onClick={generatePlan}>Regenerate</AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
          <Button variant="ghost" className="h-9 gap-1.5" style={{ fontSize: "13px" }}>
            <Download className="w-3.5 h-3.5" /> Export Plan
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

// ─── Phase 7: Rules ──────────────────────────────────────

function Phase7Rules({
  state,
  update,
  advancePhase,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
}) {
  const [newRuleText, setNewRuleText] = useState("");
  const [showBulkEditor, setShowBulkEditor] = useState(false);

  const loadMockRules = () => {
    update({ rulesContent: mockRules });
  };

  const addRule = () => {
    if (!newRuleText.trim()) return;
    const entry: RuleEntry = {
      id: Date.now().toString(),
      text: newRuleText.trim(),
    };
    update({ ruleEntries: [...state.ruleEntries, entry] });
    setNewRuleText("");
  };

  const removeRule = (id: string) => {
    update({ ruleEntries: state.ruleEntries.filter((r) => r.id !== id) });
  };

  const totalRules = state.ruleEntries.length + (state.rulesContent.trim() ? 1 : 0);

  return (
    <Card className="border border-border shadow-none">
      <CardContent className="p-5">
        <h2 className="mb-1" style={{ fontSize: "17px", fontWeight: 600 }}>Project Rules</h2>
        <p className="text-muted-foreground mb-5" style={{ fontSize: "13px" }}>
          Define rules and constraints for build task generation. Rules are optional — you can skip this step if you prefer.
        </p>

        {/* Add individual rule */}
        <div className="flex gap-2 mb-4">
          <Input
            value={newRuleText}
            onChange={(e) => setNewRuleText(e.target.value)}
            placeholder="Add a rule (e.g. All new components must use Composition API)"
            className="h-9 bg-input-background flex-1"
            style={{ fontSize: "13px" }}
            onKeyDown={(e) => { if (e.key === "Enter") addRule(); }}
          />
          <Button size="sm" className="h-9 gap-1.5 shrink-0" onClick={addRule} disabled={!newRuleText.trim()}>
            <Plus className="w-3.5 h-3.5" /> Add
          </Button>
        </div>

        {/* Individual rules list */}
        {state.ruleEntries.length > 0 && (
          <div className="space-y-2 mb-4">
            {state.ruleEntries.map((rule, idx) => (
              <div key={rule.id} className="flex items-center gap-2 p-2.5 bg-muted/50 rounded-lg">
                <span className="text-muted-foreground shrink-0" style={{ fontSize: "12px", fontWeight: 600 }}>
                  {idx + 1}.
                </span>
                <span className="text-foreground flex-1" style={{ fontSize: "13px" }}>
                  {rule.text}
                </span>
                <button
                  onClick={() => removeRule(rule.id)}
                  className="ml-auto text-muted-foreground hover:text-destructive transition-colors shrink-0"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            ))}
          </div>
        )}

        {/* Toggle bulk editor */}
        <button
          onClick={() => setShowBulkEditor(!showBulkEditor)}
          className="flex items-center gap-1.5 text-muted-foreground hover:text-foreground transition-colors mb-4"
          style={{ fontSize: "13px" }}
        >
          <Upload className="w-3.5 h-3.5" />
          {showBulkEditor ? "Hide bulk editor & upload" : "Bulk editor & file upload"}
          {showBulkEditor ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
        </button>

        {showBulkEditor && (
          <>
            <Textarea
              value={state.rulesContent}
              onChange={(e) => update({ rulesContent: e.target.value })}
              placeholder="Paste or type rules in bulk — one per line or use markdown format..."
              className="min-h-[160px] bg-input-background font-mono mb-4 resize-y"
              style={{ fontSize: "12px", lineHeight: "1.7" }}
            />

            {!state.rulesContent && (
              <div className="flex justify-center mb-4">
                <Button variant="outline" size="sm" className="h-8" style={{ fontSize: "12px" }} onClick={loadMockRules}>
                  Load sample rules
                </Button>
              </div>
            )}

            {/* File upload area */}
            <div className="border-2 border-dashed border-border rounded-lg p-6 text-center mb-4">
              <Upload className="w-6 h-6 text-muted-foreground mx-auto mb-2" />
              <p className="text-muted-foreground" style={{ fontSize: "13px" }}>
                Drag & drop rule files here, or click to browse
              </p>
              <p className="text-muted-foreground" style={{ fontSize: "11px" }}>
                .md, .txt, .yaml supported
              </p>
            </div>
          </>
        )}

        <div className="flex items-center justify-between pt-2">
          {totalRules === 0 && (
            <span className="text-muted-foreground" style={{ fontSize: "12px" }}>
              Rules are optional — you can proceed without any.
            </span>
          )}
          <Button
            className="h-9 ml-auto"
            onClick={() => {
              update({
                sessionStatus: "build_tasks",
                buildTasks: mockBuildTasks,
                stats: { ...state.stats, progress: 78 },
              });
              advancePhase();
            }}
          >
            Generate Build Tasks →
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

// ─── Phase 8: Tasks ──────────────────────────────────────

function Phase8Tasks({
  state,
  update,
  advancePhase,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
  advancePhase: () => void;
}) {
  const [expandedTask, setExpandedTask] = useState<string | null>(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [newTitle, setNewTitle] = useState("");
  const [newDesc, setNewDesc] = useState("");

  const taskStatusStyles: Record<string, string> = {
    queued: "bg-muted text-muted-foreground",
    running: "bg-primary/10 text-primary",
    completed: "bg-success/10 text-success",
    failed: "bg-destructive/10 text-destructive",
  };

  const addTask = () => {
    if (!newTitle.trim()) return;
    const task: BuildTask = {
      id: Date.now().toString(),
      title: newTitle,
      description: newDesc,
      instructions: "",
      status: "queued",
    };
    update({ buildTasks: [...state.buildTasks, task] });
    setNewTitle("");
    setNewDesc("");
    setShowAddForm(false);
  };

  const deleteTask = (id: string) => {
    update({ buildTasks: state.buildTasks.filter((t) => t.id !== id) });
  };

  return (
    <Card className="border border-border shadow-none">
      <CardContent className="p-5">
        <div className="flex items-center justify-between mb-4">
          <h2 style={{ fontSize: "17px", fontWeight: 600 }}>Build Tasks</h2>
          <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
            {state.buildTasks.length} tasks ready
          </span>
        </div>

        {/* Task list */}
        <div className="space-y-3 mb-4">
          {state.buildTasks.map((task) => {
            const isExpanded = expandedTask === task.id;
            return (
              <div key={task.id} className="border border-border rounded-lg overflow-hidden">
                <div
                  className="flex items-center justify-between p-3.5 cursor-pointer hover:bg-muted/30 transition-colors"
                  onClick={() => setExpandedTask(isExpanded ? null : task.id)}
                >
                  <div className="flex items-center gap-3 flex-1 min-w-0">
                    <span
                      className={`inline-flex items-center px-2 py-0.5 rounded-full shrink-0 ${taskStatusStyles[task.status]}`}
                      style={{ fontSize: "11px", fontWeight: 500 }}
                    >
                      {task.status === "running" && <Loader2 className="w-3 h-3 animate-spin mr-1" />}
                      {task.status}
                    </span>
                    <span className="text-foreground truncate" style={{ fontSize: "13px", fontWeight: 500 }}>
                      {task.title}
                    </span>
                  </div>
                  <div className="flex items-center gap-1.5 shrink-0 ml-2">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7"
                      onClick={(e) => { e.stopPropagation(); }}
                    >
                      <Pencil className="w-3.5 h-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-7 w-7 text-destructive"
                      onClick={(e) => {
                        e.stopPropagation();
                        deleteTask(task.id);
                      }}
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </Button>
                    {isExpanded ? (
                      <ChevronUp className="w-4 h-4 text-muted-foreground" />
                    ) : (
                      <ChevronDown className="w-4 h-4 text-muted-foreground" />
                    )}
                  </div>
                </div>
                {isExpanded && (
                  <div className="border-t border-border p-3.5 bg-muted/20">
                    <p className="text-foreground mb-3" style={{ fontSize: "13px", lineHeight: "1.6" }}>
                      {task.description}
                    </p>
                    {task.instructions && (
                      <div className="bg-[#0c0c0c] rounded-md p-3 font-mono text-[#a1a1aa]" style={{ fontSize: "12px", lineHeight: "1.8" }}>
                        {task.instructions.split("\n").map((line, i) => (
                          <div key={i}>{line}</div>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Add task */}
        {showAddForm ? (
          <div className="border border-border rounded-lg p-4 mb-4 bg-muted/30">
            <Input
              value={newTitle}
              onChange={(e) => setNewTitle(e.target.value)}
              placeholder="Task title"
              className="h-9 bg-input-background mb-2"
              style={{ fontSize: "13px" }}
            />
            <Textarea
              value={newDesc}
              onChange={(e) => setNewDesc(e.target.value)}
              placeholder="Task description"
              className="min-h-[60px] bg-input-background mb-3"
              style={{ fontSize: "13px" }}
            />
            <div className="flex gap-2">
              <Button size="sm" className="h-8" onClick={addTask} disabled={!newTitle.trim()}>
                Save Task
              </Button>
              <Button variant="ghost" size="sm" className="h-8" onClick={() => setShowAddForm(false)}>
                Cancel
              </Button>
            </div>
          </div>
        ) : (
          <Button variant="outline" className="h-9 gap-1.5 mb-4" style={{ fontSize: "13px" }} onClick={() => setShowAddForm(true)}>
            <Plus className="w-3.5 h-3.5" /> Add Task
          </Button>
        )}

        <Separator className="my-4" />
        <div className="flex items-center justify-between">
          <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
            {state.buildTasks.length} tasks ready for execution
          </span>
          <Button
            className="h-9 bg-success hover:bg-success/90 text-success-foreground"
            onClick={() => {
              update({
                tasksApproved: true,
                sessionStatus: "build_executing",
                stats: { ...state.stats, progress: 85 },
              });
              advancePhase();
            }}
          >
            Approve Build Tasks
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

// ─── Phase 9: Build Execution ────────────────────────────

function Phase9Build({
  state,
  update,
}: {
  state: WizardState;
  update: (p: Partial<WizardState>) => void;
}) {
  const [expandedLog, setExpandedLog] = useState<string | null>(null);

  const completedTasks = state.buildTasks.filter((t) => t.status === "completed").length;
  const runningTask = state.buildTasks.find((t) => t.status === "running");
  const failedTasks = state.buildTasks.filter((t) => t.status === "failed").length;
  const totalTasks = state.buildTasks.length;
  const overallProgress = Math.round((completedTasks / totalTasks) * 100);
  const isCompleted = completedTasks === totalTasks;
  const isRunning = state.buildStatus === "running";

  const startBuild = () => {
    update({ buildStatus: "running" });
    // Simulate sequential task execution
    let taskIdx = 0;
    const tasks = [...state.buildTasks];
    const runNext = () => {
      // Find next queued task
      while (taskIdx < tasks.length && tasks[taskIdx].status !== "queued") {
        taskIdx++;
      }
      if (taskIdx >= tasks.length) {
        update({
          buildTasks: tasks,
          buildStatus: "completed",
          sessionStatus: "completed",
          buildProgress: 100,
          stats: { ...state.stats, progress: 100 },
        });
        return;
      }
      tasks[taskIdx] = {
        ...tasks[taskIdx],
        status: "running",
        startedAt: new Date().toISOString(),
        log: "⟳ Starting execution...",
      };
      update({ buildTasks: [...tasks], buildProgress: Math.round(((completedTasks + taskIdx) / totalTasks) * 100) });

      setTimeout(() => {
        tasks[taskIdx] = {
          ...tasks[taskIdx],
          status: "completed",
          completedAt: new Date().toISOString(),
          log: (tasks[taskIdx].log || "") + "\n✓ Task completed successfully",
        };
        update({ buildTasks: [...tasks] });
        taskIdx++;
        runNext();
      }, 1200);
    };
    runNext();
  };

  const taskStatusStyles: Record<string, string> = {
    queued: "bg-muted text-muted-foreground",
    running: "bg-primary/10 text-primary",
    completed: "bg-success/10 text-success",
    failed: "bg-destructive/10 text-destructive",
  };

  return (
    <div className="space-y-4">
      {/* Execution header */}
      <Card className="border border-border shadow-none">
        <CardContent className="p-5">
          <div className="flex items-center justify-between mb-3">
            <h2 style={{ fontSize: "17px", fontWeight: 600 }}>Build Execution</h2>
            <Badge
              className={
                isCompleted
                  ? "bg-success/10 text-success"
                  : isRunning
                  ? "bg-primary/10 text-primary"
                  : state.buildStatus === "paused"
                  ? "bg-warning/10 text-warning"
                  : "bg-muted text-muted-foreground"
              }
            >
              {isRunning && <Loader2 className="w-3 h-3 animate-spin mr-1" />}
              {isCompleted
                ? "Completed"
                : isRunning
                ? "Executing..."
                : state.buildStatus === "paused"
                ? "Paused"
                : "Ready"}
            </Badge>
          </div>

          <div className="flex items-center justify-between mb-1.5">
            <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
              {completedTasks} of {totalTasks} tasks complete
            </span>
            <span className="text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
              {overallProgress}%
            </span>
          </div>
          <Progress value={overallProgress} className="h-2 mb-4" />

          {/* Action buttons */}
          <div className="flex flex-wrap gap-2">
            {state.buildStatus === "idle" && (
              <Button className="h-9" onClick={startBuild}>
                <Play className="w-4 h-4 mr-1.5" /> Start Build
              </Button>
            )}
            {isRunning && (
              <Button
                variant="outline"
                className="h-9 text-warning border-warning/30"
                onClick={() => update({ buildStatus: "paused" })}
              >
                <Pause className="w-4 h-4 mr-1.5" /> Pause Build
              </Button>
            )}
            {state.buildStatus === "paused" && (
              <Button className="h-9" onClick={() => update({ buildStatus: "running" })}>
                <Play className="w-4 h-4 mr-1.5" /> Resume Build
              </Button>
            )}
            {failedTasks > 0 && (
              <Button variant="outline" className="h-9" style={{ fontSize: "13px" }}>
                <RotateCcw className="w-3.5 h-3.5 mr-1.5" /> Retry Failed ({failedTasks})
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Success banner */}
      {isCompleted && (
        <div className="flex items-center gap-3 p-4 rounded-lg bg-success/10 border border-success/20">
          <Check className="w-5 h-5 text-success shrink-0" />
          <div>
            <p className="text-success" style={{ fontSize: "14px", fontWeight: 500 }}>
              Build completed successfully!
            </p>
            <p className="text-muted-foreground" style={{ fontSize: "12px" }}>
              {completedTasks} tasks completed · 0 failed
            </p>
          </div>
          <div className="ml-auto flex gap-2">
            <Link to="/tools/discovery">
              <Button variant="outline" size="sm" className="h-8" style={{ fontSize: "12px" }}>
                Back to Sessions
              </Button>
            </Link>
          </div>
        </div>
      )}

      {/* Task execution list */}
      <div className="space-y-2">
        {state.buildTasks.map((task) => {
          const isExp = expandedLog === task.id;
          return (
            <div
              key={task.id}
              className={`border rounded-lg overflow-hidden transition-colors ${
                task.status === "running"
                  ? "border-primary/40"
                  : task.status === "failed"
                  ? "border-destructive/40"
                  : "border-border"
              }`}
            >
              <div
                className="flex items-center justify-between p-3 cursor-pointer hover:bg-muted/30 transition-colors"
                onClick={() => setExpandedLog(isExp ? null : task.id)}
              >
                <div className="flex items-center gap-3 min-w-0">
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded-full shrink-0 ${taskStatusStyles[task.status]}`}
                    style={{ fontSize: "11px", fontWeight: 500 }}
                  >
                    {task.status === "running" && <Loader2 className="w-3 h-3 animate-spin mr-1" />}
                    {task.status}
                  </span>
                  <span className="text-foreground truncate" style={{ fontSize: "13px", fontWeight: 500 }}>
                    {task.title}
                  </span>
                </div>
                <div className="flex items-center gap-2 text-muted-foreground shrink-0 ml-2">
                  {task.completedAt && task.startedAt && (
                    <span className="font-mono" style={{ fontSize: "11px" }}>
                      {Math.round(
                        (new Date(task.completedAt).getTime() -
                          new Date(task.startedAt).getTime()) /
                          1000
                      )}
                      s
                    </span>
                  )}
                  {isExp ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                </div>
              </div>
              {isExp && task.log && (
                <div className="border-t border-border bg-[#0c0c0c] p-3 max-h-[200px] overflow-y-auto">
                  {task.log.split("\n").map((line, i) => (
                    <div
                      key={i}
                      className={`font-mono ${
                        line.startsWith("✓")
                          ? "text-[#4ade80]"
                          : line.startsWith("✗")
                          ? "text-[#f87171]"
                          : "text-[#a1a1aa]"
                      }`}
                      style={{ fontSize: "12px", lineHeight: "1.8" }}
                    >
                      {line}
                    </div>
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
