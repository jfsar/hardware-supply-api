<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Privacy (Phase 8)
    |--------------------------------------------------------------------------
    |
    | Right-to-erasure workflow (NFR-PRIV-001/002/003, SRS §46). After a
    | deletion request the account is frozen immediately; anonymization of
    | retained financial history runs only after a grace window so the
    | customer can cancel or a support agent can intervene.
    |
    */

    // Days between the deletion request and irreversible anonymization.
    'deletion_grace_days' => (int) env('PRIVACY_DELETION_GRACE_DAYS', 7),

];
