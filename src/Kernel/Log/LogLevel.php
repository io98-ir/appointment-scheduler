<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel\Log;

enum LogLevel: string
{
    /** Something failed and someone should look: a payment, an SMS, a request. */
    case Error = 'error';
    /** Worked around, but a sign of trouble: a retry, a fallback provider. */
    case Warning = 'warning';
    /** A notable event worth finding later, e.g. an admin override. */
    case Info = 'info';
}
