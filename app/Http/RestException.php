<?php
declare(strict_types=1);

namespace Moonfarmer\ReactionsLeadCapture\Http;

use RuntimeException;
use WP_Error;


if (!defined('ABSPATH')) {
    exit;
}

final class RestException extends RuntimeException
{
    private WP_Error $error;

    public function __construct(WP_Error $error)
    {
        $this->error = $error;
        parent::__construct((string) $error->get_error_message());
    }

    public function getError(): WP_Error
    {
        return $this->error;
    }
}
