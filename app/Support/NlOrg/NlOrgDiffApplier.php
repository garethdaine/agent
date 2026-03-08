<?php

declare(strict_types=1);

namespace App\Support\NlOrg;

use App\Models\OrgAgentProfile;
use App\Models\User;
use App\Support\Org\OrgAgentProfileService;
use App\Support\Org\OrgCouncilService;
use App\Support\Org\OrgReportingEdgeService;
use App\Support\Org\OrgRitualTemplateService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NlOrgDiffApplier
{
    public function __construct(
        private readonly OrgAgentProfileService $profileService,
        private readonly OrgReportingEdgeService $edgeService,
        private readonly OrgRitualTemplateService $ritualService,
        private readonly OrgCouncilService $councilService,
    ) {}

    public function apply(User $user, NlOrgParseResult $result): void
    {
        DB::transaction(function () use ($user, $result) {
            $changeset = $result->changeset;
            $tempIdMap = $this->buildTempIdMap($changeset);

            // 1. Process all REMOVE operations (edges first, then rituals, councils, agents last)
            $this->processRemovals($changeset, NlOrgParseResult::TYPE_REPORTING_EDGE);
            $this->processRemovals($changeset, NlOrgParseResult::TYPE_COST_LEDGER);
            $this->processRemovals($changeset, NlOrgParseResult::TYPE_RITUAL_TEMPLATE);
            $this->processRemovals($changeset, NlOrgParseResult::TYPE_COUNCIL_TEMPLATE);
            $this->processRemovals($changeset, NlOrgParseResult::TYPE_AGENT_PROFILE);

            // 2. Process ADD/UPDATE agent profiles — populate temp_id map with real UUIDs
            $this->processAgentProfiles($user, $changeset, $tempIdMap);

            // 3. Process ADD/UPDATE edges — resolve temp_ids
            $this->processEdges($changeset, $tempIdMap);

            // 4. Process ADD/UPDATE rituals
            $this->processRituals($user, $changeset, $tempIdMap);

            // 5. Process ADD/UPDATE councils
            $this->processCouncils($user, $changeset, $tempIdMap);
        });
    }

    private function buildTempIdMap(array $changeset): array
    {
        $map = [];
        foreach ($changeset as $entry) {
            if (! empty($entry['temp_id'])) {
                $map[$entry['temp_id']] = null;
            }
        }

        return $map;
    }

    private function processRemovals(array $changeset, string $entityType): void
    {
        foreach ($changeset as $entry) {
            if ($entry['entity_type'] !== $entityType || $entry['operation'] !== NlOrgParseResult::OP_REMOVE) {
                continue;
            }

            $payload = $entry['payload'];

            match ($entityType) {
                NlOrgParseResult::TYPE_AGENT_PROFILE => $this->profileService->archive(
                    OrgAgentProfile::findOrFail($payload['id']),
                ),
                NlOrgParseResult::TYPE_REPORTING_EDGE => $this->edgeService->removeManager(
                    OrgAgentProfile::findOrFail($payload['subordinate_agent_id']),
                ),
                NlOrgParseResult::TYPE_RITUAL_TEMPLATE => $this->ritualService->archive(
                    \App\Models\OrgRitualTemplate::findOrFail($payload['id']),
                ),
                NlOrgParseResult::TYPE_COUNCIL_TEMPLATE => $this->councilService->archive(
                    \App\Models\OrgCouncilTemplate::findOrFail($payload['id']),
                ),
                default => null,
            };
        }
    }

    private function processAgentProfiles(User $user, array $changeset, array &$tempIdMap): void
    {
        foreach ($changeset as $entry) {
            if ($entry['entity_type'] !== NlOrgParseResult::TYPE_AGENT_PROFILE) {
                continue;
            }

            $payload = $entry['payload'];

            if ($entry['operation'] === NlOrgParseResult::OP_ADD) {
                $profile = $this->profileService->create($user, $payload);
                if (! empty($entry['temp_id'])) {
                    $tempIdMap[$entry['temp_id']] = $profile->id;
                }
            } elseif ($entry['operation'] === NlOrgParseResult::OP_UPDATE) {
                $id = $payload['id'];
                $data = collect($payload)->except('id')->all();
                $this->profileService->update(OrgAgentProfile::findOrFail($id), $data);
            }
        }
    }

    private function processEdges(array $changeset, array &$tempIdMap): void
    {
        foreach ($changeset as $entry) {
            if ($entry['entity_type'] !== NlOrgParseResult::TYPE_REPORTING_EDGE) {
                continue;
            }

            if ($entry['operation'] === NlOrgParseResult::OP_ADD) {
                $payload = $entry['payload'];
                $subordinateId = $this->resolveId($payload['subordinate_agent_id'], $tempIdMap);
                $managerId = $this->resolveId($payload['manager_agent_id'], $tempIdMap);

                $this->edgeService->setManager(
                    OrgAgentProfile::findOrFail($subordinateId),
                    OrgAgentProfile::findOrFail($managerId),
                );
            }
        }
    }

    private function processRituals(User $user, array $changeset, array &$tempIdMap): void
    {
        foreach ($changeset as $entry) {
            if ($entry['entity_type'] !== NlOrgParseResult::TYPE_RITUAL_TEMPLATE) {
                continue;
            }

            $payload = $this->resolvePayloadTempIds($entry['payload'], $tempIdMap);

            if ($entry['operation'] === NlOrgParseResult::OP_ADD) {
                $this->ritualService->create($user, $payload);
            } elseif ($entry['operation'] === NlOrgParseResult::OP_UPDATE) {
                $id = $payload['id'];
                $data = collect($payload)->except('id')->all();
                $this->ritualService->update(
                    \App\Models\OrgRitualTemplate::findOrFail($id),
                    $data,
                );
            }
        }
    }

    private function processCouncils(User $user, array $changeset, array &$tempIdMap): void
    {
        foreach ($changeset as $entry) {
            if ($entry['entity_type'] !== NlOrgParseResult::TYPE_COUNCIL_TEMPLATE) {
                continue;
            }

            $payload = $this->resolvePayloadTempIds($entry['payload'], $tempIdMap);

            if ($entry['operation'] === NlOrgParseResult::OP_ADD) {
                $this->councilService->create($user, $payload);
            } elseif ($entry['operation'] === NlOrgParseResult::OP_UPDATE) {
                $id = $payload['id'];
                $data = collect($payload)->except('id')->all();
                $this->councilService->update(
                    \App\Models\OrgCouncilTemplate::findOrFail($id),
                    $data,
                );
            }
        }
    }

    private function resolveId(string $id, array $tempIdMap): string
    {
        if (array_key_exists($id, $tempIdMap)) {
            if ($tempIdMap[$id] === null) {
                throw new RuntimeException("Unresolved temp_id '{$id}': the referenced agent was not created before this entry.");
            }

            return $tempIdMap[$id];
        }

        // If the ID doesn't look like a valid UUID, it's likely an unregistered temp_id
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)
            && ! preg_match('/^[0-9a-f]{32}$/i', $id)
            && ! ctype_digit($id)) {
            throw new RuntimeException("Unresolved temp_id '{$id}': no matching agent add operation found in changeset.");
        }

        return $id;
    }

    private function resolvePayloadTempIds(array $payload, array $tempIdMap): array
    {
        $agentFields = ['subordinate_agent_id', 'manager_agent_id', 'org_agent_id'];

        foreach ($agentFields as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = $this->resolveId($payload[$field], $tempIdMap);
            }
        }

        if (isset($payload['member_list']) && is_array($payload['member_list'])) {
            foreach ($payload['member_list'] as $i => $member) {
                if (isset($member['agent_id'])) {
                    $payload['member_list'][$i]['agent_id'] = $this->resolveId($member['agent_id'], $tempIdMap);
                }
            }
        }

        if (isset($payload['phase_role_mappings']) && is_array($payload['phase_role_mappings'])) {
            $payload['phase_role_mappings'] = $this->resolveNestedTempIds($payload['phase_role_mappings'], $tempIdMap);
        }

        return $payload;
    }

    private function resolveNestedTempIds(array $data, array $tempIdMap): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->resolveNestedTempIds($value, $tempIdMap);
            } elseif (is_string($value) && array_key_exists($value, $tempIdMap)) {
                $data[$key] = $this->resolveId($value, $tempIdMap);
            }
        }

        return $data;
    }
}
