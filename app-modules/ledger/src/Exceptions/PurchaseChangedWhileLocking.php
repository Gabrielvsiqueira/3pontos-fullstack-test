<?php

declare(strict_types=1);

namespace Passa\Ledger\Exceptions;

use RuntimeException;

final class PurchaseChangedWhileLocking extends RuntimeException {}
