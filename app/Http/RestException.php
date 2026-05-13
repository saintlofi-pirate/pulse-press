<?php
declare(strict_types=1);

namespace PulsePress\Http;

use RuntimeException;
use WP_Error;

final class RestException extends RuntimeException
{
    public function __construct(private WP_Error $error)
    {
        parent::__construct((string) $error->get_error_message());
    }

    public function getError(): WP_Error
    {
        return $this->error;
    }
}
