# Architecture: ps_emailalerts

## Purpose

A PrestaShop module that sends email notifications to merchants and customers on order
status changes, product back-in-stock events, and other store events. Configurable per
event type and recipient group.

## Directory Structure

```
ps_emailalerts.php   # Main module class; registers hooks for order status, stock, etc.
mails/               # Email templates (HTML + text) per language
translations/        # Translation files
tests/               # PHPStan and unit tests
```

## Key Design Decisions

Hooks into PrestaShop's `actionOrderStatusUpdate`, `actionProductCoverage`, and related
events. Uses PrestaShop's `Mail::Send()` to dispatch transactional emails using the
configured SMTP settings. Templates in `mails/` are multi-language and use PrestaShop's
template variable substitution syntax.

## Extension Points

- Add custom recipients by hooking into the module's configuration.
- Extend email templates in `mails/` for custom formatting.
- Override the module class to add custom event handlers.
