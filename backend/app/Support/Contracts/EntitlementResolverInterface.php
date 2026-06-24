<?php

namespace App\Support\Contracts;

use App\Support\Entitlements\EntitlementSet;

interface EntitlementResolverInterface
{
    /** Entitlements efectivos del tenant. */
    public function for(string $tenantId): EntitlementSet;
}
