import { useState } from "react";
import { useNavigate, Link } from "react-router";
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

export function DiscoveryCreatePage() {
  const navigate = useNavigate();
  const [name, setName] = useState("");
  const [runner, setRunner] = useState("claude");
  const [projectDir, setProjectDir] = useState("/Users/garethdaine/Code/agent");
  const [interrogationType, setInterrogationType] = useState("feature");
  const [featureBrief, setFeatureBrief] = useState("");

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    navigate("/tools/discovery/wizard");
  };

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h1>New Discovery Session</h1>
        <Link to="/tools/discovery">
          <Button variant="outline" className="h-9" style={{ fontSize: "13px" }}>
            Back
          </Button>
        </Link>
      </div>

      {/* Form Card */}
      <Card className="border border-border shadow-none max-w-3xl">
        <CardContent className="p-6">
          <form onSubmit={handleCreate} className="space-y-5">
            {/* Name + Runner row */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label
                  className="block mb-1.5 text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  Name (optional)
                </label>
                <Input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder=""
                  className="h-9 bg-input-background"
                />
              </div>
              <div>
                <label
                  className="block mb-1.5 text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  Runner
                </label>
                <Select value={runner} onValueChange={setRunner}>
                  <SelectTrigger className="h-9 bg-input-background">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="claude">claude</SelectItem>
                    <SelectItem value="codex">codex</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* Project Directory */}
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Project Directory
              </label>
              <Input
                value={projectDir}
                onChange={(e) => setProjectDir(e.target.value)}
                placeholder="/path/to/project"
                className="h-9 bg-input-background font-mono"
                style={{ fontSize: "13px" }}
              />
              <p
                className="text-muted-foreground mt-1"
                style={{ fontSize: "12px" }}
              >
                Absolute path where discovery commands run.
              </p>
            </div>

            {/* Interrogation Type */}
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Interrogation Type
              </label>
              <Select
                value={interrogationType}
                onValueChange={setInterrogationType}
              >
                <SelectTrigger className="h-9 bg-input-background">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="feature">feature</SelectItem>
                  <SelectItem value="bugfix">bugfix</SelectItem>
                  <SelectItem value="refactor">refactor</SelectItem>
                  <SelectItem value="exploration">exploration</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Feature Brief */}
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Feature Brief
              </label>
              <Textarea
                value={featureBrief}
                onChange={(e) => setFeatureBrief(e.target.value)}
                placeholder="Describe the feature scope, users, and constraints."
                className="min-h-[160px] bg-input-background resize-y"
                style={{ fontSize: "13px" }}
              />
              <p
                className="text-muted-foreground mt-1"
                style={{ fontSize: "12px" }}
              >
                Required for feature sessions.
              </p>
            </div>

            {/* Submit */}
            <div className="flex justify-end pt-1">
              <Button type="submit" className="h-9 px-5">
                Create Session
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
