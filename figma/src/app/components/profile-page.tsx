import { Link } from "react-router";
import { useState } from "react";
import {
  User,
  Lock,
  Shield,
  Monitor,
  Trash2,
  Save,
  AlertTriangle,
  Globe,
  Smartphone,
  Laptop,
  Key,
} from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Card, CardContent } from "./ui/card";
import { Switch } from "./ui/switch";
import { Badge } from "./ui/badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "./ui/table";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "./ui/alert-dialog";

const sessions = [
  {
    id: "1",
    device: "Chrome on macOS",
    icon: Laptop,
    ip: "192.168.1.100",
    location: "San Francisco, CA",
    lastActive: "Active now",
    current: true,
  },
  {
    id: "2",
    device: "Safari on iPhone 15",
    icon: Smartphone,
    ip: "192.168.1.101",
    location: "San Francisco, CA",
    lastActive: "2 hours ago",
    current: false,
  },
  {
    id: "3",
    device: "Firefox on Windows",
    icon: Monitor,
    ip: "10.0.0.42",
    location: "New York, NY",
    lastActive: "1 day ago",
    current: false,
  },
  {
    id: "4",
    device: "Chrome on Linux",
    icon: Globe,
    ip: "172.16.0.12",
    location: "London, UK",
    lastActive: "3 days ago",
    current: false,
  },
];

