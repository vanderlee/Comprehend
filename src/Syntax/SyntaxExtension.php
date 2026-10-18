<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

/** Supplies optional named factories to an immutable Syntax vocabulary. */
interface SyntaxExtension
{
    /**
     * Iterated during installation; factories themselves run only during grammar authoring.
     * @return iterable<string, callable>
     */
    public function rules(): iterable;

    /**
     * Iterated after rules(); callbacks receive the current facade before user arguments.
     * @return iterable<string, callable>
     */
    public function fluents(): iterable;
}
