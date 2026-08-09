/*
 * Number formatting the admin screens share.
 *
 * Latin digits even though the interface is Arabic: these are figures an
 * operator reconciles against a database, a bank statement and a spreadsheet,
 * and Arabic-Indic digits in that loop invite transcription mistakes. Prose
 * elsewhere in the app still uses Arabic numerals — see ArabicNumerals on the
 * PHP side, which is about reading, not reconciling.
 */

export const num = (value: number) => Number(value ?? 0).toLocaleString('en-US');

export const money = (value: number) =>
    Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Compact form for chart axes and tight tiles: 12.4k, 3.1m. */
export const compact = (value: number) => {
    const v = Number(value ?? 0);
    if (Math.abs(v) >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'm';
    if (Math.abs(v) >= 1_000) return (v / 1_000).toFixed(1) + 'k';
    return num(v);
};

export const percent = (value: number) => `${Number(value ?? 0).toFixed(1)}%`;
