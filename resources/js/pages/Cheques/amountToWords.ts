const ones = [
    'Zero',
    'One',
    'Two',
    'Three',
    'Four',
    'Five',
    'Six',
    'Seven',
    'Eight',
    'Nine',
    'Ten',
    'Eleven',
    'Twelve',
    'Thirteen',
    'Fourteen',
    'Fifteen',
    'Sixteen',
    'Seventeen',
    'Eighteen',
    'Nineteen',
];
const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

const integerToWords = (number: number): string => {
    if (number < 20) return ones[number];
    if (number < 100) return `${tens[Math.floor(number / 10)]}${number % 10 ? ` ${ones[number % 10]}` : ''}`;
    if (number < 1000) return `${ones[Math.floor(number / 100)]} Hundred${number % 100 ? ` ${integerToWords(number % 100)}` : ''}`;

    for (const [value, label] of [
        [1_000_000_000_000, 'Trillion'],
        [1_000_000_000, 'Billion'],
        [1_000_000, 'Million'],
        [1000, 'Thousand'],
    ] as const) {
        if (number >= value) {
            const remainder = number % value;
            return `${integerToWords(Math.floor(number / value))} ${label}${remainder ? ` ${integerToWords(remainder)}` : ''}`;
        }
    }

    return '';
};

export const amountToWords = (amount: number): string => {
    const safeAmount = Number.isFinite(amount) ? Math.max(0, Math.round(amount * 100) / 100) : 0;
    const whole = Math.floor(safeAmount);
    const fraction = Math.round((safeAmount - whole) * 100);
    return `${integerToWords(whole)}${fraction ? ` And Fils ${integerToWords(fraction)}` : ''}`;
};

export const splitPreviewText = (text: string, maximum: number): [string, string] => {
    const clean = text.trim();
    if (clean.length <= maximum) return [clean, ''];
    const candidate = clean.slice(0, maximum + 1);
    const wordBreak = candidate.lastIndexOf(' ');
    const splitAt = wordBreak > 0 ? wordBreak : maximum;
    return [clean.slice(0, splitAt).trim(), clean.slice(splitAt).trim()];
};
