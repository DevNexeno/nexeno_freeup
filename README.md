<!--
  - SPDX-FileCopyrightText: 2026 Nexeno contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Nexeno FreeUp

[![License: AGPL-3.0-or-later](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue.svg)](COPYING)
![Nextcloud 31–35](https://img.shields.io/badge/Nextcloud-31%E2%80%9335-0082c9.svg)

**♻️ Free up licences and storage in Nextcloud, safely and with approval.**

Nexeno FreeUp finds accounts nobody uses any more and files that exist many times across your organisation.
Your team proposes a clean-up, an approver says yes, users are notified, and only then does FreeUp act. It
disables accounts instead of deleting them, and moves extra copies of a file to the trash. Nothing is lost, and
everything can be restored.

It is built for organisations with a lot of turnover, such as universities, schools, public bodies and companies,
where accounts of graduates and former staff keep using paid licences and storage.

![Overview: licences in use, inactive accounts, storage held by inactive accounts and space taken by duplicate files](https://nexeno.io/assets/nexeno_freeup/overview.png)
![Inactive accounts: accounts with no login for the chosen period, ready to propose for disabling](https://nexeno.io/assets/nexeno_freeup/inactive-accounts.png)
![Approvals: requests moving from proposed to approved to completed](https://nexeno.io/assets/nexeno_freeup/approvals.png)
![Duplicate files: identical files stored by several users and the space a clean-up would free](https://nexeno.io/assets/nexeno_freeup/duplicate-files.png)
![Storage by account: storage per account against its quota, by status](https://nexeno.io/assets/nexeno_freeup/storage-by-account.png)

## Features

- **🔍 Inactive accounts:** lists every account with no login for a set time (4 to 24 months, 6 by default).
  Accounts that never logged in count from the day they were created.
- **💾 Storage by account:** shows who uses how much storage, against their quota, and how much is held by inactive
  or disabled accounts.
- **🗂️ Duplicate files:** finds the same file stored by several users and how much space the extra copies take.
- **✅ Approval workflow:** nothing happens without approval. Requests are proposed, approved by the users you
  choose, and then carried out by FreeUp. Filter the board by accounts or duplicate files, or search by name.
- **🔔 Users are notified first:** users get an email and a Nextcloud notification and have a waiting period
  (14 days by default). Signing in during that time keeps the account; owners of duplicate files can delete their
  own copy first.
- **↩️ Everything can be restored:** a disabled account keeps all its files and can be enabled again with one
  click. Replaced copies sit in the owner's trash bin.
- **📊 Licences per month:** licences in use and logins for each month over a rolling 12 months, with an optional
  monthly email to finance.
- **📄 Reports and exports:** a PDF overview report and CSV downloads of every list.
- **🕑 Recent activity:** a record of every proposal, decision and action, kept for a period you choose.
- **🌍 Your language and locale:** follows each user's Nextcloud language and locale. Translated into German
  (informal and formal), French, Dutch, Italian, Spanish, Swedish, Portuguese (Brazil), Czech, Danish, Finnish and
  Norwegian Bokmål.

## How it works

### Inactive accounts

1. **Find.** FreeUp lists the accounts with no login for longer than the *Inactive after* setting.
2. **Propose.** A user who may propose selects accounts and gives a reason.
3. **Approve.** An approver approves or keeps each account. Approvers get a Nextcloud notification for every new request.
4. **Notify.** With *Notify users first* on, each user gets an email and a notification and has the waiting period
   to sign in. If they sign in, the request is cancelled.
5. **Disable.** When the waiting period ends, FreeUp disables the account and its licence is free again. The files stay.
   With *Notify users first* off, this happens straight after approval.
6. **Restore.** An administrator can enable the account again at any time.

### Duplicate files

FreeUp keeps **one** copy of a duplicated file, the oldest copy owned by an active account. After approval and
the waiting period (or straight after approval when *Notify users first* is off), every other copy moves to its owner's trash bin. Each of those owners gets a read-only share of
the copy that was kept, so everyone still has access to the file.

## Requirements

- Nextcloud **31, 32, 33, 34 or 35**
- PostgreSQL, MySQL/MariaDB or SQLite
- **System cron** for Nextcloud background jobs, as Nextcloud recommends
- The **Deleted files** (trash bin) app, for duplicate clean-ups. FreeUp never deletes a file permanently.
- An email server in Nextcloud (optional). Without one, users are notified in Nextcloud only.

FreeUp works through Nextcloud background jobs:

| Job | How often | What it does |
| --- | --- | --- |
| Disable when due | every hour | Disables accounts and replaces duplicate copies whose waiting period has ended |
| Daily maintenance | every day | Records the month's licence figures, deletes records older than the retention period, and carries out any approved requests that are due |
| Monthly licence report | checks every day | Sends the finance email once, at the start of each month |

If background jobs run late, FreeUp catches up the next time an approver opens the app.

## Installation

**From the App Store:** in Nextcloud, open **Apps**, search for *Nexeno FreeUp* and click **Download and enable**.

**From a release package:** extract `nexeno_freeup.tar.gz` into your Nextcloud `apps/` (or `custom_apps/`) folder, then run:

```bash
sudo -u www-data php occ app:enable nexeno_freeup
```

Then open **Nexeno FreeUp** from the app menu and check **Settings** in the app.

## Configuration

Administrators set everything on the app's **Settings** page:

| Setting | What it controls |
| --- | --- |
| Inactive after | How long without login before an account counts as inactive (4, 6, 8, 12 or 24 months) |
| Never list | Users and groups that are never listed, for example administrators or management |
| Approvals | The users and groups who may approve inactive accounts, and separately duplicate clean-ups |
| Notify users first · Waiting period | Whether users are notified before anything happens, and how long FreeUp waits (1 to 90 days) |
| Monthly licence report | Sends the licence figures to a finance address every month |
| Keep activity history for | How long the activity record is kept before it is deleted |
| Locale for Nexeno FreeUp | Use each user's Nextcloud locale (recommended) or one locale for the whole app |

Administrators can always propose, approve and restore.

## Privacy

FreeUp only reads what Nextcloud already knows: logins, storage, groups and file checksums. It stores the
requests, a monthly count of licences (12 months only) and the activity record, which is deleted after the period
you set. The PDF report and the finance email contain counts only, with no names or file names.

## Development

You need Node.js, Composer and a Nextcloud server. Clone the app into the server's `apps/` or `custom_apps/` folder, then:

```bash
make install-deps   # npm ci + composer install
make build          # build the front end into js/ and css/ (not in git, so build after every clone or pull)
make watch          # rebuild on every change
make lint           # ESLint, PHP syntax, code style and Psalm
make cs-fix         # fix PHP code style
make test-e2e       # Playwright tests
make appstore       # build build/artifacts/appstore/nexeno_freeup.tar.gz
```

The Playwright tests need a running Nextcloud with the app enabled. Install the browser once with
`npx playwright install chromium`, then pass the server address and an admin login:

```bash
NC_BASE_URL=http://localhost:8080 NEXTCLOUD_ADMIN_PASSWORD=your-admin-password make test-e2e
```

`NC_BASE_URL` defaults to `http://localhost:8080` and `NEXTCLOUD_ADMIN_USER` to `admin`.

Run `make help` to list every command.

## Reporting issues

Please report bugs and ideas in the [issue tracker](https://github.com/Nexeno-HQ/nexeno_freeup/issues). For a
bug, include your Nextcloud version, your database and the steps to reproduce it.

## Licence

Nexeno FreeUp is free software, licensed under the [GNU Affero General Public License v3.0 or later](COPYING).
© 2026 Nexeno contributors. See [CHANGELOG.md](CHANGELOG.md) for the release history.
