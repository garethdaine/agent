import { Link } from "react-router";
import { Compass, Database, MessageSquare, ToggleLeft, ArrowRight } from "lucide-react";
import { Card, CardContent } from "./ui/card";

const tools = [
  {
    category: "Requirements Discovery",
    categoryColor: "text-primary",
    title: "Interactive discovery wizard",
    description: "Guide AI-led requirements interrogation and generate summaries/plans.",
    icon: Compass,
    path: "/tools/discovery",
  },
  {
    category: "Operations",
    categoryColor: "text-warning",
    title: "Database backup settings",
    description: "Configure daily DB backups, retention window, timezone, and run immediate backups.",
    icon: Database,
    path: "/tools/backups",
  },
  {
    category: "Messenger",
    categoryColor: "text-success",
    title: "Control plane dashboard",
    description: "View connector health, chat sessions, recent actions, and control-plane metrics.",
    icon: MessageSquare,
    path: "/tools/messenger",
  },
  {
    category: "Configuration",
    categoryColor: "text-[#d97706]",
    title: "Feature flag settings",
    description: "Enable or disable runtime feature flags without changing env/config files.",
    icon: ToggleLeft,
    path: "/tools/features",
  },
];

export function ToolsPage() {
  return (
    <div>
      <div className="mb-6">
        <h1>Tools</h1>
        <p className="text-muted-foreground mt-0.5" style={{ fontSize: '14px' }}>
          Utilities and configuration for your agent infrastructure
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {tools.map((tool) => {
          const Icon = tool.icon;
          return (
            <Link key={tool.path} to={tool.path} className="group">
              <Card className="border border-border shadow-none hover:shadow-md hover:border-primary/20 transition-all h-full">
                <CardContent className="p-6">
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <span className={`uppercase tracking-wider ${tool.categoryColor}`} style={{ fontSize: '11px', fontWeight: 600 }}>
                        {tool.category}
                      </span>
                      <h3 className="mt-1.5 text-foreground" style={{ fontSize: '16px', fontWeight: 600 }}>{tool.title}</h3>
                      <p className="text-muted-foreground mt-1" style={{ fontSize: '13px' }}>{tool.description}</p>
                    </div>
                    <div className="w-10 h-10 rounded-lg bg-primary/8 flex items-center justify-center shrink-0 ml-4 group-hover:bg-primary/15 transition-colors">
                      <Icon className="w-5 h-5 text-primary" />
                    </div>
                  </div>
                  <div className="flex items-center gap-1 mt-4 text-primary opacity-0 group-hover:opacity-100 transition-opacity" style={{ fontSize: '13px', fontWeight: 500 }}>
                    Open <ArrowRight className="w-3.5 h-3.5" />
                  </div>
                </CardContent>
              </Card>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
