<?php

namespace App\Core\Exceptions;

/** Service-laag gooit dit bij een scope-/rechtenschending — de API-laag zet dit om naar 403. */
class ForbiddenException extends \RuntimeException
{
}
