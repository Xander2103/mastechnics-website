<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Decode the JSON-LD graph a public page emits.
     *
     * Asserting on decoded nodes instead of on raw substrings means the tests
     * keep passing when the encoder changes whitespace or escaping, and fail
     * when the actual structured data is wrong — which is the point.
     *
     * @return array<int, array<string, mixed>> every node in @graph
     */
    protected function schemaNodes(TestResponse $response): array
    {
        preg_match_all(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $response->getContent(),
            $matches
        );

        $nodes = [];

        foreach ($matches[1] as $json) {
            $decoded = json_decode(trim($json), true);

            $this->assertIsArray(
                $decoded,
                'Page emitted invalid JSON-LD: ' . json_last_error_msg()
            );

            foreach ($decoded['@graph'] ?? [$decoded] as $node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * The first node of a given @type. Handles both `"@type": "WebPage"` and
     * `"@type": ["LocalBusiness", "HVACBusiness"]`.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    protected function schemaNode(array $nodes, string $type): ?array
    {
        foreach ($nodes as $node) {
            $nodeTypes = (array) ($node['@type'] ?? []);

            if (in_array($type, $nodeTypes, true)) {
                return $node;
            }
        }

        return null;
    }
}
