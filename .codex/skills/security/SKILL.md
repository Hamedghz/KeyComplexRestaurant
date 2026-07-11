---
name: security
description: Review authentication, authorization, input validation, file upload safety, secrets, and secure error handling.
---

# Security Skill

## Severity Groups

## Critical
- Authentication bypass
- Authorization bypass
- SQL injection
- Arbitrary file upload execution
- Secret exposure
- Destructive unauthenticated action

## High
- Missing CSRF on write actions
- IDOR
- Raw SQL/database errors exposed
- Unsafe dynamic SQL fields
- Stored XSS
- Missing permission checks on admin actions

## Medium
- Weak validation
- Missing pagination
- Unsafe file type validation
- Excessive logging
- Inconsistent error handling

## Low
- Minor UI leakage
- Inconsistent messages
- Missing audit context

## Review Checklist

- Authentication required
- Permission checked
- CSRF protection
- SQL injection protection
- XSS protection
- File upload validation
- Secret handling
- Safe logs
- Safe errors
- Rate limits where relevant
