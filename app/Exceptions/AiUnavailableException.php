<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when the AI provider cannot give us a usable answer (down, rate limited, bad key, bad output). */
class AiUnavailableException extends RuntimeException
{
}
