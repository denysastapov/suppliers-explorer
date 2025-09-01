# Suppliers Explorer (WordPress)

A lightweight WordPress plugin that turns a spreadsheet of vendors into a fast, searchable **Suppliers Directory** with instant filters, inline details, and a **remote‑mode** so other WordPress sites can reuse the same data via REST by dropping a shortcode.

> Works great with Elementor (via the Shortcode widget), but **doesn’t depend** on it.

---

## ✨ Features
- CPT **`supplier`** + taxonomies **`top-level-category`** (hierarchical) & **`sub-category`**
- 5×5 grid (configurable) with **Load more**
- Multi‑select filters (**AND** logic across top-level categories)
- **Universal search** (title + matching term names: top/sub)
- Inline **detail drawer** under the clicked row (smooth transitions)
- **Remote‑mode** shortcode to power other WP sites over REST
- Import pipeline **CSV → WXR** (maps Featured Image via Media GUID; skips empty fields)

---

## 🔌 Shortcode
```text
[suppliers_explorer api="https://PRIMARY-SITE.tld" per_page="25"]
```
- `api` (optional): when set → **remote‑mode** (fetch from this site).
- `per_page` (optional): cards per page (default 15). Use `25` for a 5×5 grid on desktop.

**Elementor:** add a *Shortcode* widget and paste the shortcode.

---

## 🚀 Quick Start

### Primary / Source site
1. Copy the plugin into `wp-content/plugins/suppliers-explorer/` and **Activate**.
2. Ensure taxonomies exist with these slugs:
   - `top-level-category` (hierarchical **ON**)
   - `sub-category`
3. (Optional) ACF term field on sub‑category for parent top‑level (admin comfort).
4. Local usage:
   ```text
   [suppliers_explorer per_page="25"]
   ```

### Import data (WXR)
**Tools → Import → WordPress** → select WXR. Keep **“Download and import file attachments”** checked.  
Importer will match existing Media by **GUID (File URL)** and set `_thumbnail_id` without re‑uploading.

### Consumer / Remote site
1. Install the same plugin and **Activate**.
2. Insert on a page:
   ```text
   [suppliers_explorer api="https://PRIMARY-SITE.tld" per_page="25"]
   ```
3. On the **primary** site, whitelist the consumer origin via CORS (below).

---

## 🌐 CORS (primary site)
Add to the primary site (plugin or theme `functions.php`). Origins must match exactly (scheme + host + port), **no trailing slash**.
```php
add_action('rest_api_init', function () {
  add_filter('rest_pre_serve_request', function ($served, $result, $request) {
    $route  = $request->get_route();
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? rtrim($_SERVER['HTTP_ORIGIN'], '/') : '';

    $allowed = [
      'https://consumer.example',   // replace with your consumer origin(s)
      // 'http://consumer.local:10003',
    ];

    $is_se = strpos($route, '/suppliers/v1/') === 0;
    $is_tax = strpos($route, '/wp/v2/top-level-category') === 0;

    if (($is_se || $is_tax) && in_array($origin, $allowed, true)) {
      header('Access-Control-Allow-Origin: ' . $origin);
      header('Vary: Origin');
      header('Access-Control-Allow-Methods: GET, OPTIONS');
      header('Access-Control-Allow-Headers: Content-Type');
    }
    return $served;
  }, 10, 3);
});
```

---

## 🔁 REST API (overview)
Base (local): `/wp-json/suppliers/v1/*`  
- `GET /list` — params: `page`, `per_page` (≤50), `q`, `top[]` (AND), `orderby`, `order`  
- `GET /single?id=123` — single supplier

Base (terms): `/wp-json/wp/v2/top-level-category`

**Example**
```bash
curl "https://primary-site.tld/wp-json/suppliers/v1/list?top[]=cloud&top[]=cybersecurity&per_page=25"
```

---

## 🗂 Data Model
- **Post type:** `supplier`
- **Taxonomies:** `top-level-category` (hierarchical), `sub-category`
- **Featured Image:** logo (`_thumbnail_id`), auto‑mapped on WXR import

---

## 🎛 Frontend UX
- Responsive CSS Grid (5 columns on desktop)
- Instant multi‑select filters + debounced search
- Inline detail drawer with smooth height/fade transitions

---

## 🧪 Troubleshooting
- **CORS blocked:** origin mismatch or trailing slash. Fix the CORS whitelist on the primary site.
- **Empty categories (remote):** confirm `GET /wp-json/wp/v2/top-level-category?...` on the primary.
- **No logos after import:** Media **GUIDs** must match URLs in WXR; keep the attachments checkbox on.
- **No results:** verify term slugs and that `top-level-category` is **hierarchical**.

---

## 📁 Folder Structure
```
suppliers-explorer/
├─ suppliers-explorer.php
├─ includes/
│  ├─ class-suppliers-explorer-assets.php
│  ├─ class-suppliers-explorer-shortcode.php
│  └─ class-suppliers-explorer-rest.php
├─ assets/
│  ├─ css/suppliers-explorer.css
│  └─ js/suppliers-explorer.js
└─ docs/
   ├─ banner.png
   ├─ screenshot-grid.png
   └─ screenshot-detail.png
```

---

## 🗺 Roadmap
Sorting (A→Z/Z→A), analytics events, optional server‑side caching, Gutenberg block variant.

---

## 📦 Requirements
WordPress 6.x+, PHP 7.4+ (PHP 8.x compatible). ACF Pro optional.

---

## 🤝 Contributing
PRs welcome. Keep slugs & routes stable. Avoid hard deps. Bump `SE_VERSION` when assets change.

---

## 📄 License
GPL‑2.0‑or‑later.

---

## 📸 Screenshots
<img width="1301" height="1096" alt="Снимок экрана 2025-09-01 в 14 31 22" src="https://github.com/user-attachments/assets/228fda74-a3c1-4340-9530-d9a4add2dba6" />

<img width="1299" height="468" alt="Снимок экрана 2025-09-01 в 14 31 40" src="https://github.com/user-attachments/assets/b8bb2c67-38e3-4d1a-9149-09de3e8e2d82" />

<img width="1311" height="940" alt="Снимок экрана 2025-09-01 в 14 31 53" src="https://github.com/user-attachments/assets/7a1ff264-02eb-4e41-bc14-3e651e4e9b4e" />


