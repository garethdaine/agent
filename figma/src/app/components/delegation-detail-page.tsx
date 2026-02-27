import { useState } from "react";
import { useParams, Link, useNavigate } from "react-router";
import {
  ArrowLeft,
  GitBranch,
  CheckCircle2,
  Clock,
  AlertTriangle,
  XCircle,
  Play,
  Copy,
  Trash2,
  MoreHorizontal,
  Eye,
} from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";
import { Progress } from "./ui/progress";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";

const graphData = {
  id: "1",
  name: "Feature: Auth Module",
  description: "Implement complete authentication module with login, registration, password reset, and 2FA support.",
  status: "active" as const,
  priority: "high",
  tasks: 8,
  completed: 5,
  delegatees: ["Claude (Backend)", "Codex (Frontend)", "Claude (Tests)"],
  created: "2026-02-24T10:00:00Z",
  updatedAt: "2026-02-26T14:35:00Z",
};

const tasks = [
  { id: "T1", title: "Database schema for users/sessions", status: "completed", assignee: "Claude (Backend)", duration: "12m 34s", completedAt: "2026-02-24T11:00:00Z" },
  { id: "T2", title: "User registration endpoint", status: "completed", assignee: "Claude (Backend)", duration: "8m 22s", completedAt: "2026-02-24T12:30:00Z" },
  { id: "T3", title: "Login & JWT token flow", status: "completed", assignee: "Claude (Backend)", duration: "15m 07s", completedAt: "2026-02-24T14:00:00Z" },
  { id: "T4", title: "Password reset flow", status: "completed", assignee: "Claude (Backend)", duration: "9m 45s", completedAt: "2026-02-25T09:00:00Z" },
  { id: "T5", title: "2FA TOTP implementation", status: "completed", assignee: "Claude (Backend)", duration: "18m 12s", completedAt: "2026-02-25T11:00:00Z" },
  { id: "T6", title: "Frontend auth forms", status: "active", assignee: "Codex (Frontend)", duration: "—", completedAt: null },
  { id: "T7", title: "Integration tests", status: "pending_verification", assignee: "Claude (Tests)", duration: "—", completedAt: null },
  { id: "T8", title: "E2E test suite", status: "queued", assignee: "Claude (Tests)", duration: "—", completedAt: null },
];

const events = [
  { time: "14:35", message: "Task T6 progress update: 3/5 components completed", type: "info" },
  { time: "14:20", message: "Task T7 submitted for human verification", type: "warning" },
  { time: "13:45", message: "Task T5 completed successfully", type: "success" },
  { time: "12:30", message: "Task T6 assigned to Codex (Frontend)", type: "info" },
  { time: "11:15", message: "Task T4 completed successfully", type: "success" },
  { time: "10:00", message: "Graph execution resumed", type: "info" },
];

const statusConfig: Record<string, { label: string; color: string; bgColor: string }> = {
  completed: { label: "Completed", color: "text-success", bgColor: "bg-success/10" },
  active: { label: "In Progress", color: "text-primary", bgColor: "bg-primary/10" },
  pending_verification: { label: "Needs Verification", color: "text-warning", bgColor: "bg-warning/10" },
  queued: { label: "Queued", color: "text-muted-foreground", bgColor: "bg-muted" },
  failed: { label: "Failed", color: "text-destructive", bgColor: "bg-destructive/10" },
};

