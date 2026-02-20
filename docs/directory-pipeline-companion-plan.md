# Directory Data Pipeline — Agent Companion Plan

A structured implementation plan for building automated directory businesses powered by the Agent Scheduler. Distilled from the [Startup Ideas Podcast episode](https://www.youtube.com/watch?v=I_wbc5ND79o) with Greg Isenberg and Frey Chu (Feb 15, 2026), and mapped to our existing Agent infrastructure.

---

## 1. Strategic Context

### 1.1 Why Directories as an Agent Use Case

The Agent Scheduler already handles scheduled subprocess execution, approval workflows, rate-limit detection, and multi-phase orchestration. Directory businesses are a natural fit because the entire data lifecycle — scrape, clean, verify, enrich, publish — is a series of scheduled, repeatable agent jobs with human-in-the-loop approval gates.

The podcast lays out a proven playbook: directories built on high-quality, hard-to-obtain data generate passive revenue through lead generation, premium listings, vertical SaaS upsells, and display ads. The gap has always been the manual labour of data curation. Agent + Claude Code + Crawl4AI closes that gap.

### 1.2 Revenue Models (From Case Studies)

| Directory | Niche | Monthly Organic Traffic | Revenue Model | Est. Revenue |
|---|---|---|---|---|
| [parting.com](https://www.parting.com) | Funeral homes | 61K | Vertical SaaS ([Parting Pro](https://www.partingpro.com)) + lead gen + agency services | $1M–5M/yr |
| [aplaceformom.com](https://www.aplaceformom.com) | Senior living | 824K | Pure lead gen (referral fee / % of first month's rent) | ~$50M/yr |
| [gasbuddy.com](https://www.gasbuddy.com) | Gas prices | 1.1M | Subscription debit card ([GasBuddy Premium](https://www.gasbuddy.com/plus)) + display ads | Significant |

The monetisation ladder from the episode: traffic + leads → premium listings → vertical SaaS (CRM, quoting, booking) → agents handling intake/routing/follow-ups → transaction layer.

### 1.3 Niche Selection Framework

Successful directories satisfy at least one of: help people **save time**, **save money**, or **make money**. The strongest directories add **price transparency** in industries where pricing is opaque.

Key selection criteria from the episode:

- **High-ticket services** — the lead value justifies the build cost (weddings, legal, healthcare, home services)
- **Local SEO viability** — local queries ("X near me", "X in [city]") haven't been disrupted by LLMs the way product queries have
- **Deal-breaker features** — niches where a single attribute disqualifies options (ADA accessible, dementia care, luxury tier) let you go ultra-specific
- **Public data availability** — [data.gov](https://data.gov) and similar sources provide free starting datasets for niches like water quality, air quality, permits
- **Avoid product-based niches** — SERPs are too competitive with shopping results, social content, and AI overviews

---

## 2. Seven-Step Data Pipeline

This is Frey's process, restructured as a repeatable pipeline that maps to Agent Scheduler jobs.

### Step 1: Raw Data Scrape

**Tool:** [Outscraper](https://outscraper.com) (Google Maps scraper)
**Alternatives mentioned:** [Apify](https://apify.com)

Scrape the target niche nationwide from Google Maps. Frey's luxury restroom trailer directory started with 71,000 raw rows covering the entire US. Outscraper costs roughly $100 for a scrape at this scale.

**Agent mapping:** This is a one-shot or periodic job. Could be triggered manually via Run Now or scheduled monthly for data refresh. The output is a set of CSVs stored in the job's working directory.

### Step 2: Initial Junk Removal (Claude Code)

**Tool:** [Claude Code](https://claude.com/claude-code) (CLI)

Feed the raw CSVs to Claude Code with a prompt to strip obvious junk: listings with no business name, no address, permanently closed, big-box retailers, and anything clearly unrelated to the niche. This is a deterministic, rule-based pass.

**Result:** 71K → 20K rows.

**Agent mapping:** A Claude-runner agent job. The prompt template references the CSV paths and niche-specific exclusion criteria. The `task_markdown_path` field holds the cleaning instructions. Output CSVs are written to the working directory for the next step.

**Prompt pattern (from episode):**

> "Here are my five CSVs. Look at every single one and use this criteria to clean the data: remove listings with no business name, address, city, state; remove permanently closed; remove obvious non-matches like big-box retailers."

### Step 3: Website Verification (Crawl4AI + Claude Code)

**Tool:** [Crawl4AI](https://github.com/unclecode/crawl4ai) — open-source, LLM-friendly web crawler/scraper (free, installed locally)

This is the breakthrough step. Crawl4AI visits every website in the cleaned dataset and Claude Code acts as the brain, analysing page content for niche-specific keywords and indicators.

**Key module:** `AsyncWebCrawler` — crawls multiple sites concurrently, dramatically reducing wall-clock time.

**Result:** 20K → 725 verified luxury restroom trailer businesses (with confidence scores).

**Agent mapping:** This is the heaviest job in the pipeline. It runs for hours (Frey's took ~3 hours for 20K sites). Perfect for the Agent Scheduler's long-running subprocess support (`max_runtime_seconds` up to 86400). The rate-limit detection and temporary hold policy protects against API throttling during the Claude analysis phase.

**Prompt pattern:**

> "Go through every single one of these 20,000 websites and identify [niche keyword] related content. Look for synonyms: [list all synonyms]. Use Crawl4AI's AsyncWebCrawler for concurrent crawling. Give me verification confidence for each."

### Step 4: First Enrichment Pass — Inventory/Products

**Tool:** Crawl4AI + Claude Code

Visit each verified business website and extract their specific product/service offerings. For restroom trailers: stall counts, trailer types, fleet details. For other niches: service tiers, specialisations, product categories.

**Critical lesson from the episode:** Do NOT try to extract everything in one pass. It produces low-quality results. Go one layer at a time, examine results, fix edge cases, and rerun 2-3 times until the data is clean.

**Agent mapping:** Separate agent job per enrichment layer. Each job reads the output of the previous step. The approval workflow is useful here — review a sample of results before committing to the full dataset.

**Prompt pattern:**

> "Take these [N] verified businesses and use Crawl4AI to visit each website. Find the full fleet of [products/services] they offer. Give me your game plan first. Tell me if I'm missing anything before we proceed."

### Step 5: Image Scraping + Vision Validation

**Tools:** Crawl4AI (scraping) + [Claude Vision API](https://docs.anthropic.com/en/docs/build-with-claude/vision) (validation)

Two-phase approach:

1. Crawl4AI scrapes candidate images from business websites using alt text, filenames, and page context to identify the top 3 image candidates per listing.
2. Claude Vision API validates images, filtering out logos, favicons, and irrelevant photos. Cost: ~$30 for 700 listings via the API.

**Legal note from the episode:** Image scraping is a grey area. Options include: reaching out for permission via listing claims, using stock images, or skipping images entirely (Frey's crappy first directory got leads with no real images). The directory can rank and convert without custom images.

**Agent mapping:** Two chained jobs. The scrape job outputs image URLs to a CSV. The vision validation job reads that CSV and calls the Claude API. The `env_json` field on the agent job holds the `ANTHROPIC_API_KEY`.

### Step 6: Amenities & Features Enrichment

**Tool:** Crawl4AI + Claude Code

Extract filterable attributes: amenities, features, certifications, specialisations. These become the filter facets on the directory frontend.

**Edge case handling (from episode):** First passes return garbage tokens ("it", "and", "the" appearing as features). The fix: tell Claude Code to navigate to the most relevant pages first (homepage, product pages), go deep, and explicitly exclude non-feature words.

**Prompt pattern:**

> "Go to the homepage, look for any page with [niche keyword], go deep. Identify all amenities and features. The first time I ran this, it returned junk words. Exclude common stop words and only return genuine [niche] features."

### Step 7: Service Areas & Geography

**Tool:** Crawl4AI + Claude Code

Extract service areas broken down by city, region, and radius. Important for local SEO and user filtering.

**Edge case (from episode):** Businesses operating in multiple states would sometimes show incorrect service areas on the first pass (e.g., a Florida business showing Texas and Arizona). Requires validation against the business's primary location.

**Agent mapping:** Final enrichment job. Output is the complete, enriched dataset ready for database import.

---

## 3. Agent Job Configuration

Here's how the seven-step pipeline maps to Agent Scheduler job definitions:

| Job Name | Runner | Schedule | Max Runtime | Notes |
|---|---|---|---|---|
| `directory:scrape-raw` | `custom` | Manual / monthly | 1h | Outscraper API call or CLI wrapper |
| `directory:clean-junk` | `claude` | Chained after scrape | 30m | Rule-based CSV cleaning |
| `directory:verify-websites` | `claude` | Chained after clean | 6h | Crawl4AI + Claude Code, heaviest job |
| `directory:enrich-inventory` | `claude` | Chained after verify | 3h | Product/service extraction |
| `directory:enrich-images` | `claude` | Chained after inventory | 2h | Image scrape + Vision API validation |
| `directory:enrich-features` | `claude` | Chained after images | 2h | Amenity/feature extraction |
| `directory:enrich-service-areas` | `claude` | Chained after features | 1h | Geography extraction |
| `directory:build-database` | `claude` | Chained after all enrichment | 30m | Import to Supabase/Postgres |
| `directory:refresh-cycle` | `claude` | Weekly/monthly cron | 4h | Re-verify and update stale listings |

### Future: Job Chaining

The pipeline above is sequential — each step depends on the previous step's output. The Agent Scheduler currently supports individual job scheduling but not explicit job chaining/DAGs. This is a natural extension:

- A `depends_on` or `triggers` relationship between jobs
- Pipeline-level status tracking (the Interrogation Session model's phase progression is a precedent)
- Automatic progression with approval gates between steps

---

## 4. Database & Publishing

Once all enrichment is complete, the cleaned dataset gets imported into the directory's database. Frey uses [Supabase](https://supabase.com) (Postgres-backed, free tier available).

The directory frontend can be built with Claude Code in a single session — Frey's took the remaining time of his 4-day build. The podcast mentions using Claude Code to generate the full Next.js/Supabase application from the enriched CSV schema.

### Data Schema (Derived from Episode)

Core listing fields from the enrichment pipeline:

- `business_name`, `address`, `city`, `state`, `zip`, `phone`, `website`
- `verification_status`, `verification_confidence`
- `products[]` — array of product/service types with attributes (e.g., stall count)
- `images[]` — validated image URLs with quality scores
- `amenities[]` — filterable features (running water, climate control, ADA accessible, etc.)
- `service_areas[]` — city, region, radius
- `pricing` — where available (the price transparency moat)
- `source_url`, `last_verified_at`, `data_version`

---

## 5. SEO & Distribution Strategy

From the episode, directories have a structural SEO advantage: a single directory creates hundreds or thousands of pages with topical relevance. The ranking path is:

1. **Long-tail local** — "luxury restroom trailer Bakersfield CA" (low competition, fast ranking)
2. **Metro areas** — "luxury restroom trailer Los Angeles" (higher competition, builds over months)
3. **Category pages** — "2-stall luxury restroom trailers" (feature-based aggregations)
4. **National** — "luxury restroom trailers" (requires strong backlink profile)

Key SEO principles from the episode:

- **Local queries haven't changed** — Google's local pack + organic listings still work the same way for service-based searches, unlike product queries which are cluttered with shopping, social, and AI overviews
- **Niche beats horizontal** — "senior living homes for people with dementia" (1K+ monthly searches) is winnable; "senior living homes" is not (dominated by aplaceformom.com etc.)
- **Data as a moat** — price transparency, verified inventory, and deal-breaker features are defensible
- **No backlinks needed to start** — the tap water quality directory example (Andy's project) got 40K monthly visitors and [Mediavine](https://www.mediavine.com) acceptance with zero backlinks

### Directories in the AI Search Era

The episode's take on LLM-driven search (Perplexity, ChatGPT, Gemini):

- Directories serve the **decision-making phase**, not the discovery phase — people browsing a directory are comparing options, not discovering that a category exists
- High-stakes decisions (healthcare, legal, finance) won't be delegated to a single LLM response — people will still compare options
- LLMs must reference source data — niche directories become the authoritative source that LLMs cite, and instead of competing with 1,000 blue links, you're one of 2-3 cited sources
- **Build ultra-niche** to position for AI search — the more specific your directory, the more likely it becomes the canonical source for that query

---

## 6. Tools & References

### Core Tools

| Tool | Purpose | Cost | Link |
|---|---|---|---|
| Claude Code | Data cleaning, enrichment brain, website builder | $100/mo (Max plan) | [claude.com/claude-code](https://claude.com/claude-code) |
| Crawl4AI | Open-source web crawler/scraper, LLM-friendly | Free | [github.com/unclecode/crawl4ai](https://github.com/unclecode/crawl4ai) |
| Outscraper | Google Maps data scraping | ~$100 per large scrape | [outscraper.com](https://outscraper.com) |
| Claude Vision API | Image validation and classification | ~$30 per 700 listings | [docs.anthropic.com](https://docs.anthropic.com/en/docs/build-with-claude/vision) |
| Supabase | Postgres database + auth + hosting | Free tier available | [supabase.com](https://supabase.com) |

### Alternative / Complementary Tools

| Tool | Purpose | Link |
|---|---|---|
| Apify | Alternative web scraper marketplace | [apify.com](https://apify.com) |
| Ahrefs | SEO research, keyword volumes, backlink analysis | [ahrefs.com](https://ahrefs.com) |
| Mediavine | Display ad network (requires 50K sessions/mo) | [mediavine.com](https://www.mediavine.com) |
| WordPress | Alternative directory platform (aplaceformom.com uses it) | [wordpress.org](https://wordpress.org) |

### Directory Examples Referenced

| Site | Niche | Link |
|---|---|---|
| Parting | Funeral home comparison | [parting.com](https://www.parting.com) |
| Parting Pro | Vertical SaaS for funeral homes | [partingpro.com](https://www.partingpro.com) |
| A Place for Mom | Senior living directory | [aplaceformom.com](https://www.aplaceformom.com) |
| GasBuddy | Crowdsourced gas prices | [gasbuddy.com](https://www.gasbuddy.com) |
| GasBuddy Premium | Subscription debit card for gas savings | [gasbuddy.com/plus](https://www.gasbuddy.com/plus) |
| Care.com | Caregiver marketplace | [care.com](https://www.care.com) |
| Trust MRR (Mark Lou) | SaaS directory / marketplace | [trustmrr.com](https://trustmrr.com) |
| Porta Potty Match | Frey's original (low-quality) directory | [portapottymatch.com](https://www.portapottymatch.com) |

### Data Sources

| Source | Use Case | Link |
|---|---|---|
| data.gov | Public datasets (water quality, air quality, permits, etc.) | [data.gov](https://data.gov) |
| Google Maps (via Outscraper) | Business listings, reviews, contact info | [outscraper.com](https://outscraper.com) |
| Wayback Machine | Historical research on competitor evolution | [web.archive.org](https://web.archive.org) |

### People & Communities

| Person/Community | Context | Link |
|---|---|---|
| Greg Isenberg | Host, Startup Ideas Podcast. CEO of Late Checkout | [@gregisenberg](https://x.com/gregisenberg) |
| Frey Chu | Directory builder, guest on the episode | YouTube channel (weekly directory content) |
| Startup Ideas Podcast | Source podcast | [@startupideaspod](https://x.com/startupideaspod) |
| Frey's Directory Community | Free community, 3,200+ members | (linked in podcast show notes) |
| Andy (community member) | Built tap water quality directory — 40K monthly visitors, zero backlinks | Referenced in episode |

---

## 7. Cost Breakdown

Frey's total build cost for the luxury restroom trailer directory:

| Item | Cost |
|---|---|
| Claude Code Max subscription | $100 |
| Outscraper data scrape (71K rows, nationwide) | $100 |
| Claude API credits (Vision validation + deep cleaning) | $50 |
| **Total** | **$250** |

Timeline: **4 days** from raw CSV to published directory.

Estimated time saved: **2,000+ hours** of manual data cleaning, verification, and enrichment.

---

## 8. Integration with Agent Scheduler

### What Already Exists

The Agent Scheduler provides everything needed to operationalise this pipeline:

- **Scheduled execution** — cron-based dispatch for periodic data refresh jobs
- **Long-running subprocess support** — `max_runtime_seconds` up to 86,400s handles multi-hour crawl jobs
- **Claude + Codex runners** — adapter pattern supports both, with the `command_template` and `task_markdown_path` fields holding pipeline-specific prompts
- **Rate-limit detection** — automatic temporary hold when upstream APIs throttle, preventing wasted tokens
- **Approval workflows** — human review gate between enrichment passes (review sample data before committing to full run)
- **Audit logging** — full mutation trail for data provenance
- **Real-time monitoring** — WebSocket-powered run status via Reverb/Echo
- **Stream capture** — stdout/stderr logging for debugging crawl failures

### What Would Extend It

To fully support the directory pipeline as a first-class workflow:

1. **Job chaining / DAG support** — define step dependencies so `directory:verify-websites` auto-triggers after `directory:clean-junk` completes successfully. The Interrogation Session's phase model is a precedent for this pattern.

2. **Pipeline entity** — a parent model grouping related jobs into a named pipeline with aggregate status, duration, and output tracking. Similar to how `InterrogationSession` groups `InterrogationEvent` records.

3. **Output artifact passing** — structured mechanism for one job's output (CSV path, row count, summary stats) to feed into the next job's input. Currently requires manual path coordination via `working_directory`.

4. **Data quality dashboards** — per-pass metrics: rows in → rows out, confidence distribution, edge cases flagged, rerun count. Extends the existing Phase 9 dashboard metrics.

5. **Template library** — reusable prompt templates for common enrichment patterns (inventory extraction, image validation, feature parsing, service area mapping) stored as `task_markdown_path` references.

---

## 9. Niche Ideas Pipeline

Directory ideas from the episode, ranked by feasibility for this pipeline:

### High Confidence (Local SEO + High Ticket)

- **ADA accessible bathroom contractors** — deal-breaker feature in a competitive space
- **Dementia-specialised senior living** — 1K+ monthly searches, high referral value
- **Luxury restroom trailer rentals** — proven by Frey, $20K+ leads
- **Specialised legal directories** — immigration lawyers, patent attorneys, etc.

### Medium Confidence (Public Data + Enrichment)

- **Tap water quality by ZIP code** — proven by Andy (40K visitors), data from [data.gov](https://data.gov)
- **Air quality directories** — EPA data + local enrichment
- **Building permit directories** — public records + contractor matching

### Exploratory (Event / Marketplace)

- **Niche event directories** — scrape Eventbrite + Meetup + Facebook Events, create better UX for specific verticals (singles events, tech meetups, outdoor activities)
- **Crowdsourced price directories** — Gas Buddy model applied to other commodities (grocery, utilities)

---

## 10. Implementation Phases

### Phase A: Single Directory Proof of Concept

Pick one niche. Run the seven-step pipeline manually using Agent Scheduler jobs. Validate the full cycle from raw scrape to published directory. Target: working directory with 500+ verified, enriched listings.

### Phase B: Pipeline Automation

Formalise the job chain. Build prompt templates for each step. Add approval gates and data quality checks between passes. Target: end-to-end pipeline that runs with minimal human intervention.

### Phase C: Multi-Directory Scaling

Abstract the pipeline into a niche-agnostic template. Swap in different Outscraper queries, keyword lists, and enrichment criteria per niche. Target: launch a second directory in under 2 days using the same infrastructure.

### Phase D: Monetisation Layer

Add lead capture forms, premium listing tiers, or affiliate integrations to the directory frontend. Use Agent Scheduler to automate lead routing, follow-up sequences, and listing claim outreach.

---

*Source transcript: [docs/claude-code-273-day-online-directory-transcript.md](claude-code-273-day-online-directory-transcript.md)*
