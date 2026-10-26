# app/ — Nuxt 4 PWA (Vaud Ride)

Complète le `CLAUDE.md` racine ; règles propres au front.

- Gestionnaire de paquets : **bun** (`bun run dev|build|lint|typecheck|test`). TypeScript 5.9 épinglé (typescript-eslint ne supporte pas encore TS 7).
- TypeScript strict, ESLint (`@nuxt/eslint`) + Prettier (sans point-virgule, guillemets simples).
- Tests Vitest : `tests/` et `app/lib/**/*.test.ts`. La physique et le BLE (via le simulateur) doivent être testés.
- Toute la couche BLE passe par l'interface `app/lib/ble/transport.ts` ; ne jamais appeler `navigator.bluetooth` ailleurs.
- UI en français ; chaque texte passe par `$t()` avec clés dans `i18n/locales/{fr,de,en}.json`.
- Mode sans matériel : `?sim=1`.
- Attribution « © swisstopo » visible dans la scène 3D.
