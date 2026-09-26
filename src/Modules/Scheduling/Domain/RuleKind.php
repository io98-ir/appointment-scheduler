<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Scheduling\Domain;

enum RuleKind: string
{
    case Work = 'work';
    case Break = 'break';
}
