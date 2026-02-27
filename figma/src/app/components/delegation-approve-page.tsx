import { useState } from "react";
import { useParams, Link, useNavigate } from "react-router";
import {
  ArrowLeft,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  FileText,
  Terminal,
  MessageSquare,
} from "lucide-react";
import { Button } from "./ui/button";
import { Textarea } from "./ui/textarea";
import { Card, CardContent } from "./ui/card";
import { Badge } from "./ui/badge";

const taskData = {
  id: "T7",
  graphName: "Feature: Auth Module",
  title: "Integration tests",
  assignee: "Claude (Tests)",
  submittedAt: "2026-02-26T14:20:00Z",
  summary: "Completed 42 out of 45 integration tests for the authentication module. Three tests failed: expired 2FA token returns wrong status code (403 vs 401), concurrent session limit test times out, and password reset token reuse check passes when it should fail.",
  testResults: {
    passed: 42,
    failed: 3,
    coverage: "87.3%",
  },
  failedTests: [
    {
      name: "test_2fa_expired_token",
      expected: "HTTP 401 Unauthorized",
      actual: "HTTP 403 Forbidden",
      note: "Status code mismatch — the controller returns 403 for all auth failures. Spec says 401 for expired tokens.",
    },
    {
      name: "test_session_concurrent_limit",
      expected: "Reject 6th concurrent session",
      actual: "Timeout after 30s",
      note: "The session limit middleware isn't active in the test environment. Needs test config update.",
    },
    {
      name: "test_password_reset_reuse",
      expected: "Error on second use of reset token",
      actual: "Token accepted for reuse",
      note: "The token invalidation happens async — race condition in test. The feature works in production.",
    },
  ],
  filesChanged: [
    "tests/Feature/Auth/RegistrationTest.php",
    "tests/Feature/Auth/LoginTest.php",
    "tests/Feature/Auth/PasswordResetTest.php",
    "tests/Feature/Auth/TwoFactorTest.php",
    "tests/Feature/Auth/SessionTest.php",
    "tests/TestCase.php",
  ],
};

