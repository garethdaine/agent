import { useState } from "react";
import { Link } from "react-router";
import {
  Search,
  Plus,
  Settings,
  MoreHorizontal,
  RotateCcw,
  Pencil,
  Trash2,
  ExternalLink,
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

const sessions = [
  {
    id: "1",
    name: "Adversarial Reviewer",
    directory: "/Users/garethdaine/Code/agent",
    runner: "claude",
    type: "feature",
    status: "completed",
    updated: "2026-02-26T09:37:53.000Z",
  },
  {
    id: "2",
    name: "Agent Org Layer (AI Workforce)",
    directory: "/Users/garethdaine/Code/agent",
    runner: "claude",
    type: "feature",
    status: "setup",
    updated: "2026-02-25T10:17:27.000Z",
  },
  {
    id: "3",
    name: "Consolidated Implementation for Agent Platform v1",
    directory: "/Users/garethdaine/Code/agent",
    runner: "claude",
    type: "feature",
    status: "completed",
    updated: "2026-02-25T09:56:14.000Z",
  },
  {
    id: "4",
    name: "Messenger Control Plane",
    directory: "/Users/garethdaine/Code/agent",
    runner: "claude",
    type: "feature",
    status: "completed",
    updated: "2026-02-21T10:00:58.000Z",
  },
];

const statusStyles: Record<string, string> = {
  completed: "bg-success/10 text-success",
  setup: "bg-muted text-muted-foreground",
  running: "bg-primary/10 text-primary",
  failed: "bg-destructive/10 text-destructive",
};

export function DiscoveryPage() {
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");

  const filtered = sessions.filter((s) => {
    if (search && !s.name.toLowerCase().includes(search.toLowerCase())) return false;
    if (statusFilter !== "all" && s.status !== statusFilter) return false;
    return true;
  });

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1>Requirements Discovery</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: '14px' }}>
            AI-led interrogation sessions for requirements gathering
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" className="h-9 gap-2" style={{ fontSize: '13px' }} asChild>
            <Link to="/tools/discovery/settings">
              <Settings className="w-4 h-4" /> Settings
            </Link>
          </Button>
          <Link to="/tools/discovery/create">
            <Button className="h-9 gap-2">
              <Plus className="w-4 h-4" /> New Session
            </Button>
          </Link>
        </div>
      </div>

      {/* Filters */}
      <Card className="border border-border shadow-none p-4 mb-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1 max-w-xs">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search sessions"
              className="pl-9 h-9 bg-input-background"
            />
          </div>
          <Select value={statusFilter} onValueChange={setStatusFilter}>
            <SelectTrigger className="w-36 h-9"><SelectValue placeholder="All statuses" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All statuses</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="setup">Setup</SelectItem>
              <SelectItem value="running">Running</SelectItem>
              <SelectItem value="failed">Failed</SelectItem>
            </SelectContent>
          </Select>
          <Select defaultValue="all">
            <SelectTrigger className="w-36 h-9"><SelectValue placeholder="All types" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All types</SelectItem>
              <SelectItem value="feature">Feature</SelectItem>
              <SelectItem value="bugfix">Bugfix</SelectItem>
            </SelectContent>
          </Select>
          <Select defaultValue="all">
            <SelectTrigger className="w-36 h-9"><SelectValue placeholder="All runners" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All runners</SelectItem>
              <SelectItem value="claude">Claude</SelectItem>
              <SelectItem value="codex">Codex</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </Card>

      {/* Table */}
      <Card className="border border-border shadow-none overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-border bg-muted/50">
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Session</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider hidden md:table-cell" style={{ fontSize: '11px', fontWeight: 600 }}>Runner</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider hidden md:table-cell" style={{ fontSize: '11px', fontWeight: 600 }}>Type</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Status</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider hidden lg:table-cell" style={{ fontSize: '11px', fontWeight: 600 }}>Updated</th>
                <th className="text-right px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((session) => (
                <tr key={session.id} className="border-b border-border last:border-0 hover:bg-muted/30 transition-colors">
                  <td className="px-5 py-3">
                    <Link to="/tools/discovery/wizard" className="hover:text-primary transition-colors">
                      <div style={{ fontSize: '13px', fontWeight: 500 }}>{session.name}</div>
                    </Link>
                    <div className="text-muted-foreground font-mono" style={{ fontSize: '11px' }}>{session.directory}</div>
                  </td>
                  <td className="px-5 py-3 hidden md:table-cell">
                    <span className="inline-flex items-center px-2 py-0.5 rounded bg-secondary text-secondary-foreground font-mono" style={{ fontSize: '12px' }}>
                      {session.runner}
                    </span>
                  </td>
                  <td className="px-5 py-3 hidden md:table-cell" style={{ fontSize: '13px' }}>{session.type}</td>
                  <td className="px-5 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full ${statusStyles[session.status] || "bg-muted text-muted-foreground"}`} style={{ fontSize: '12px', fontWeight: 500 }}>
                      {session.status}
                    </span>
                  </td>
                  <td className="px-5 py-3 hidden lg:table-cell text-muted-foreground font-mono" style={{ fontSize: '12px' }}>
                    {session.updated}
                  </td>
                  <td className="px-5 py-3 text-right">
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="h-8 w-8">
                          <MoreHorizontal className="w-4 h-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem className="gap-2"><RotateCcw className="w-3.5 h-3.5" /> Restart</DropdownMenuItem>
                        <DropdownMenuItem className="gap-2"><Pencil className="w-3.5 h-3.5" /> Rename</DropdownMenuItem>
                        <DropdownMenuItem className="gap-2"><Settings className="w-3.5 h-3.5" /> Settings</DropdownMenuItem>
                        <DropdownMenuItem className="gap-2"><ExternalLink className="w-3.5 h-3.5" /> Open</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="gap-2 text-destructive"><Trash2 className="w-3.5 h-3.5" /> Delete</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

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