export function DelegationDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const progress = Math.round((graphData.completed / graphData.tasks) * 100);

  return (
    <div>
      <div className="flex items-center gap-3 mb-6">
        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => navigate("/delegation")}>
          <ArrowLeft className="w-4 h-4" />
        </Button>
        <div className="flex-1">
          <div className="flex items-center gap-3">
            <GitBranch className="w-5 h-5 text-primary" />
            <h1>{graphData.name}</h1>
            <Badge className="bg-primary/10 text-primary border-primary/20">Active</Badge>
            <Badge variant="outline" className="text-warning border-warning/20">High Priority</Badge>
          </div>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: "14px" }}>
            {graphData.description}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" className="h-9 gap-2" style={{ fontSize: "13px" }}>
            <Play className="w-3.5 h-3.5" /> Start
          </Button>
          <Button variant="outline" className="h-9 gap-2" style={{ fontSize: "13px" }}>
            <Copy className="w-3.5 h-3.5" /> Clone
          </Button>
          <Button variant="outline" className="h-9 gap-2 text-destructive border-destructive/30" style={{ fontSize: "13px" }}>
            <Trash2 className="w-3.5 h-3.5" /> Cancel
          </Button>
        </div>
      </div>

      {/* Progress Summary */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <Card className="border border-border shadow-none">
          <CardContent className="p-4">
            <div className="text-muted-foreground uppercase tracking-wider mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>Progress</div>
            <div className="flex items-center gap-3">
              <span style={{ fontSize: "22px", fontWeight: 700 }}>{progress}%</span>
              <Progress value={progress} className="flex-1 h-2" />
            </div>
            <div className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>{graphData.completed}/{graphData.tasks} tasks complete</div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4">
            <div className="text-muted-foreground uppercase tracking-wider mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>Delegatees</div>
            <div style={{ fontSize: "22px", fontWeight: 700 }}>{graphData.delegatees.length}</div>
            <div className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>agents assigned</div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4">
            <div className="text-muted-foreground uppercase tracking-wider mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>Created</div>
            <div style={{ fontSize: "14px", fontWeight: 500 }}>Feb 24, 2026</div>
            <div className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>10:00 UTC</div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4">
            <div className="text-muted-foreground uppercase tracking-wider mb-1" style={{ fontSize: "11px", fontWeight: 600 }}>Last Updated</div>
            <div style={{ fontSize: "14px", fontWeight: 500 }}>Feb 26, 2026</div>
            <div className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>14:35 UTC</div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Tasks List */}
        <div className="lg:col-span-2">
          <Card className="border border-border shadow-none">
            <div className="px-5 py-4 border-b border-border">
              <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Tasks</h3>
            </div>
            <div className="divide-y divide-border">
              {tasks.map((task) => {
                const sc = statusConfig[task.status] || statusConfig.queued;
                return (
                  <div key={task.id} className="px-5 py-3 flex items-center justify-between hover:bg-muted/30 transition-colors">
                    <div className="flex items-center gap-3 flex-1 min-w-0">
                      <span className="text-muted-foreground font-mono shrink-0" style={{ fontSize: "12px" }}>{task.id}</span>
                      <div className="min-w-0">
                        <Link
                          to={`/delegation/${id}/tasks/${task.id}`}
                          className="text-foreground hover:text-primary transition-colors truncate block"
                          style={{ fontSize: "13px", fontWeight: 500 }}
                        >
                          {task.title}
                        </Link>
                        <div className="text-muted-foreground" style={{ fontSize: "11px" }}>
                          {task.assignee} · {task.duration}
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-full ${sc.bgColor} ${sc.color}`} style={{ fontSize: "11px", fontWeight: 500 }}>
                        {sc.label}
                      </span>
                      {task.status === "pending_verification" && (
                        <Button variant="outline" size="sm" className="h-7 gap-1" style={{ fontSize: "11px" }} asChild>
                          <Link to={`/delegation/${id}/tasks/${task.id}/approve`}>
                            <Eye className="w-3 h-3" /> Review
                          </Link>
                        </Button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </Card>
        </div>

        {/* Event Log */}
        <div>
          <Card className="border border-border shadow-none">
            <div className="px-5 py-4 border-b border-border">
              <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Event Log</h3>
            </div>
            <div className="divide-y divide-border">
              {events.map((event, i) => (
                <div key={i} className="px-5 py-3">
                  <div className="flex items-center gap-2 mb-0.5">
                    <span className="text-muted-foreground font-mono" style={{ fontSize: "11px" }}>{event.time}</span>
                    <span className={`w-1.5 h-1.5 rounded-full ${
                      event.type === "success" ? "bg-success" : event.type === "warning" ? "bg-warning" : "bg-primary"
                    }`} />
                  </div>
                  <p className="text-foreground" style={{ fontSize: "12px" }}>{event.message}</p>
                </div>
              ))}
            </div>
          </Card>

          {/* Delegatees */}
          <Card className="border border-border shadow-none mt-4">
            <div className="px-5 py-4 border-b border-border">
              <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Delegatees</h3>
            </div>
            <div className="p-5 space-y-2">
              {graphData.delegatees.map((d) => (
                <div key={d} className="flex items-center gap-2 p-2 bg-muted/50 rounded-lg">
                  <div className="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center">
                    <span className="text-primary" style={{ fontSize: "10px", fontWeight: 600 }}>{d[0]}</span>
                  </div>
                  <span style={{ fontSize: "13px", fontWeight: 500 }}>{d}</span>
                </div>
              ))}
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}
