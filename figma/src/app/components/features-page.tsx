import { useState } from "react";
import { Link } from "react-router";
import { ArrowLeft, ToggleLeft, Save } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { Switch } from "./ui/switch";

interface FeatureFlag {
  id: string;
  label: string;
  description: string;
  key: string;
  enabled: boolean;
}

const initialFlags: FeatureFlag[] = [
  {
    id: "1",
    label: "Dark Mode",
    description:
      "Enable the dark mode theme toggle for all users. When disabled, the app defaults to light mode only.",
    key: "FEATURE_DARK_MODE",
    enabled: true,
  },
  {
    id: "2",
    label: "Notification Digest",
    description:
      "Aggregate notifications into a daily digest email instead of sending individual alerts in real-time.",
    key: "FEATURE_NOTIFICATION_DIGEST",
    enabled: false,
  },
  {
    id: "3",
    label: "API Rate Limiting",
    description:
      "Enforce per-user rate limits on all API endpoints. Helps prevent abuse and ensures fair usage.",
    key: "FEATURE_API_RATE_LIMIT",
    enabled: true,
  },
  {
    id: "4",
    label: "Experimental Pipeline",
    description:
      "Enable the experimental multi-step pipeline execution engine. May cause unexpected behavior in edge cases.",
    key: "FEATURE_EXPERIMENTAL_PIPELINE",
    enabled: false,
  },
  {
    id: "5",
    label: "Verbose Logging",
    description:
      "Output detailed debug-level logs for all agent operations. Increases log volume significantly.",
    key: "FEATURE_VERBOSE_LOGGING",
    enabled: false,
  },
];

export function FeaturesPage() {
  const [flags, setFlags] = useState<FeatureFlag[]>(initialFlags);

  const toggleFlag = (id: string) => {
    setFlags((prev) =>
      prev.map((f) => (f.id === id ? { ...f, enabled: !f.enabled } : f))
    );
  };

  const enabledCount = flags.filter((f) => f.enabled).length;

  return (
    <div className="max-w-2xl mx-auto">
      {/* Header with back arrow */}
      <div className="mb-6">
        <Link
          to="/tools"
          className="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground transition-colors mb-3"
          style={{ fontSize: "13px", fontWeight: 500 }}
        >
          <ArrowLeft className="w-4 h-4" />
          Back to Tools
        </Link>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-[#d97706]/10 flex items-center justify-center">
              <ToggleLeft className="w-5 h-5 text-[#d97706]" />
            </div>
            <div>
              <h1>Feature Flags</h1>
              <p
                className="text-muted-foreground mt-0.5"
                style={{ fontSize: "14px" }}
              >
                Toggle runtime features without redeploying
              </p>
            </div>
          </div>
          <span
            className="text-muted-foreground hidden sm:block"
            style={{ fontSize: "13px" }}
          >
            {enabledCount} of {flags.length} enabled
          </span>
        </div>
      </div>

      {/* Feature Flag List */}
      <div className="space-y-3 mb-6">
        {flags.map((flag) => (
          <Card
            key={flag.id}
            className={`border shadow-none transition-colors ${
              flag.enabled
                ? "border-primary/20 bg-primary/[0.02]"
                : "border-border"
            }`}
          >
            <CardContent className="p-5">
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2.5 mb-1">
                    <h3 style={{ fontSize: "15px", fontWeight: 600 }}>
                      {flag.label}
                    </h3>
                    {flag.enabled && (
                      <span
                        className="inline-flex items-center px-1.5 py-0.5 rounded bg-primary/10 text-primary"
                        style={{ fontSize: "10px", fontWeight: 600 }}
                      >
                        ACTIVE
                      </span>
                    )}
                  </div>
                  <p
                    className="text-muted-foreground mb-2"
                    style={{ fontSize: "13px" }}
                  >
                    {flag.description}
                  </p>
                  <code
                    className="inline-block px-2 py-0.5 rounded bg-muted text-muted-foreground font-mono"
                    style={{ fontSize: "11px" }}
                  >
                    {flag.key}
                  </code>
                </div>
                <Switch
                  checked={flag.enabled}
                  onCheckedChange={() => toggleFlag(flag.id)}
                  className="shrink-0 mt-1"
                />
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Save Button */}
      <div className="mb-8">
        <Button className="h-9 gap-2" style={{ fontSize: "13px" }}>
          <Save className="w-4 h-4" /> Save Changes
        </Button>
      </div>
    </div>
  );
}
