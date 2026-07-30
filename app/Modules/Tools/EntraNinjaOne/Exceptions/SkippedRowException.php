<?php

namespace App\Modules\Tools\EntraNinjaOne\Exceptions;

/**
 * Gegooid door NinjaOneUserMapper::mapToNinjaOneUser() voor een Entra-rij die op basis van de
 * business rules niet wordt meegenomen (ontbrekend/ongeldig e-mailadres, geen 'Member', ontbrekende
 * displayName). Geen fout in de zin van een kapot bestand — getMessage() is de reden die in de
 * preview/foutenlijst aan de gebruiker getoond wordt.
 */
class SkippedRowException extends \RuntimeException
{
}
