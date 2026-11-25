<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * @property int $id
 * @property string $domain Subdomain label for InitializeTenancyBySubdomain (e.g. disdukcapil)
 * @property string $tenant_id
 */
class Domain extends BaseDomain
{
    protected $table = 'sys_domains';
}
