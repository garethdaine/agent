import { useParams, Link, useNavigate } from "react-router";
import {
  ArrowLeft,
  CheckCircle2,
  Clock,
  Terminal,
  FileText,
  AlertTriangle,
  ExternalLink,
} from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";
import { Progress } from "./ui/progress";

const taskData = {
  id: "T7",
  graphId: "1",
  graphName: "Feature: Auth Module",
  title: "Integration tests",
  description: "Write comprehensive integration tests for all authentication endpoints including registration, login, password reset, and 2FA flows.",
  status: "pending_verification",
  assignee: "Claude (Tests)",
  priority: "high",
  created: "2026-02-25T14:00:00Z",
  started: "2026-02-25T14:02:00Z",
  duration: "22m 15s",
  subtasks: [
    { name: "Registration endpoint tests", done: true },
    { name: "Login flow tests", done: true },
    { name: "Password reset tests", done: true },
    { name: "2FA challenge tests", done: true },
    { name: "Session management tests", done: false },
  ],
  output: `✓ 42 tests passed
✗ 3 tests failed
  - test_2fa_expired_token: Expected 401, got 403
  - test_session_concurrent_limit: Timeout after 30s
  - test_password_reset_reuse: Expected error, got success

Coverage: 87.3% statements, 82.1% branches`,
  events: [
    { time: "14:22", message: "Test suite execution completed with 3 failures" },
    { time: "14:20", message: "Running E2E test suite..." },
    { time: "14:15", message: "Unit tests completed: 38/38 passed" },
    { time: "14:10", message: "Setting up test database fixtures" },
    { time: "14:05", message: "Installing test dependencies" },
    { time: "14:02", message: "Task execution started" },
  ],
};

const subtasksDone = taskData.subtasks.filter((s) => s.done).length;
const subtaskProgress = Math.round((subtasksDone / taskData.subtasks.length) * 100);

export function DelegationTaskPage() {
  const { graphId, taskId } = useParams();
  const navigate = useNavigate();

  return (
    <div>
      <div className="flex items-center gap-3 mb-6">
        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => navigate(`/delegation/${graphId}`)}>
          <ArrowLeft className="w-4 h-4" />
        </Button>
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-1">
            <Link to={`/delegation/${graphId}`} className="text-muted-foreground hover:text-primary transition-colors" style={{ fontSize: "12px" }}>
              {taskData.graphName}
            </Link>
            <span className="text-muted-foreground" style={{ fontSize: "12px" }}>/</span>
            <span className="text-muted-foreground font-mono" style={{ fontSize: "12px" }}>{taskData.id}</span>
          </div>
          <h1>{taskData.title}</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: "14px" }}>
            {taskData.description}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Badge className="bg-warning/10 text-warning border-warning/20">Needs Verification</Badge>
          {taskData.status === "pending_verification" && (
            <Button className="h-9 gap-2" asChild>
              <Link to={`/delegation/${graphId}/tasks/${taskId}/approve`}>
                Review & Approve
              </Link>
            </Button>
          )}
        </div>
      </div>

      {/* Meta Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <Card className="border border-border shadow-none">
          <CardContent className="p-4">
            <div className="text-muted-foreground uppercase tracking-wider mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>Assignee</div>
            <div style={{ fontSize: "14px", fontWeight: 500 }}>{taskData.assignee}</div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4">
            <div className="text-muted-foreground uppercase tracking-wider mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>Duration</div>
            <div className="font-mono" style={{ fontSize: "14px", fontWeight: 500 }}>{taskData.duration}</div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4">
            <div className="text-muted-foreground uppercase tracking-wider mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>Priority</div>
            <div className="text-warning" style={{ fontSize: "14px", fontWeight: 500 }}>High</div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4">
            <div className="text-muted-foreground uppercase tracking-wider mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>Subtasks</div>
            <div style={{ fontSize: "14px", fontWeight: 500 }}>{subtasksDone}/{taskData.subtasks.length}</div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div className="lg:col-span-2 space-y-4">
          {/* Subtasks */}
          <Card className="border border-border shadow-none">
            <div className="px-5 py-4 border-b border-border flex items-center justify-between">
              <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Subtasks</h3>
              <span className="text-muted-foreground" style={{ fontSize: "12px" }}>{subtaskProgress}% complete</span>
            </div>
            <CardContent className="p-5">
              <Progress value={subtaskProgress} className="h-2 mb-4" />
              <div className="space-y-2">
                {taskData.subtasks.map((st, i) => (
                  <div key={i} className="flex items-center gap-3 p-2 rounded-lg hover:bg-muted/30">
                    {st.done ? (
                      <CheckCircle2 className="w-4 h-4 text-success shrink-0" />
                    ) : (
                      <Clock className="w-4 h-4 text-muted-foreground shrink-0" />
                    )}
                    <span className={st.done ? "text-muted-foreground line-through" : "text-foreground"} style={{ fontSize: "13px" }}>
                      {st.name}
                    </span>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Output */}
          <Card className="border border-border shadow-none">
            <div className="px-5 py-4 border-b border-border flex items-center gap-2">
              <Terminal className="w-4 h-4 text-primary" />
              <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Task Output</h3>
            </div>
            <div className="bg-[#0f172a] text-[#38bdf8] p-4 font-mono rounded-b-lg overflow-x-auto" style={{ fontSize: "12px", lineHeight: 1.6 }}>
              {taskData.output.split("\n").map((line, i) => (
                <div key={i} className={
                  line.startsWith("✓") ? "text-emerald-400" :
                  line.startsWith("✗") || line.startsWith("  -") ? "text-red-400" :
                  "text-sky-300"
                }>
                  {line}
                </div>
              ))}
            </div>
          </Card>
        </div>

        {/* Event Timeline */}
        <div>
          <Card className="border border-border shadow-none">
            <div className="px-5 py-4 border-b border-border">
              <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Activity</h3>
            </div>
            <div className="p-5">
              <div className="space-y-4">
                {taskData.events.map((event, i) => (
                  <div key={i} className="flex gap-3">
                    <div className="flex flex-col items-center">
                      <div className={`w-2 h-2 rounded-full mt-1.5 ${i === 0 ? "bg-warning" : "bg-muted-foreground/50"}`} />
                      {i < taskData.events.length - 1 && <div className="w-px flex-1 bg-border mt-1" />}
                    </div>
                    <div className="pb-4">
                      <span className="text-muted-foreground font-mono" style={{ fontSize: "11px" }}>{event.time}</span>
                      <p className="text-foreground mt-0.5" style={{ fontSize: "12px" }}>{event.message}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}
