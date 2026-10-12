import { test, expect, type Page } from '@playwright/test';

// Storefront regression checks for the problems found on the live site:
// product clicks, product images, raw HTML in descriptions, mobile filter layout,
// and text/background contrast when the OS is in dark mode.

async function firstProductHref(page: Page): Promise<string> {
    const link = page.locator("a[href^='/product/']").first();
    await expect(link).toBeAttached();
    const href = await link.getAttribute('href');
    expect(href).toBeTruthy();
    return href as string;
}

test('clicking a product name opens the product detail page', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    const href = await firstProductHref(page);
    const nameLink = page.locator(`a[href='${href}']`).filter({ hasText: /\S/ }).first();
    await nameLink.scrollIntoViewIfNeeded();
    await nameLink.click();
    await page.waitForURL(`**${href}`);
    await expect(page.locator('h1').first()).not.toHaveText('');
});

test('product page shows no raw HTML tags in descriptions', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    const href = await firstProductHref(page);
    await page.goto(href, { waitUntil: 'networkidle' });
    const body = await page.locator('body').innerText();
    expect(body).not.toMatch(/<\/?p>/);
    expect(body).not.toMatch(/<\/?[a-z][^>]*>/i);
});

test('product images that are rendered actually load', async ({ page }) => {
    const failed: string[] = [];
    page.on('response', (r) => {
        if (r.url().includes('/storage/products/') && r.status() >= 400) failed.push(`${r.status()} ${r.url()}`);
    });
    await page.goto('/', { waitUntil: 'networkidle' });
    expect(failed).toEqual([]);
});

test('products listing: mobile filters are hidden behind a button, desktop shows the sidebar', async ({ page, viewport, isMobile }) => {
    await page.goto('/products', { waitUntil: 'networkidle' });
    const aside = page.locator('#product-filters');
    if (isMobile || (viewport && viewport.width < 1024)) {
        await expect(aside).toBeHidden();
        const trigger = page.getByRole('button', { name: 'Filters', exact: true });
        await expect(trigger).toBeVisible();
        await trigger.click();
        await expect(aside).toBeVisible();
    } else {
        await expect(aside).toBeVisible();
    }
});

test('products listing: mobile product grid starts above the filters', async ({ page, viewport, isMobile }) => {
    test.skip(!(isMobile || (viewport && viewport.width < 1024)), 'mobile only');
    await page.goto('/products', { waitUntil: 'networkidle' });
    const firstCard = page.locator("a[href^='/product/']").first();
    await expect(firstCard).toBeVisible();
    const cardTop = (await firstCard.boundingBox())?.y ?? Infinity;
    expect(cardTop).toBeLessThan(900);
});

test('dark OS mode keeps search and form text readable', async ({ page, colorScheme }) => {
    test.skip(colorScheme !== 'dark', 'dark-mode project only');
    await page.goto('/products', { waitUntil: 'networkidle' });
    const bad = await page.evaluate(() => {
        const parse = (c: string) => {
            const m = c.match(/rgba?\(([^)]+)\)/);
            if (!m) return null;
            const p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number);
            return { r: p[0], g: p[1], b: p[2] };
        };
        const lum = ({ r, g, b }: { r: number; g: number; b: number }) => {
            const f = (v: number) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
            return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
        };
        type Rgb = { r: number; g: number; b: number };
        const ratio = (a: Rgb, b: Rgb) => {
            const [x, y] = [lum(a), lum(b)];
            return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
        };
        const problems: string[] = [];
        document.querySelectorAll<HTMLElement>('input, select').forEach((el) => {
            const cs = getComputedStyle(el);
            let bgEl: HTMLElement | null = el;
            let bg: Rgb = { r: 255, g: 255, b: 255 };
            while (bgEl) {
                const c = parse(getComputedStyle(bgEl).backgroundColor);
                if (c && getComputedStyle(bgEl).backgroundColor !== 'rgba(0, 0, 0, 0)') { bg = c; break; }
                bgEl = bgEl.parentElement;
            }
            const fg = parse(cs.color);
            if (fg && ratio(fg, bg) < 4.5) problems.push(`${el.tagName} ${cs.color} on rgb(${bg.r},${bg.g},${bg.b})`);
        });
        return problems;
    });
    expect(bad).toEqual([]);
});
