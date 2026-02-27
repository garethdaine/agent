import { Card, CardContent } from "../ui/card";
import { Progress } from "../ui/progress";

interface StatsPanelProps {
  questions: number;
  answers: number;
  elapsed: string;
  status: string;
  progress: number;
  categories: string[];
}

const statusColorMap: Record<string, string> = {
  setup: "bg-muted text-muted-foreground",
  discovering: "bg-primary/10 text-primary",
  interrogating: "bg-primary/10 text-primary",
  summarizing: "bg-warning/10 text-warning",
  planning: "bg-warning/10 text-warning",
  build_rules: "bg-warning/10 text-warning",
  build_tasks: "bg-primary/10 text-primary",
  build_executing: "bg-primary/10 text-primary",
  completed: "bg-success/10 text-success",
  failed: "bg-destructive/10 text-destructive",
  paused: "bg-warning/10 text-warning",
};

const categoryColors = [
  "bg-primary/10 text-primary",
  "bg-destructive/10 text-destructive",
  "bg-warning/10 text-warning",
  "bg-[#7c3aed]/10 text-[#7c3aed]",
  "bg-success/10 text-success",
];

export function StatsPanel({
  questions,
  answers,
  elapsed,
  status,
  progress,
  categories,
}: StatsPanelProps) {
  return (
    <Card className="border border-border shadow-none">
      <CardContent className="p-4">
        <h4
          className="uppercase tracking-wider text-muted-foreground mb-3"
          style={{ fontSize: "11px", fontWeight: 600 }}
        >
          Stats
        </h4>
        <div className="space-y-2.5">
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
              Questions
            </span>
            <span className="text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
              {questions}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
              Answers
            </span>
            <span className="text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
              {answers}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
              Elapsed
            </span>
            <span className="text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
              {elapsed}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
              Status
            </span>
            <span
              className={`inline-flex items-center px-2 py-0.5 rounded-full ${statusColorMap[status] || "bg-muted text-muted-foreground"}`}
              style={{ fontSize: "11px", fontWeight: 500 }}
            >
              {status}
            </span>
          </div>
          <div>
            <div className="flex items-center justify-between mb-1.5">
              <span className="text-muted-foreground" style={{ fontSize: "13px" }}>
                Progress
              </span>
              <span className="text-foreground" style={{ fontSize: "13px", fontWeight: 500 }}>
                {progress}%
              </span>
            </div>
            <Progress value={progress} className="h-1.5" />
          </div>
        </div>

        {/* Categories */}
        <div className="mt-4 pt-3 border-t border-border">
          <h4
            className="uppercase tracking-wider text-muted-foreground mb-2"
            style={{ fontSize: "11px", fontWeight: 600 }}
          >
            Categories
          </h4>
          {categories.length === 0 ? (
            <p className="text-muted-foreground" style={{ fontSize: "12px" }}>
              No categories yet
            </p>
          ) : (
            <div className="flex flex-wrap gap-1.5">
              {categories.map((cat, i) => (
                <span
                  key={cat}
                  className={`inline-flex items-center px-2 py-0.5 rounded-full ${categoryColors[i % categoryColors.length]}`}
                  style={{ fontSize: "11px", fontWeight: 500 }}
                >
                  {cat}
                </span>
              ))}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
