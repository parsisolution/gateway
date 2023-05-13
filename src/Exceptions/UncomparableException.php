<?php

namespace Parsisolution\Gateway\Exceptions;

use InvalidArgumentException;

/**
 * Defines the incomparable exception.
 *
 * This exception is thrown if an implementor of {@see Comparable} is unable to compare a given value with itself.
 *
 * ## Wording
 * > Two or more things that can’t be compared with each other are uncomparable. Something that is so good that it is
 * > beyond comparison is incomparable. Some dictionaries don’t list uncomparable, and your spell check might say it’s
 * > wrong, but it’s a perfectly good, useful word. It fills a role not conventionally filled by incomparable.
 * >
 * > --- [Grammarist](http://grammarist.com/usage/incomparable-uncomparable/)
 */
class UncomparableException extends InvalidArgumentException
{
    // Intentionally left blank.
}
