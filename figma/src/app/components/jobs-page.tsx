import { useState } from "react";
import { Link, useNavigate } from "react-router";
import {
  Search,
  Plus,
  Play,
  Pencil,
  Trash2,
  ToggleLeft,
  ToggleRight,
  MoreHorizontal,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Card } from "./ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";

type JobTab = "all" | "user" | "build";

const mockJobs = [
  {
    id: "1",
    name: "Daily Report Generator",
    description: "Generates daily analytics reports",
    enabled: true,
    runner: "codex",
    cron: "0 9 * * *",
    timezone: "UTC",
    nextRun: "2026-02-27T09:00:00Z",
    lastRun: "2026-02-26T09:00:00Z",
    lastStatus: "succeeded",
    type: "user",
  },
  {
    id: "2",
    name: "Database Cleanup",
    description: "Removes stale records older than 30 days",
    enabled: true,
    runner: "codex",
    cron: "0 2 * * 0",
    timezone: "America/New_York",
    nextRun: "2026-03-01T02:00:00Z",
    lastRun: "2026-02-23T02:00:00Z",
    lastStatus: "succeeded",
    type: "user",
  },
  {
    id: "3",
    name: "Interrogation Build S1 T01",
    description: "Database Migrations and Models for Messenger Control Plane",
    enabled: false,
    runner: "claude",
    cron: "0 0 1 1 0",
    timezone: "UTC",
    nextRun: null,
    lastRun: "2026-02-25T16:36:00Z",
    lastStatus: "failed",
    type: "build",
  },
  {
    id: "4",
    name: "API Health Checker",
    description: "Pings external API endpoints every 5 minutes",
    enabled: true,
    runner: "codex",
    cron: "*/5 * * * *",
    timezone: "UTC",
    nextRun: "2026-02-26T10:25:00Z",
    lastRun: "2026-02-26T10:20:00Z",
    lastStatus: "succeeded",
    type: "user",
  },
  {
    id: "5",
    name: "Feature Build: Auth Module",
    description: "Builds authentication module from requirements",
    enabled: true,
    runner: "claude",
    cron: "0 8 * * 1-5",
    timezone: "UTC",
    nextRun: "2026-02-27T08:00:00Z",
    lastRun: "2026-02-26T08:00:00Z",
    lastStatus: "succeeded",
    type: "build",
  },
];

