<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog

All notable changes to this project are documented in this file.

## 1.0.0 - 2026-10-21

First public release, for Nextcloud 31, 32, 33, 34 and 35.

### Added

#### Inactive accounts
- List of accounts with no login for a set time (4, 6, 8, 12 or 24 months; 6 by default).
- Accounts that never logged in count from the day they were created.
- Filters by inactive time and group, search, sortable columns and CSV export.
- Propose disabling for one account or many at once, with a reason.
- Users and groups that are never listed, such as administrators.

#### Storage by account
- Storage per account against its quota, with a status filter and CSV export.
- "Where the storage goes" bar: active, protected, inactive and disabled accounts, and free space.

#### Duplicate files
- Scan for the same file stored by several users, with the space the extra copies take.
- Detail page with every copy and its location.
- Clean-up proposals for one file or many: the oldest copy owned by an active account is kept, the other copies
  move to their owners' trash bin, and each of those owners gets a read-only share of the kept copy.

#### Approvals
- Board with three columns: waiting for approval, approved and scheduled, completed.
- Filter the board by accounts or duplicate files, and search by user or file name.
- Separate approvers for inactive accounts and duplicate files, chosen by user or group.
- Approve or dismiss several requests at once.
- "Notify users first" with a waiting period of 1 to 90 days: users get an email and a Nextcloud notification.
  A user who signs in during the waiting period keeps their account. With the notice switched off, approving a
  request carries it out straight away, and the background job retries it if that fails.
- Reminders, processing approved items early, and restoring disabled accounts. Accounts disabled directly in
  Nextcloud can be restored too.

#### Reports and records
- Overview page with the key figures and what needs attention.
- Licences per month over a rolling 12 months, with an optional monthly email to finance (CSV attached).
- PDF overview report and CSV downloads of every list.
- Recent activity record with a retention period you choose.
- `occ nexeno_freeup:capture-snapshot` to record the current month's figures.

#### General
- Background jobs: an hourly job carries out approved requests when their waiting period ends; daily jobs record
  the licence figures, delete records older than the retention period and send the monthly report. When jobs run late, FreeUp catches up the next time an approver opens it.
- Dates, numbers and sizes follow each user's Nextcloud locale, or one locale chosen for the whole app.
- Translations: German (informal and formal), French, Dutch, Italian, Spanish, Swedish, Portuguese (Brazil), Czech,
  Danish, Finnish and Norwegian Bokmål.
- Supports PostgreSQL, MySQL/MariaDB and SQLite.
