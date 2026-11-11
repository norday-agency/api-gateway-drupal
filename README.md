# API Gateway Drupal connector
This package gives you the connection with a Drupal CMS. The package can be used on it's own, or in combination with the stadspas-api-gateway package (^v3.0.0)

## Installation
There are a few steps to go through to be able to use everything this package has to offer.

* Add the following lines (including all the locales you require to your `config/app.php` file:

```
'supported_locales' => ['nl', 'en'],
'base_locale' => 'nl',
``` 

* Add the following to your projects composer file

```
"repositories": { 
   "api-gateway-drupal": { 
      "type": "vcs", 
      "url": "git@github.com:IN10/api-gateway-drupal" 
    } 
}

"extra": {
    "enable-patching": true,
    "patches": {
        "": ""
    }
}
```

* Require the package in your (clean) Laravel project

`composer require in10/api-gateway-drupal`

* Publish the config files

`php artisan vendor:publish --tag="ApiGatewayDrupal"`

* Add the following line to the `$middlewareGroups api` section in the `Kernel.php` :

`\IN10\ApiGatewayDrupal\Middleware\Localization::class`

NOTE: any project specific requirements will have to be implemented in the project api layer 

## Choosing includes from the frontend

Without `include`, Drupal adds the full `default_include` of the type to every item. For a list of cards that is far more than needed. A frontend can ask for less:

```
GET /admin/v1.0/nl/verhalen?include=header_image.image.image&limit=12
GET /admin/v1.0/nl/verhalen/{slug}?include=header_image.image.image,related_stories
GET /admin/v1.0/nl/verhalen?include=
```

The last one includes nothing. Separate paths with a comma.

### Which includes can I use?

```
GET /admin/v1.0/includes
```

```json
{
    "data": {
        "stories": ["header_image.image.image", "meta_image.image.image", "components", "related_stories"],
        "pages": ["parent_menu_item", "header_image.image.image", "components"]
    },
    "statusCode": 200
}
```

The keys are the types in `cms.types`; the paths are the `default_include` of that resource in Drupal. A path is allowed when it is in the list or is the start of one: `header_image` is fine because `header_image.image.image` is listed. Anything else returns a 400 and Drupal is not called:

```json
{ "data": { "invalid": ["bogus"], "allowed": ["header_image.image.image", "..."] }, "statusCode": 400, "statusText": "Invalid include" }
```

A relation you leave out comes back empty. Without `header_image.image.image` a card has no image.

Example with the Next.js `cmsGet` helper:

```ts
const stories = await cmsGet('verhalen', 'nl', { include: 'header_image.image.image', limit: 12 });
```

### Where the list comes from

The gateway gets it from Drupal (`drupal_laravel_cache` 2.3+, `GET /{locale}/gateway/includes`) and caches it for `cms.cache.ttl` with the tag `includes`. Without that endpoint `?include` is ignored, Drupal's default includes apply, and `GET /admin/v1.0/includes` returns a 503.

## Pagination

Lists take `?page=n` and `?limit=n`. A `limit` between 1 and `cms.pagination.max_limit` (default 48, `CMS_PAGINATION_MAX_LIMIT`) is used; anything else falls back to `cms.pagination.limit`.

A page past the last item returns a 404. Set `cms.pagination.empty_page_ok` to `true` (`CMS_PAGINATION_EMPTY_PAGE_OK`) to get a 200 with `data: []` and the `pagination` block instead.

If your project published `config/cms.php` with its own `pagination` array, add `max_limit` and `empty_page_ok` there: the package defaults do not merge into a published array.
