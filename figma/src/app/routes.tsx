import { createBrowserRouter, Navigate } from "react-router";
import { AppShell } from "./components/app-shell";
import { LoginPage } from "./components/login-page";
import { RegisterPage } from "./components/register-page";
import { ForgotPasswordPage } from "./components/forgot-password-page";
import { ResetPasswordPage } from "./components/reset-password-page";
import { TwoFactorPage } from "./components/two-factor-page";
import { TermsPage } from "./components/terms-page";
import { PrivacyPage } from "./components/privacy-page";
import { MessengerLinkPage } from "./components/messenger-link-page";
import { DashboardPage } from "./components/dashboard-page";
import { JobsPage } from "./components/jobs-page";
import { JobFormPage } from "./components/job-form-page";
import { MonitorPage } from "./components/monitor-page";
import { MessengerPage } from "./components/messenger-page";
import { ToolsPage } from "./components/tools-page";
import { DiscoveryPage } from "./components/discovery-page";
import { DiscoveryCreatePage } from "./components/discovery-create-page";
import { DiscoverySettingsPage } from "./components/discovery-settings-page";
import { DiscoveryWizardPage } from "./components/discovery-wizard/wizard-page";
import { BackupsPage } from "./components/backups-page";
import { FeaturesPage } from "./components/features-page";
import { DelegationPage } from "./components/delegation-page";
import { DelegationCreatePage } from "./components/delegation-create-page";
import { DelegationDetailPage } from "./components/delegation-detail-page";
import { DelegationTaskPage } from "./components/delegation-task-page";
import { DelegationApprovePage } from "./components/delegation-approve-page";
import { DelegateeProfilesPage } from "./components/delegatee-profiles-page";
import { DelegateeProfileFormPage } from "./components/delegatee-profile-form-page";
import { ProfilePage } from "./components/profile-page";
import { ApiTokensPage } from "./components/api-tokens-page";

function RedirectToDashboard() {
  return <Navigate to="/dashboard" replace />;
}

export const router = createBrowserRouter([
  // Public / Auth routes
  {
    path: "/login",
    Component: LoginPage,
  },
  {
    path: "/register",
    Component: RegisterPage,
  },
  {
    path: "/forgot-password",
    Component: ForgotPasswordPage,
  },
  {
    path: "/reset-password",
    Component: ResetPasswordPage,
  },
  {
    path: "/reset-password/:token",
    Component: ResetPasswordPage,
  },
  {
    path: "/two-factor",
    Component: TwoFactorPage,
  },
  {
    path: "/terms",
    Component: TermsPage,
  },
  {
    path: "/privacy",
    Component: PrivacyPage,
  },
  {
    path: "/messenger/link/:token",
    Component: MessengerLinkPage,
  },
  // Authenticated routes
  {
    path: "/",
    Component: AppShell,
    children: [
      { index: true, Component: RedirectToDashboard },
      { path: "dashboard", Component: DashboardPage },
      // Jobs
      { path: "jobs", Component: JobsPage },
      { path: "jobs/create", Component: JobFormPage },
      { path: "jobs/:id/edit", Component: JobFormPage },
      // Monitor
      { path: "monitor", Component: MonitorPage },
      // Messenger
      { path: "messenger", Component: MessengerPage },
      // Tools
      { path: "tools", Component: ToolsPage },
      { path: "tools/discovery", Component: DiscoveryPage },
      { path: "tools/discovery/create", Component: DiscoveryCreatePage },
      { path: "tools/discovery/new", Component: DiscoveryCreatePage },
      { path: "tools/discovery/settings", Component: DiscoverySettingsPage },
      { path: "tools/discovery/wizard", Component: DiscoveryWizardPage },
      { path: "tools/discovery/:id", Component: DiscoveryWizardPage },
      { path: "tools/discovery/:id/settings", Component: DiscoverySettingsPage },
      { path: "tools/backups", Component: BackupsPage },
      { path: "tools/backups/settings", Component: BackupsPage },
      { path: "tools/messenger", Component: MessengerPage },
      { path: "tools/features", Component: FeaturesPage },
      { path: "tools/features/settings", Component: FeaturesPage },
      // Delegation
      { path: "delegation", Component: DelegationPage },
      { path: "delegation/create", Component: DelegationCreatePage },
      { path: "delegation/profiles", Component: DelegateeProfilesPage },
      { path: "delegation/profiles/create", Component: DelegateeProfileFormPage },
      { path: "delegation/profiles/:id/edit", Component: DelegateeProfileFormPage },
      { path: "delegation/:id", Component: DelegationDetailPage },
      { path: "delegation/:graphId/tasks/:taskId", Component: DelegationTaskPage },
      { path: "delegation/:graphId/tasks/:taskId/approve", Component: DelegationApprovePage },
      // Profile & Account
      { path: "profile", Component: ProfilePage },
      { path: "profile/tokens", Component: ApiTokensPage },
    ],
  },
]);
