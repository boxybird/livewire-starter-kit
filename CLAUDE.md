# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## About This Project

This is a **Laravel starter kit optimized for agentic coding tools** like Claude Code, OpenCode, and other AI assistants. It's a fork of the Laravel framework with conventions, guardrails, and tooling designed to help AI assistants work effectively.

**Read AGENTS.md first** - it contains comprehensive guidelines for Laravel conventions, code style, testing requirements, and Laravel Boost MCP integration.

## Quick Reference

```bash
# Development
composer run dev                    # Start server, queue, logs, and Vite

# Testing
php artisan test --compact          # Run all tests
php artisan test --filter=testName  # Run specific test

# Code Quality
vendor/bin/pint --dirty             # Format changed files
vendor/bin/phpstan analyse          # Static analysis (Larastan)
vendor/bin/rector process --dry-run # Preview Rector changes
vendor/bin/rector process           # Apply Rector changes

# Task Tracking (Beads)
bd ready                            # Show tasks ready to work on
bd create "Task title"              # Create a new task
bd show <id>                        # View task details
bd close <id>                       # Mark task complete
bd dep add <child> <parent>         # Add dependency between tasks
```

## Key Architecture Decisions

- **Laravel 12 structure**: `bootstrap/app.php` is the config hub (middleware, exceptions, routing). No Kernel.php files.
- **Controllers**: Public methods only - enforced by the custom "cruddy" architecture preset
- **Tests**: Use Pest syntax. Architecture tests in `tests/Architecture/` are guardrails.

## Code Quality Tools

- **Pint**: Laravel's code formatter (PSR-12 + Laravel style)
- **Larastan**: Static analysis at level 5 (`phpstan.neon`)
- **Rector**: Automated refactoring for PHP 8.4, code quality, dead code removal, type declarations (`rector.php`)

## AI-Specific Design

- **AGENTS.md**: Primary guidelines covering PHP conventions, Laravel patterns, MCP tools (Laravel Boost + Playwright), and frontend validation workflows
- **Architecture tests**: Run `./vendor/bin/pest --filter=arch` after implementing code - violations provide detailed fix instructions
- **Beads**: Git-backed task tracking for AI agents (`.beads/` directory)

## MCP Servers

This project uses two MCP servers for enhanced AI assistance:

- **Laravel Boost**: `search-docs`, `tinker`, `database-query`, `get-absolute-url`, `browser-logs`
- **Playwright**: `browser_navigate`, `browser_snapshot`, `browser_take_screenshot`, `browser_fill_form`, `browser_console_messages`

See AGENTS.md for detailed usage guidance on both.
