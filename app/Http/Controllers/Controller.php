<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Escapes the wildcards a user could otherwise smuggle into a LIKE pattern.
     *
     * The value is already bound as a parameter, so this is not about SQL
     * injection — an unescaped `%` turns an indexed prefix match into a full
     * table scan across a contact list with millions of rows.
     */
    protected static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
