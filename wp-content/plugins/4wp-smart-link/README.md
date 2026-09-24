# 4WP Smart Link

[![WordPress plugin version](https://img.shields.io/wordpress/plugin/v/4wp-smart-link?style=flat-square)](https://wordpress.org/plugins/4wp-smart-link/)
[![WordPress tested version](https://img.shields.io/wordpress/plugin/tested/4wp-smart-link?style=flat-square)](https://wordpress.org/plugins/4wp-smart-link/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)

![4WP Smart Link — make Cover, Group, and Column blocks fully clickable in Gutenberg and Query Loop](.wordpress-org/assets/banner-1544x500.png)

**Make Cover, Group, and Column blocks fully clickable** in Gutenberg and Query Loop—custom URL or link to the current post in each card. No wrapper block, no custom code.

A plugin by **[4wp.dev](https://4wp.dev/plugin/4wp-smart-link/)**.

## Demo

[![Watch the 4WP Smart Link demo on YouTube](https://img.youtube.com/vi/8ZGojkTl2CM/hqdefault.jpg)](https://www.youtube.com/watch?v=8ZGojkTl2CM)

## Perfect for

- Clickable **post cards** in a Query Loop  
- **Cover** heroes that open a landing page or the current post  
- **Group** or **Column** layouts that act as one big tap target  

## How it works

1. Select a **Cover**, **Group**, or **Column** block.  
2. Open **Smart Link** in the block toolbar.  
3. Choose **Custom Link** or **Post Link** (inside a Query Loop post template).  
4. Use **Preview** or the published page to test clicks.  

Smart Link is applied on the **front end**. The editor canvas may not match the live page—use Preview when you verify behavior.

**Inner links:** If the block has no other links inside, the whole block opens your URL. If you added a post title, buttons, or terms, those links stay separate; clicks on empty space open your Smart Link URL.

## Install

- **[WordPress.org](https://wordpress.org/plugins/4wp-smart-link/)** — recommended for production sites.  
- **From source:** clone this repo, run `npm install && npm run build`, then activate the plugin.  

## FAQ highlights

- **Post Link** works inside a Query Loop post template only.  
- **Accessibility:** one link for the whole block when possible; inner buttons and links stay focusable when present.  
- **New tab:** `noopener` / `noreferrer` are added when needed.  

Full FAQ: [plugin page](https://wordpress.org/plugins/4wp-smart-link/#faq).

## Links

| | |
|---|---|
| Product page | [4wp.dev/plugin/4wp-smart-link](https://4wp.dev/plugin/4wp-smart-link/) |
| WordPress.org | [wordpress.org/plugins/4wp-smart-link](https://wordpress.org/plugins/4wp-smart-link/) |
| Support | [Plugin support forum](https://wordpress.org/support/plugin/4wp-smart-link/) |

## For developers

Anchor and host modes, CSS classes, and filters are documented in [readme.txt](readme.txt) under **Other Notes**.

```bash
npm install
npm run build
```

Editor build output: `build/editor/`. WordPress.org listing assets: `.wordpress-org/assets/`.

## License

GPL v2 or later. See [LICENSE](LICENSE).
