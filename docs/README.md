# Documentation

Merchant Panel is an e-commerce backend with a merchant panel, built without a framework.
These pages explain how it works, why it is built this way, and how to run it. Code is linked
by path rather than copied, so every claim can be checked against the source.

## Reading order

1. [Overview](overview.md): what the system does, its parts, and the technology.
2. Architecture: how the parts fit together.
   - [System](architecture/system.md): containers and how they communicate.
   - [Backend](architecture/backend.md): features, layers, dependency injection, HTTP, and
     messaging inside the PHP application.
   - [Frontend](architecture/frontend.md): the React panel, its session handling, and its
     typed API client.
   - [Request lifecycle](architecture/request-lifecycle.md): one request followed from the
     browser to the database and back.
3. Flows: what happens, step by step, in each business process.
   - [Registration and email confirmation](flows/registration.md)
   - [Authentication and access](flows/authentication.md)
   - [Cart, checkout, and order expiry](flows/checkout.md)
   - [Payments and webhooks](flows/payments.md)
   - [Merchant panel and reports](flows/panel.md)
   - [Replacing legacy code](flows/legacy.md)
4. Decisions (planned): one architecture decision record per choice, such as session cookies
   over JWT or the transactional outbox.
5. Operations (planned): local development, testing, CI/CD, production, observability, and
   runbooks.
6. Security, known limitations, roadmap, and glossary (planned).

[Coding patterns](patterns.md) collects the rules the code follows for recurring problems:
API actions, sessions, idempotency, locking, messaging, caching, and more. Read the matching
section before changing code in that area.

## Conventions

- Pages describe the code as it is. A change to the code updates the page it affects in the
  same commit.
- Each page starts with what the part does and why, then shows a diagram, then walks through
  the code with links, and ends with its limitations.
- Diagrams use [Mermaid](https://mermaid.js.org), which GitHub renders.
