import { useState } from "react";
import { Link, useNavigate } from "react-router";
import {
  Plus,
  Search,
  Pencil,
  Trash2,
  MoreHorizontal,
  Bot,
  Shield,
  Zap,
} from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";

interface DelegateeProfile {
  id: string;
  name: string;
  runner: string;
  permissionProfile: string;
  description: string;
  usedInGraphs: number;
  createdAt: string;
}

const profiles: DelegateeProfile[] = [
  {
    id: "1",
    name: "Claude (Backend)",
    runner: "claude",
    permissionProfile: "full",
    description: "Backend development agent for API endpoints, database operations, and server-side logic.",
    usedInGraphs: 4,
    createdAt: "2026-02-10",
  },
  {
    id: "2",
    name: "Codex (Frontend)",
    runner: "codex",
    permissionProfile: "interactive",
    description: "Frontend development agent for React components, styling, and UI integration.",
    usedInGraphs: 3,
    createdAt: "2026-02-12",
  },
  {
    id: "3",
    name: "Claude (Tests)",
    runner: "claude",
    permissionProfile: "readonly",
    description: "Testing agent for unit tests, integration tests, and E2E test suites.",
    usedInGraphs: 5,
    createdAt: "2026-02-14",
  },
  {
    id: "4",
    name: "Claude (DevOps)",
    runner: "claude",
    permissionProfile: "full",
    description: "Infrastructure agent for CI/CD pipelines, Docker configs, and deployment scripts.",
    usedInGraphs: 2,
    createdAt: "2026-02-18",
  },
  {
    id: "5",
    name: "Codex (Docs)",
    runner: "codex",
    permissionProfile: "interactive",
    description: "Documentation agent for API docs, README files, and inline code documentation.",
    usedInGraphs: 1,
    createdAt: "2026-02-20",
  },
];

const permColors: Record<string, string> = {
  full: "bg-destructive/10 text-destructive",
  interactive: "bg-warning/10 text-warning",
  readonly: "bg-success/10 text-success",
};

export function DelegateeProfilesPage() {
  const [search, setSearch] = useState("");
  const navigate = useNavigate();

  const filtered = profiles.filter(
    (p) => !search || p.name.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div>
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
          <h1>Delegatee Profiles</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: "14px" }}>
            Manage agent profiles used in delegation graphs
          </p>
        </div>
        <Button className="h-9 gap-2 shrink-0" asChild>
          <Link to="/delegation/profiles/create">
            <Plus className="w-4 h-4" /> New Profile
          </Link>
        </Button>
      </div>

      <div className="mb-4">
        <div className="relative max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search profiles..."
            className="pl-9 h-9 bg-input-background"
          />
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {filtered.length === 0 ? (
          <Card className="border border-border shadow-none col-span-full">
            <CardContent className="p-12 text-center">
              <Bot className="w-8 h-8 text-muted-foreground mx-auto mb-3" />
              <p className="text-muted-foreground" style={{ fontSize: "14px" }}>No delegatee profiles found.</p>
              <Button className="mt-4 h-9 gap-2" asChild>
                <Link to="/delegation/profiles/create">
                  <Plus className="w-4 h-4" /> Create Profile
                </Link>
              </Button>
            </CardContent>
          </Card>
        ) : (
          filtered.map((profile) => (
            <Card key={profile.id} className="border border-border shadow-none hover:shadow-sm transition-shadow">
              <CardContent className="p-5">
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                      <Bot className="w-5 h-5 text-primary" />
                    </div>
                    <div>
                      <div style={{ fontSize: "14px", fontWeight: 600 }}>{profile.name}</div>
                      <div className="flex items-center gap-2 mt-0.5">
                        <Badge variant="secondary" className="font-mono" style={{ fontSize: "11px" }}>
                          {profile.runner}
                        </Badge>
                        <Badge className={`${permColors[profile.permissionProfile] || "bg-muted text-muted-foreground"}`} style={{ fontSize: "11px" }}>
                          <Shield className="w-3 h-3 mr-1" />
                          {profile.permissionProfile}
                        </Badge>
                      </div>
                    </div>
                  </div>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="icon" className="h-7 w-7">
                        <MoreHorizontal className="w-4 h-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem className="gap-2" onSelect={() => navigate(`/delegation/profiles/${profile.id}/edit`)}>
                        <Pencil className="w-3.5 h-3.5" /> Edit
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem className="gap-2 text-destructive">
                        <Trash2 className="w-3.5 h-3.5" /> Delete
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
                <p className="text-muted-foreground mb-3" style={{ fontSize: "13px" }}>
                  {profile.description}
                </p>
                <div className="flex items-center justify-between text-muted-foreground" style={{ fontSize: "12px" }}>
                  <span className="flex items-center gap-1">
                    <Zap className="w-3 h-3" />
                    Used in {profile.usedInGraphs} graph{profile.usedInGraphs !== 1 ? "s" : ""}
                  </span>
                  <span>Created {profile.createdAt}</span>
                </div>
              </CardContent>
            </Card>
          ))
        )}
      </div>
    </div>
  );
}