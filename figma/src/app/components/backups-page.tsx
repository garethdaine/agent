import { useState } from "react";
import { Link } from "react-router";
import {
  ArrowLeft,
  Database,
  Save,
  Play,
  CheckCircle2,
  Clock,
  HardDrive,
  Calendar,
} from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent } from "./ui/card";
import { Switch } from "./ui/switch";
import { Input } from "./ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";

const timezones = [
  "UTC",
  "America/New_York",
  "America/Chicago",
  "America/Denver",
  "America/Los_Angeles",
  "Europe/London",
  "Europe/Berlin",
  "Asia/Tokyo",
  "Asia/Shanghai",
  "Australia/Sydney",
];

export function BackupsPage() {
  const [dailyBackup, setDailyBackup] = useState(true);
  const [timezone, setTimezone] = useState("UTC");
  const [hour, setHour] = useState("3");
  const [minute, setMinute] = useState("00");
  const [retention, setRetention] = useState("30");

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
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
            <Database className="w-5 h-5 text-primary" />
          </div>
          <div>
            <h1>Database Backup Settings</h1>
            <p
              className="text-muted-foreground mt-0.5"
              style={{ fontSize: "14px" }}
            >
              Configure automated daily backups and retention policies
            </p>
          </div>
        </div>
      </div>

      {/* Daily Backup Toggle */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center justify-between">
            <div>
              <h3 style={{ fontSize: "15px", fontWeight: 600 }}>
                Daily Automated Backup
              </h3>
              <p
                className="text-muted-foreground mt-0.5"
                style={{ fontSize: "13px" }}
              >
                Automatically back up the database every day at the configured
                time
              </p>
            </div>
            <Switch checked={dailyBackup} onCheckedChange={setDailyBackup} />
          </div>
        </CardContent>
      </Card>

      {/* Schedule Configuration */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <h3 className="mb-4" style={{ fontSize: "15px", fontWeight: 600 }}>
            Backup Schedule
          </h3>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {/* Timezone */}
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Timezone
              </label>
              <Select value={timezone} onValueChange={setTimezone}>
                <SelectTrigger className="h-9 bg-input-background">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {timezones.map((tz) => (
                    <SelectItem key={tz} value={tz}>
                      {tz}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Hour */}
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Hour (0–23)
              </label>
              <Input
                type="number"
                min={0}
                max={23}
                value={hour}
                onChange={(e) => setHour(e.target.value)}
                className="h-9 bg-input-background"
              />
            </div>

            {/* Minute */}
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Minute (0–59)
              </label>
              <Input
                type="number"
                min={0}
                max={59}
                value={minute}
                onChange={(e) => setMinute(e.target.value)}
                className="h-9 bg-input-background"
              />
            </div>
          </div>

          {/* Retention */}
          <div className="mt-4">
            <label
              className="block mb-1.5 text-foreground"
              style={{ fontSize: "13px", fontWeight: 500 }}
            >
              Retention Period (days)
            </label>
            <div className="max-w-[200px]">
              <Input
                type="number"
                min={1}
                max={365}
                value={retention}
                onChange={(e) => setRetention(e.target.value)}
                className="h-9 bg-input-background"
              />
            </div>
            <p
              className="text-muted-foreground mt-1.5"
              style={{ fontSize: "12px" }}
            >
              Backups older than {retention} days will be automatically purged.
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Config Status Card */}
      <Card className="border border-border shadow-none mb-6 bg-muted/30">
        <CardContent className="p-5">
          <h3 className="mb-3" style={{ fontSize: "15px", fontWeight: 600 }}>
            Current Configuration Status
          </h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div className="flex items-center gap-3 p-3 bg-card rounded-lg border border-border">
              <div className="w-8 h-8 rounded-md bg-success/10 flex items-center justify-center">
                <CheckCircle2 className="w-4 h-4 text-success" />
              </div>
              <div>
                <div
                  className="text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  Last Backup
                </div>
                <div
                  className="text-muted-foreground"
                  style={{ fontSize: "12px" }}
                >
                  Feb 26, 2026 — 03:00 UTC
                </div>
              </div>
            </div>
            <div className="flex items-center gap-3 p-3 bg-card rounded-lg border border-border">
              <div className="w-8 h-8 rounded-md bg-primary/10 flex items-center justify-center">
                <Clock className="w-4 h-4 text-primary" />
              </div>
              <div>
                <div
                  className="text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  Next Scheduled
                </div>
                <div
                  className="text-muted-foreground"
                  style={{ fontSize: "12px" }}
                >
                  Feb 27, 2026 — 03:00 UTC
                </div>
              </div>
            </div>
            <div className="flex items-center gap-3 p-3 bg-card rounded-lg border border-border">
              <div className="w-8 h-8 rounded-md bg-warning/10 flex items-center justify-center">
                <HardDrive className="w-4 h-4 text-warning" />
              </div>
              <div>
                <div
                  className="text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  Storage Used
                </div>
                <div
                  className="text-muted-foreground"
                  style={{ fontSize: "12px" }}
                >
                  2.4 GB across 28 backups
                </div>
              </div>
            </div>
            <div className="flex items-center gap-3 p-3 bg-card rounded-lg border border-border">
              <div className="w-8 h-8 rounded-md bg-primary/10 flex items-center justify-center">
                <Calendar className="w-4 h-4 text-primary" />
              </div>
              <div>
                <div
                  className="text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  Retention
                </div>
                <div
                  className="text-muted-foreground"
                  style={{ fontSize: "12px" }}
                >
                  {retention} days — oldest: Jan 28, 2026
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Action Buttons */}
      <div className="flex items-center gap-3 mb-8">
        <Button className="h-9 gap-2" style={{ fontSize: "13px" }}>
          <Save className="w-4 h-4" /> Save Settings
        </Button>
        <Button
          variant="outline"
          className="h-9 gap-2"
          style={{ fontSize: "13px" }}
        >
          <Play className="w-4 h-4" /> Run Backup Now
        </Button>
      </div>
    </div>
  );
}
