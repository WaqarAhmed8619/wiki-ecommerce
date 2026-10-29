# Sample photography

Images are stored in `public/assets/images/` for local development. The five `wiki-*.png` fragrance images are original AI-generated WIKI product concepts, with optimized WebP derivatives used by the storefront. They are illustrative packaging, not photographs of manufactured goods. Clothing/editorial photos use the Unsplash references below. Replace samples with owned product photography and validate commercial usage/model/property rights before launch.

`bin/optimize-images.php` preserves originals and creates WebP versions plus 480px/960px responsive copies using PHP GD. Product cards select the appropriate size. Generated fragrance artwork replaces the earlier third-party fragrance samples on active storefront pages; those original source files remain as unused development references.

Source: Unsplash image CDN, referenced by the following stable photo identifiers:

| Local image | Source identifier |
|---|---|
| hero, collection | photo-1483985988355-763728e1935b |
| lookbook | photo-1445205170230-053b83016050 |
| fragrance, oud | photo-1541643600914-78b084683601 |
| tee | photo-1521572163474-6864f9cf17ab |
| hoodie | photo-1556821840-3a63f95609a7 |
| shirt | photo-1596755094514-f87e34085b2c |
| pants | photo-1473966968600-fa801b869a1a |
| jacket | photo-1551028719-00167b16eac5 |
| azure | photo-1594035910387-fea47794261f |
| noir | photo-1615634260167-c8cdede054de |
| rose | photo-1616949755610-8c9bbc08f138 |
| imperial | photo-1587017539504-67cfbddac569 |

Each original URL has the form `https://images.unsplash.com/IDENTIFIER?auto=format&fit=crop&w=1000&q=85`; the hero uses width 1800. Seeded gallery positions are illustrative repeats, not true distinct angles. The SVG fallback is authored locally.