export function DelegationApprovePage() {
  const { graphId, taskId } = useParams();
  const navigate = useNavigate();
  const [feedback, setFeedback] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const handleApprove = () => {
    setSubmitting(true);
    setTimeout(() => navigate(`/delegation/${graphId}`), 800);
  };

  const handleReject = () => {
    setSubmitting(true);
    setTimeout(() => navigate(`/delegation/${graphId}`), 800);
  };

  return (
    <div className="max-w-3xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => navigate(`/delegation/${graphId}/tasks/${taskId}`)}>
          <ArrowLeft className="w-4 h-4" />
        </Button>
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-1">
            <Link to={`/delegation/${graphId}`} className="text-muted-foreground hover:text-primary transition-colors" style={{ fontSize: "12px" }}>
              {taskData.graphName}
            </Link>
            <span className="text-muted-foreground" style={{ fontSize: "12px" }}>/</span>
            <span className="text-muted-foreground font-mono" style={{ fontSize: "12px" }}>{taskData.id}</span>
          </div>
          <h1>Human Verification: {taskData.title}</h1>
        </div>
      </div>

      {/* Alert Banner */}
      <div className="flex items-center gap-3 p-4 mb-6 rounded-lg bg-warning/10 border border-warning/20">
        <AlertTriangle className="w-5 h-5 text-warning shrink-0" />
        <div>
          <div style={{ fontSize: "14px", fontWeight: 500 }}>Approval Required</div>
          <p className="text-muted-foreground" style={{ fontSize: "13px" }}>
            This task has been submitted for human verification. Review the output and decide whether to approve or reject.
          </p>
        </div>
      </div>

      {/* Task Summary */}
      <Card className="border border-border shadow-none mb-4">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-3">
            <FileText className="w-4 h-4 text-primary" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Task Summary</h3>
          </div>
          <p className="text-foreground mb-4" style={{ fontSize: "13px", lineHeight: 1.6 }}>
            {taskData.summary}
          </p>
          <div className="grid grid-cols-3 gap-4">
            <div className="p-3 bg-success/5 rounded-lg border border-success/20 text-center">
              <div className="text-success" style={{ fontSize: "20px", fontWeight: 700 }}>{taskData.testResults.passed}</div>
              <div className="text-success" style={{ fontSize: "11px", fontWeight: 500 }}>Passed</div>
            </div>
            <div className="p-3 bg-destructive/5 rounded-lg border border-destructive/20 text-center">
              <div className="text-destructive" style={{ fontSize: "20px", fontWeight: 700 }}>{taskData.testResults.failed}</div>
              <div className="text-destructive" style={{ fontSize: "11px", fontWeight: 500 }}>Failed</div>
            </div>
            <div className="p-3 bg-primary/5 rounded-lg border border-primary/20 text-center">
              <div className="text-primary" style={{ fontSize: "20px", fontWeight: 700 }}>{taskData.testResults.coverage}</div>
              <div className="text-primary" style={{ fontSize: "11px", fontWeight: 500 }}>Coverage</div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Failed Tests Detail */}
      <Card className="border border-destructive/20 shadow-none mb-4">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-3">
            <XCircle className="w-4 h-4 text-destructive" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Failed Tests ({taskData.failedTests.length})</h3>
          </div>
          <div className="space-y-4">
            {taskData.failedTests.map((test, i) => (
              <div key={i} className="p-3 bg-muted/50 rounded-lg">
                <div className="font-mono text-destructive mb-2" style={{ fontSize: "13px", fontWeight: 500 }}>
                  {test.name}
                </div>
                <div className="grid grid-cols-2 gap-2 mb-2">
                  <div>
                    <span className="text-muted-foreground" style={{ fontSize: "11px" }}>Expected:</span>
                    <div style={{ fontSize: "12px" }}>{test.expected}</div>
                  </div>
                  <div>
                    <span className="text-muted-foreground" style={{ fontSize: "11px" }}>Actual:</span>
                    <div className="text-destructive" style={{ fontSize: "12px" }}>{test.actual}</div>
                  </div>
                </div>
                <p className="text-muted-foreground" style={{ fontSize: "12px" }}>{test.note}</p>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Files Changed */}
      <Card className="border border-border shadow-none mb-4">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-3">
            <Terminal className="w-4 h-4 text-primary" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Files Changed ({taskData.filesChanged.length})</h3>
          </div>
          <div className="space-y-1">
            {taskData.filesChanged.map((file, i) => (
              <div key={i} className="flex items-center gap-2 p-1.5 rounded hover:bg-muted/50">
                <FileText className="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                <span className="font-mono text-muted-foreground" style={{ fontSize: "12px" }}>{file}</span>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Feedback + Actions */}
      <Card className="border border-primary/20 shadow-none mb-8">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-3">
            <MessageSquare className="w-4 h-4 text-primary" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>Review Decision</h3>
          </div>
          <div className="mb-4">
            <label className="block mb-1.5 text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
              Feedback (optional)
            </label>
            <Textarea
              value={feedback}
              onChange={(e) => setFeedback(e.target.value)}
              placeholder="Provide feedback for the agent, especially if rejecting..."
              className="min-h-[100px] bg-input-background"
              style={{ fontSize: "13px" }}
            />
          </div>
          <div className="flex items-center gap-3">
            <Button
              className="h-10 gap-2 bg-success hover:bg-success/90 text-success-foreground"
              onClick={handleApprove}
              disabled={submitting}
            >
              <CheckCircle2 className="w-4 h-4" />
              Approve Task
            </Button>
            <Button
              variant="outline"
              className="h-10 gap-2 text-destructive border-destructive/30 hover:bg-destructive/10"
              onClick={handleReject}
              disabled={submitting}
            >
              <XCircle className="w-4 h-4" />
              Reject & Retry
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
