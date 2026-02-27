import { useState } from "react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "./ui/table";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";
import { Link, useNavigate } from "react-router";
import {
  Clock,
  MoreHorizontal,
  Plus,
  Search,
  Filter,
  ChevronRight,
  ChevronLeft,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  GitBranch,
  Eye,
  Pencil,
  Trash2,
} from "lucide-react";

interface DelegationGraph {
  id: string;
  name: string;
  status: "active" | "completed" | "pending_verification" | "failed";
  tasks: number;
  completed: number;
  delegatees: number;
  created: string;
  updatedAt: string;
}

const allGraphs: DelegationGraph[] = [
  {
    id: "1",
    name: "Feature: Auth Module",
    status: "active",
    tasks: 8,
    completed: 5,
    delegatees: 3,
    created: "2026-02-24",
    updatedAt: "2026-02-26",
  },
  {
    id: "2",
    name: "Refactor: Payment Gateway",
    status: "completed",
    tasks: 12,
    completed: 12,
    delegatees: 4,
    created: "2026-02-20",
    updatedAt: "2026-02-23",
  },
  {
    id: "3",
    name: "Bugfix: Session Timeout",
    status: "pending_verification",
    tasks: 3,
    completed: 2,
    delegatees: 1,
    created: "2026-02-25",
    updatedAt: "2026-02-26",
  },
  {
    id: "4",
    name: "Feature: Dashboard Analytics",
    status: "active",
    tasks: 15,
    completed: 9,
    delegatees: 5,
    created: "2026-02-18",
    updatedAt: "2026-02-26",
  },
  {
    id: "5",
    name: "Migration: Database Schema v3",
    status: "failed",
    tasks: 6,
    completed: 4,
    delegatees: 2,
    created: "2026-02-15",
    updatedAt: "2026-02-17",
  },
  {
    id: "6",
    name: "Feature: Notification System",
    status: "completed",
    tasks: 10,
    completed: 10,
    delegatees: 3,
    created: "2026-02-12",
    updatedAt: "2026-02-19",
  },
  {
    id: "7",
    name: "Refactor: API Middleware",
    status: "active",
    tasks: 7,
    completed: 2,
    delegatees: 2,
    created: "2026-02-22",
    updatedAt: "2026-02-26",
  },
  {
    id: "8",
    name: "Feature: User Permissions",
    status: "pending_verification",
    tasks: 5,
    completed: 5,
    delegatees: 2,
    created: "2026-02-10",
    updatedAt: "2026-02-14",
  },
  {
    id: "9",
    name: "Bugfix: Rate Limiter Edge Case",
    status: "completed",
    tasks: 2,
    completed: 2,
    delegatees: 1,
    created: "2026-02-08",
    updatedAt: "2026-02-09",
  },
  {
    id: "10",
    name: "Feature: Export Reports",
    status: "active",
    tasks: 11,
    completed: 3,
    delegatees: 4,
    created: "2026-02-23",
    updatedAt: "2026-02-26",
  },
  {
    id: "11",
    name: "Infra: CI/CD Pipeline Update",
    status: "completed",
    tasks: 4,
    completed: 4,
    delegatees: 1,
    created: "2026-02-05",
    updatedAt: "2026-02-07",
  },
  {
    id: "12",
    name: "Feature: Webhook Integration",
    status: "active",
    tasks: 9,
    completed: 6,
    delegatees: 3,
    created: "2026-02-21",
    updatedAt: "2026-02-26",
  },
];

const statusConfig: Record<
  string,
  {
    label: string;
    color: string;
    bgColor: string;
    icon: typeof CheckCircle2;
  }
> = {
  active: {
    label: "Active",
    color: "text-primary",
    bgColor: "bg-primary/10",
    icon: Clock,
  },
  completed: {
    label: "Completed",
    color: "text-success",
    bgColor: "bg-success/10",
    icon: CheckCircle2,
  },
  pending_verification: {
    label: "Needs Verification",
    color: "text-warning",
    bgColor: "bg-warning/10",
    icon: AlertTriangle,
  },
  failed: {
    label: "Failed",
    color: "text-destructive",
    bgColor: "bg-destructive/10",
    icon: XCircle,
  },
};

