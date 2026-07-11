---
name: deployment
description: Prepare deployment, environment checks, server setup, backup, rollback, and production validation.
---

# Deployment Skill

## Checklist

- Confirm branch
- Confirm changed files
- Confirm environment variables
- Confirm PHP version
- Confirm database backup
- Confirm file backup
- Confirm migration plan
- Confirm seed plan
- Confirm rollback plan
- Confirm permissions
- Confirm scheduled jobs
- Confirm cache/session implications
- Confirm health check
- Confirm logs after deployment

## Rule

Never deploy blindly.

Always define:

- What changes
- What backup exists
- How to rollback
- How to verify
- What manual checks are required

## Production Safety

- Do not run destructive SQL.
- Do not overwrite uploads.
- Do not overwrite config with secrets.
- Do not enable debug output.
- Do not expose raw errors.
