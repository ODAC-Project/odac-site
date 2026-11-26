<?php

// SPDX-License-Identifier: Apache-2.0

namespace App\Actions\Odac;

use App\Models\OdacDevice;

/**
 * Turns a projected device into the props of its sheet, organised BY PROOF
 * and not by field: one block per piece of evidence, its quote shown once,
 * then a summary table where each value points to its block.
 *
 * Rules carried here, not in the page:
 *  - no value without its provenance next to it (fields without evidence are
 *    listed as such, never shown as if they were sourced);
 *  - a reason (`to_be_measured`, `firmware_dependent`...) is not an empty cell;
 *  - a contested field shows every candidate with its proof and names the one
 *    the build retained, without settling the disagreement.
 */
final class BuildDeviceSheet
{
    public function __invoke(OdacDevice $device): array
    {
        $data = $device->data;
        $fields = $this->fields($data);
        $evidence = $this->normalizeKeys((array) ($data['evidence'] ?? []));
        $contested = $this->normalizeKeys((array) ($data['contested'] ?? []));

        $proofs = [];
        $proofIndex = [];
        foreach ($evidence as $field => $proof) {
            if (! is_array($proof)) {
                continue;
            }
            $key = $this->proofKey($proof);
            if (! isset($proofIndex[$key])) {
                $proofIndex[$key] = count($proofs);
                $proofs[] = [
                    'number' => count($proofs) + 1,
                    'claim' => is_string($proof['claim'] ?? null) ? $proof['claim'] : null,
                    'source' => $this->source($proof),
                    'weight' => $proof['weight'] ?? null,
                    'submitted_by' => is_string($proof['submitted_by'] ?? null) ? $proof['submitted_by'] : 'anonymous',
                    'submitted_on' => $proof['submitted_on'] ?? null,
                    'fields' => [],
                ];
            }
            if (array_key_exists($field, $fields)) {
                $proofs[$proofIndex[$key]]['fields'][] = ['field' => $field, 'value' => $fields[$field]];
            }
        }

        $summary = [];
        foreach ($fields as $field => $value) {
            $proof = isset($evidence[$field]) && is_array($evidence[$field])
                ? $proofs[$proofIndex[$this->proofKey($evidence[$field])]]['number']
                : null;
            $summary[] = [
                'field' => $field,
                'value' => $value,
                'proof' => $proof,
                'contested' => isset($contested[$field]) ? $this->candidates($contested[$field]) : null,
            ];
        }
        // A contested field the build left out of the facts still shows.
        foreach ($contested as $field => $candidates) {
            if (! array_key_exists($field, $fields)) {
                $summary[] = ['field' => $field, 'value' => null, 'proof' => null, 'contested' => $this->candidates($candidates)];
            }
        }

        return [
            'identity' => [
                'odac_id' => $device->odac_id,
                'brand' => $device->brand_name,
                'brand_slug' => $device->brand_slug,
                'model' => $device->model_name,
                'kind' => $device->kind,
                'level' => $device->level,
                'aliases' => ProjectSnapshot::aliases($data),
            ],
            'proofs' => $proofs,
            'summary' => $summary,
            'open_doubt' => $this->openDoubt($data['open_doubt'] ?? null),
        ];
    }

    /**
     * Facts as `field => value`, inputs as `inputs.<interface>.<field>`.
     *
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $fields = [];
        foreach ((array) ($data['facts'] ?? []) as $field => $value) {
            $fields[(string) $field] = $value;
        }
        foreach ((array) ($data['inputs'] ?? []) as $interface => $values) {
            foreach ((array) $values as $field => $value) {
                $fields["inputs.{$interface}.{$field}"] = $value;
            }
        }

        return $fields;
    }

    /** `facts.dsd_max` and `dsd_max` name the same field. */
    private function normalizeKeys(array $map): array
    {
        $out = [];
        foreach ($map as $key => $value) {
            $key = (string) $key;
            $out[str_starts_with($key, 'facts.') ? substr($key, 6) : $key] = $value;
        }

        return $out;
    }

    private function proofKey(array $proof): string
    {
        if (is_string($proof['claim'] ?? null) && $proof['claim'] !== '') {
            return 'claim:'.$proof['claim'];
        }

        return 'source:'.sha1(json_encode($this->source($proof)));
    }

    private function source(array $proof): array
    {
        $source = array_merge($proof, is_array($proof['source'] ?? null) ? $proof['source'] : []);
        $url = is_string($source['url'] ?? null) && preg_match('#^https?://#i', $source['url']) ? $source['url'] : null;

        return [
            'type' => is_string($source['type'] ?? null) ? $source['type'] : null,
            'url' => $url,
            'quote' => is_string($source['quote'] ?? null) ? $source['quote'] : null,
            'read_on' => is_string($source['read_on'] ?? null) ? $source['read_on'] : null,
            // false = "this source describes the range, not this model".
            'names_subject' => isset($source['names_subject']) ? (bool) $source['names_subject'] : null,
        ];
    }

    private function candidates(mixed $candidates): array
    {
        $out = [];
        foreach ((array) $candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            $out[] = [
                'value' => $candidate['value'] ?? null,
                'chosen' => (bool) ($candidate['chosen'] ?? false),
                'weight' => $candidate['weight'] ?? null,
                'source' => $this->source($candidate),
            ];
        }

        return $out;
    }

    private function openDoubt(mixed $doubt): ?array
    {
        if (is_string($doubt) && trim($doubt) !== '') {
            return ['text' => trim($doubt)];
        }
        if (is_array($doubt) && is_string($doubt['text'] ?? $doubt['question'] ?? null)) {
            return ['text' => $doubt['text'] ?? $doubt['question'], 'fields' => array_values((array) ($doubt['fields'] ?? []))];
        }

        return null;
    }
}
