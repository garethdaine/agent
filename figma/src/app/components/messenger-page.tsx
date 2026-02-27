import { useState } from "react";
import {
  Heart,
  Plug,
  MessageSquare,
  Zap,
  Plus,
  RefreshCw,
  CheckCircle2,
  XCircle,
  Settings,
} from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { Input } from "./ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "./ui/dialog";

const connectors = [
  {
    id: "1",
    provider: "Slack",
    status: "connected",
    sessions: 12,
    messages: 284,
    lastActive: "2 min ago",
  },
  {
    id: "2",
    provider: "Discord",
    status: "connected",
    sessions: 3,
    messages: 47,
    lastActive: "15 min ago",
  },
  {
    id: "3",
    provider: "Telegram",
    status: "disconnected",
    sessions: 0,
    messages: 0,
    lastActive: "Never",
  },
];

const recentActions = [
  { type: "message_sent", connector: "Slack", channel: "#dev-ops", time: "2 min ago" },
  { type: "session_created", connector: "Slack", channel: "#general", time: "5 min ago" },
  { type: "message_received", connector: "Discord", channel: "build-updates", time: "15 min ago" },
  { type: "action_executed", connector: "Slack", channel: "#alerts", time: "22 min ago" },
];

export function MessengerPage() {
  const [connectOpen, setConnectOpen] = useState(false);

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1>Messenger Control Plane</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: '14px' }}>
            Manage connectors, sessions, and messaging integrations
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" className="h-9 gap-2" style={{ fontSize: '13px' }}>
            <RefreshCw className="w-4 h-4" /> Refresh
          </Button>
          <Dialog open={connectOpen} onOpenChange={setConnectOpen}>
            <DialogTrigger asChild>
              <Button className="h-9 gap-2">
                <Plus className="w-4 h-4" /> Connect Provider
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Connect Messenger Provider</DialogTitle>
                <DialogDescription>Select a provider and connection mode to connect your messaging platform.</DialogDescription>
              </DialogHeader>
              <div className="space-y-4 mt-4">
                <div>
                  <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Provider</label>
                  <Select defaultValue="slack">
                    <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="slack">Slack</SelectItem>
                      <SelectItem value="discord">Discord</SelectItem>
                      <SelectItem value="telegram">Telegram</SelectItem>
                      <SelectItem value="teams">Microsoft Teams</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Connection Mode</label>
                  <Select defaultValue="oauth">
                    <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="oauth">OAuth</SelectItem>
                      <SelectItem value="token">API Token</SelectItem>
                      <SelectItem value="webhook">Webhook</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>API Token</label>
                  <Input placeholder="xoxb-..." className="h-9 font-mono bg-input-background" />
                </div>
                <div className="flex justify-end gap-2 pt-2">
                  <Button variant="outline" onClick={() => setConnectOpen(false)}>Cancel</Button>
                  <Button onClick={() => setConnectOpen(false)}>Connect</Button>
                </div>
              </div>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      {/* Health Summary */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <Card className="border border-border shadow-none">
          <CardContent className="p-4 flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-success/10 flex items-center justify-center">
              <Heart className="w-5 h-5 text-success" />
            </div>
            <div>
              <div className="text-muted-foreground" style={{ fontSize: '11px', fontWeight: 600 }}>STATUS</div>
              <div className="text-success" style={{ fontSize: '16px', fontWeight: 600 }}>Healthy</div>
            </div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4 flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
              <Plug className="w-5 h-5 text-primary" />
            </div>
            <div>
              <div className="text-muted-foreground" style={{ fontSize: '11px', fontWeight: 600 }}>CONNECTORS</div>
              <div style={{ fontSize: '16px', fontWeight: 600 }}>2 / 3 active</div>
            </div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4 flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
              <MessageSquare className="w-5 h-5 text-primary" />
            </div>
            <div>
              <div className="text-muted-foreground" style={{ fontSize: '11px', fontWeight: 600 }}>SESSIONS</div>
              <div style={{ fontSize: '16px', fontWeight: 600 }}>15 active</div>
            </div>
          </CardContent>
        </Card>
        <Card className="border border-border shadow-none">
          <CardContent className="p-4 flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
              <Zap className="w-5 h-5 text-primary" />
            </div>
            <div>
              <div className="text-muted-foreground" style={{ fontSize: '11px', fontWeight: 600 }}>MESSAGES (24H)</div>
              <div style={{ fontSize: '16px', fontWeight: 600 }}>331</div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* Connectors */}
        <Card className="border border-border shadow-none">
          <div className="px-5 py-4 border-b border-border">
            <h3 style={{ fontSize: '15px', fontWeight: 600 }}>Connectors</h3>
          </div>
          <div className="divide-y divide-border">
            {connectors.map((c) => (
              <div key={c.id} className="px-5 py-4 flex items-center justify-between hover:bg-muted/30 transition-colors">
                <div className="flex items-center gap-3">
                  <div className={`w-2 h-2 rounded-full ${c.status === "connected" ? "bg-success" : "bg-muted-foreground"}`} />
                  <div>
                    <div style={{ fontSize: '14px', fontWeight: 500 }}>{c.provider}</div>
                    <div className="text-muted-foreground" style={{ fontSize: '12px' }}>
                      {c.sessions} sessions · {c.messages} messages · {c.lastActive}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  {c.status === "connected" ? (
                    <CheckCircle2 className="w-4 h-4 text-success" />
                  ) : (
                    <XCircle className="w-4 h-4 text-muted-foreground" />
                  )}
                  <Button variant="ghost" size="icon" className="h-8 w-8">
                    <Settings className="w-4 h-4" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        </Card>

        {/* Recent Actions */}
        <Card className="border border-border shadow-none">
          <div className="px-5 py-4 border-b border-border">
            <h3 style={{ fontSize: '15px', fontWeight: 600 }}>Recent Actions</h3>
          </div>
          <div className="divide-y divide-border">
            {recentActions.map((a, i) => (
              <div key={i} className="px-5 py-3 flex items-center justify-between hover:bg-muted/30 transition-colors">
                <div>
                  <div style={{ fontSize: '13px', fontWeight: 500 }}>
                    {a.type.replace(/_/g, " ")}
                  </div>
                  <div className="text-muted-foreground" style={{ fontSize: '12px' }}>
                    {a.connector} · {a.channel}
                  </div>
                </div>
                <span className="text-muted-foreground" style={{ fontSize: '12px' }}>{a.time}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}