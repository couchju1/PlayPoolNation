# Nate Hall Insurance website

A single-page site for Nate Hall Insurance in Hartford, SD. It's built with plain HTML, CSS, and vanilla JS. There's no framework and no build step.

```
nate-hall-insurance/
├── index.html        Main page
├── privacy.html      Privacy policy (linked from the footer)
├── thanks.html       Form confirmation page (used only if JavaScript is off)
├── css/styles.css
├── js/main.js        Tabs, FAQ accordion, testimonials carousel, form submit
├── assets/           logo.png, nate.jpg (+ .webp), og-image.jpg, favicons
├── netlify.toml      Netlify publish settings and headers
└── README.md
```

## TODO placeholders (fill these in before launch)

Each placeholder is a plain-text token, so you can find and replace it across all files. Run this to list any that are left:

```bash
grep -rn "TODO_" --include=*.html --include=*.js .
```

| Token | What to put there | Where it appears |
|---|---|---|
| `TODO_EMAIL` | Email address | index.html (contact list, JSON-LD), privacy.html |
| `TODO_HOURS` | Office hours, e.g. `Mon–Fri 9am–5pm, evenings by appointment` | index.html (office box). In the JSON-LD, use schema format such as `Mo-Fr 09:00-17:00` |
| `TODO_BIO` | More about Nate: background, family, hobbies | index.html, About tab (a commented-out template is ready to fill in) |
| `TODO_NPN` *(optional)* | NPN / license number | index.html footer (commented out; uncomment to show it) |

> Link preview: `assets/og-image.jpg` (1200×630) is what shows when the site is shared on Facebook or by text. It's Nate's headshot with his name, title, and town, and it doesn't include contact info. Facebook caches previews, so after changing it, run the page URL through the [Sharing Debugger](https://developers.facebook.com/tools/debug/) and click **Scrape Again**.

**Already filled in:**
- Site address: `https://dashing-gumption-107aca.netlify.app`, used in the canonical links, Open Graph tags, and JSON-LD. If you add a custom domain later, search for `dashing-gumption` and replace it.
- Phone number `605-321-5367` (`tel:+16053215367`). It's used in the Call buttons, office box, contact section, form messages, privacy and thanks pages, and the JSON-LD. To change it, search for both formats.

## Testimonials

The carousel is built but hidden. To turn it on, open `js/main.js`, add real client quotes to the `TESTIMONIALS` array, and set `TESTIMONIALS_ENABLED = true`. Comments in that file explain how. Only use real reviews, with the client's permission.

## Run it locally

```bash
cd nate-hall-insurance
python3 -m http.server 8000
# open http://localhost:8000
```

The contact form only submits on Netlify. Locally it will show the "something went wrong" message, which is expected.

## Deploy on Netlify

This site lives in the `nate-hall-insurance/` folder of the repo.

1. Log in at <https://app.netlify.com> and choose **Add new site → Import an existing project**.
2. Connect GitHub and pick this repository and branch.
3. Set **Base directory** to `nate-hall-insurance`. Leave **Build command** empty, and set **Publish directory** to `nate-hall-insurance` (or `.` relative to the base). `netlify.toml` already sets the publish directory.
4. Click **Deploy**.
5. **Forms:** go to **Site configuration → Forms** and enable form detection if it's off, then redeploy. Submissions to the `quote-request` form will show up there. Under **Forms → Form notifications**, add an email notification so Nate gets each request in his inbox.
6. **Custom domain** (optional): go to **Domain management → Add a domain**. HTTPS is set up automatically.
7. Replace `TODO_SITE_URL` with the final URL and push again.

*Drag-and-drop option:* you can also drag the `nate-hall-insurance` folder onto <https://app.netlify.com/drop>. Forms still work, but you'll need to redeploy by hand after each change.

## Deploy on GitHub Pages

GitHub Pages serves the files as-is, but **it doesn't process Netlify Forms**. If you host there, point the form's `action` to a form service such as Formspree, then remove `data-netlify` and update the `fetch('/')` URL in `js/main.js` to match. Because the site sits in a subfolder, you'd also want to move it to its own repo (or the repo root/`docs/` folder) for Pages.

## Notes

- Palette (from the logo): ink `#14171C`, charcoal `#262B33`, steel `#5B6573`, silver `#C9CED6`, mist `#F3F4F6`, gold `#C9A24A` (gold text on light backgrounds uses `#8A6420`).
- Font: Cormorant Garamond (Google Fonts) for headings, system fonts for body text.
- Accessibility check: axe-core reported zero violations at 375px and 1280px. Tabs follow the WAI-ARIA pattern (Arrow keys, Home, End), and the FAQ uses buttons with `aria-expanded`.
- Prudential Financial is mentioned in text only (no logo).
