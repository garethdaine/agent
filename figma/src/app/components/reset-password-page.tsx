import { useState } from "react";
import { useNavigate, Link } from "react-router";
import { Clock, Eye, EyeOff, ArrowLeft, CheckCircle2 } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Card, CardContent } from "./ui/card";

export function ResetPasswordPage() {
  const navigate = useNavigate();
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPw, setShowPw] = useState(false);
  const [showConfirmPw, setShowConfirmPw] = useState(false);
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      setLoading(false);
      setSuccess(true);
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
            {success ? (
              <div className="text-center">
                <div className="w-12 h-12 rounded-full bg-success/10 flex items-center justify-center mx-auto mb-4">
                  <CheckCircle2 className="w-6 h-6 text-success" />
                </div>
                <h2 className="mb-2" style={{ fontSize: "18px", fontWeight: 600 }}>Password Reset</h2>
                <p className="text-muted-foreground mb-6" style={{ fontSize: "13px" }}>
                  Your password has been successfully reset. You can now sign in with your new password.
                </p>
                <Button className="w-full h-10" onClick={() => navigate("/login")}>
                  Back to Sign In
                </Button>
              </div>
            ) : (
              <>
                <h2 className="text-center mb-1" style={{ fontSize: "18px", fontWeight: 600 }}>Reset Password</h2>
                <p className="text-center text-muted-foreground mb-6" style={{ fontSize: "13px" }}>
                  Choose a new password for your account
                </p>
                <form onSubmit={handleSubmit} className="space-y-4">
                  <div>
                    <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>New Password</label>
                    <div className="relative">
                      <Input
                        type={showPw ? "text" : "password"}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="Min. 8 characters"
                        className="h-10 pr-10 bg-input-background"
                        required
                      />
                      <button
                        type="button"
                        onClick={() => setShowPw(!showPw)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                      >
                        {showPw ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                      </button>
                    </div>
                  </div>
                  <div>
                    <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Confirm Password</label>
                    <div className="relative">
                      <Input
                        type={showConfirmPw ? "text" : "password"}
                        value={confirmPassword}
                        onChange={(e) => setConfirmPassword(e.target.value)}
                        placeholder="Re-enter new password"
                        className="h-10 pr-10 bg-input-background"
                        required
                      />
                      <button
                        type="button"
                        onClick={() => setShowConfirmPw(!showConfirmPw)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                      >
                        {showConfirmPw ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                      </button>
                    </div>
                  </div>
                  <p className="text-muted-foreground" style={{ fontSize: "12px" }}>
                    Must contain at least 8 characters, one uppercase letter, one number, and one special character.
                  </p>

                  <Button type="submit" className="w-full h-10" disabled={loading}>
                    {loading ? (
                      <span className="flex items-center gap-2">
                        <span className="w-4 h-4 border-2 border-primary-foreground/30 border-t-primary-foreground rounded-full animate-spin" />
                        Resetting...
                      </span>
                    ) : (
                      "Reset Password"
                    )}
                  </Button>
                </form>
              </>
            )}
          </CardContent>
        </Card>

        {!success && (
          <p className="text-center mt-4">
            <Link to="/login" className="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground transition-colors" style={{ fontSize: "13px" }}>
              <ArrowLeft className="w-3.5 h-3.5" />
              Back to sign in
            </Link>
          </p>
        )}
      </div>
    </div>
  );
}
