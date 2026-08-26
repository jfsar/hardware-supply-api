---
paths:
  - 'app/OpenApi/**'
---

# Open Api

## OpenAPI error envelope extensions pattern
Scramble docs extensions live here. ErrorEnvelope::response() builds responses matching ApiExceptionRenderer ({error:{code,message,details},request_id}). Each ApiErrorEnvelopeExtension subclass mirrors one branch of ApiExceptionRenderer::resolve(); HttpEnvelopeExtension is the generic fallback and must keep excluding classes listed in SPECIFICALLY_HANDLED. Register new extensions in config/scramble.php 'extensions' (NOT Scramble::registerExtension — operation extensions registered via statics are snapshotted before AppServiceProvider boots and get dropped). ThrottledResponsesExtension/PermissionDeniedResponsesExtension add 429/403 from middleware strings.

## Scramble addRequired() takes arrays; register extensions in both places
Dedoc\Scramble\Support\Generator\Types\ObjectType::addRequired() takes an array only (addRequired(['field'])); passing a bare string throws a TypeError at docs-generation time, surfacing as DocsTest failures. Every new ApiErrorEnvelopeExtension subclass must also be registered in config/scramble.php 'extensions' and listed in ApiErrorEnvelopeExtension::SPECIFICALLY_HANDLED so HttpEnvelopeExtension does not claim the exception first.
