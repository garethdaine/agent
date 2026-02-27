import { useState } from "react";
import { Link } from "react-router";
import { ArrowLeft, Save, Globe, Cpu, Clock, Zap } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Textarea } from "./ui/textarea";
import { Card, CardContent } from "./ui/card";
import { Switch } from "./ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";

export function DiscoverySettingsPage() {
  const [defaultRunner, setDefaultRunner] = useState("claude");
  const [defaultDir, setDefaultDir] = useState("/Users/garethdaine/Code/agent");
  const [autoTechStack, setAutoTechStack] = useState(true);
  const [maxQuestions, setMaxQuestions] = useState("25");
  const [questionTimeout, setQuestionTimeout] = useState("300");
  const [autoAdvance, setAutoAdvance] = useState(true);
  const [linearEnabled, setLinearEnabled] = useState(true);
  const [linearTeam, setLinearTeam] = useState("agent-orchestration");
  const [defaultBrief, setDefaultBrief] = useState("");

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-6">
        <Link
          to="/tools/discovery"
          className="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground transition-colors mb-3"
          style={{ fontSize: "13px", fontWeight: 500 }}
        >
          <ArrowLeft className="w-4 h-4" />
          Back to Discovery
        </Link>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <Globe className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h1>Discovery Global Settings</h1>
            <p className="text-muted-foreground mt-0.5" style={{ fontSize: "14px" }}>
              Default configuration for all new discovery sessions
            </p>
          </div>
        </div>
      </div>

      {/* Defaults */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-4">
            <Cpu className="w-4 h-4 text-primary" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Session Defaults</h3>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Default Runner</label>
              <Select value={defaultRunner} onValueChange={setDefaultRunner}>
                <SelectTrigger className="h-9 bg-input-background"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="claude">Claude</SelectItem>
                  <SelectItem value="codex">Codex</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Default Project Directory</label>
              <Input value={defaultDir} onChange={(e) => setDefaultDir(e.target.value)} className="h-9 bg-input-background font-mono" style={{ fontSize: "13px" }} />
            </div>
          </div>
          <div className="mt-4">
            <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Default Feature Brief Template</label>
            <Textarea
              value={defaultBrief}
              onChange={(e) => setDefaultBrief(e.target.value)}
              placeholder="Optional template text pre-filled in new sessions..."
              className="min-h-[80px] bg-input-background"
              style={{ fontSize: "13px" }}
            />
          </div>
          <div className="flex items-center justify-between mt-4 p-3 bg-muted/50 rounded-lg">
            <div>
              <div style={{ fontSize: "13px", fontWeight: 500 }}>Auto-detect Tech Stack</div>
              <p className="text-muted-foreground" style={{ fontSize: "12px" }}>
                Automatically scan project for frameworks and libraries during discovery
              </p>
            </div>
            <Switch checked={autoTechStack} onCheckedChange={setAutoTechStack} />
          </div>
        </CardContent>
      </Card>

      {/* Interrogation */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-4">
            <Clock className="w-4 h-4 text-primary" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Interrogation Settings</h3>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Max Questions per Session</label>
              <Input type="number" value={maxQuestions} onChange={(e) => setMaxQuestions(e.target.value)} className="h-9 bg-input-background" />
              <p className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>AI will stop asking after this many questions.</p>
            </div>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Question Timeout (seconds)</label>
              <Input type="number" value={questionTimeout} onChange={(e) => setQuestionTimeout(e.target.value)} className="h-9 bg-input-background" />
              <p className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>Auto-skip unanswered questions after this duration.</p>
            </div>
          </div>
          <div className="flex items-center justify-between mt-4 p-3 bg-muted/50 rounded-lg">
            <div>
              <div style={{ fontSize: "13px", fontWeight: 500 }}>Auto-advance Phases</div>
              <p className="text-muted-foreground" style={{ fontSize: "12px" }}>
                Automatically progress to next phase when conditions are met
              </p>
            </div>
            <Switch checked={autoAdvance} onCheckedChange={setAutoAdvance} />
          </div>
        </CardContent>
      </Card>

      {/* Linear Integration */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-4">
            <Zap className="w-4 h-4 text-primary" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Task Provider (Linear)</h3>
          </div>
          <div className="flex items-center justify-between p-3 bg-muted/50 rounded-lg mb-4">
            <div>
              <div style={{ fontSize: "13px", fontWeight: 500 }}>Enable Linear Integration</div>
              <p className="text-muted-foreground" style={{ fontSize: "12px" }}>
                Sync generated tasks to Linear for new sessions
              </p>
            </div>
            <Switch checked={linearEnabled} onCheckedChange={setLinearEnabled} />
          </div>
          {linearEnabled && (
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Default Team</label>
              <Select value={linearTeam} onValueChange={setLinearTeam}>
                <SelectTrigger className="h-9 bg-input-background"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="agent-orchestration">Agent Orchestration</SelectItem>
                  <SelectItem value="platform">Platform</SelectItem>
                  <SelectItem value="infrastructure">Infrastructure</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>
                New sessions will default to this Linear team. Can be overridden per-session.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      <div className="mb-8">
        <Button className="h-9 gap-2" style={{ fontSize: "13px" }}>
          <Save className="w-4 h-4" /> Save Global Settings
        </Button>
      </div>
    </div>
  );
}
