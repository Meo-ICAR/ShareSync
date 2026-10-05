<?php

namespace App\Enums;

/**
 * Grado di severity con cui un check riporta il proprio stato a UnicoBPM, dal meno al più grave.
 */
enum Severity: string
{
    case Ok = 'ok';
    case Regular = 'regular';
    case Warning = 'warning';
    case Alert = 'alert';
}
