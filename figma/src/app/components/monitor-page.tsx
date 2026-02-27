import { useState } from "react";
import { Heart, Gauge, Radio, RefreshCw, AlertTriangle, ShieldCheck, HelpCircle } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";

const mockRuns = [
  { id: "#48", jobId: 37, status: "succeeded", alerts: null, created: "2026-02-26T09:35:04.000Z" },
  { id: "#47", jobId: 36, status: "succeeded", alerts: null, created: "2026-02-26T09:32:25.000Z" },
  { id: "#46", jobId: 35, status: "succeeded", alerts: null, created: "2026-02-26T09:28:57.000Z" },
  { id: "#45", jobId: 34, status: "succeeded", alerts: null, created: "2026-02-26T09:24:30.000Z" },
  { id: "#44", jobId: 33, status: "succeeded", alerts: null, created: "2026-02-26T09:20:38.000Z" },
  { id: "#43", jobId: 33, status: "failed", alerts: null, created: "2026-02-25T16:43:41.000Z" },
  { id: "#42", jobId: 32, status: "succeeded", alerts: null, created: "2026-02-25T16:40:13.000Z" },
  { id: "#41", jobId: 32, status: "failed", alerts: "Rate limit", created: "2026-02-25T16:36:34.000Z" },
  { id: "#40", jobId: 31, status: "succeeded", alerts: null, created: "2026-02-25T16:32:42.000Z" },
  { id: "#39", jobId: 30, status: "succeeded", alerts: null, created: "2026-02-25T16:06:24.000Z" },
];

const eventLog = [
  "[173 stdout]",
  "Tool call: Bash",
  "[174 lifecycle]",
  "heartbeat | 110s elapsed | at 2026-02-26T09:36:56+00:00",
  "[178 stdout]",
  "Tool call: Bash",
  "[182 stdout]",
  "There are some style issues. Let me fix them.",
  "[182 stdout]",
  "Tool call: Bash",
  "[186 stdout]",
  "Tool call: Bash",
  "[188 lifecycle]",
  "heartbeat | 115s elapsed | at 2026-02-26T09:37:01+00:00",
  "[189 stdout]",
  "Tool call: TodoWrite",
  "[198 stdout]",
  "Tool call: TodoWrite",
  "[199 stdout]",
  "Todo list updated (7 items)",
  "[200 stdout]",
  "Let me verify the tests still pass after the ...",
];

