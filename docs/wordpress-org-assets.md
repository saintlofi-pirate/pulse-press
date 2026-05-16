# WordPress.org Assets

PulsePress brand/banner assets are generated from source files in `wordpress-org-assets/source/` and exported to `wordpress-org-assets/final/`.

## Current Logo Source

- Runtime/reference logo: `resources/social_engagement_content_card_icon.png`
- WordPress.org icon source: `wordpress-org-assets/source/icon.svg`
- Final icon exports:
  - `wordpress-org-assets/final/icon.svg`
  - `wordpress-org-assets/final/icon-256x256.svg`
  - `wordpress-org-assets/final/icon-128x128.svg`
  - `wordpress-org-assets/final/icon-256x256.png`
  - `wordpress-org-assets/final/icon-128x128.png`

WordPress.org supports SVG for plugin icons. Keep the PNG files as fallbacks for older browsers or any renderer that fails to display the SVG.

## Current Banner Source

- Source: `wordpress-org-assets/source/banner-1544x500.svg`
- Final exports:
  - `wordpress-org-assets/final/banner-1544x500.png`
  - `wordpress-org-assets/final/banner-772x250.png`

WordPress.org does not support SVG banners, so the banner source SVG is only a local generation file. Upload/use the PNG banner exports.

The banner keeps the visual direction from session `019e278d-71d2-7d61-b541-f65f34efe9eb`, but replaces the previous heart mark with the supplied social engagement content-card logo.

## Regeneration Notes

- The banner SVG source references `resources/social_engagement_content_card_icon.png`.
- The final SVG icons embed the logo image as a data URI so they can stand alone in the WordPress.org assets directory.
- Exported PNGs should stay exactly `1544x500`, `772x250`, `256x256`, and `128x128`.
- Keep the banner copy short enough that it does not sit underneath the right analytics card.
