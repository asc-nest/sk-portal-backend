# Backend Instructions

Paths are relative to this backend directory. Also follow the root instructions.

## Detected Stack

- Laravel dependency range: ^11.0
- PHP requirement: ^8.2
- Test dependencies: PHPUnit ^10.5
- Database/connection: UNKNOWN: confirm with developer (expected preset: MySQL).

## Laravel Conventions

- Follow the project's existing structure; inspect related code first.
- Use migrations for every schema change; consider rollback and existing data.
- Use Form Requests for input validation.
- Authorize every endpoint explicitly; document intentionally public endpoints.
- Use transactions for multi-step writes that must succeed or fail together.
- Use row-level locking and idempotency where duplicate requests are possible.
- Add or update tests for behavior changes, including authorization and validation.
- Preserve API envelopes, status codes, pagination, and downloads unless the task changes them.
- Update the root `docs/api-contract.md` when API behavior changes.
- Use isolated test data and fake mail, queues, and external side effects as needed.
- Never run destructive database operations against real data without authorization.

## Verification

- Inspect composer.json and the existing test setup for supported commands.
- Test command and isolated database setup: UNKNOWN: confirm with developer.
- Report tests run, failures, and any checks that could not be performed.
