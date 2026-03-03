# Native Research + Grounded Answer Integration Brief

## Metadata
- Status: Draft
- Author: Codex
- Date: 2026-03-03
- Reference Architecture: [Perplexica](https://github.com/ItzCrazyKns/Perplexica)

## Executive Summary
Treat Perplexica as a reference architecture, not as a dependency. Build the same core capability natively inside Agent Scheduler as a first-class `Research` subsystem.

This subsystem should power scheduled jobs and agent workflows with grounded, citable research outputs:
- ranked sources,
- normalized documents and citable chunks,
- evidence packs with diversity and quality controls,
- optional grounded answers with enforced citations,
- optional streaming lifecycle events.

The target is not a chat product. It is a reusable research toolchain that can be invoked by any job pipeline.

## Objectives
- Build a native, modular research pipeline integrated with existing Agent job/run/event infrastructure.
- Ensure generated claims trace back to source chunks with stable citation IDs.
- Support production controls: verticals, optimization modes, caching, and source diversity.
- Keep interfaces pluggable so search providers, rankers, embedders, and generators can be swapped without schema churn.

## Scope
### In Scope (v1-v2)
- Query planning pipeline.
- Pluggable retrieval providers.
- URL acquisition and content normalization (HTML, PDF, markdown).
- Chunking and indexing (vector + optional BM25 hybrid).
- Evidence selection and disagreement surfacing.
- Grounded answer generation with citation enforcement.
- Scheduler-native execution and artifacts.

### Out of Scope (initial)
- Full conversational UX parity with Perplexica.
- Real-time collaborative annotations.
- Browser extension and end-user search interface.

## Why Native in Agent Scheduler
Agent Scheduler has capabilities Perplexica-style apps do not natively optimize for:
- scheduled re-verification of sources,
- persistent project research memory,
- job-run artifact lineage and auditability,
- multi-agent flows (researcher -> critic -> verifier).

This should be implemented as a bounded context named `Research`.

## Bounded Context Design
### Core Domain Primitives
- `ResearchJob` - query + constraints + execution mode.
- `Source` - result metadata and trust/freshness signals.
- `Document` - normalized fetched content.
- `EvidenceChunk` - citable chunk with offsets and provenance.
- `EvidencePack` - selected chunk set used as grounding material.
- `GroundedAnswer` - answer text + citations + claim map.

### Suggested Model Mapping
- `research_jobs` (input + settings + status)
- `research_runs` (lifecycle tied to `agent_job_runs`)
- `research_sources`
- `research_documents`
- `research_chunks`
- `research_evidence_packs`
- `research_grounded_answers`
- `research_events` (optional if not reusing `agent_run_events`)

## Pipeline Architecture
Build as composable services behind contracts.

### 1) Query Pipeline
Goal: convert user intent into a deterministic research plan.

Components:
- `QueryNormalizer`
- `QueryClassifier` (`web|academic|discussions`)
- `QueryRewriter` (2-6 variants)
- `PolicyGate` (optional policy/guardrails)

Output contract:
```json
{
  "canonicalQuery": "...",
  "vertical": "web|academic|discussions",
  "mode": "speed|balanced|quality",
  "queryVariants": ["...", "..."],
  "mustInclude": ["..."],
  "mustAvoid": ["..."]
}
```

### 2) Retrieval Layer
Goal: retrieve candidate sources via pluggable providers.

Contract:
- `SearchProvider::search(string $query, SearchOptions $options): array<SearchResult>`

Providers:
- SERP API providers,
- self-hosted metasearch (for example SearXNG),
- vertical providers (Reddit/arXiv/PubMed connectors).

`SearchResult` shape:
- `url`
- `title`
- `snippet`
- `publishedAt` (nullable)
- `sourceType`
- `rankHint`

### 3) Acquisition + Normalization
Goal: transform URLs into clean, chunkable text.

Components:
- `DocumentFetcher` (timeouts/retries/user-agent policy)
- `ContentExtractor` (readability-like extraction)
- `MimeHandler` set (`HtmlHandler`, `PdfHandler`, `MarkdownHandler`)
- `Canonicalizer` (canonical URL + content hash dedupe)

`Document` shape:
- `url`
- `title`
- `author` (nullable)
- `publishedAt` (nullable)
- `text`
- `html` (nullable)
- `contentHash`

### 4) Chunking + Indexing (RAG-ready)
Goal: produce citable units optimized for retrieval and attribution.

Components:
- `SemanticChunker` (heading-aware + sliding window)
- `Embedder` (local or remote)
- `VectorStore`
- optional `Bm25Indexer` for hybrid retrieval

`EvidenceChunk` shape:
- `id`
- `documentUrl`
- `text`
- `startOffset`
- `endOffset`
- `embedding` (nullable)

### 5) Ranking + Evidence Selection
Goal: maximize evidential quality, not just relevance.

Components:
- `HybridRanker` (BM25 + embedding + freshness)
- `DiversityFilter` (domain/source-type spread)
- `EvidenceBudgeter` (top N docs + top K chunks/doc)
- optional `ContradictionFinder` (disagreement notes)

Output:
- `EvidencePack { chunks[], sources[], notesOnDisagreement? }`

### 6) Grounded Generation + Citation Attachment
Goal: every claim must map to evidence chunk IDs.

Preferred approach:
- generation-time citation enforcement (model emits chunk IDs inline)

Fallback:
- post-hoc alignment (sentence-to-chunk mapping), lower reliability

`GroundedAnswer` shape:
```json
{
  "answer": "...",
  "citations": [
    { "citeId": "C12", "url": "...", "title": "...", "chunkIds": ["ch_1", "ch_9"] }
  ],
  "claimMap": [
    { "claim": "...", "supports": ["ch_9"] }
  ]
}
```

### 7) Streaming Events (Optional)
Support real-time lifecycle over SSE/WebSocket or persisted events:
- `init`
- `search_results`
- `documents_fetched`
- `evidence_selected`
- `token_delta`
- `done`

## Agent Scheduler Integration
### Step Contract
Add reusable workflow step:
- `ResearchStep`
  - Input: query, vertical, mode, evidence limits, generation toggle
  - Output: `EvidencePack` + optional `GroundedAnswer`

Downstream steps can:
- consume evidence only, or
- refine grounded draft answer into final deliverable format.

## Existing Platform Fit
Map to current primitives:
- `AgentJob` stores step config defaults.
- `AgentJobRun` tracks execution state and timing.
- `AgentRunEvent` streams lifecycle/progress.
- `AgentAuditLog` captures mutation and policy decisions.
- Queue workers execute acquisition/ranking/generation jobs with retry policy.

## Required Production Knobs (Perplexica-like)
Expose and persist:
- `vertical`: `web|academic|discussions`
- `mode`: `speed|balanced|quality`
- history-aware query enrichment
- source diversity controls
- caching controls:
  - query -> result cache
  - URL -> normalized document cache
  - chunk -> embedding cache

## Suggested Laravel Service Contracts
- `ResearchPlannerInterface`
- `SearchProviderInterface`
- `DocumentAcquisitionInterface`
- `ChunkIndexInterface`
- `EvidenceSelectionInterface`
- `GroundedGenerationInterface`
- `CitationResolverInterface`
- `ResearchCacheInterface`

Bind concrete implementations in a dedicated `ResearchServiceProvider`.

## Data and Cache Strategy
### Persistence
- Persist document/chunk artifacts for reproducibility and scheduled re-checks.
- Persist citation graph (`claim -> chunk -> source`) for audit/reporting.

### Caching
- Query cache TTL tuned by mode (`speed` shorter, `quality` longer).
- URL/content hash dedupe to prevent repeated fetch cost.
- Embedding cache keyed by model + normalized chunk hash.

## Security, Policy, and Compliance
- Reuse existing path/env/command policy boundaries for any local execution helpers.
- Add domain allow/deny policy for retrieval providers.
- Redact sensitive provider tokens from events and logs.
- Enforce outbound request limits and per-run budget constraints.

## Observability
Metrics:
- retrieval latency by provider,
- fetch success rate + extraction failure ratio,
- chunk counts and evidence coverage ratio,
- grounded citation coverage (% claims with support),
- contradiction detection rate,
- cache hit rate by layer.

Logs and traces:
- per-stage timings with run correlation id,
- citation resolution failures,
- provider degradation/fallback events.

## Phased Delivery Plan
1. **Phase 1 - Retrieval + Acquisition Baseline**
   - Query pipeline + one search provider + fetch/extract + canonicalization.
2. **Phase 2 - Chunking + Evidence Selection**
   - Semantic chunking, indexing, initial hybrid ranking, evidence budget.
3. **Phase 3 - Grounded Generation**
   - Citation-enforced generation and claim map output.
4. **Phase 4 - Caching + Dedupe Hardening**
   - Query/doc/embedding caches, idempotency and re-run determinism.
5. **Phase 5 - Streaming + Advanced Ranking**
   - Event streaming, freshness boosts, diversity and contradiction features.
6. **Phase 6 - Vertical Connectors**
   - Academic/discussion-specific connectors and provider strategy.

## Verification Strategy
### Unit Tests
- query rewrite determinism and policy gating,
- canonical URL/content hash dedupe,
- chunk boundary and citation id stability,
- ranker score composition and diversity filter behavior.

### Integration Tests
- end-to-end `ResearchStep` returning reproducible `EvidencePack`,
- grounded generation always returns valid chunk-backed citations,
- cache hit/miss behavior and fallback when provider unavailable.

### Regression Gates
- no uncited claims in strict grounded mode,
- no duplicate source spam after diversity filtering,
- scheduled re-check updates existing source artifacts safely.

## Risks and Mitigations
- Risk: hallucinated claims in generated answer.
  - Mitigation: enforce generation-time chunk-id citations and reject uncited claims.
- Risk: provider instability and rate limits.
  - Mitigation: provider abstraction + retries + fallback strategy + cache-first reads.
- Risk: monoculture evidence from one domain.
  - Mitigation: diversity filter and domain caps per evidence pack.
- Risk: cost growth from repeated embedding/generation.
  - Mitigation: multilayer cache, dedupe, and explicit evidence budgets.

## Acceptance Criteria
- `ResearchStep` is invokable from Agent workflows and returns `EvidencePack`.
- Grounded mode returns `GroundedAnswer` where each claim has citation support.
- Verticals/modes are user-configurable and persisted in run metadata.
- Query, URL, and embedding caches are active and observable.
- Re-check workflows can refresh source sets and flag substantive changes.

## Definition of Done
- Core research services and contracts implemented and container-bound.
- Schema/migrations and artifacts wired into job/run lifecycle.
- End-to-end tests for strict-grounded flow pass.
- Operational dashboards include retrieval quality and citation coverage metrics.
- Documentation includes provider setup, runbook, and troubleshooting guidance.
