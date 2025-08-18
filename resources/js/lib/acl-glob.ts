function buildRegexBody(glob: string, sep: string): string {
	const len = glob.length;
	let i = 0;
	let out = "";
	const sepQuoted = escapeRegex(sep);

	const lit = (s: string) => escapeRegex(s);

	while (i < len) {
		const ch = glob[i];

		// Backslash escapes the next char as a literal
		if (ch === "\\") {
			if (i + 1 < len) {
				out += lit(glob[i + 1]);
				i += 2;
			} else {
				out += lit("\\");
				i += 1;
			}
			continue;
		}

		// Segment separator
		if (ch === sep) {
			out += sepQuoted;
			i += 1;
			continue;
		}

		// Stars: * (segment) or ** (globstar)
		if (ch === "*") {
			let j = i + 1;
			while (j < len && glob[j] === "*") j++;
			const count = j - i;

			if (count >= 2) {
				// globstar
				out += ".*";
				i += count;
			} else {
				// single star within a segment
				out += `(?:[^${sepQuoted}]*)`;
				i += 1;
			}
			continue;
		}

		// Single-char wildcard
		if (ch === "?") {
			out += `(?:[^${sepQuoted}])`;
			i += 1;
			continue;
		}

		// Character class
		if (ch === "[") {
			i += 1;
			let cls = "[";

			// Negation
			if (i < len && (glob[i] === "!" || glob[i] === "^")) {
				cls += "^";
				i += 1;
			}

			// Leading ']' as literal
			if (i < len && glob[i] === "]") {
				cls += "\\]";
				i += 1;
			}

			// Consume until closing ']'
			while (i < len && glob[i] !== "]") {
				const c = glob[i++];
				if (c === "\\" || c === "-" || c === "^") cls += "\\" + c;
				else cls += c;
			}

			if (i < len && glob[i] === "]") {
				cls += "]";
				i += 1;
			} else {
				// Unclosed class: treat as literal '['
				cls = "\\[";
			}

			out += cls;
			continue;
		}

		// Brace alternation
		if (ch === "{") {
			const { alts, next } = parseBraceAlts(glob, i);
			if (alts === null) {
				out += lit("{");
				i += 1; // literal {
			} else {
				const pieces = alts.map((alt) => buildRegexBody(alt, sep));
				out += "(?:" + pieces.join("|") + ")";
				i = next;
			}
			continue;
		}

		// Literal character
		out += lit(ch);
		i += 1;
	}

	return out;
}

/**
 * Parse a brace alternation block `{a,b,{c,d}}` starting at `pos` (which must be `{`).
 * Returns the list of alternatives and the index *after* the closing `}`,
 * or `{ alts: null, next: pos }` if unbalanced.
 */
function parseBraceAlts(
	s: string,
	pos: number
): { alts: string[] | null; next: number } {
	const len = s.length;
	if (pos >= len || s[pos] !== "{") return { alts: null, next: pos };

	let depth = 0;
	let i = pos;
	let buf = "";
	const parts: string[] = [];

	while (i < len) {
		const ch = s[i];

		if (ch === "\\") {
			if (i + 1 < len) {
				buf += s[i] + s[i + 1];
				i += 2;
			} else {
				buf += "\\";
				i += 1;
			}
			continue;
		}
		if (ch === "{") {
			depth++;
			if (depth > 1) buf += "{";
			i += 1;
			continue;
		}
		if (ch === "}") {
			depth--;
			if (depth === 0) {
				parts.push(buf);
				i += 1;
				return { alts: parts, next: i };
			}
			buf += "}";
			i += 1;
			continue;
		}
		if (ch === "," && depth === 1) {
			parts.push(buf);
			buf = "";
			i += 1;
			continue;
		}

		buf += ch;
		i += 1;
	}

	return { alts: null, next: pos }; // unbalanced
}

/** Escape a string for literal use inside a JS RegExp. */
function escapeRegex(s: string): string {
	return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/**
 * Utility class for matching and comparing Bash-like glob patterns in TypeScript.
 *
 * Supported syntax:
 * - `*`   → matches within a single segment (excludes `sep`)
 * - `**`  → matches across segments (includes `sep`)
 * - `?`   → matches exactly one character (excludes `sep`)
 * - `[abc]`, `[!abc]` → character classes, optional negation
 * - `{a,b,{c,d}}` → alternation with nesting
 *
 * Intended for generic string pattern matching, not specifically for file paths.
 */
export namespace ACLGlob {
	/**
	 * Check if a subject matches a glob pattern.
	 * @param pattern Glob pattern.
	 * @param subject String to test.
	 * @returns true if it matches, false otherwise.
	 */
	export function match(pattern: string, subject: string): boolean {
		const re = ACLGlob.toRegex(pattern);
		return re.test(subject);
	}

	/**
	 * Convert a Bash-like glob pattern to a JavaScript RegExp.
	 *
	 * @param glob   The glob pattern to convert.
	 * @param sep    Segment separator (default '/').
	 * @param anchor If true (default), anchors the regex with ^...$.
	 * @returns A RegExp that implements the glob semantics.
	 */
	export function toRegex(
		glob: string,
		sep: string = "/",
		anchor: boolean = true
	): RegExp {
		const body = buildRegexBody(glob, sep);
		const src = anchor ? `^${body}$` : body;
		return new RegExp(src);
	}
}
