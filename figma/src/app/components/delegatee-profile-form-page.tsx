import { useState } from "react";
import { useNavigate, useParams } from "react-router";
import { ArrowLeft, Save, Bot } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Textarea } from "./ui/textarea";
import { Card, CardContent } from "./ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";

export function DelegateeProfileFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEdit = Boolean(id);

  const [name, setName] = useState(isEdit ? "Claude (Backend)" : "");
  const [runner, setRunner] = useState(isEdit ? "claude" : "claude");
  const [permProfile, setPermProfile] = useState(isEdit ? "full" : "interactive");
  const [description, setDescription] = useState(
    isEdit ? "Backend development agent for API endpoints, database operations, and server-side logic." : ""
  );
  const [workingDir, setWorkingDir] = useState(isEdit ? "/Users/garethdaine/Code/agent" : "");
  const [commandTemplate, setCommandTemplate] = useState("");
  const [maxRuntime, setMaxRuntime] = useState(isEdit ? "1800" : "600");
  const [saving, setSaving] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setTimeout(() => navigate("/delegation/profiles"), 800);
  };

  return (
    <div className="max-w-3xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => navigate("/delegation/profiles")}>
          <ArrowLeft className="w-4 h-4" />
        </Button>
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <Bot className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h1>{isEdit ? "Edit Delegatee Profile" : "Create Delegatee Profile"}</h1>
            <p className="text-muted-foreground mt-0.5" style={{ fontSize: "14px" }}>
              {isEdit ? "Update agent profile configuration" : "Configure a new agent delegatee profile"}
            </p>
          </div>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Profile Details</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Profile Name</label>
                <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Claude (Backend)" className="h-9 bg-input-background" required />
                <p className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>Human-friendly label for this agent profile.</p>
              </div>
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Runner Type</label>
                <Select value={runner} onValueChange={setRunner}>
                  <SelectTrigger className="h-9 bg-input-background"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="claude">Claude</SelectItem>
                    <SelectItem value="codex">Codex</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Description</label>
              <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Describe this agent's role and capabilities..."
                className="min-h-[80px] bg-input-background"
              />
            </div>
          </CardContent>
        </Card>

        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Execution Settings</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Permission Profile</label>
                <Select value={permProfile} onValueChange={setPermProfile}>
                  <SelectTrigger className="h-9 bg-input-background"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="interactive">Interactive (approve each action)</SelectItem>
                    <SelectItem value="full">Full permissions</SelectItem>
                    <SelectItem value="readonly">Read-only</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>Controls what actions the agent can take autonomously.</p>
              </div>
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Max Runtime (seconds)</label>
                <Input type="number" value={maxRuntime} onChange={(e) => setMaxRuntime(e.target.value)} className="h-9 bg-input-background" />
              </div>
            </div>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Working Directory</label>
              <Input value={workingDir} onChange={(e) => setWorkingDir(e.target.value)} placeholder="/path/to/project" className="h-9 bg-input-background font-mono" style={{ fontSize: "13px" }} />
            </div>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Command Template (optional)</label>
              <Input value={commandTemplate} onChange={(e) => setCommandTemplate(e.target.value)} placeholder="Leave empty to use runner defaults" className="h-9 bg-input-background font-mono" style={{ fontSize: "13px" }} />
            </div>
          </CardContent>
        </Card>

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
                {isEdit ? "Update Profile" : "Create Profile"}
              </>
            )}
          </Button>
        </div>
      </form>
    </div>
  );
}
