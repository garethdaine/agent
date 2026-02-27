import { useState } from "react";
import { useNavigate, useParams } from "react-router";
import { ArrowLeft, Save } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Textarea } from "./ui/textarea";
import { Card, CardContent } from "./ui/card";
import { Checkbox } from "./ui/checkbox";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";

export function JobFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEdit = Boolean(id);

  const [name, setName] = useState(isEdit ? "Interrogation Build S1 T01" : "");
  const [runner, setRunner] = useState(isEdit ? "claude" : "codex");
  const [scheduleMode, setScheduleMode] = useState<"basic" | "advanced">(isEdit ? "advanced" : "basic");
  const [frequency, setFrequency] = useState("daily");
  const [hour, setHour] = useState("09");
  const [minute, setMinute] = useState("00");
  const [cronExpr, setCronExpr] = useState(isEdit ? "0 0 1 1 0" : "0 9 * * *");
  const [timezone, setTimezone] = useState("UTC");
  const [maxRuntime, setMaxRuntime] = useState(isEdit ? "3600" : "300");
  const [cooldown, setCooldown] = useState("0");
  const [promptSource, setPromptSource] = useState<"file" | "inline">(isEdit ? "inline" : "file");
  const [taskPath, setTaskPath] = useState("/Users/garethdaine/Code/agent/tasks/");
  const [markdownContent, setMarkdownContent] = useState(
    isEdit
      ? "# Build Task\n\nSession ID: 1\nTask Sequence: 1\nTask Title: Database Migrations and Models for Messenger Control Plane\n\n## Objective\n\nCreate database schema and Eloquent models for messenger connectors, chat sessions, messages, actions, and account linking."
      : ""
  );
  const [workingDir, setWorkingDir] = useState("/Users/garethdaine/Code/agent");
  const [commandTemplate, setCommandTemplate] = useState("");
  const [description, setDescription] = useState(
    isEdit ? "Create database schema and Eloquent models for messenger connectors, chat sessions, messages, actions, and account linking." : ""
  );
  const [envJson, setEnvJson] = useState(isEdit ? '{\n  "AGENT_JOB_SOURCE": "interrogation_build"\n}' : "{}");
  const [enabled, setEnabled] = useState(!isEdit);
  const [permProfile, setPermProfile] = useState("interactive");
  const [saving, setSaving] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setTimeout(() => {
      navigate("/jobs");
    }, 800);
  };

  return (
    <div className="max-w-3xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => navigate("/jobs")}>
          <ArrowLeft className="w-4 h-4" />
        </Button>
        <div>
          <h1>{isEdit ? "Edit Agent Job" : "Create Agent Job"}</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: '14px' }}>
            {isEdit ? "Modify job configuration and schedule" : "Configure a new scheduled agent task"}
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Basic Info */}
        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <h3 style={{ fontSize: '15px', fontWeight: 600 }}>Basic Information</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Name</label>
                <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Job name" className="h-9 bg-input-background" />
                <p className="text-muted-foreground mt-1" style={{ fontSize: '12px' }}>A human-friendly label for this job in the UI.</p>
              </div>
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Runner Type</label>
                <Select value={runner} onValueChange={setRunner}>
                  <SelectTrigger className="h-9">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="codex">Codex</SelectItem>
                    <SelectItem value="claude">Claude</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-muted-foreground mt-1" style={{ fontSize: '12px' }}>Chooses the executable policy and default command template.</p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Schedule */}
        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <div className="flex items-center justify-between">
              <h3 style={{ fontSize: '15px', fontWeight: 600 }}>Schedule</h3>
              <div className="flex items-center gap-1 bg-muted rounded-lg p-0.5">
                <button
                  type="button"
                  onClick={() => setScheduleMode("basic")}
                  className={`px-3 py-1 rounded-md transition-colors ${
                    scheduleMode === "basic" ? "bg-card shadow-sm text-foreground" : "text-muted-foreground"
                  }`}
                  style={{ fontSize: '12px', fontWeight: 500 }}
                >
                  Basic
                </button>
                <button
                  type="button"
                  onClick={() => setScheduleMode("advanced")}
                  className={`px-3 py-1 rounded-md transition-colors ${
                    scheduleMode === "advanced" ? "bg-card shadow-sm text-foreground" : "text-muted-foreground"
                  }`}
                  style={{ fontSize: '12px', fontWeight: 500 }}
                >
                  Advanced
                </button>
              </div>
            </div>
            <p className="text-muted-foreground" style={{ fontSize: '12px' }}>
              {scheduleMode === "basic"
                ? "Use guided scheduling with frequency presets."
                : "Type a cron expression directly."}
            </p>

            {scheduleMode === "basic" ? (
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Frequency</label>
                  <Select value={frequency} onValueChange={setFrequency}>
                    <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="hourly">Hourly</SelectItem>
                      <SelectItem value="daily">Daily</SelectItem>
                      <SelectItem value="weekly">Weekly</SelectItem>
                      <SelectItem value="monthly">Monthly</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Hour (24h)</label>
                  <Select value={hour} onValueChange={setHour}>
                    <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {Array.from({ length: 24 }, (_, i) => i.toString().padStart(2, "0")).map((h) => (
                        <SelectItem key={h} value={h}>{h}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Minute</label>
                  <Select value={minute} onValueChange={setMinute}>
                    <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {["00", "15", "30", "45"].map((m) => (
                        <SelectItem key={m} value={m}>{m}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
            ) : (
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Cron Expression</label>
                <Input
                  value={cronExpr}
                  onChange={(e) => setCronExpr(e.target.value)}
                  className="h-9 font-mono bg-input-background"
                  placeholder="* * * * *"
                />
                <p className="text-muted-foreground mt-1" style={{ fontSize: '12px' }}>
                  5-part numeric cron. Supports numbers, *, ranges, lists, and step values.
                </p>
              </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Timezone</label>
                <Input value={timezone} onChange={(e) => setTimezone(e.target.value)} className="h-9 bg-input-background" />
              </div>
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Max Runtime (s)</label>
                <Input type="number" value={maxRuntime} onChange={(e) => setMaxRuntime(e.target.value)} className="h-9 bg-input-background" />
              </div>
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Cooldown (s)</label>
                <Input type="number" value={cooldown} onChange={(e) => setCooldown(e.target.value)} className="h-9 bg-input-background" />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Task Prompt */}
        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <div className="flex items-center justify-between">
              <h3 style={{ fontSize: '15px', fontWeight: 600 }}>Task Prompt Source</h3>
              <div className="flex items-center gap-1 bg-muted rounded-lg p-0.5">
                <button
                  type="button"
                  onClick={() => setPromptSource("file")}
                  className={`px-3 py-1 rounded-md transition-colors ${
                    promptSource === "file" ? "bg-card shadow-sm text-foreground" : "text-muted-foreground"
                  }`}
                  style={{ fontSize: '12px', fontWeight: 500 }}
                >
                  File Path
                </button>
                <button
                  type="button"
                  onClick={() => setPromptSource("inline")}
                  className={`px-3 py-1 rounded-md transition-colors ${
                    promptSource === "inline" ? "bg-card shadow-sm text-foreground" : "text-muted-foreground"
                  }`}
                  style={{ fontSize: '12px', fontWeight: 500 }}
                >
                  Inline Markdown
                </button>
              </div>
            </div>

            {promptSource === "file" ? (
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Task Markdown Path</label>
                <Input value={taskPath} onChange={(e) => setTaskPath(e.target.value)} className="h-9 font-mono bg-input-background" />
                <p className="text-muted-foreground mt-1" style={{ fontSize: '12px' }}>Absolute path to an existing markdown task/prompt file.</p>
              </div>
            ) : (
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Task Markdown Editor</label>
                <Textarea
                  value={markdownContent}
                  onChange={(e) => setMarkdownContent(e.target.value)}
                  className="min-h-[200px] font-mono bg-input-background"
                  style={{ fontSize: '13px' }}
                />
              </div>
            )}
          </CardContent>
        </Card>

        {/* Execution */}
        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <h3 style={{ fontSize: '15px', fontWeight: 600 }}>Execution Settings</h3>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Working Directory</label>
              <Input value={workingDir} onChange={(e) => setWorkingDir(e.target.value)} className="h-9 font-mono bg-input-background" />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Permission Profile</label>
                <Select value={permProfile} onValueChange={setPermProfile}>
                  <SelectTrigger className="h-9"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="interactive">Interactive approvals (default)</SelectItem>
                    <SelectItem value="full">Full permissions</SelectItem>
                    <SelectItem value="readonly">Read-only</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Command Template (optional)</label>
              <Input value={commandTemplate} onChange={(e) => setCommandTemplate(e.target.value)} className="h-9 font-mono bg-input-background" placeholder="Leave empty to use runner defaults" />
            </div>
          </CardContent>
        </Card>

        {/* Description + Env */}
        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <h3 style={{ fontSize: '15px', fontWeight: 600 }}>Additional Options</h3>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Description</label>
              <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="min-h-[80px] bg-input-background"
                placeholder="Optional free-text notes about what this job does."
              />
            </div>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>env_json</label>
              <Textarea
                value={envJson}
                onChange={(e) => setEnvJson(e.target.value)}
                className="min-h-[100px] font-mono bg-input-background"
                style={{ fontSize: '13px' }}
              />
              <p className="text-muted-foreground mt-1" style={{ fontSize: '12px' }}>Optional environment variable overrides as JSON object.</p>
            </div>
            <div className="flex items-center gap-2">
              <Checkbox checked={enabled} onCheckedChange={(v) => setEnabled(v === true)} id="enabled" />
              <label htmlFor="enabled" className="text-foreground cursor-pointer" style={{ fontSize: '13px', fontWeight: 500 }}>
                Enabled
              </label>
            </div>
          </CardContent>
        </Card>

        {/* Submit */}
        <div className="flex justify-end pb-8">
          <Button type="submit" className="h-10 px-6 gap-2" disabled={saving}>
            {saving ? (
              <span className="flex items-center gap-2">
                <span className="w-4 h-4 border-2 border-primary-foreground/30 border-t-primary-foreground rounded-full animate-spin" />
                Saving...
              </span>
            ) : (
              <>
                <Save className="w-4 h-4" />
                {isEdit ? "Update Job" : "Create Job"}
              </>
            )}
          </Button>
        </div>
      </form>
    </div>
  );
}
