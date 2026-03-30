# InspectionBundle

Inspection helpers for API Platform resources:

- discover collection routes (`api_route`)
- derive searchable and sortable fields (`searchable_fields`, `sortable_fields`)
- build default column metadata (`api_columns`)

## Install

```bash
composer require survos/inspection-bundle
```

## Routes

This bundle no longer ships a Symfony installer recipe. Import routes manually when needed:

```yaml
# config/routes/survos_inspection.yaml
survos_inspection:
  resource: '@SurvosInspectionBundle/config/routes.yaml'
  prefix: '/inspection'
```

## Twig Helpers

```twig
{% set class = 'App\\Entity\\Asset' %}

{{ api_route(class) }}
{{ searchable_fields(class)|join(', ') }}
{{ sortable_fields(class)|join(', ') }}
```
