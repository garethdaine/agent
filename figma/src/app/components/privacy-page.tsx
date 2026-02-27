import { Link } from "react-router";
import { Clock, ArrowLeft } from "lucide-react";
import { Card, CardContent } from "./ui/card";

export function PrivacyPage() {
  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-4">
      <div className="w-full max-w-2xl">
        <div className="flex items-center justify-center gap-3 mb-8">
          <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center">
            <Clock className="w-5 h-5 text-primary-foreground" />
          </div>
          <span className="text-foreground tracking-tight" style={{ fontSize: "20px", fontWeight: 600 }}>Agent Scheduler</span>
        </div>

        <Card className="border border-border shadow-sm">
          <CardContent className="p-8">
            <h1 className="mb-6" style={{ fontSize: "24px", fontWeight: 600 }}>Privacy Policy</h1>
            <p className="text-muted-foreground mb-4" style={{ fontSize: "12px" }}>Last updated: February 1, 2026</p>

            <div className="space-y-6 text-foreground" style={{ fontSize: "14px", lineHeight: 1.7 }}>
              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>1. Information We Collect</h3>
                <p className="text-muted-foreground">
                  Agent Scheduler collects minimal information necessary to operate: account credentials (email, hashed password), session data for authentication, job configurations and scheduling preferences, and discovery session data and generated artifacts. All operational data (code, tasks, build outputs) remains on your local infrastructure.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>2. How We Use Information</h3>
                <p className="text-muted-foreground">
                  Information is used solely to authenticate users and manage sessions, execute scheduled jobs and discovery workflows, display monitoring and reporting dashboards, and manage messenger integrations and delegation graphs.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>3. Data Storage and Security</h3>
                <p className="text-muted-foreground">
                  All data is stored locally on your infrastructure. Database backups are stored in your configured storage location. Passwords are hashed using bcrypt with appropriate salt rounds. API tokens are encrypted at rest. Two-factor authentication is available for additional account security.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>4. Third-Party Data Sharing</h3>
                <p className="text-muted-foreground">
                  When using AI providers (Claude, Codex), task content is sent to provider APIs for processing. When using Linear integration, project data is exchanged via Linear's API. Messenger integrations (Slack, Discord, Telegram) exchange messages via respective APIs. No data is sold, rented, or shared with third parties for marketing purposes.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>5. Data Retention</h3>
                <p className="text-muted-foreground">
                  Job run history is retained per your configuration. Discovery sessions persist until manually deleted. Database backups follow your configured retention policy. Account data is deleted upon account deletion (irreversible).
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>6. Your Rights</h3>
                <p className="text-muted-foreground">
                  You may access and export your data at any time, modify your account information, delete your account and all associated data, and revoke third-party integrations. For data-related requests, use the Profile & Security settings or contact your system administrator.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>7. Contact</h3>
                <p className="text-muted-foreground">
                  For privacy-related inquiries, contact your system administrator or refer to the <Link to="/terms" className="text-primary hover:underline">Terms of Service</Link> for additional information.
                </p>
              </section>
            </div>
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
