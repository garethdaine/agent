---
name: property-listing-generator
description: |
  Generates property listing descriptions for estate agents and lettings agencies. Produces compelling marketing copy from property details, measurements, and features while ensuring compliance with Property Misdescriptions Act requirements, EPC rating disclosures, and Consumer Protection Regulations.
version: "1.0.0"
author: "agentops"
license: "MIT"
x-agent:
  industries: [property, real-estate]
  risk_level: low
  requires_approval: false
  memory_blocks: []
  mcp_dependencies: []
  tools: [file-read]
  trigger_keywords: [property listing, estate agent, marketing, lettings, property description, EPC]
  run_after: []
  compatibility: "Agent Platform >= 1.0"
---

# Property Listing Generator

## Purpose

Produces professional property listing descriptions from structured property data, measurements, and feature notes provided by estate agents or lettings negotiators. The skill generates marketing copy that is compelling and accurate while meeting the requirements of the Consumer Protection from Unfair Trading Regulations 2008, Material Information in Property Listings guidance from National Trading Standards, and EPC disclosure obligations.

## When to Use

- A new property instruction has been received and the agent needs listing copy for portal upload to Rightmove, Zoopla, or OnTheMarket
- A property has been re-instructed or reduced in price and the listing description needs refreshing
- A lettings property needs a description tailored for the rental market with tenancy-specific details included
- Bulk listing descriptions are needed for a new development or portfolio of managed properties
- An existing listing needs revising to include material information that was previously omitted following updated Trading Standards guidance

## Instructions

1. Ingest the property data sheet, which should include the full address, property type, number of bedrooms and bathrooms, reception rooms, tenure (freehold, leasehold, or share of freehold), council tax band, EPC rating, and asking price or rental figure. Note any missing fields and flag them for the agent to complete before publication.

2. Process the room-by-room measurements and feature notes. Convert all measurements to a consistent format using both metric and imperial as standard for UK portal listings. Note key selling points such as period features, recent renovations, garden aspects, parking arrangements, and proximity to transport links or schools.

3. Write the headline summary, which should be a single sentence of no more than 20 words capturing the property type, key feature, and location. This line appears as the primary search result text on property portals and must be factual rather than subjective.

4. Write the main description body in a logical room-by-room flow, starting with the approach and entrance, moving through reception rooms and kitchen, then bedrooms and bathrooms, and finishing with external spaces and parking. Use factual, descriptive language. Avoid subjective superlatives such as "stunning" or "amazing" unless they can be substantiated. Include measurements for each room in the format provided by the agent.

5. Add the material information section covering tenure details, council tax band, EPC rating, flood risk zone where known, broadband speed availability, and any factors that a reasonable buyer or tenant would consider material to their decision. This section should be clearly separated from the marketing description.

6. For lettings properties, include the rental amount, deposit requirement, permitted occupancy, furnished or unfurnished status, available date, and minimum tenancy term. Note whether the property is managed or tenant-find only.

7. Review the completed listing against the Consumer Protection from Unfair Trading Regulations to ensure no misleading claims, omissions of material facts, or aggressive sales practices are present in the text. Remove or rephrase any statements that could be considered misleading by omission.

## Output Format

The output should be a portal-ready listing comprising:

- **Headline**: Single-sentence summary suitable for search result display
- **Key Features**: Bullet list of 6-10 top features, each no more than one line
- **Full Description**: Room-by-room narrative description with measurements
- **Material Information**: Structured section covering tenure, council tax, EPC, and other material facts
- **Lettings Addendum** (if applicable): Tenancy-specific terms and conditions summary

All text should be formatted for direct upload to UK property portals without further editing.

## Quality Checks

- Every room listed in the property data has been described with measurements included
- The EPC rating is stated and matches the certificate reference provided
- Tenure is correctly identified and any leasehold-specific details (service charge, ground rent, lease length remaining) are included where applicable
- No subjective claims are made that cannot be supported by the property data provided
- Material information fields required by National Trading Standards Estate and Letting Agent Team guidance are all present
- The listing does not contain any discriminatory language in breach of the Equality Act 2010

## Limitations

- Cannot verify the accuracy of measurements, EPC ratings, or other property data provided by the instructing agent; all copy is generated from the supplied information
- Does not produce floor plans, photographs, or virtual tour content
- Cannot assess whether a property is located in a conservation area, Article 4 direction zone, or other planning restriction area unless this is stated in the supplied data
- Flood risk information is included only where provided; the skill does not query the Environment Agency flood map independently
- Marketing copy is generated in British English and follows UK property portal conventions; it is not suitable for international listings without adaptation
