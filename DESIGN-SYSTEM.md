# Lightly Salted Design System

## Overview
This document describes the visual system for the Lightly Salted theme. It maps brand tokens to the theme styles and outlines the reusable layout patterns used across page templates and components.

## Tokens
Tokens live in `assets/styles/_variables.scss` and are available as CSS custom properties.

### Colors
- Base: `--ink-900`, `--white`, `--surface-*`
- Accents: `--blue-700`, `--blue-500`, `--green-500`
- Semantic: `--color-accent`, `--color-background`, `--color-border`, `--color-text`

### Typography
- Headings: `--font-heading`
- Body: `--font-body`
- Sizes: `--font-size-body`, `--font-size-body-small`, `--font-size-small`, `--font-size-lede`

### Spacing & Layout
- Spacing scale: `--space-xs` through `--space-4xl`
- Layout: `--container-max`, `--content-max-width`, `--component-spacing`

### Radius & Shadows
- Radius: `--radius-sm` through `--radius-xl`, `--radius-pill`
- Shadows: `--shadow-sm` through `--shadow-xl`

## Layout Patterns
Use the following layout patterns to keep page templates consistent.

### Page Hero
Use `.page-hero` + `.page-hero__inner` for two-column hero layouts with optional breadcrumb and media.

### Sections
Use `.section` for consistent vertical rhythm. Optional variants:
- `.section--muted` for soft background
- `.section--contrast` for alternate surface

### Cards & Grids
- `.card-grid` for multi-column layouts
- `.card` for contained content blocks

### Steps & Icon Lists
- `.steps` + `.step` for ordered sequences
- `.icon-list` + `.icon-list__item` for paired icon + text

### Metrics
- `.metrics-grid` + `.metric` for stat blocks

### CTA Panels
- `.cta-panel` for full-width call-to-action blocks

## Component Styling Notes
- Components should use `--color-*` tokens and avoid hard-coded colors.
- For buttons, prefer `.button`, `.button--outlined`, and `.button--text`.
- Use `.pill` for filters, tags, and metadata chips.

## ACF Integration
Page templates map ACF fields directly to these patterns. Keep labels, intro text, and CTA fields near their associated sections to ensure consistent layout and spacing.
