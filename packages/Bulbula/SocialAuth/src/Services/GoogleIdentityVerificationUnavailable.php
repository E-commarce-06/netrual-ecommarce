<?php

namespace Bulbula\SocialAuth\Services;

use RuntimeException;
use Throwable;

class GoogleIdentityVerificationUnavailable extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Google identity verification is temporarily unavailable.', 0, $previous);
    }
}
