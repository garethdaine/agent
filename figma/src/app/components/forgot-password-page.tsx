import { useState } from "react";
import { Link } from "react-router";
import { Clock, ArrowLeft, Mail } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Card, CardContent } from "./ui/card";

export function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      setLoading(false);
      setSent(true);
    }, 800);
  };

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-4">
      <div className="w-full max-w-sm">
        {/* Logo */}
        <div className="flex items-center justify-center gap-3 mb-8">
          <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
            <Clock className="w-5 h-5 text-primary-foreground" />
          </div>
          <span className="text-foreground tracking-tight" style={{ fontSize: "20px", fontWeight: 600 }}>Agent Scheduler</span>
        </div>

        <Card className="border border-border shadow-sm">
          <CardContent className="p-6">
            {sent ? (
              <div className="text-center">
                <div className="w-12 h-12 rounded-full bg-success/10 flex items-center justify-center mx-auto mb-4">
                  <Mail className="w-6 h-6 text-success" />
                </div>
                <h2 className="mb-2" style={{ fontSize: "18px", fontWeight: 600 }}>Check your email</h2>
                <p className="text-muted-foreground mb-6" style={{ fontSize: "13px" }}>
                  We've sent a password reset link to{" "}
                  <span className="text-foreground" style={{ fontWeight: 500 }}>{email}</span>.
                  Check your inbox and follow the instructions to reset your password.
                </p>
                <Button variant="outline" className="w-full h-10" onClick={() => setSent(false)}>
                  Send again
                </Button>
              </div>
            ) : (
              <>
                <h2 className="text-center mb-1" style={{ fontSize: "18px", fontWeight: 600 }}>Forgot Password</h2>
                <p className="text-center text-muted-foreground mb-6" style={{ fontSize: "13px" }}>
                  Enter your email and we'll send you a reset link
                </p>
                <form onSubmit={handleSubmit} className="space-y-4">
                  <div>
                    <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Email</label>
                    <Input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="you@example.com"
                      className="h-10 bg-input-background"
                      required
                    />
                  </div>

                  <Button type="submit" className="w-full h-10" disabled={loading}>
                    {loading ? (
                      <span className="flex items-center gap-2">
                        <span className="w-4 h-4 border-2 border-primary-foreground/30 border-t-primary-foreground rounded-full animate-spin" />
                        Sending...
                      </span>
                    ) : (
                      "Send Reset Link"
                    )}
                  </Button>
                </form>
              </>
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
