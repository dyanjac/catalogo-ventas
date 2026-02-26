# Repository Guidelines

## Project Structure & Module Organization
Keep application code in `src/`, grouped by feature when possible (for example: `src/features/orders/`, `src/features/catalog/`). Shared UI and utilities should live in `src/components/` and `src/lib/`. Static files belong in `public/` (or `assets/` if used by the build). Place tests near implementation files or in a top-level `tests/` folder for cross-module/integration coverage.

Typical layout:
- `src/` core app logic
- `public/` static assets
- `tests/` integration/e2e tests
- `docs/` design notes and architecture decisions

## Build, Test, and Development Commands
Use the package scripts as the source of truth:
- `npm install` (or `pnpm install`): install dependencies
- `npm run dev`: start local development server
- `npm run build`: produce production build artifacts
- `npm run test`: run automated tests
- `npm run lint`: run lint checks
- `npm run format` (if available): apply formatting rules

If this repository uses `pnpm`, prefer `pnpm <script>` consistently.

## Coding Style & Naming Conventions
Use 2-space indentation for JS/TS/JSON/YAML. Prefer TypeScript for new modules when the project supports it. Use:
- `PascalCase` for React components (`OrderCard.tsx`)
- `camelCase` for functions/variables (`calculateTotal`)
- `kebab-case` for non-component file names (`order-service.ts`)

Keep files focused, avoid overly large components, and run lint/format before committing.

## Testing Guidelines
Write unit tests for business logic and integration tests for user flows. Name tests by behavior:
- `*.test.ts` / `*.test.tsx` for unit/component tests
- `*.spec.ts` for broader integration scenarios

Cover new logic and bug fixes with tests. Run `npm run test` locally before opening a PR.

## Commit & Pull Request Guidelines
Follow Conventional Commit style seen in many JS repos:
- `feat: add order subtotal calculation`
- `fix: handle empty cart state`
- `docs: update setup instructions`

PRs should include:
- clear description of change and scope
- linked issue/ticket (if applicable)
- screenshots/video for UI changes
- test notes (`what was tested`)

## Security & Configuration Tips
Never commit secrets. Keep local values in `.env.local` and document required variables in `.env.example`. Validate inputs at API boundaries and avoid logging sensitive customer data.
