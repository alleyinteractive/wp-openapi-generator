# Changelog

All notable changes to `OpenAPI Generator` will be documented in this file.

## 0.1.0 - Unreleased

- Initial release.
- Generate an OpenAPI 3.1 document from registered REST routes, including path parameters, query parameters, request bodies, response schemas, pagination headers, and media uploads.
- Serve the document at `/wp-json/openapi/v1/spec` and through `wp openapi generate`.
- Browse and call the API with Swagger UI at `/openapi/`, optionally public, from **Settings > OpenAPI**.
- Limit the documented paths with an allow or deny list of wildcard patterns.
- List operations with missing request or response shapes on the settings page.
- Customize the document with the `openapi` endpoint option and filters for routes, arguments, schemas, responses, operations, info, servers, and security.
