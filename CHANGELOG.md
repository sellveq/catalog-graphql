# Changelog

## 4.0.0

Forked from `scandipwa/catalog-graphql` 3.4.0. Module name and namespace are unchanged, and the package replaces `scandipwa/catalog-graphql` at every version, so it installs as a drop-in replacement.

- Magento 2.4.9 and PHP 8.3 support.
- DI compilation failed on 2.4.9: the `ProductCollectionSearchCriteriaBuilder` override and the plugin beside it are gone with the core class Adobe removed, and `SearchCriteriaBuilder` forwards the constructor parameters 2.4.9 added.
- `pageSize` and `currentPage` are honoured on every products path, validated by core's own rules, and capped at 500 items per page.
- A page beyond the search engine's result window is refused, instead of answered from the wrong offset.
- `filter.customer_group_id` no longer writes the session customer group; the field and the wire shape are unchanged.
- `conditions` is scoped like the products path, to enabled, catalog-visible, in-store products, and capped at 500 candidates in a fixed order, with one logged line when a rule matches more.
- A `conditions` tree that matches nothing now returns nothing, instead of the entire catalogue.
- Removed: the `min_price` and `max_price` fields on `Products`, which a second definition of the type in the same file had always shadowed.
- The dynamic price interval algorithm iterates over its keys, so writing to the array mid-loop no longer skips intervals.
- `MatchQuery` overrides `build()`, the method 2.4.9 actually calls, so quoted phrases, boosting and fuzziness apply again.
- Breadcrumbs carry `category_uid`, and a category image whose file is missing falls back to core's placeholder.
- Removed: the dead `PageSizeProvider` virtual type, the disabled `bundle` plugin naming a plugin that does not exist, the unwired `Category\Products` resolver, and dead constructor parameters from the preference classes.
- `currencyData` declares `@cache(cacheable: false)` because it answers from the visitor's session, and `menuItems` declares a cache identity returning the category tag for every category in the menu, so a store without a cacheability allow-list no longer pools either answer wrongly.

## 1.4.4

- fixed logic for recognizing attributes (broken in 1.4.0 due to attributes are not auto-imported to the schema)
- fixed a bug when products could not be filtered with custom attribute w/o a category in the same request

## 1.4.1

- `min_price, max_price` fields for accurate price filter
- filterable attributes are added via `di.xml`
- layered navigation (filters) now utilizes the same collection (avoiding loading unnecessary data)

## 1.4.0

- attributes are not auto-injected into schema (reduce bootstrap time)

## 1.0.0

- custom resolver to `category` field
- type `ProductThumbnails` to `ProductInterface` interface
- elasticsearch page size
- `category` field input options with `url_path`
- `products` field input options with:
- Configurable products attributes (`color`, `size`, `shoes_size`)
- Category URL (`category_url_key`, `category_url_path`)
