# Contao Member Import Bundle

This bundle allows importing members via CSV, auto-creating groups, and bulk-sending password reset emails using Contao's built-in Lost Password module.

## Install
1. Copy into system/modules or load via composer.
2. Clear Contao cache.
3. Access in Backend under Accounts > Member CSV Import.
4. Create a Frontend page at /reset-password with the built-in "Lost password" (mod_lostPassword) module.
5. Imported users will get an email with a link to that page.

