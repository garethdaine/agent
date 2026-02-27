import { Check } from "lucide-react";
import type { Phase } from "./types";

interface PhaseStepperProps {
  phases: Phase[];
  onPhaseClick: (id: number) => void;
}

export function PhaseStepper({ phases, onPhaseClick }: PhaseStepperProps) {
  return (
    <div className="bg-card border border-border rounded-lg p-4 mb-4 overflow-x-auto">
      <div className="flex items-center justify-between min-w-[700px]">
        {phases.map((phase, i) => {
          const isCompleted = phase.status === "completed";
          const isActive = phase.status === "active";
          const isFuture = phase.status === "future";
          const isLast = i === phases.length - 1;

          return (
            <div key={phase.id} className="flex items-center flex-1 last:flex-none">
              {/* Circle + label */}
              <button
                onClick={() => onPhaseClick(phase.id)}
                className="flex flex-col items-center gap-1.5 group cursor-pointer"
                disabled={isFuture}
              >
                <div
                  className={`w-7 h-7 rounded-full flex items-center justify-center transition-all ${
                    isCompleted
                      ? "bg-success text-success-foreground"
                      : isActive
                      ? "bg-primary text-primary-foreground ring-4 ring-primary/20"
                      : "border-2 border-muted-foreground/30 text-muted-foreground"
                  }`}
                  style={{ fontSize: "12px", fontWeight: 600 }}
                >
                  {isCompleted ? (
                    <Check className="w-3.5 h-3.5" />
                  ) : (
                    phase.id
                  )}
                </div>
                <span
                  className={`whitespace-nowrap transition-colors ${
                    isCompleted
                      ? "text-success"
                      : isActive
                      ? "text-primary"
                      : "text-muted-foreground"
                  }`}
                  style={{ fontSize: "11px", fontWeight: 500 }}
                >
                  {phase.label}
                </span>
              </button>

              {/* Connecting line */}
              {!isLast && (
                <div className="flex-1 mx-2 mt-[-18px]">
                  <div
                    className={`h-0.5 w-full rounded-full ${
                      isCompleted
                        ? "bg-success"
                        : "bg-muted-foreground/20"
                    }`}
                  />
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