export function MonitorPage() {
  const [autoFollow, setAutoFollow] = useState(true);

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1>Run Monitor</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: '14px' }}>
            Live scheduler status and event stream
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" className="h-9" style={{ fontSize: '13px' }}>
            Jobs
          </Button>
          <Button
            variant={autoFollow ? "default" : "outline"}
            className="h-9"
            onClick={() => setAutoFollow(!autoFollow)}
            style={{ fontSize: '13px' }}
          >
            {autoFollow ? "Auto-follow on" : "Auto-follow off"}
          </Button>
        </div>
      </div>

      {/* Status Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <Card className="border border-border shadow-none">
          <CardContent className="p-5">
            <div className="flex items-center gap-2 mb-3">
              <Heart className="w-4 h-4 text-success" />
              <span className="text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>
                Scheduler Health
              </span>
            </div>
            <div className="text-success" style={{ fontSize: '22px', fontWeight: 700 }}>healthy</div>
            <div className="text-muted-foreground mt-1" style={{ fontSize: '12px' }}>
              Last seen: 2026-02-26T10:21:00.000Z
            </div>
            <div className="text-muted-foreground" style={{ fontSize: '12px' }}>Age: 20.11s</div>
          </CardContent>
        </Card>

        <Card className="border border-border shadow-none">
          <CardContent className="p-5">
            <div className="flex items-center gap-2 mb-3">
              <Gauge className="w-4 h-4 text-primary" />
              <span className="text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>
                Queue Lag
              </span>
            </div>
            <div style={{ fontSize: '22px', fontWeight: 700 }}>0 queued / oldest 0s</div>
            <div className="text-muted-foreground mt-1" style={{ fontSize: '12px' }}>
              Warning threshold: oldest &gt; 60s or queued &gt; 10
            </div>
          </CardContent>
        </Card>

        <Card className="border border-border shadow-none">
          <CardContent className="p-5">
            <div className="flex items-center gap-2 mb-3">
              <Radio className="w-4 h-4 text-primary" />
              <span className="text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>
                Poll Status
              </span>
            </div>
            <div style={{ fontSize: '22px', fontWeight: 700 }}>Consecutive failures: 0</div>
            <div className="text-muted-foreground mt-1" style={{ fontSize: '12px' }}>
              Intervals: active 2s, inactive 10s, hidden 15s with backoff
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Runs + Event Tail */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Latest Runs */}
        <Card className="border border-border shadow-none">
          <div className="px-5 py-4 border-b border-border">
            <h3 style={{ fontSize: '13px', fontWeight: 600 }} className="uppercase tracking-wider text-muted-foreground">
              Latest Runs (24h, max 50)
            </h3>
          </div>
          <div className="overflow-x-auto max-h-[500px] overflow-y-auto">
            <table className="w-full">
              <thead className="sticky top-0 bg-card">
                <tr className="border-b border-border">
                  <th className="text-left px-5 py-2.5 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Run</th>
                  <th className="text-left px-5 py-2.5 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Status</th>
                  <th className="text-left px-5 py-2.5 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Alerts</th>
                  <th className="text-left px-5 py-2.5 text-muted-foreground uppercase tracking-wider" style={{ fontSize: '11px', fontWeight: 600 }}>Created</th>
                </tr>
              </thead>
              <tbody>
                {mockRuns.map((run, i) => (
                  <tr key={i} className="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer">
                    <td className="px-5 py-2.5 font-mono" style={{ fontSize: '13px' }}>
                      {run.id} <span className="text-muted-foreground">(job {run.jobId})</span>
                    </td>
                    <td className="px-5 py-2.5">
                      <span className={run.status === "succeeded" ? "text-success" : "text-destructive"} style={{ fontSize: '13px' }}>
                        {run.status}
                      </span>
                    </td>
                    <td className="px-5 py-2.5" style={{ fontSize: '13px' }}>
                      {run.alerts ? (
                        <span className="inline-flex items-center px-2 py-0.5 rounded border border-destructive/30 text-destructive bg-destructive/5" style={{ fontSize: '11px', fontWeight: 500 }}>
                          {run.alerts}
                        </span>
                      ) : (
                        <span className="text-muted-foreground">–</span>
                      )}
                    </td>
                    <td className="px-5 py-2.5 text-muted-foreground font-mono" style={{ fontSize: '12px' }}>
                      {run.created}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>

        {/* Event Tail */}
        <Card className="border border-border shadow-none">
          <div className="px-5 py-4 border-b border-border flex items-center justify-between">
            <h3 style={{ fontSize: '13px', fontWeight: 600 }} className="uppercase tracking-wider text-muted-foreground">
              Event Tail
            </h3>
            <div className={`w-2 h-2 rounded-full ${autoFollow ? "bg-success animate-pulse" : "bg-muted-foreground"}`} />
          </div>
          <div className="bg-[#0f172a] text-[#38bdf8] p-4 font-mono overflow-y-auto max-h-[500px] rounded-b-lg" style={{ fontSize: '12px', lineHeight: 1.6 }}>
            {eventLog.map((line, i) => (
              <div key={i} className={line.startsWith("[") && line.includes("lifecycle") ? "text-emerald-400" : line.startsWith("[") ? "text-slate-500" : "text-sky-300"}>
                {line}
              </div>
            ))}
            {autoFollow && (
              <div className="inline-block w-2 h-4 bg-sky-400 animate-pulse ml-0.5" />
            )}
          </div>
        </Card>
      </div>

      {/* Intervention States */}
      <div className="mt-6">
        <h3 className="mb-4" style={{ fontSize: "15px", fontWeight: 600 }}>Active Interventions</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Approval Needed */}
          <Card className="border border-warning/30 shadow-none">
            <CardContent className="p-4">
              <div className="flex items-center gap-2 mb-3">
                <ShieldCheck className="w-4 h-4 text-warning" />
                <span className="text-warning uppercase tracking-wider" style={{ fontSize: "11px", fontWeight: 600 }}>Approval Needed</span>
                <Badge className="bg-warning/10 text-warning border-warning/20 ml-auto" style={{ fontSize: "10px" }}>1 pending</Badge>
              </div>
              <div className="p-3 bg-warning/5 rounded-lg border border-warning/10 mb-3">
                <div style={{ fontSize: "13px", fontWeight: 500 }}>Run #48 — Job 37</div>
                <p className="text-muted-foreground mt-0.5" style={{ fontSize: "12px" }}>
                  Agent requests permission to execute <code className="text-warning font-mono" style={{ fontSize: "11px" }}>rm -rf dist/</code> in working directory.
                </p>
              </div>
              <div className="flex gap-2">
                <Button size="sm" className="h-7 flex-1 bg-success hover:bg-success/90 text-success-foreground" style={{ fontSize: "12px" }}>Approve</Button>
                <Button size="sm" variant="outline" className="h-7 flex-1 text-destructive border-destructive/30" style={{ fontSize: "12px" }}>Deny</Button>
              </div>
            </CardContent>
          </Card>

          {/* Rate Limit Detected */}
          <Card className="border border-destructive/30 shadow-none">
            <CardContent className="p-4">
              <div className="flex items-center gap-2 mb-3">
                <AlertTriangle className="w-4 h-4 text-destructive" />
                <span className="text-destructive uppercase tracking-wider" style={{ fontSize: "11px", fontWeight: 600 }}>Rate Limit Detected</span>
              </div>
              <div className="p-3 bg-destructive/5 rounded-lg border border-destructive/10 mb-3">
                <div style={{ fontSize: "13px", fontWeight: 500 }}>Anthropic API — Claude 3.5 Sonnet</div>
                <p className="text-muted-foreground mt-0.5" style={{ fontSize: "12px" }}>
                  429 Too Many Requests. Retry after 45s. Backoff active — 2 runs paused.
                </p>
                <div className="flex items-center gap-2 mt-2">
                  <div className="w-full h-1.5 bg-muted rounded-full overflow-hidden">
                    <div className="h-full bg-destructive rounded-full animate-pulse" style={{ width: "75%" }} />
                  </div>
                  <span className="text-muted-foreground shrink-0" style={{ fontSize: "11px" }}>45s</span>
                </div>
              </div>
              <Button size="sm" variant="outline" className="h-7 w-full" style={{ fontSize: "12px" }}>Force Retry Now</Button>
            </CardContent>
          </Card>

          {/* Clarification Requested */}
          <Card className="border border-primary/30 shadow-none">
            <CardContent className="p-4">
              <div className="flex items-center gap-2 mb-3">
                <HelpCircle className="w-4 h-4 text-primary" />
                <span className="text-primary uppercase tracking-wider" style={{ fontSize: "11px", fontWeight: 600 }}>Clarification Requested</span>
              </div>
              <div className="p-3 bg-primary/5 rounded-lg border border-primary/10 mb-3">
                <div style={{ fontSize: "13px", fontWeight: 500 }}>Run #47 — Job 36</div>
                <p className="text-muted-foreground mt-0.5" style={{ fontSize: "12px" }}>
                  "The task references a <code className="font-mono" style={{ fontSize: "11px" }}>PaymentGateway</code> interface but I found two different implementations. Which should I use?"
                </p>
              </div>
              <div className="flex gap-2">
                <input
                  type="text"
                  placeholder="Type your response..."
                  className="flex-1 h-7 rounded-md border border-input bg-input-background px-2 text-sm text-foreground placeholder:text-muted-foreground outline-none focus-visible:border-ring"
                  style={{ fontSize: "12px" }}
                />
                <Button size="sm" className="h-7" style={{ fontSize: "12px" }}>Send</Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}