import { useState } from "react";
import { Link } from "react-router";
import {
  ArrowLeft,
  Key,
  Plus,
  Copy,
  Trash2,
  CheckCircle2,
  Eye,
  EyeOff,
  Shield,
} from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "./ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "./ui/alert-dialog";

interface ApiToken {
  id: string;
  name: string;
  lastUsed: string | null;
  createdAt: string;
  abilities: string[];
  lastFour: string;
}

const initialTokens: ApiToken[] = [
  {
    id: "1",
    name: "CI/CD Pipeline",
    lastUsed: "2 hours ago",
    createdAt: "2026-01-15",
    abilities: ["jobs:read", "jobs:write", "monitor:read"],
    lastFour: "a3f9",
  },
  {
    id: "2",
    name: "Local Development",
    lastUsed: "5 min ago",
    createdAt: "2026-02-01",
    abilities: ["*"],
    lastFour: "k7m2",
  },
  {
    id: "3",
    name: "Monitoring Dashboard",
    lastUsed: "1 day ago",
    createdAt: "2026-02-10",
    abilities: ["monitor:read", "dashboard:read"],
    lastFour: "p4x8",
  },
  {
    id: "4",
    name: "Messenger Webhook",
    lastUsed: null,
    createdAt: "2026-02-20",
    abilities: ["messenger:write"],
    lastFour: "w1n5",
  },
];

