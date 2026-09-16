# ScandiPWA CatalogGraphQl

Fork of [scandipwa/catalog-graphql](https://github.com/scandipwa/catalog-graphql) 3.4.0, maintained by Selveq for Magento 2.4.9 and PHP 8.3. Module name and namespace are unchanged, and the package replaces `scandipwa/catalog-graphql` at every version, so it installs as a drop-in replacement. Selveq is not affiliated with or endorsed by Scandiweb.

## What it does

- Owns the product read path: `conditions`, `customer_group_id`, `news_from_date`, `news_to_date` and `id` join `ProductAttributeFilterInput`, and search criteria, the match query and the price interval algorithm are replaced end to end.
- Builds layered navigation from the collection the query already loaded, and gives every aggregation `position`, `is_boolean` and `has_swatch`.
- Adds the storefront's product and category fields: seven `ProductPrice` money values, `variants_plp`, `MediaGalleryEntry.thumbnail`/`base`/`large`, `stock_item`, breadcrumbs carrying `category_uid`, and category images that fall back to core's placeholder.
- Bounds every query: `pageSize` is capped at 500 items, a page past the search engine's result window is refused, and a `conditions` rule is scoped to enabled, catalog-visible, in-store products and capped at 500 candidates.

## Install

```sh
composer require selveq/catalog-graphql
bin/magento setup:upgrade
```

## License

[OSL-3.0](LICENSE), the license of the original work. Scandiweb's copyright notices are kept in every file, and each file Selveq changed carries a `Modifications © Selveq` notice.
