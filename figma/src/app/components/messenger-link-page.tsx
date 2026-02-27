import { useState, useEffect } from "react";
import { useParams, Link } from "react-router";
import { Clock, CheckCircle2, XCircle, Loader2, MessageSquare, ArrowLeft, AlertTriangle } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";

export function MessengerLinkPage() {
  const { token } = useParams();
  const [status, setStatus] = useState<"loading" | "valid" | "invalid" | "expired">("loading");

  useEffect(() => {
    // Simulate token validation
    const timer = setTimeout(() => {
      if (token === "valid-token" || token === "abc123") {
        setStatus("valid");
      } else if (token === "expired-token") {
        setStatus("expired");
      } else {
        setStatus("invalid");
      }
    }, 1500);
    return () => clearTimeout(timer);
  }, [token]);

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-4">
      <div className="w-full max-w-sm">
        <div className="flex items-center justify-center gap-3 mb-8">
          <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
            <Clock className="w-5 h-5 text-primary-foreground" />
          </div>
          <span className="text-foreground tracking-tight" style={{ fontSize: "20px", fontWeight: 600 }}>Agent Scheduler</span>
        </div>

        <Card className="border border-border shadow-sm">
          <CardContent className="p-6">
            {status === "loading" && (
              <div className="text-center py-8">
                <div className="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center mx-auto mb-4">
                  <Loader2 className="w-6 h-6 text-primary animate-spin" />
                </div>
                <h2 className="mb-2" style={{ fontSize: "18px", fontWeight: 600 }}>Verifying Link</h2>
                <p className="text-muted-foreground" style={{ fontSize: "13px" }}>
                  Validating your messenger account linking token...
                </p>
              </div>
            )}

            {status === "valid" && (
              <div className="text-center py-4">
                <div className="w-12 h-12 rounded-full bg-success/10 flex items-center justify-center mx-auto mb-4">
                  <CheckCircle2 className="w-6 h-6 text-success" />
                </div>
                <h2 className="mb-2" style={{ fontSize: "18px", fontWeight: 600 }}>Account Linked</h2>
                <p className="text-muted-foreground mb-6" style={{ fontSize: "13px" }}>
                  Your messenger account has been successfully linked to Agent Scheduler.
                  You can now receive notifications and interact with the agent through your messaging platform.
                </p>

                <div className="bg-muted/50 rounded-lg p-4 mb-6 text-left">
                  <div className="flex items-center gap-2 mb-2">
                    <MessageSquare className="w-4 h-4 text-primary" />
                    <span style={{ fontSize: "13px", fontWeight: 500 }}>Connection Details</span>
                  </div>
                  <div className="space-y-1">
                    <div className="flex justify-between">
                      <span className="text-muted-foreground" style={{ fontSize: "12px" }}>Provider</span>
                      <span style={{ fontSize: "12px", fontWeight: 500 }}>Slack</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground" style={{ fontSize: "12px" }}>Workspace</span>
                      <span style={{ fontSize: "12px", fontWeight: 500 }}>Agent Development</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground" style={{ fontSize: "12px" }}>User</span>
                      <span style={{ fontSize: "12px", fontWeight: 500 }}>gareth@garethdaine.com</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-muted-foreground" style={{ fontSize: "12px" }}>Linked</span>
                      <span style={{ fontSize: "12px", fontWeight: 500 }}>Just now</span>
                    </div>
                  </div>
                </div>

                <Button className="w-full h-10" asChild>
                  <Link to="/dashboard">Go to Dashboard</Link>
                </Button>
              </div>
            )}

            {status === "invalid" && (
              <div className="text-center py-4">
                <div className="w-12 h-12 rounded-full bg-destructive/10 flex items-center justify-center mx-auto mb-4">
                  <XCircle className="w-6 h-6 text-destructive" />
                </div>
                <h2 className="mb-2" style={{ fontSize: "18px", fontWeight: 600 }}>Invalid Link</h2>
                <p className="text-muted-foreground mb-6" style={{ fontSize: "13px" }}>
                  This account linking token is not valid. It may have been mistyped or corrupted.
                  Please request a new linking token from the messenger control plane.
                </p>
                <div className="space-y-2">
                  <Button className="w-full h-10" asChild>
                    <Link to="/messenger">Go to Messenger</Link>
                  </Button>
                  <Button variant="outline" className="w-full h-10" asChild>
                    <Link to="/login">Sign In</Link>
                  </Button>
                </div>
              </div>
            )}

            {status === "expired" && (
              <div className="text-center py-4">
                <div className="w-12 h-12 rounded-full bg-warning/10 flex items-center justify-center mx-auto mb-4">
                  <AlertTriangle className="w-6 h-6 text-warning" />
                </div>
                <h2 className="mb-2" style={{ fontSize: "18px", fontWeight: 600 }}>Link Expired</h2>
                <p className="text-muted-foreground mb-6" style={{ fontSize: "13px" }}>
                  This account linking token has expired. Linking tokens are valid for 15 minutes.
                  Please request a new linking token from the messenger control plane.
                </p>
                <div className="space-y-2">
                  <Button className="w-full h-10" asChild>
                    <Link to="/messenger">Request New Link</Link>
                  </Button>
                  <Button variant="outline" className="w-full h-10" asChild>
                    <Link to="/login">Sign In</Link>
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        <p className="text-center mt-4">
          <Link to="/login" className="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground transition-colors" style={{ fontSize: "13px" }}>
            <ArrowLeft className="w-3.5 h-3.5" />
            Back to sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
