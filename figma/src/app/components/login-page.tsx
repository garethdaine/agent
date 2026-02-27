import { useState } from "react";
import { useNavigate, Link } from "react-router";
import { Clock, Eye, EyeOff } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Checkbox } from "./ui/checkbox";
import { Card, CardContent } from "./ui/card";

export function LoginPage() {
  const navigate = useNavigate();
  const [email, setEmail] = useState("gareth@garethdaine.com");
  const [password, setPassword] = useState("password123456");
  const [showPw, setShowPw] = useState(false);
  const [remember, setRemember] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleLogin = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      navigate("/dashboard");
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
          <span className="text-foreground tracking-tight" style={{ fontSize: '20px', fontWeight: 600 }}>Agent Scheduler</span>
        </div>

        <Card className="border border-border shadow-sm">
          <CardContent className="p-6">
            <h2 className="text-center mb-6" style={{ fontSize: '18px', fontWeight: 600 }}>Sign in to your account</h2>
            <form onSubmit={handleLogin} className="space-y-4">
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Email</label>
                <Input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.com"
                  className="h-10 bg-input-background"
                />
              </div>
              <div>
                <label className="block mb-1.5 text-foreground" style={{ fontSize: '13px', fontWeight: 500 }}>Password</label>
                <div className="relative">
                  <Input
                    type={showPw ? "text" : "password"}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Enter password"
                    className="h-10 pr-10 bg-input-background"
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

              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Checkbox
                    checked={remember}
                    onCheckedChange={(v) => setRemember(v === true)}
                    id="remember"
                  />
                  <label htmlFor="remember" className="text-muted-foreground cursor-pointer" style={{ fontSize: '13px', fontWeight: 400 }}>
                    Remember me
                  </label>
                </div>
                <Link to="/forgot-password" className="text-primary hover:underline" style={{ fontSize: '13px' }}>
                  Forgot password?
                </Link>
              </div>

              <Button type="submit" className="w-full h-10" disabled={loading}>
                {loading ? (
                  <span className="flex items-center gap-2">
                    <span className="w-4 h-4 border-2 border-primary-foreground/30 border-t-primary-foreground rounded-full animate-spin" />
                    Signing in...
                  </span>
                ) : (
                  "Sign in"
                )}
              </Button>
            </form>
          </CardContent>
        </Card>

        <p className="text-center mt-4 text-muted-foreground" style={{ fontSize: '13px' }}>
          Don't have an account?{" "}
          <Link to="/register" className="text-primary hover:underline">Create one</Link>
        </p>
      </div>
    </div>
  );
}