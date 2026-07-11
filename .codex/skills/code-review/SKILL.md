---
name: code-review
description: Review PHP code quality, bugs, maintainability, security risks, and architectural consistency.
---

# Code Review Skill

Use this skill when reviewing code changes.

## Checklist

- Syntax correctness
- Runtime errors
- Broken includes/requires
- Undefined variables
- Missing permission checks
- Missing CSRF protection
- SQL injection risks
- XSS risks
- Unsafe file upload handling
- Raw error exposure
- Duplicate logic
- Large mixed-responsibility files
- Backward compatibility
- Database safety
- UI consistency
- Persian RTL compatibility

## Output Format

## Critical Issues
- Issues that can break production, security, data, login, or public pages.

## Important Improvements
- Issues that should be fixed before merge.

## Optional Cleanup
- Refactors that improve maintainability but are not blockers.

## Suggested Patch
- Small, focused patch suggestions.
