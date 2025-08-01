# Payable Package - Claude Instructions

## Branch Structure

This package maintains two active versions:

-   **Version 2**: Built from the `main` branch (current development)
-   **Version 1**: Built from the `v1` branch (legacy support)

## GitHub Issue Resolution

When resolving GitHub issues:

1. **Check the issue context** - Determine if the issue affects:

    - Only v2 (main branch)
    - Only v1 (v1 branch)
    - Both versions

2. **For issues affecting both versions:**

    - Create separate branches from each base branch
    - Apply fixes to both versions
    - Create separate pull requests for each branch
    - Ensure compatibility with each version's codebase

3. **Branch naming convention:**
    - For v2: `feature/issue-{id}-{slug}` from `main`
    - For v1: `feature/v1-issue-{id}-{slug}` from `v1`

## Development Guidelines

-   Always check which version(s) an issue targets before starting work
-   Consider backward compatibility when making changes to v1
-   Test changes in the appropriate version context
-   Document any version-specific considerations in pull requests
