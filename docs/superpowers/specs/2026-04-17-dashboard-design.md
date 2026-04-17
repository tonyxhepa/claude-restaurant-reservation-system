# Dashboard Redesign — Rich Data Display

**Date:** 2026-04-17  
**Status:** Approved

## Overview

Redesign the restaurant dashboard to display rich operational data while maintaining a clean, professional "Modern Minimal" aesthetic.

## Design Direction

**Style:** Modern Minimal  
- Clean lines, subtle shadows, generous whitespace
- Neutral zinc palette with strategic color accents for data visualization
- Professional and focused, optimized for quick data scanning

## Layout Structure

### Row 1: Key Metrics (4 Cards)
| Card | Primary Value | Secondary Info |
|------|---------------|----------------|
| Today's Total | Total reservation count | Comparison to yesterday |
| Confirmed | Confirmed count | Conversion percentage |
| Guests Expected | Total guest count | Average party size |
| Capacity | Utilization % | Progress bar visualization |

### Row 2: Weekly Overview + Quick Stats
**Left (2/3 width):** Week Preview
- Horizontal bar chart showing reservation counts for Mon-Sun
- Current day highlighted with primary color
- Weekend emphasized with accent colors

**Right (1/3 width):** Quick Stats Panel
- Average lead time (days)
- No-show rate (%)
- Repeat guest rate (%)

### Row 3: Today's Reservations Table
- Columns: Time, Guest Name, Party Size, Table, Status
- Status badges: Confirmed (green), Pending (amber), Cancelled (red), Completed (gray)
- Zebra striping for readability

## Component Specifications

### Stat Cards
- Border radius: 10px
- Padding: 16px
- Shadow: `0 1px 3px rgba(0,0,0,0.06)`
- Label: 11px uppercase, zinc-500, letter-spacing 0.5px
- Value: 28px, font-weight 700, slate-900
- Sub-value: 12px, muted color

### Week Preview Bars
- Height: consistent per day
- Background: zinc-100 (inactive), primary/accent (active)
- Day label: 10px, zinc-500

### Table
- Header: 11px uppercase, zinc-500, bottom border
- Rows: zebra striping with zinc-50
- Hover state: subtle highlight

## Technical Implementation

- **Framework:** Livewire 4 with Flux UI components
- **Styling:** Tailwind CSS v4
- **Data:** Computed properties from existing reservation data
- **Dark mode:** Support via zinc palette variants

## Files to Modify

- `resources/views/pages/⚡dashboard.blade.php` — Main dashboard component