const PAGE_SIZE = 6;

export function DelegationPage() {
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [currentPage, setCurrentPage] = useState(1);
  const navigate = useNavigate();

  // Filter
  const filtered = allGraphs.filter((g) => {
    const matchesSearch =
      !search || g.name.toLowerCase().includes(search.toLowerCase());
    const matchesStatus =
      statusFilter === "all" || g.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  // Pagination
  const totalPages = Math.ceil(filtered.length / PAGE_SIZE);
  const paged = filtered.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE
  );

  // Reset page on filter change
  const handleSearchChange = (value: string) => {
    setSearch(value);
    setCurrentPage(1);
  };
  const handleStatusChange = (value: string) => {
    setStatusFilter(value);
    setCurrentPage(1);
  };

  // Summary counts
  const activeCt = allGraphs.filter((g) => g.status === "active").length;
  const completedCt = allGraphs.filter((g) => g.status === "completed").length;
  const pendingCt = allGraphs.filter(
    (g) => g.status === "pending_verification"
  ).length;
  const failedCt = allGraphs.filter((g) => g.status === "failed").length;

  return (
    <div>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
          <h1>Delegation Graphs</h1>
          <p
            className="text-muted-foreground mt-0.5"
            style={{ fontSize: "14px" }}
          >
            Manage task delegation graphs and human verification workflows
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" className="h-9 gap-2 shrink-0" asChild>
            <Link to="/delegation/profiles">
              Delegatee Profiles
            </Link>
          </Button>
          <Button className="h-9 gap-2 shrink-0" asChild>
            <Link to="/delegation/create">
              <Plus className="w-4 h-4" /> New Graph
            </Link>
          </Button>
        </div>
      </div>

      {/* Summary stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        {[
          { label: "Active", count: activeCt, color: "text-primary", bg: "bg-primary/10" },
          { label: "Completed", count: completedCt, color: "text-success", bg: "bg-success/10" },
          { label: "Needs Verification", count: pendingCt, color: "text-warning", bg: "bg-warning/10" },
          { label: "Failed", count: failedCt, color: "text-destructive", bg: "bg-destructive/10" },
        ].map((stat) => (
          <div
            key={stat.label}
            className="flex items-center gap-3 p-3 bg-card border border-border rounded-lg"
          >
            <div className={`w-9 h-9 rounded-md ${stat.bg} flex items-center justify-center`}>
              <span className={stat.color} style={{ fontSize: "16px", fontWeight: 600 }}>
                {stat.count}
              </span>
            </div>
            <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
              {stat.label}
            </span>
          </div>
        ))}
      </div>

      {/* Search & Filter */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-4">
        <div className="relative w-full sm:max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder="Search graphs..."
            className="pl-9 h-9 bg-input-background"
          />
        </div>
        <div className="flex items-center gap-2">
          <Filter className="w-4 h-4 text-muted-foreground" />
          <Select value={statusFilter} onValueChange={handleStatusChange}>
            <SelectTrigger className="h-9 w-[180px] bg-input-background">
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Statuses</SelectItem>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="pending_verification">
                Needs Verification
              </SelectItem>
              <SelectItem value="failed">Failed</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Table */}
      <div className="rounded-lg border border-border overflow-hidden bg-card mb-4">
        <Table>
          <TableHeader>
            <TableRow className="bg-muted/50">
              <TableHead style={{ fontSize: "12px" }}>Name</TableHead>
              <TableHead style={{ fontSize: "12px" }}>Status</TableHead>
              <TableHead style={{ fontSize: "12px" }}>Tasks</TableHead>
              <TableHead style={{ fontSize: "12px" }} className="hidden sm:table-cell">
                Created
              </TableHead>
              <TableHead style={{ fontSize: "12px" }} className="text-right">
                Actions
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {paged.length === 0 ? (
              <TableRow>
                <TableCell
                  colSpan={5}
                  className="text-center py-8 text-muted-foreground"
                  style={{ fontSize: "13px" }}
                >
                  No delegation graphs found.
                </TableCell>
              </TableRow>
            ) : (
              paged.map((graph) => {
                const status = statusConfig[graph.status];
                const StatusIcon = status.icon;
                const progress = Math.round(
                  (graph.completed / graph.tasks) * 100
                );

                return (
                  <TableRow key={graph.id}>
                    <TableCell>
                      <div className="flex items-center gap-2.5">
                        <GitBranch className="w-4 h-4 text-primary shrink-0" />
                        <div>
                          <Link
                            to={`/delegation/${graph.id}`}
                            className="text-foreground hover:text-primary transition-colors"
                            style={{ fontSize: "13px", fontWeight: 500 }}
                          >
                            {graph.name}
                          </Link>
                          <div
                            className="text-muted-foreground"
                            style={{ fontSize: "11px" }}
                          >
                            {graph.delegatees} delegatee
                            {graph.delegatees !== 1 ? "s" : ""}
                          </div>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell>
                      <span
                        className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full ${status.bgColor} ${status.color}`}
                        style={{ fontSize: "11px", fontWeight: 500 }}
                      >
                        <StatusIcon className="w-3 h-3" />
                        {status.label}
                      </span>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-3 min-w-[140px]">
                        <div className="flex-1">
                          <div className="flex items-center justify-between mb-1">
                            <span
                              className="text-muted-foreground"
                              style={{ fontSize: "11px" }}
                            >
                              {graph.completed}/{graph.tasks}
                            </span>
                            <span
                              className="text-muted-foreground"
                              style={{ fontSize: "11px" }}
                            >
                              {progress}%
                            </span>
                          </div>
                          <div className="w-full h-1.5 bg-muted rounded-full overflow-hidden">
                            <div
                              className={`h-full rounded-full transition-all ${
                                graph.status === "failed"
                                  ? "bg-destructive"
                                  : progress === 100
                                  ? "bg-success"
                                  : "bg-primary"
                              }`}
                              style={{ width: `${progress}%` }}
                            />
                          </div>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="hidden sm:table-cell">
                      <span
                        className="text-muted-foreground"
                        style={{ fontSize: "13px" }}
                      >
                        {graph.created}
                      </span>
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="h-7 w-7"
                          >
                            <MoreHorizontal className="w-4 h-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem className="gap-2" onSelect={() => navigate(`/delegation/${graph.id}`)}>
                            <Eye className="w-3.5 h-3.5" /> View Graph
                          </DropdownMenuItem>
                          <DropdownMenuItem className="gap-2">
                            <Pencil className="w-3.5 h-3.5" /> Edit
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem className="gap-2 text-destructive">
                            <Trash2 className="w-3.5 h-3.5" /> Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                );
              })
            )}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between">
          <span
            className="text-muted-foreground"
            style={{ fontSize: "13px" }}
          >
            Showing {(currentPage - 1) * PAGE_SIZE + 1}–
            {Math.min(currentPage * PAGE_SIZE, filtered.length)} of{" "}
            {filtered.length} graphs
          </span>
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="icon"
              className="h-8 w-8"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage((p) => p - 1)}
            >
              <ChevronLeft className="w-4 h-4" />
            </Button>
            {Array.from({ length: totalPages }, (_, i) => i + 1).map(
              (page) => (
                <Button
                  key={page}
                  variant={page === currentPage ? "default" : "outline"}
                  size="icon"
                  className="h-8 w-8"
                  onClick={() => setCurrentPage(page)}
                  style={{ fontSize: "13px" }}
                >
                  {page}
                </Button>
              )
            )}
            <Button
              variant="outline"
              size="icon"
              className="h-8 w-8"
              disabled={currentPage === totalPages}
              onClick={() => setCurrentPage((p) => p + 1)}
            >
              <ChevronRight className="w-4 h-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}