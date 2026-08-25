---
paths:
  - 'app/OpenApi/**'
---

# Open Api

## OpenAPI error envelope extensions pattern
Scramble docs extensions live here. ErrorEnvelope::response() builds responses matching ApiExceptionRenderer ({error:{code,message,details},request_id}). Each ApiErrorEnvelopeExtension subclass mirrors one branch of ApiExceptionRenderer::resolve(); HttpEnvelopeExtension is the generic fallback and must keep excluding classes listed in SPECIFICALLY_HANDLED. Register new extensions in config/scramble.php 'extensions' (NOT Scramble::registerExtension — operation extensions registered via statics are snapshotted before AppServiceProvider boots and get dropped). ThrottledResponsesExtension/PermissionDeniedResponsesExtension add 429/403 from middleware strings.
