<?php

declare(strict_types=1);

namespace Vaqtyar\Modules\Payments\Domain;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingCallback = 'awaiting_callback';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
