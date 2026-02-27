import { useState } from "react";
import { useNavigate, Link } from "react-router";
import { ArrowLeft, Save, Plus, X } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Textarea } from "./ui/textarea";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";

export function DelegationCreatePage() {
  const navigate = useNavigate();
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [priority, setPriority] = useState("medium");
  const [delegatees, setDelegatees] = useState<string[]>([]);
  const [newDelegatee, setNewDelegatee] = useState("");
  const [saving, setSaving] = useState(false);

  const addDelegatee = () => {
    if (newDelegatee.trim() && !delegatees.includes(newDelegatee.trim())) {
      setDelegatees([...delegatees, newDelegatee.trim()]);
      setNewDelegatee("");
    }
  };

  const removeDelegatee = (d: string) => {
    setDelegatees(delegatees.filter((x) => x !== d));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setTimeout(() => navigate("/delegation"), 800);
  };

  return (
    <div className="max-w-3xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => navigate("/delegation")}>
          <ArrowLeft className="w-4 h-4" />
        </Button>
        <div>
          <h1>Create Delegation Graph</h1>
          <p className="text-muted-foreground mt-0.5" style={{ fontSize: "14px" }}>
            Define a new task delegation workflow
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Graph Details</h3>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Name</label>
              <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Feature: Auth Module" className="h-9 bg-input-background" required />
            </div>
            <div>
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Description</label>
              <Textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Describe the scope and objectives of this delegation..."
                className="min-h-[100px] bg-input-background"
              />
            </div>
            <div className="max-w-xs">
              <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Priority</label>
              <Select value={priority} onValueChange={setPriority}>
                <SelectTrigger className="h-9 bg-input-background"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="low">Low</SelectItem>
                  <SelectItem value="medium">Medium</SelectItem>
                  <SelectItem value="high">High</SelectItem>
                  <SelectItem value="critical">Critical</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Delegatees</h3>
            <p className="text-muted-foreground" style={{ fontSize: "13px" }}>
              Add agent profiles that will execute tasks in this graph.
            </p>
            <div className="flex gap-2">
              <Input
                value={newDelegatee}
                onChange={(e) => setNewDelegatee(e.target.value)}
                placeholder="Delegatee profile name"
                className="h-9 bg-input-background flex-1"
                onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addDelegatee(); } }}
              />
              <Button type="button" variant="outline" className="h-9 gap-1.5" onClick={addDelegatee}>
                <Plus className="w-3.5 h-3.5" /> Add
              </Button>
            </div>
            {delegatees.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {delegatees.map((d) => (
                  <Badge key={d} variant="secondary" className="gap-1.5 px-2.5 py-1">
                    {d}
                    <button type="button" onClick={() => removeDelegatee(d)} className="hover:text-destructive">
                      <X className="w-3 h-3" />
                    </button>
                  </Badge>
                ))}
              </div>
            )}
            <p className="text-muted-foreground" style={{ fontSize: "12px" }}>
              You can also manage delegatee profiles from{" "}
              <Link to="/delegation/profiles" className="text-primary hover:underline">Delegatee Profiles</Link>.
            </p>
          </CardContent>
        </Card>

        <Card className="border border-border shadow-none">
          <CardContent className="p-5 space-y-4">
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Initial Tasks</h3>
            <p className="text-muted-foreground" style={{ fontSize: "13px" }}>
              Define initial tasks for the graph. Tasks can also be added after creation.
            </p>
            <div className="border border-dashed border-border rounded-lg p-6 text-center">
              <p className="text-muted-foreground" style={{ fontSize: "13px" }}>
                Tasks will be added after the graph is created.
              </p>
            </div>
          </CardContent>
        </Card>

        <div className="flex justify-end pb-8">
          <Button type="submit" className="h-10 px-6 gap-2" disabled={saving}>
            {saving ? (
              <span className="flex items-center gap-2">
                <span className="w-4 h-4 border-2 border-primary-foreground/30 border-t-primary-foreground rounded-full animate-spin" />
                Creating...
              </span>
            ) : (
              <>
                <Save className="w-4 h-4" /> Create Graph
              </>
            )}
          </Button>
        </div>
      </form>
    </div>
  );
}
