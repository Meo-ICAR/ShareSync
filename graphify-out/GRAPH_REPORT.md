# Graph Report - ShareSync  (2026-10-05)

## Corpus Check
- 155 files · ~73,910 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 20 file(s) not represented in the graph (top: (none) 16, .example 1, .xml 1)

## Summary
- 219 nodes · 260 edges · 28 communities (9 shown, 19 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Users Migration
- Composer Autoload
- NPM Frontend Deps
- Document Models
- User & Config
- Database Seeders
- SharePoint Commands
- Bootstrap App
- Composer Dev Deps
- Composer Scripts
- Composer Config
- App Service Provider
- Feature Tests
- Unit Tests
- MCP Config (mcp.json)
- MCP Config (.mcp.json)
- Base Controller

## God Nodes (most connected - your core abstractions)
1. `User` - 9 edges
2. `require-dev` - 9 edges
3. `scripts` - 9 edges
4. `DocumentType` - 8 edges
5. `Fornitori` - 7 edges
6. `AppServiceProvider` - 5 edges
7. `config` - 5 edges
8. `UserFactory` - 5 edges
9. `ListSharePointTree` - 4 edges
10. `require` - 4 edges

## Surprising Connections (you probably didn't know these)
- `ExampleTest` --inherits--> `TestCase`  [EXTRACTED]
  tests/Feature/ExampleTest.php → tests/TestCase.php

## Import Cycles
- None detected.

## Communities (28 total, 19 thin omitted)

### Community 0 - "Users Migration"
Cohesion: 0.12
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}() (+2 more)

### Community 1 - "Composer Autoload"
Cohesion: 0.08
Nodes (23): autoload, autoload-dev, psr-4, psr-4, description, extra, laravel, keywords (+15 more)

### Community 2 - "NPM Frontend Deps"
Cohesion: 0.10
Nodes (20): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, optionalDependencies, @laravel/multiplex (+12 more)

### Community 3 - "Document Models"
Cohesion: 0.17
Nodes (4): DocumentType, Fornitori, DocumentTypeFactory, FornitoriFactory

### Community 5 - "Database Seeders"
Cohesion: 0.26
Nodes (3): DatabaseSeeder, DocumentTypeSeeder, FornitoriSeeder

### Community 7 - "Bootstrap App"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 8 - "Composer Dev Deps"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+1 more)

### Community 9 - "Composer Scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 10 - "Composer Config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

## Knowledge Gaps
- **54 isolated node(s):** `wsl.exe`, `wsl.exe`, `Controller`, `$schema`, `name` (+49 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 106 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **19 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User & Config` to `Document Models`, `Database Seeders`?**
  _High betweenness centrality (0.030) - this node is a cross-community bridge._
- **What connects `wsl.exe`, `wsl.exe`, `Controller` to the rest of the system?**
  _54 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Users Migration` be split into smaller, more focused modules?**
  _Cohesion score 0.11904761904761904 - nodes in this community are weakly interconnected._
- **Why does `require-dev` connect `Composer Dev Deps` to `Composer Autoload`?**
  _High betweenness centrality (0.015) - this node is a cross-community bridge._
- **Should `Composer Autoload` be split into smaller, more focused modules?**
  _Cohesion score 0.08333333333333333 - nodes in this community are weakly interconnected._
- **Why does `scripts` connect `Composer Scripts` to `Composer Autoload`?**
  _High betweenness centrality (0.015) - this node is a cross-community bridge._
- **Should `NPM Frontend Deps` be split into smaller, more focused modules?**
  _Cohesion score 0.09956709956709957 - nodes in this community are weakly interconnected._