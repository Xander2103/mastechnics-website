<?php

namespace App\Services\Hvac\Import;

use RuntimeException;

/**
 * Raised when an uploaded XLSX cannot be read safely. The message is
 * user-facing (Dutch, no server details) — controllers show it verbatim.
 */
class XlsxReadException extends RuntimeException
{
}
