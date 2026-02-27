import { Link } from "react-router";
import { Clock, ArrowLeft } from "lucide-react";
import { Card, CardContent } from "./ui/card";

export function TermsPage() {
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
            <h1 className="mb-6" style={{ fontSize: "24px", fontWeight: 600 }}>Terms of Service</h1>
            <p className="text-muted-foreground mb-4" style={{ fontSize: "12px" }}>Last updated: February 1, 2026</p>

            <div className="space-y-6 text-foreground" style={{ fontSize: "14px", lineHeight: 1.7 }}>
              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>1. Acceptance of Terms</h3>
                <p className="text-muted-foreground">
                  By accessing or using Agent Scheduler ("the Service"), you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use the Service. The Service is provided by Agent Scheduler and is intended for use by authorized personnel managing automated agent workflows.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>2. Description of Service</h3>
                <p className="text-muted-foreground">
                  Agent Scheduler provides tools for scheduling, monitoring, and managing AI agent tasks, including job scheduling, requirements discovery, task delegation, messenger integrations, and database backup management. The Service operates on your local infrastructure and interacts with AI providers on your behalf.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>3. User Responsibilities</h3>
                <p className="text-muted-foreground">
                  You are responsible for maintaining the confidentiality of your account credentials, all activities that occur under your account, ensuring that your use of the Service complies with applicable laws, and reviewing and approving AI-generated outputs before deployment to production systems.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>4. Data and Privacy</h3>
                <p className="text-muted-foreground">
                  Your data remains on your infrastructure. Agent Scheduler does not store, transmit, or process your code or task data on external servers beyond what is necessary for AI provider API calls. See our <Link to="/privacy" className="text-primary hover:underline">Privacy Policy</Link> for full details.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>5. API and Third-Party Services</h3>
                <p className="text-muted-foreground">
                  The Service integrates with third-party AI providers (e.g., Anthropic Claude, OpenAI Codex) and task management tools (e.g., Linear). Your use of these integrations is subject to the respective provider's terms of service. Agent Scheduler is not responsible for third-party service availability or data handling.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>6. Limitation of Liability</h3>
                <p className="text-muted-foreground">
                  The Service is provided "as is" without warranties of any kind. Agent Scheduler shall not be liable for any damages arising from the use or inability to use the Service, including but not limited to damages from AI-generated code, automated task execution failures, or data loss from backup operations.
                </p>
              </section>

              <section>
                <h3 className="mb-2" style={{ fontSize: "16px", fontWeight: 600 }}>7. Modifications</h3>
                <p className="text-muted-foreground">
                  We reserve the right to modify these terms at any time. Continued use of the Service after changes constitutes acceptance of the modified terms. Material changes will be communicated via the Service interface.
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
