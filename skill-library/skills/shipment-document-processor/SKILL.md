---
name: shipment-document-processor
description: |
  Processes shipping documentation including bills of lading, commercial invoices, packing lists, and customs declarations. Extracts key shipment data, validates document completeness against trade requirements, cross-references quantities and values, and flags discrepancies for freight forwarders and logistics teams.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [logistics, supply-chain]
  risk_level: standard
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [shipping, bill of lading, customs, freight, shipment, logistics, commercial invoice]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Shipment Document Processor

## Purpose

Extracts, validates, and cross-references data from shipping documentation sets commonly used in international freight forwarding. The skill identifies missing fields, value mismatches, and regulatory gaps across bills of lading, commercial invoices, packing lists, and customs declarations to reduce clearance delays and compliance risk.

## When to Use

- A new shipment documentation set has been received from a shipper or freight forwarder and needs pre-clearance review.
- Customs brokers require a completeness check before submitting import or export declarations.
- Discrepancies have been flagged between a commercial invoice and the corresponding bill of lading, and root cause analysis is needed.
- A logistics team needs to verify that HS codes, EORI numbers, and Incoterms references are present and consistent across all documents in a shipment file.
- Trade compliance requires audit-ready document summaries for a batch of recent consignments.

## Instructions

1. Receive the shipment documentation set and identify each document type present (bill of lading, commercial invoice, packing list, customs declaration, certificate of origin, or other supporting documents).
2. Extract core reference fields from each document: shipper name and address, consignee name and address, notify party, vessel or flight details, port of loading, port of discharge, container numbers, seal numbers, and shipment reference or booking number.
3. Parse line-item details from the commercial invoice and packing list, capturing product descriptions, HS tariff codes, quantities, unit values, total values, net weights, gross weights, and package counts.
4. Cross-reference quantities and values between the commercial invoice and packing list. Flag any mismatches in total packages, gross weight, net weight, or declared value that exceed a tolerance of 1%.
5. Validate the bill of lading against the commercial invoice for consistency in shipper/consignee details, cargo descriptions, container numbers, and Incoterms. Note any instances where the B/L description is too vague to match invoice line items.
6. Check for the presence of mandatory fields required by HMRC (for UK imports) or the destination country customs authority, including EORI numbers, commodity codes at the correct digit level, country of origin declarations, and applicable preference or trade agreement references.
7. Compile a discrepancy report listing each issue found, the documents involved, the specific fields affected, and a severity classification (critical, warning, or informational).
8. Produce a shipment summary with consolidated totals, key dates, and a document completeness score expressed as a percentage of required fields successfully validated.

## Output Format

The output consists of two sections:

**Shipment Summary** presented as a structured table containing: shipment reference, shipper, consignee, origin, destination, Incoterms, total packages, total gross weight, total declared value, vessel/voyage or flight number, ETD, ETA, and document completeness score.

**Discrepancy Report** presented as a list of findings, each with:
- Document pair (e.g. "Commercial Invoice vs Packing List")
- Field name (e.g. "Gross Weight")
- Expected value and actual value
- Severity (Critical / Warning / Informational)
- Recommended action

## Quality Checks

- All container numbers conform to ISO 6346 format (four letters, seven digits including check digit).
- HS codes are validated to at least 6-digit level, or 8-digit/10-digit where the destination country requires it.
- Currency values are consistent across documents and match the declared invoice currency.
- Weight units (kg vs lbs) are consistent or explicitly converted where documents use different units.
- EORI numbers follow the correct format for the relevant country (e.g. GB followed by 12 digits for UK entities).
- Incoterms references use valid ICC 2020 codes.

## Limitations

- Cannot verify the authenticity or legal validity of documents; only structural and data consistency checks are performed.
- HS code validation is limited to format checking and does not confirm the correctness of tariff classification against the actual goods.
- Does not connect to live customs systems or port community systems to verify vessel schedules, duty rates, or entry status.
- Scanned or image-based documents require prior OCR processing; this skill operates on text-extracted or structured data inputs only.
- Trade agreement eligibility (e.g. UK-EU TCA preference claims) is flagged for review but not definitively determined, as rules of origin assessment requires product-specific analysis.
