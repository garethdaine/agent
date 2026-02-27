import { useState, useRef } from "react";
import { useNavigate, Link } from "react-router";
import { Clock, ArrowLeft, Shield } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";

export function TwoFactorPage() {
  const navigate = useNavigate();
  const [code, setCode] = useState<string[]>(["", "", "", "", "", ""]);
  const [loading, setLoading] = useState(false);
  const [showBackupInput, setShowBackupInput] = useState(false);
  const [backupCode, setBackupCode] = useState("");
  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);

  const handleDigitChange = (index: number, value: string) => {
    // Only allow single digit
    const digit = value.replace(/\D/g, "").slice(-1);
    const newCode = [...code];
    newCode[index] = digit;
    setCode(newCode);

    // Auto-focus next input
    if (digit && index < 5) {
      inputRefs.current[index + 1]?.focus();
    }
  };

  const handleKeyDown = (index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Backspace" && !code[index] && index > 0) {
      inputRefs.current[index - 1]?.focus();
    }
  };

  const handlePaste = (e: React.ClipboardEvent) => {
    e.preventDefault();
    const pasted = e.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
    const newCode = [...code];
    for (let i = 0; i < pasted.length; i++) {
      newCode[i] = pasted[i];
    }
    setCode(newCode);
    // Focus the next empty input or the last one
    const nextEmpty = newCode.findIndex((d) => !d);
    const focusIdx = nextEmpty === -1 ? 5 : nextEmpty;
    inputRefs.current[focusIdx]?.focus();
  };

  const handleVerify = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      navigate("/dashboard");
    }, 800);
  };

  const handleBackupSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setTimeout(() => {
      navigate("/dashboard");
    }, 800);
  };

  const codeComplete = code.every((d) => d !== "");

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
            <div className="flex justify-center mb-4">
              <div className="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                <Shield className="w-6 h-6 text-primary" />
              </div>
            </div>
            <h2 className="text-center mb-1" style={{ fontSize: "18px", fontWeight: 600 }}>Two-Factor Authentication</h2>
            <p className="text-center text-muted-foreground mb-6" style={{ fontSize: "13px" }}>
              {showBackupInput
                ? "Enter one of your backup codes to verify your identity"
                : "Enter the 6-digit code from your authenticator app"
              }
            </p>

            {showBackupInput ? (
              <form onSubmit={handleBackupSubmit} className="space-y-4">
                <div>
                  <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>Backup Code</label>
                  <input
                    type="text"
                    value={backupCode}
                    onChange={(e) => setBackupCode(e.target.value)}
                    placeholder="xxxx-xxxx-xxxx"
                    className="flex w-full h-10 rounded-md border border-input bg-input-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] font-mono tracking-wider text-center"
                    style={{ fontSize: "15px", letterSpacing: "0.1em" }}
                  />
                </div>

                <Button type="submit" className="w-full h-10" disabled={loading || !backupCode.trim()}>
                  {loading ? (
                    <span className="flex items-center gap-2">
                      <span className="w-4 h-4 border-2 border-primary-foreground/30 border-t-primary-foreground rounded-full animate-spin" />
                      Verifying...
                    </span>
                  ) : (
                    "Verify"
                  )}
                </Button>

                <button
                  type="button"
                  onClick={() => { setShowBackupInput(false); setBackupCode(""); }}
                  className="w-full text-center text-primary hover:underline"
                  style={{ fontSize: "13px" }}
                >
                  Use authenticator code instead
                </button>
              </form>
            ) : (
              <form onSubmit={handleVerify} className="space-y-4">
                {/* 6-digit code input */}
                <div className="flex items-center justify-center gap-2" onPaste={handlePaste}>
                  {code.map((digit, i) => (
                    <input
                      key={i}
                      ref={(el) => { inputRefs.current[i] = el; }}
                      type="text"
                      inputMode="numeric"
                      maxLength={1}
                      value={digit}
                      onChange={(e) => handleDigitChange(i, e.target.value)}
                      onKeyDown={(e) => handleKeyDown(i, e)}
                      className="w-11 h-12 rounded-md border border-input bg-input-background text-center text-foreground outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] transition-all"
                      style={{ fontSize: "20px", fontWeight: 600 }}
                    />
                  ))}
                </div>

                <Button type="submit" className="w-full h-10" disabled={loading || !codeComplete}>
                  {loading ? (
                    <span className="flex items-center gap-2">
                      <span className="w-4 h-4 border-2 border-primary-foreground/30 border-t-primary-foreground rounded-full animate-spin" />
                      Verifying...
                    </span>
                  ) : (
                    "Verify"
                  )}
                </Button>

                <button
                  type="button"
                  onClick={() => setShowBackupInput(true)}
                  className="w-full text-center text-primary hover:underline"
                  style={{ fontSize: "13px" }}
                >
                  Use a backup code
                </button>
              </form>
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