export function ApiTokensPage() {
  const [tokens, setTokens] = useState<ApiToken[]>(initialTokens);
  const [createOpen, setCreateOpen] = useState(false);
  const [newTokenName, setNewTokenName] = useState("");
  const [newTokenCreated, setNewTokenCreated] = useState<string | null>(null);
  const [copiedId, setCopiedId] = useState<string | null>(null);

  const handleCreateToken = () => {
    const fakeToken = "as_" + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
    setNewTokenCreated(fakeToken);
    setTokens([
      ...tokens,
      {
        id: Date.now().toString(),
        name: newTokenName,
        lastUsed: null,
        createdAt: "2026-02-26",
        abilities: ["*"],
        lastFour: fakeToken.slice(-4),
      },
    ]);
  };

  const handleDeleteToken = (id: string) => {
    setTokens(tokens.filter((t) => t.id !== id));
  };

  const copyToClipboard = (text: string, id: string) => {
    navigator.clipboard.writeText(text).catch(() => {});
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-6">
        <Link
          to="/profile"
          className="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground transition-colors mb-3"
          style={{ fontSize: "13px", fontWeight: 500 }}
        >
          <ArrowLeft className="w-4 h-4" />
          Back to Profile
        </Link>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
              <Key className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h1>API Tokens</h1>
              <p className="text-muted-foreground mt-0.5" style={{ fontSize: "14px" }}>
                Manage personal access tokens for API authentication
              </p>
            </div>
          </div>
          <Dialog open={createOpen} onOpenChange={(open) => { setCreateOpen(open); if (!open) { setNewTokenName(""); setNewTokenCreated(null); } }}>
            <DialogTrigger asChild>
              <Button className="h-9 gap-2">
                <Plus className="w-4 h-4" /> Create Token
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{newTokenCreated ? "Token Created" : "Create API Token"}</DialogTitle>
                <DialogDescription>
                  {newTokenCreated ? "Copy your token below before closing this dialog." : "Enter a name for your new API token."}
                </DialogDescription>
              </DialogHeader>
              {newTokenCreated ? (
                <div className="space-y-4 mt-4">
                  <div className="flex items-center gap-2 p-3 rounded-lg bg-success/10 border border-success/20">
                    <CheckCircle2 className="w-4 h-4 text-success shrink-0" />
                    <p className="text-success" style={{ fontSize: "13px" }}>
                      Token created successfully. Copy it now — you won't be able to see it again.
                    </p>
                  </div>
                  <div className="relative">
                    <Input
                      value={newTokenCreated}
                      readOnly
                      className="h-10 font-mono pr-10 bg-input-background"
                      style={{ fontSize: "13px" }}
                    />
                    <button
                      onClick={() => copyToClipboard(newTokenCreated, "new")}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                      {copiedId === "new" ? <CheckCircle2 className="w-4 h-4 text-success" /> : <Copy className="w-4 h-4" />}
                    </button>
                  </div>
                  <Button className="w-full" onClick={() => { setCreateOpen(false); setNewTokenCreated(null); setNewTokenName(""); }}>
                    Done
                  </Button>
                </div>
              ) : (
                <div className="space-y-4 mt-4">
                  <div>
                    <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Token Name</label>
                    <Input
                      value={newTokenName}
                      onChange={(e) => setNewTokenName(e.target.value)}
                      placeholder="e.g. CI/CD Pipeline"
                      className="h-9 bg-input-background"
                    />
                    <p className="text-muted-foreground mt-1" style={{ fontSize: "12px" }}>
                      A descriptive name to help you identify this token later.
                    </p>
                  </div>
                  <div className="flex justify-end gap-2 pt-2">
                    <Button variant="outline" onClick={() => setCreateOpen(false)}>Cancel</Button>
                    <Button onClick={handleCreateToken} disabled={!newTokenName.trim()}>Create Token</Button>
                  </div>
                </div>
              )}
            </DialogContent>
          </Dialog>
        </div>
      </div>

      {/* Info Card */}
      <Card className="border border-primary/20 shadow-none mb-6 bg-primary/[0.02]">
        <CardContent className="p-4 flex items-start gap-3">
          <Shield className="w-5 h-5 text-primary shrink-0 mt-0.5" />
          <div>
            <div style={{ fontSize: "13px", fontWeight: 500 }}>API Token Security</div>
            <p className="text-muted-foreground" style={{ fontSize: "12px" }}>
              Tokens provide full API access to your account. Keep them secret and rotate them regularly.
              If a token is compromised, delete it immediately and create a new one.
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Token List */}
      <div className="space-y-3 mb-8">
        {tokens.length === 0 ? (
          <Card className="border border-border shadow-none">
            <CardContent className="p-12 text-center">
              <Key className="w-8 h-8 text-muted-foreground mx-auto mb-3" />
              <p className="text-muted-foreground" style={{ fontSize: "14px" }}>No API tokens yet.</p>
              <Button className="mt-4 h-9 gap-2" onClick={() => setCreateOpen(true)}>
                <Plus className="w-4 h-4" /> Create Token
              </Button>
            </CardContent>
          </Card>
        ) : (
          tokens.map((token) => (
            <Card key={token.id} className="border border-border shadow-none">
              <CardContent className="p-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-muted flex items-center justify-center">
                      <Key className="w-4 h-4 text-muted-foreground" />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <span style={{ fontSize: "14px", fontWeight: 500 }}>{token.name}</span>
                        <code className="text-muted-foreground font-mono" style={{ fontSize: "11px" }}>
                          ···{token.lastFour}
                        </code>
                      </div>
                      <div className="flex items-center gap-3 mt-0.5">
                        <span className="text-muted-foreground" style={{ fontSize: "12px" }}>
                          Created {token.createdAt}
                        </span>
                        <span className="text-muted-foreground" style={{ fontSize: "12px" }}>
                          {token.lastUsed ? `Last used ${token.lastUsed}` : "Never used"}
                        </span>
                      </div>
                      <div className="flex flex-wrap gap-1 mt-1.5">
                        {token.abilities.map((ability) => (
                          <Badge key={ability} variant="secondary" className="font-mono" style={{ fontSize: "10px" }}>
                            {ability}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  </div>
                  <AlertDialog>
                    <AlertDialogTrigger asChild>
                      <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-destructive">
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>Delete token "{token.name}"?</AlertDialogTitle>
                        <AlertDialogDescription>
                          Any applications using this token will no longer be able to access the API. This action cannot be undone.
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                          className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                          onClick={() => handleDeleteToken(token.id)}
                        >
                          Delete Token
                        </AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                </div>
              </CardContent>
            </Card>
          ))
        )}
      </div>
    </div>
  );
}