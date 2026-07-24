<?php

namespace App\Core\Exceptions;

/** Service-laag gooit dit wanneer een opgevraagd item niet bestaat — de API-laag zet dit om naar 404. */
class NotFoundException extends \RuntimeException
{
}
