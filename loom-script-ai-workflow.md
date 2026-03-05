# Loom Script: AI-First Delivery Workflow
**Duration:** ~2 minutes | **Format:** Screen-share of Agent Ops

---

## OPENING (0:00–0:15)
**Show:** Agent Ops dashboard — jobs list, run monitor, system overview

> "This is Agent Ops — a platform I've been building solo to manage AI agent jobs for businesses. 75 models, 66 services, 81,000 lines of tests. Built entirely with AI-assisted development — Claude and Codex."

---

## THE FEATURE: AI Governance Stack (0:15–0:45)
**Show:** Scroll through key files: `StarPreambleGenerator.php` → `AdversarialReviewerService.php` → `ApprovalGate.php`

> "The feature I want to walk through is the AI governance stack — guardrails I built *into* the platform because I kept hitting the same problems *using* AI to build it."

> "Problem one: AI agents make confident but wrong decisions."

**Show:** `StarPreambleGenerator.php` — the preamble template

> "So every agent job gets a STAR preamble injected — Situation, Task, Action, Result. The agent has to articulate how completion will be verified *before* it starts. Research-backed, and it dramatically reduces drift."

> "Problem two: AI accepts its own output uncritically."

**Show:** `AdversarialReviewerService.php` — the `reviewSummary` method

> "So I built an adversarial reviewer — a separate AI subprocess that reviews the first AI's work, with payload guards and bounded retries. AI checking AI, with hard validation gates."

---

## THE MISTAKE + FIX (0:45–1:20)
**Show:** `ApprovalGate.php` — the `MUTATION_TOOLS` array and `requiresApproval` method

> "Here's a real limitation I hit. Early on, AI agents were executing tool calls — file writes, process spawns — with no approval layer. They'd confidently chain destructive operations. One agent deleted a config file it decided was 'temporary'."

> "The fix wasn't a prompt change — it was architectural. I built a policy engine with three runtime modes. Every mutation goes through an approval gate. The system captures policy snapshots for a forensic audit trail."

**Show:** `PolicyEngine.php` — `captureSnapshot` method

> "Core insight: you can't prompt your way to AI safety. You have to *architect* it. Structural constraints, not instructions."

---

## THE WORKFLOW (1:20–1:50)
**Show:** Your `CLAUDE.md` rules briefly, or the gap analysis document

> "My development workflow mirrors what I build. Plan mode for anything non-trivial — STAR before writing code. Subagents to keep context clean. Every correction logged to a lessons file that prevents repeats. Nothing ships without verification."

> "Result: a solo-built platform, 42 feature areas at 86% completion, 96% run success rate, production-grade governance."

---

## CLOSE (1:50–2:00)
**Show:** System overview dashboard or bottom-line stats

> "This is what AI-first delivery looks like to me — not just using AI to write code faster, but building systems that make AI *accountable*. Happy to dig deeper on any of this."

---

## DELIVERY NOTES

**Pacing:** ~325 spoken words. At natural pace (150 wpm) that's ~2:10. The screen transitions eat a few seconds so you'll land right at 2 minutes. Don't rush — the code on screen does half the work.

**Screen-share flow:**
1. Dashboard (5 sec) → 2. StarPreambleGenerator (10 sec) → 3. AdversarialReviewerService (10 sec) → 4. ApprovalGate (15 sec) → 5. PolicyEngine (10 sec) → 6. CLAUDE.md or gap analysis (10 sec) → 7. Dashboard/stats (5 sec)

**Key soundbites to nail:**
- "AI agents make confident but wrong decisions"
- "AI checking AI, with hard validation gates"
- "You can't prompt your way to AI safety — you have to architect it"
- "Not just using AI to write code faster, but building systems that make AI accountable"

**What David is evaluating:**
1. ✅ Recent feature shipped → The entire AI governance stack
2. ✅ AI tools and flow → Claude + Codex with STAR, plan mode, subagents, lessons loop
3. ✅ Mistake AI introduced + how you fixed it → Destructive tool chaining → Policy engine + approval gates
4. ✅ Clarity of thinking → Structured narrative, problem → solution → insight pattern