export function JobsPage() {
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [runnerFilter, setRunnerFilter] = useState("all");
  const [activeTab, setActiveTab] = useState<JobTab>("all");
  const navigate = useNavigate();

  const filtered = mockJobs.filter((job) => {
    if (activeTab === "user" && job.type !== "user") return false;
    if (activeTab === "build" && job.type !== "build") return false;
    if (search && !job.name.toLowerCase().includes(search.toLowerCase())) return false;
    if (statusFilter === "enabled" && !job.enabled) return false;
    if (statusFilter === "disabled" && job.enabled) return false;
    if (runnerFilter !== "all" && job.runner !== runnerFilter) return false;
    return true;
  });

  const tabs: { key: JobTab; label: string }[] = [
    { key: "all", label: "All Jobs" },
    { key: "user", label: "User Jobs" },
    { key: "build", label: "Build Jobs" },
  ];

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1>Agent Jobs</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: '14px' }}>
            Manage scheduled agent tasks and automation
          </p>
        </div>
        <Button asChild className="h-9 gap-2">
          <Link to="/jobs/create">
            <Plus className="w-4 h-4" />
            New Job
          </Link>
        </Button>
      </div>

      {/* Filters */}
      <Card className="border border-border shadow-none p-4 mb-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1 max-w-xs">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search name / description"
              className="pl-9 h-9 bg-input-background"
            />
          </div>
          <Select value={statusFilter} onValueChange={setStatusFilter}>
            <SelectTrigger className="w-36 h-9">
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All statuses</SelectItem>
              <SelectItem value="enabled">Enabled</SelectItem>
              <SelectItem value="disabled">Disabled</SelectItem>
            </SelectContent>
          </Select>
          <Select value={runnerFilter} onValueChange={setRunnerFilter}>
            <SelectTrigger className="w-36 h-9">
              <SelectValue placeholder="All runners" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All runners</SelectItem>
              <SelectItem value="codex">Codex</SelectItem>
              <SelectItem value="claude">Claude</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </Card>

      {/* Tabs */}
      <div className="flex items-center gap-1 mb-4">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-3 py-1.5 rounded-md transition-colors ${
              activeTab === tab.key
                ? "bg-primary text-primary-foreground"
                : "text-muted-foreground hover:bg-muted hover:text-foreground"
            }`}
            style={{ fontSize: '13px', fontWeight: 500 }}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Jobs Table */}
      <Card className="border border-border shadow-none overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-border bg-muted/50">
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Name</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Enabled</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Runner</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider hidden lg:table-cell" style={{ fontSize: '11px', fontWeight: 600 }}>Cron / TZ</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider hidden md:table-cell" style={{ fontSize: '11px', fontWeight: 600 }}>Next Run</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider hidden md:table-cell" style={{ fontSize: '11px', fontWeight: 600 }}>Last Run</th>
                <th className="text-right px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan={7} className="text-center py-12 text-muted-foreground" style={{ fontSize: '14px' }}>
                    No jobs found.
                  </td>
                </tr>
              ) : (
                filtered.map((job) => (
                  <tr key={job.id} className="border-b border-border last:border-0 hover:bg-muted/30 transition-colors">
                    <td className="px-5 py-3">
                      <div style={{ fontSize: '13px', fontWeight: 500 }}>{job.name}</div>
                      <div className="text-muted-foreground" style={{ fontSize: '12px' }}>{job.description}</div>
                    </td>
                    <td className="px-5 py-3">
                      <span
                        className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full ${
                          job.enabled
                            ? "bg-success/10 text-success"
                            : "bg-muted text-muted-foreground"
                        }`}
                        style={{ fontSize: '12px', fontWeight: 500 }}
                      >
                        <span className={`w-1.5 h-1.5 rounded-full ${job.enabled ? "bg-success" : "bg-muted-foreground"}`} />
                        {job.enabled ? "Active" : "Disabled"}
                      </span>
                    </td>
                    <td className="px-5 py-3">
                      <span className="inline-flex items-center px-2 py-0.5 rounded bg-secondary text-secondary-foreground font-mono" style={{ fontSize: '12px' }}>
                        {job.runner}
                      </span>
                    </td>
                    <td className="px-5 py-3 hidden lg:table-cell">
                      <div className="font-mono" style={{ fontSize: '12px' }}>{job.cron}</div>
                      <div className="text-muted-foreground" style={{ fontSize: '11px' }}>{job.timezone}</div>
                    </td>
                    <td className="px-5 py-3 hidden md:table-cell text-muted-foreground" style={{ fontSize: '12px' }}>
                      {job.nextRun ? new Date(job.nextRun).toLocaleString() : "—"}
                    </td>
                    <td className="px-5 py-3 hidden md:table-cell">
                      <div className="text-muted-foreground" style={{ fontSize: '12px' }}>
                        {job.lastRun ? new Date(job.lastRun).toLocaleString() : "—"}
                      </div>
                      {job.lastStatus && (
                        <span
                          className={`${
                            job.lastStatus === "succeeded" ? "text-success" : "text-destructive"
                          }`}
                          style={{ fontSize: '11px', fontWeight: 500 }}
                        >
                          {job.lastStatus}
                        </span>
                      )}
                    </td>
                    <td className="px-5 py-3 text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" className="h-8 w-8">
                            <MoreHorizontal className="w-4 h-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem className="gap-2">
                            <Play className="w-3.5 h-3.5" /> Run Now
                          </DropdownMenuItem>
                          <DropdownMenuItem className="gap-2" onSelect={() => navigate(`/jobs/${job.id}/edit`)}>
                            <Pencil className="w-3.5 h-3.5" /> Edit
                          </DropdownMenuItem>
                          <DropdownMenuItem className="gap-2">
                            {job.enabled ? (
                              <><ToggleLeft className="w-3.5 h-3.5" /> Disable</>
                            ) : (
                              <><ToggleRight className="w-3.5 h-3.5" /> Enable</>
                            )}
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem className="gap-2 text-destructive">
                            <Trash2 className="w-3.5 h-3.5" /> Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </Card>

      {/* Pagination */}
      <div className="flex items-center justify-between mt-4">
        <span className="text-muted-foreground" style={{ fontSize: '13px' }}>
          Showing page 1 of 1 ({filtered.length} total)
        </span>
        <div className="flex gap-1">
          <Button variant="outline" size="sm" disabled className="h-8 gap-1">
            <ChevronLeft className="w-3.5 h-3.5" /> Prev
          </Button>
          <Button variant="outline" size="sm" disabled className="h-8 gap-1">
            Next <ChevronRight className="w-3.5 h-3.5" />
          </Button>
        </div>
      </div>
    </div>
  );
}