export function ProfilePage() {
  const [name, setName] = useState("Gareth Daine");
  const [email, setEmail] = useState("gareth@garethdaine.com");
  const [twoFactor, setTwoFactor] = useState(false);
  const [deleteConfirmText, setDeleteConfirmText] = useState("");

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-6">
        <h1>Profile & Security</h1>
        <p
          className="text-muted-foreground mt-0.5"
          style={{ fontSize: "14px" }}
        >
          Manage your account settings and security preferences
        </p>
      </div>

      {/* Profile Info */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-4">
            <User className="w-4 h-4 text-primary" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>
              Profile Information
            </h3>
          </div>

          {/* Avatar section */}
          <div className="flex items-center gap-4 mb-5">
            <div className="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
              <span
                className="text-primary"
                style={{ fontSize: "20px", fontWeight: 600 }}
              >
                GD
              </span>
            </div>
            <div>
              <div style={{ fontSize: "14px", fontWeight: 500 }}>
                Profile Photo
              </div>
              <p
                className="text-muted-foreground mt-0.5"
                style={{ fontSize: "12px" }}
              >
                JPG, PNG or GIF. Max 2MB.
              </p>
              <Button
                variant="outline"
                size="sm"
                className="mt-2 h-7"
                style={{ fontSize: "12px" }}
              >
                Change Avatar
              </Button>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Full Name
              </label>
              <Input
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="h-9 bg-input-background"
              />
            </div>
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Email Address
              </label>
              <Input
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="h-9 bg-input-background"
              />
            </div>
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Role
              </label>
              <Input
                value="Admin"
                disabled
                className="h-9 bg-input-background opacity-60"
              />
            </div>
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Member Since
              </label>
              <Input
                value="January 2026"
                disabled
                className="h-9 bg-input-background opacity-60"
              />
            </div>
          </div>
          <div className="flex justify-end mt-4">
            <Button className="h-9 gap-2" style={{ fontSize: "13px" }}>
              <Save className="w-4 h-4" /> Save Profile
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* API Tokens */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center justify-between">
            <div className="flex items-start gap-3">
              <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center mt-0.5">
                <Key className="w-4.5 h-4.5 text-primary" />
              </div>
              <div>
                <h3 style={{ fontSize: "15px", fontWeight: 600 }}>
                  API Tokens
                </h3>
                <p
                  className="text-muted-foreground mt-0.5"
                  style={{ fontSize: "13px" }}
                >
                  Manage personal access tokens for API authentication. Tokens provide programmatic access to the Agent Scheduler API.
                </p>
              </div>
            </div>
            <Button variant="outline" className="h-9 shrink-0" style={{ fontSize: "13px" }} asChild>
              <Link to="/profile/tokens">
                Manage Tokens
              </Link>
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Password */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center gap-2 mb-4">
            <Lock className="w-4 h-4 text-primary" />
            <h3 style={{ fontSize: "15px", fontWeight: 600 }}>
              Update Password
            </h3>
          </div>
          <div className="space-y-4">
            <div>
              <label
                className="block mb-1.5 text-foreground"
                style={{ fontSize: "13px", fontWeight: 500 }}
              >
                Current Password
              </label>
              <Input
                type="password"
                placeholder="Enter current password"
                className="h-9 bg-input-background max-w-sm"
              />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label
                  className="block mb-1.5 text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  New Password
                </label>
                <Input
                  type="password"
                  placeholder="Min. 8 characters"
                  className="h-9 bg-input-background"
                />
              </div>
              <div>
                <label
                  className="block mb-1.5 text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  Confirm Password
                </label>
                <Input
                  type="password"
                  placeholder="Re-enter new password"
                  className="h-9 bg-input-background"
                />
              </div>
            </div>
          </div>
          <p
            className="text-muted-foreground mt-3"
            style={{ fontSize: "12px" }}
          >
            Password must contain at least 8 characters, one uppercase letter,
            one number, and one special character.
          </p>
          <div className="flex justify-end mt-4">
            <Button className="h-9" style={{ fontSize: "13px" }}>
              Update Password
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* 2FA */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center justify-between">
            <div className="flex items-start gap-3">
              <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center mt-0.5">
                <Shield className="w-4.5 h-4.5 text-primary" />
              </div>
              <div>
                <h3 style={{ fontSize: "15px", fontWeight: 600 }}>
                  Two-Factor Authentication
                </h3>
                <p
                  className="text-muted-foreground mt-0.5"
                  style={{ fontSize: "13px" }}
                >
                  Add an extra layer of security to your account using an
                  authenticator app.
                </p>
                <div className="mt-2">
                  {twoFactor ? (
                    <Badge className="bg-success/10 text-success border-success/20">
                      Enabled
                    </Badge>
                  ) : (
                    <Badge
                      variant="outline"
                      className="text-muted-foreground border-border"
                    >
                      Disabled
                    </Badge>
                  )}
                </div>
              </div>
            </div>
            <Switch checked={twoFactor} onCheckedChange={setTwoFactor} />
          </div>
        </CardContent>
      </Card>

      {/* Browser Sessions */}
      <Card className="border border-border shadow-none mb-6">
        <CardContent className="p-5">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Monitor className="w-4 h-4 text-primary" />
              <h3 style={{ fontSize: "15px", fontWeight: 600 }}>
                Browser Sessions
              </h3>
            </div>
            <Button
              variant="outline"
              size="sm"
              className="h-7"
              style={{ fontSize: "12px" }}
            >
              Log Out All Others
            </Button>
          </div>
          <p
            className="text-muted-foreground mb-4"
            style={{ fontSize: "13px" }}
          >
            Manage and log out your active sessions on other browsers and
            devices.
          </p>

          <div className="rounded-lg border border-border overflow-hidden">
            <Table>
              <TableHeader>
                <TableRow className="bg-muted/50">
                  <TableHead style={{ fontSize: "12px" }}>Device</TableHead>
                  <TableHead style={{ fontSize: "12px" }}>IP Address</TableHead>
                  <TableHead style={{ fontSize: "12px" }}>Location</TableHead>
                  <TableHead style={{ fontSize: "12px" }}>
                    Last Active
                  </TableHead>
                  <TableHead style={{ fontSize: "12px" }} className="text-right">
                    Action
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {sessions.map((session) => {
                  const Icon = session.icon;
                  return (
                    <TableRow key={session.id}>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Icon className="w-4 h-4 text-muted-foreground" />
                          <span style={{ fontSize: "13px" }}>
                            {session.device}
                          </span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <code
                          className="text-muted-foreground font-mono"
                          style={{ fontSize: "12px" }}
                        >
                          {session.ip}
                        </code>
                      </TableCell>
                      <TableCell>
                        <span
                          className="text-muted-foreground"
                          style={{ fontSize: "13px" }}
                        >
                          {session.location}
                        </span>
                      </TableCell>
                      <TableCell>
                        <span
                          className={
                            session.current
                              ? "text-success"
                              : "text-muted-foreground"
                          }
                          style={{ fontSize: "12px", fontWeight: 500 }}
                        >
                          {session.lastActive}
                        </span>
                      </TableCell>
                      <TableCell className="text-right">
                        {session.current ? (
                          <Badge className="bg-success/10 text-success border-success/20">
                            Current
                          </Badge>
                        ) : (
                          <Button
                            variant="outline"
                            size="sm"
                            className="h-7"
                            style={{ fontSize: "12px" }}
                          >
                            Revoke
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      {/* Delete Account */}
      <Card className="border border-destructive/20 shadow-none mb-8">
        <CardContent className="p-5">
          <div className="flex items-start gap-3 mb-4">
            <div className="w-9 h-9 rounded-lg bg-destructive/10 flex items-center justify-center mt-0.5">
              <Trash2 className="w-4.5 h-4.5 text-destructive" />
            </div>
            <div>
              <h3
                className="text-destructive"
                style={{ fontSize: "15px", fontWeight: 600 }}
              >
                Danger Zone
              </h3>
              <p
                className="text-muted-foreground mt-0.5"
                style={{ fontSize: "13px" }}
              >
                Permanently delete your account and all associated data
                including jobs, sessions, backup configurations, and delegation
                graphs. This action cannot be undone.
              </p>
            </div>
          </div>
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button
                variant="destructive"
                className="h-9"
                style={{ fontSize: "13px" }}
              >
                Delete Account
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <div className="flex items-center gap-2 mb-2">
                  <div className="w-9 h-9 rounded-full bg-destructive/10 flex items-center justify-center">
                    <AlertTriangle className="w-5 h-5 text-destructive" />
                  </div>
                  <AlertDialogTitle>
                    Are you absolutely sure?
                  </AlertDialogTitle>
                </div>
                <AlertDialogDescription>
                  This action cannot be undone. This will permanently delete your
                  account and remove all data from our servers including jobs,
                  sessions, backup configurations, and delegation graphs.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <div className="mt-2">
                <label
                  className="block mb-1.5 text-foreground"
                  style={{ fontSize: "13px", fontWeight: 500 }}
                >
                  Type{" "}
                  <code className="px-1 py-0.5 bg-muted rounded text-destructive font-mono" style={{ fontSize: "12px" }}>
                    DELETE
                  </code>{" "}
                  to confirm
                </label>
                <Input
                  value={deleteConfirmText}
                  onChange={(e) => setDeleteConfirmText(e.target.value)}
                  placeholder="DELETE"
                  className="h-9 bg-input-background"
                />
              </div>
              <AlertDialogFooter>
                <AlertDialogCancel onClick={() => setDeleteConfirmText("")}>
                  Cancel
                </AlertDialogCancel>
                <AlertDialogAction
                  className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                  disabled={deleteConfirmText !== "DELETE"}
                >
                  Yes, delete my account
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </CardContent>
      </Card>
    </div>
  );
}