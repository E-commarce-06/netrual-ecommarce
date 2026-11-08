/**
 * Convert stored rich-text product descriptions into safe, readable paragraphs.
 * The storefront deliberately renders the result with Vue interpolation, never
 * v-html, so HTML from product data cannot become executable markup.
 */
export function toProductDescriptionParagraphs(raw: string | null | undefined): string[] {
    if (!raw) return [];

    // Some imports/admin inputs persist one or more layers of HTML entities.
    // Decode a bounded number of layers before removing tags so `&lt;p&gt;`
    // cannot leak through as visible text. Vue interpolation still escapes the
    // final plain text when it is rendered.
    let text = raw;
    for (let i = 0; i < 4; i++) {
        const decoded = text
            .replace(/&nbsp;/gi, ' ')
            .replace(/&lt;/gi, '<')
            .replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"')
            .replace(/&apos;/gi, "'")
            .replace(/&#39;/gi, "'")
            .replace(/&amp;/gi, '&');

        if (decoded === text) break;
        text = decoded;
    }

    return text
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/?(?:p|div|li|h[1-6]|blockquote|ul|ol)\b[^>]*>/gi, '\n')
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<\/?[a-z][^>]*>/gi, '')
        .split(/[\r\n]+/)
        .map(paragraph => paragraph.replace(/\s+/g, ' ').trim())
        .filter(Boolean);
}
