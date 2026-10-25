<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

#[Fillable(['domain', 'tenant_id'])]
class Domain extends BaseDomain {}
