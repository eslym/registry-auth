<?php

namespace App\Lib;

use Illuminate\Support\Str;

/**
 * Utility class for matching and comparing Bash-like glob patterns.
 *
 * Supports:
 * - `*`   → matches zero or more characters within a single segment (no separator)
 * - `**`  → matches across multiple segments (can include separators)
 * - `?`   → matches exactly one character (no separator)
 * - `[abc]` / `[!abc]` → character classes and negations
 * - `{a,b,c}` → alternation (supports nested braces)
 *
 * Intended for generic string pattern matching, not specifically for file paths.
 */
final class ACLGlob
{
    /**
     * Match a string against a glob pattern.
     *
     * @param string $pattern The glob pattern to use.
     * @param string $subject The string to test against the pattern.
     * @return bool True if the subject matches the pattern; otherwise false.
     */
    public static function match(string $pattern, string $subject): bool
    {
        // Convert the glob pattern to a regex pattern
        $regex = self::toRegex($pattern);

        // Perform the regex match
        return (bool)preg_match($regex, $subject);
    }

    /**
     * Convert a Bash-like glob pattern to a PCRE regex.
     *
     * Supported syntax:
     * - `*`   matches within a single segment (excludes $sep)
     * - `**`  matches across multiple segments (includes $sep)
     * - `?`   matches exactly one character (excludes $sep)
     * - `[abc]`, `[!abc]` character classes with optional negation
     * - `{a,b,c}` alternation (supports nesting)
     *
     * @param string $glob   The glob pattern to convert.
     * @param string $sep    The segment separator (default '/').
     * @param bool   $anchor Whether to anchor the regex with ^...$.
     * @return string The generated PCRE regex with delimiters.
     */
    public static function toRegex(string $glob, string $sep = '/', bool $anchor = true): string
    {
        $len = strlen($glob);
        $i = 0;
        $out = '';
        $sepQuoted = preg_quote($sep, '#');

        while ($i < $len) {
            $ch = $glob[$i];

            // escape helper
            $lit = static fn(string $s) => preg_quote($s, '#');

            if ($ch === '\\') { // backslash-escape next char
                if ($i + 1 < $len) {
                    $out .= $lit($glob[++$i]);
                    $i++;
                } else {
                    $out .= $lit('\\');
                    $i++;
                }
                continue;
            }

            if ($ch === $sep) {
                $out .= $sepQuoted;
                $i++;
                continue;
            }

            if ($ch === '*') {
                // count consecutive *
                $j = $i + 1;
                while ($j < $len && $glob[$j] === '*') $j++;
                $count = $j - $i;

                if ($count >= 2) {
                    // globstar
                    $out .= '.*';
                    $i += $count;
                } else {
                    // single star: no separator
                    $out .= '(?:[^' . $sepQuoted . ']*)';
                    $i++;
                }
                continue;
            }

            if ($ch === '?') {
                $out .= '(?:[^' . $sepQuoted . '])';
                $i++;
                continue;
            }

            if ($ch === '[') {
                // character class
                $i++;
                $cls = '[';
                if ($i < $len && ($glob[$i] === '!' || $glob[$i] === '^')) {
                    $cls .= '^';
                    $i++;
                }
                if ($i < $len && $glob[$i] === ']') { // leading ] literal
                    $cls .= '\]';
                    $i++;
                }
                while ($i < $len && $glob[$i] !== ']') {
                    $c = $glob[$i++];
                    if ($c === '\\' || $c === '-' || $c === '^') $cls .= '\\' . $c;
                    else $cls .= $c;
                }
                if ($i < $len && $glob[$i] === ']') {
                    $cls .= ']';
                    $i++;
                } else {
                    $cls = '\[';
                } // unclosed -> treat as literal
                $out .= $cls;
                continue;
            }

            if ($ch === '{') {
                [$alts, $next] = self::parseBraceAlts($glob, $i);
                if ($alts === null) {
                    $out .= $lit('{');
                    $i++; // literal {
                } else {
                    $pieces = [];
                    foreach ($alts as $alt) {
                        // recurse without re-anchoring
                        $pieces[] = substr(self::toRegex($alt, $sep, false), 1, -1); // strip regex delimiters
                    }
                    $out .= '(?:' . implode('|', $pieces) . ')';
                    $i = $next;
                }
                continue;
            }

            // literal
            $out .= $lit($ch);
            $i++;
        }

        return $anchor ? '#^' . $out . '$#' : '#' . $out . '#';
    }

    /**
     * Parse a brace alternation block `{a,b,{c,d}}` from a given position.
     *
     * @param string $s   The pattern string.
     * @param int    $pos The position of the '{'.
     * @return array{0:?array<int,string>,1:int} List of alternatives and next position,
     *                                           or [null, $pos] if unbalanced.
     */
    private static function parseBraceAlts(string $s, $pos): array
    {
        $len = strlen($s);
        if ($pos >= $len || $s[$pos] !== '{') return [null, $pos];

        $depth = 0;
        $i = $pos;
        $buf = '';
        $parts = [];
        while ($i < $len) {
            $ch = $s[$i];

            if ($ch === '\\') {
                if ($i + 1 < $len) {
                    $buf .= $s[$i] . $s[$i + 1];
                    $i += 2;
                    continue;
                }
                $buf .= '\\';
                $i++;
                continue;
            }
            if ($ch === '{') {
                $depth++;
                if ($depth > 1) $buf .= '{';
                $i++;
                continue;
            }
            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $parts[] = $buf;
                    $i++;
                    return [$parts, $i];
                }
                $buf .= '}';
                $i++;
                continue;
            }
            if ($ch === ',' && $depth === 1) {
                $parts[] = $buf;
                $buf = '';
                $i++;
                continue;
            }

            $buf .= $ch;
            $i++;
        }
        return [null, $pos]; // unbalanced
    }
}
