<?php

namespace Tests\Feature;

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Scramble;
use Tests\TestCase;

class DocsTest extends TestCase
{
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $spec = app(CacheableGenerator::class)
            ->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API))
            ->spec();

        $this->spec = is_string($spec) ? json_decode($spec, true) : $spec;
    }

    public function test_the_document_carries_api_metadata_and_a_v1_server(): void
    {
        $this->assertSame('Hardware Supply API', $this->spec['info']['title']);
        $this->assertStringContainsString('VALIDATION_ERROR', $this->spec['info']['description']);
        $this->assertStringEndsWith('/api/v1', $this->spec['servers'][0]['url']);
    }

    public function test_every_error_response_uses_the_renderer_envelope(): void
    {
        $validation = $this->spec['components']['responses']['ValidationException']['content']['application/json']['schema'];

        $code = $validation['properties']['error']['properties']['code'];
        $this->assertSame('string', $code['type']);
        $this->assertSame(['VALIDATION_ERROR'], $code['examples']);

        $fields = $validation['properties']['error']['properties']['details']['properties']['fields'];
        $this->assertSame('object', $fields['type']);
        $this->assertSame('array', $fields['additionalProperties']['type']);
        $this->assertSame('string', $fields['additionalProperties']['items']['type']);

        $this->assertSame(
            ['error', 'request_id'],
            $validation['required'],
        );
    }

    public function test_login_documents_suspension_and_two_factor_outcomes(): void
    {
        $responses = $this->operation('POST', '/auth/login')['responses'];

        $this->assertArrayHasKey('403', $responses);
        $this->assertArrayHasKey('409', $responses);
        $this->assertArrayHasKey('429', $responses);

        $challenge = $responses['409']['$ref'] ?? null;
        $this->assertSame('#/components/responses/TwoFactorRequiredException', $challenge);

        $challengeSchema = $this->spec['components']['responses']['TwoFactorRequiredException']['content']['application/json']['schema'];
        $details = $challengeSchema['properties']['error']['properties']['details']['properties'];

        $this->assertArrayHasKey('challenge_token', $details);
        $this->assertSame(
            ['TWO_FACTOR_REQUIRED'],
            $challengeSchema['properties']['error']['properties']['code']['examples'],
        );
    }

    public function test_throttled_routes_document_a_429_with_retry_after(): void
    {
        $tooMany = $this->operation('POST', '/auth/login')['responses']['429'];

        $this->assertSame(
            ['TOO_MANY_REQUESTS'],
            $tooMany['content']['application/json']['schema']['properties']['error']['properties']['code']['examples'],
        );

        $retryAfter = $tooMany['headers']['Retry-After'];
        $this->assertTrue($retryAfter['required']);
        $this->assertSame('integer', $retryAfter['schema']['type']);
    }

    public function test_admin_operations_document_permission_denial(): void
    {
        foreach (['/admin/products', '/admin/categories/{category}'] as $path) {
            $responses = $this->operation($path === '/admin/products' ? 'GET' : 'DELETE', $path)['responses'];

            $this->assertArrayHasKey('403', $responses);
            $this->assertSame(
                ['FORBIDDEN'],
                $responses['403']['content']['application/json']['schema']['properties']['error']['properties']['code']['examples'],
            );
        }
    }

    public function test_public_catalog_is_explicitly_unsecured_and_staff_routes_inherit_bearer(): void
    {
        $scheme = $this->spec['components']['securitySchemes']['http'];
        $this->assertSame('http', $scheme['type']);
        $this->assertSame('bearer', $scheme['scheme']);

        $this->assertSame([], $this->operation('GET', '/products')['security']);

        $this->assertArrayHasKey('security', $this->spec);
        $this->assertArrayNotHasKey('security', $this->operation('GET', '/auth/me'));
    }

    public function test_operations_are_grouped_into_stable_tags(): void
    {
        $this->assertSame(['Catalog'], $this->operation('GET', '/products')['tags']);
        $this->assertSame(['Auth'], $this->operation('POST', '/auth/login')['tags']);
        $this->assertSame(['Auth · Two-Factor'], $this->operation('POST', '/auth/2fa/challenge')['tags']);
        $this->assertSame(['Admin · Catalog'], $this->operation('GET', '/admin/products')['tags']);
        $this->assertSame(['Account · Address'], $this->operation('PUT', '/address')['tags']);
        $this->assertSame(['Catalog · Search'], $this->operation('GET', '/search/autocomplete')['tags']);
    }

    public function test_product_listing_example_documents_facets(): void
    {
        $listing = json_encode($this->operation('GET', '/products')['responses']['200']);

        $this->assertStringContainsString('facets', $listing);
        $this->assertStringContainsString('relation_types', json_encode(
            $this->operation('GET', '/products/{slug}/related')['responses']['200'],
        ));
    }

    /**
     * A single documented operation from the generated specification.
     *
     * @return array<string, mixed>
     */
    private function operation(string $method, string $path): array
    {
        $operation = $this->spec['paths'][$path][strtolower($method)] ?? null;

        $this->assertNotNull($operation, "Expected {$method} {$path} to be documented.");

        return $operation;
    }
}
