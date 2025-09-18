# BCS Member Import

A Contao 5.3 backend module that allows importing members from a CSV file into the `tl_member` table.

## Features
- Backend module under **Accounts → Member Import**
- Append-only CSV member import (does not overwrite existing members)
- Will support creating member groups and assigning members
- Planned: send password reset emails after import

## Installation
1. Add repository to Packagist (after pushing to GitHub).
2. Install via Contao Manager or composer:
   ```bash
   composer require bright-cloud-studio/contao-bcs-member-import
   ```
3. Run `vendor/bin/contao-console cache:clear`.
4. Log into the Contao backend → Accounts → Member Import.

## Development
This bundle was scaffolded following Contao 5.3 standards and modeled after `contao-osi`.
