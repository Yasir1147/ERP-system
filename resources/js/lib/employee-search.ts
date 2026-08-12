/**
 * Forgiving search for employee lists.
 *
 * Worker names reach the system through inconsistent transliteration —
 * RUHUL and RAHUL, MOHAMMAD and MUHAMMAD and MD — so an exact substring
 * search hides people who are really there. This matches a term when the
 * spelling is close enough, not only when it is identical.
 */

/** Lowercase, strip punctuation, collapse whitespace. */
function normalize(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Edit distance, abandoned as soon as it cannot come in under `limit`.
 * Bailing early keeps this cheap enough to run over the whole list on
 * every keystroke.
 */
function editDistance(first: string, second: string, limit: number): number {
    if (Math.abs(first.length - second.length) > limit) return limit + 1;

    let previous = Array.from({ length: second.length + 1 }, (_, index) => index);

    for (let i = 1; i <= first.length; i++) {
        const current = [i];
        let rowBest = i;

        for (let j = 1; j <= second.length; j++) {
            const cost = first[i - 1] === second[j - 1] ? 0 : 1;

            current[j] = Math.min(
                current[j - 1] + 1, // insertion
                previous[j] + 1, // deletion
                previous[j - 1] + cost, // substitution
            );

            rowBest = Math.min(rowBest, current[j]);
        }

        if (rowBest > limit) return limit + 1;

        previous = current;
    }

    return previous[second.length];
}

/**
 * How far a term may be from a word and still count as the same word.
 *
 * Short terms get no tolerance: at three letters or fewer almost anything
 * is within one edit, and the list would fill with noise.
 */
function tolerance(length: number): number {
    if (length <= 3) return 0;
    if (length <= 6) return 1;

    return 2;
}

function wordMatches(word: string, term: string): boolean {
    if (word.includes(term) || term.includes(word)) return true;

    const allowed = tolerance(term.length);

    return allowed > 0 && editDistance(word, term, allowed) <= allowed;
}

/**
 * True when every term in `query` is found in `fields`, allowing for small
 * spelling differences. Multiple terms all have to match, so typing more
 * still narrows the list.
 */
export function matchesEmployeeSearch(fields: Array<string | null | undefined>, query: string): boolean {
    return scoreEmployeeSearch(fields, query) > 0;
}

/**
 * How well a term matches one word. Higher is a better match.
 *
 * The tiers matter because the fuzzy matching above is deliberately
 * generous: searching "Amir" also finds "Amin". Both should be listed, but
 * the one the user actually typed has to come first.
 */
function wordScore(word: string, term: string): number {
    if (word === term) return 100; // whole word is exactly what was typed
    if (word.startsWith(term)) return 80; // "amir" in "amirul"
    if (word.includes(term)) return 60; // term sits inside the word
    if (term.includes(word)) return 40; // typed more than the word holds

    const allowed = tolerance(term.length);

    if (allowed > 0) {
        const distance = editDistance(word, term, allowed);

        // Closer spellings rank above looser ones, all below a real match.
        if (distance <= allowed) return 20 - distance;
    }

    return 0;
}

/**
 * Relevance of `fields` for `query`, or 0 when it does not match at all.
 *
 * Every term must match something. The result is the total of each term's
 * best word score, so a row matching two terms strongly outranks one that
 * matches both weakly.
 */
export function scoreEmployeeSearch(fields: Array<string | null | undefined>, query: string): number {
    const terms = normalize(query).split(' ').filter(Boolean);

    if (terms.length === 0) return 1;

    const haystack = normalize(fields.filter(Boolean).join(' '));

    if (!haystack) return 0;

    const words = haystack.split(' ');
    let total = 0;

    for (const term of terms) {
        let best = 0;

        for (const word of words) {
            best = Math.max(best, wordScore(word, term));
            if (best === 100) break;
        }

        // A term spanning several words, e.g. "shah jalal" against a name.
        if (best === 0 && haystack.includes(term)) best = 60;

        if (best === 0) return 0;

        total += best;
    }

    return total;
}

/**
 * Filters and orders a list by search relevance.
 *
 * Rows keep their original order when they score the same, so an empty
 * search leaves the caller's sorting untouched.
 */
export function sortByEmployeeSearch<T>(items: T[], query: string, fieldsOf: (item: T) => Array<string | null | undefined>): T[] {
    if (!query.trim()) return items;

    return items
        .map((item, index) => ({ item, index, score: scoreEmployeeSearch(fieldsOf(item), query) }))
        .filter((entry) => entry.score > 0)
        .sort((first, second) => second.score - first.score || first.index - second.index)
        .map((entry) => entry.item);
}
