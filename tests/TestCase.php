<?php

namespace Tests;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test request looks like a real browser posting a form: the trust
     * gate of the public forms treats a request without these headers as a
     * script (needs_review, no mail). Bot-behaviour tests override or drop
     * them explicitly (withHeaders() / withoutHeader()).
     *
     * @var array<string, string>
     */
    protected $defaultHeaders = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'nl-BE,nl;q=0.9,fr;q=0.8,en;q=0.7',
        'Sec-Fetch-Site' => 'same-origin',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Dest' => 'document',
    ];

    /**
     * Session payload of a logged-in admin. The admin middleware verifies the
     * account exists and that the session fingerprint matches its password
     * hash, so the account row is created here when it does not exist yet.
     *
     * @return array<string, string>
     */
    protected function adminSession(string $email = 'admin@test.com', string $name = 'Admin'): array
    {
        $adminUser = AdminUser::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('TestWachtwoord123')]
        );

        return $adminUser->sessionPayload();
    }

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
