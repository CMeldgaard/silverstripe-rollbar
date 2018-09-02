<?php

namespace silverstripe\rollbar\Exception;

/**
 * The module has its own exception subclasses to easily distinguish between project
 * and module exceptions.
 */

final class RollbarClientAdaptorException extends \RuntimeException
{
}
