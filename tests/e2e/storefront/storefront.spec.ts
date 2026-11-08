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

test('quick view shows product descriptions without raw HTML tags', async ({ page }) => {
    await page.goto('/products', { waitUntil: 'networkidle' });
    const quickViewButton = page.getByRole('button', { name: 'Quick View' }).first();
    await expect(quickViewButton).toBeAttached();
    await quickViewButton.click();

    const dialog = page.getByRole('dialog', { name: 'Product quick view' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByText('View Full Details')).toBeVisible();
    const text = await dialog.innerText();
    expect(text).not.toMatch(/<\/?[a-z][^>]*>/i);
});

test('footer advertises exactly the active payment methods from the server', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });

    const pageData = await page.locator('#app').getAttribute('data-page');
    expect(pageData).toBeTruthy();
    const props = JSON.parse(pageData as string).props;
    const expectedNames = (props.activePaymentMethods ?? []).map((method: { name: string }) => method.name);
    const renderedNames = await page.locator('[data-payment-method]').allTextContents();

    expect(renderedNames).toEqual(expectedNames);
    await expect(page.locator('footer')).not.toContainText('Visa · Mastercard · PayPal · Stripe');
});

test('slow navigation shows a visible, non-blocking progress line without copy', async ({ page }) => {
    await page.goto('/', { waitUntil: 'networkidle' });
    const href = await firstProductHref(page);

    await page.route(`**${href}**`, async route => {
        await new Promise(resolve => setTimeout(resolve, 900));
        await route.continue();
    });

    const productLink = page.locator(`a[href='${href}']`).first();
    await productLink.click();

    const progressBar = page.locator('#nprogress .bar');
    await expect(progressBar).toBeVisible();
    await expect(progressBar).toHaveCSS('height', '8px');
    await expect(progressBar).toHaveCSS('background-color', 'rgb(4, 23, 53)');
    await expect(page.locator('#nprogress')).toHaveCSS('pointer-events', 'none');
    await expect(page.locator('#navigation-progress-status')).toHaveCount(0);
    await expect(page.locator('#app')).toHaveAttribute('aria-busy', 'true');

    await page.waitForURL(`**${href}`);
    await expect(page.locator('#app')).toHaveAttribute('aria-busy', 'false');
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

test('products listing: narrow phones get readable cards and an uncramped toolbar', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 800 });
    await page.goto('/products', { waitUntil: 'networkidle' });

    const cards = page.locator('.storefront-product-card');
    await expect(cards.first()).toBeVisible();
    const firstCard = await cards.nth(0).boundingBox();
    const secondCard = await cards.nth(1).boundingBox();
    expect(firstCard).not.toBeNull();
    expect(secondCard).not.toBeNull();
    expect(firstCard!.width).toBeGreaterThan(270);
    expect(secondCard!.y).toBeGreaterThan(firstCard!.y + firstCard!.height);

    const sortSelect = page.locator('select').first();
    const sortBox = await sortSelect.boundingBox();
    expect(sortBox).not.toBeNull();
    expect(sortBox!.width).toBeGreaterThan(150);
    expect(sortBox!.x + sortBox!.width).toBeLessThanOrEqual(320);
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
