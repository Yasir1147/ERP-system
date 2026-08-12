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
    const terms = normalize(query).split(' ').filter(Boolean);

    if (terms.length === 0) return true;

    const haystack = normalize(fields.filter(Boolean).join(' '));

    if (!haystack) return false;

    const words = haystack.split(' ');

    return terms.every((term) => haystack.includes(term) || words.some((word) => wordMatches(word, term)));
}
