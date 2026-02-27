import { useState } from "react";
import {
  Activity,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Timer,
  Heart,
  RefreshCw,
  ChevronDown,
  TrendingUp,
  TrendingDown,
} from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";

const kpiData = [
  {
    label: "Runs Today",
    value: "12",
    change: "+3 vs yesterday",
    trend: "up",
    icon: Activity,
    color: "text-primary",
    bgColor: "bg-primary/8",
  },
  {
    label: "Success Rate (24h)",
    value: "92%",
    change: "+4% from last period",
    trend: "up",
    icon: CheckCircle2,
    color: "text-success",
    bgColor: "bg-success/8",
  },
  {
    label: "Avg Duration (24h)",
    value: "1m 42s",
    change: "-18s improvement",
    trend: "up",
    icon: Timer,
    color: "text-primary",
    bgColor: "bg-primary/8",
  },
  {
    label: "Backlog Count",
    value: "0",
    change: "No pending items",
    trend: "neutral",
    icon: Clock,
    color: "text-muted-foreground",
    bgColor: "bg-muted",
  },
  {
    label: "Oldest Queued Age",
    value: "0s",
    change: "Queue is clear",
    trend: "neutral",
    icon: AlertTriangle,
    color: "text-muted-foreground",
    bgColor: "bg-muted",
  },
  {
    label: "Scheduler Health",
    value: "Healthy",
    change: "Age: 3.41s",
    trend: "up",
    icon: Heart,
    color: "text-success",
    bgColor: "bg-success/8",
  },
];

const recentRuns = [
  { id: "#48", job: "Job 37", status: "succeeded", duration: "1m 23s", time: "09:35 UTC" },
  { id: "#47", job: "Job 36", status: "succeeded", duration: "2m 05s", time: "09:32 UTC" },
  { id: "#46", job: "Job 35", status: "succeeded", duration: "0m 58s", time: "09:28 UTC" },
  { id: "#45", job: "Job 34", status: "succeeded", duration: "1m 42s", time: "09:24 UTC" },
  { id: "#44", job: "Job 33", status: "failed", duration: "3m 12s", time: "09:20 UTC" },
  { id: "#43", job: "Job 33", status: "succeeded", duration: "1m 15s", time: "08:43 UTC" },
];

export function DashboardPage() {
  const [timeWindow, setTimeWindow] = useState("Last 24h");
  const [refreshing, setRefreshing] = useState(false);

  const handleRefresh = () => {
    setRefreshing(true);
    setTimeout(() => setRefreshing(false), 1000);
  };

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1>Dashboard</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: '14px' }}>
            System overview and key metrics
          </p>
        </div>
        <div className="flex items-center gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" className="h-9 gap-2" style={{ fontSize: '13px' }}>
                {timeWindow}
                <ChevronDown className="w-3.5 h-3.5" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {["Last 1h", "Last 6h", "Last 24h", "Last 7d"].map((tw) => (
                <DropdownMenuItem key={tw} onClick={() => setTimeWindow(tw)}>
                  {tw}
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
          <Button
            variant="outline"
            className="h-9 gap-2"
            onClick={handleRefresh}
            style={{ fontSize: '13px' }}
          >
            <RefreshCw className={`w-3.5 h-3.5 ${refreshing ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* KPI Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        {kpiData.map((kpi) => {
          const Icon = kpi.icon;
          return (
            <Card key={kpi.label} className="border border-border shadow-none hover:shadow-sm transition-shadow">
              <CardContent className="p-5">
                <div className="flex items-start justify-between mb-3">
                  <span className="text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>
                    {kpi.label}
                  </span>
                  <div className={`w-8 h-8 rounded-lg ${kpi.bgColor} flex items-center justify-center`}>
                    <Icon className={`w-4 h-4 ${kpi.color}`} />
                  </div>
                </div>
                <div className={`mb-1 ${kpi.value === "Healthy" ? "text-success" : "text-foreground"}`} style={{ fontSize: '28px', fontWeight: 700, lineHeight: 1.2 }}>
                  {kpi.value}
                </div>
                <div className="flex items-center gap-1 text-muted-foreground" style={{ fontSize: '12px' }}>
                  {kpi.trend === "up" && <TrendingUp className="w-3 h-3 text-success" />}
                  {kpi.trend === "down" && <TrendingDown className="w-3 h-3 text-destructive" />}
                  {kpi.change}
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Recent Runs Table */}
      <Card className="border border-border shadow-none">
        <div className="px-5 py-4 border-b border-border">
          <h3>Recent Runs</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-border bg-muted/50">
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Run</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Job</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Status</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Duration</th>
                <th className="text-left px-5 py-3 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Time</th>
              </tr>
            </thead>
            <tbody>
              {recentRuns.map((run) => (
                <tr key={run.id + run.job} className="border-b border-border last:border-0 hover:bg-muted/30 transition-colors">
                  <td className="px-5 py-3 font-mono" style={{ fontSize: '13px' }}>{run.id}</td>
                  <td className="px-5 py-3" style={{ fontSize: '13px' }}>{run.job}</td>
                  <td className="px-5 py-3">
                    <span
                      className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full ${
                        run.status === "succeeded"
                          ? "bg-success/10 text-success"
                          : "bg-destructive/10 text-destructive"
                      }`}
                      style={{ fontSize: '12px', fontWeight: 500 }}
                    >
                      <span className={`w-1.5 h-1.5 rounded-full ${
                        run.status === "succeeded" ? "bg-success" : "bg-destructive"
                      }`} />
                      {run.status}
                    </span>
                  </td>
                  <td className="px-5 py-3 text-muted-foreground font-mono" style={{ fontSize: '13px' }}>{run.duration}</td>
                  <td className="px-5 py-3 text-muted-foreground" style={{ fontSize: '13px' }}>{run.time}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
