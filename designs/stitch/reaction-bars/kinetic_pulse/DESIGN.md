---
name: Kinetic Pulse
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#424754'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#727785'
  outline-variant: '#c2c6d6'
  surface-tint: '#005ac2'
  primary: '#0058be'
  on-primary: '#ffffff'
  primary-container: '#2170e4'
  on-primary-container: '#fefcff'
  inverse-primary: '#adc6ff'
  secondary: '#006c49'
  on-secondary: '#ffffff'
  secondary-container: '#6cf8bb'
  on-secondary-container: '#00714d'
  tertiary: '#b90538'
  on-tertiary: '#ffffff'
  tertiary-container: '#dc2c4f'
  on-tertiary-container: '#fffbff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d8e2ff'
  primary-fixed-dim: '#adc6ff'
  on-primary-fixed: '#001a42'
  on-primary-fixed-variant: '#004395'
  secondary-fixed: '#6ffbbe'
  secondary-fixed-dim: '#4edea3'
  on-secondary-fixed: '#002113'
  on-secondary-fixed-variant: '#005236'
  tertiary-fixed: '#ffdadb'
  tertiary-fixed-dim: '#ffb2b7'
  on-tertiary-fixed: '#40000d'
  on-tertiary-fixed-variant: '#92002a'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 14px
    letterSpacing: 0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  unit: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 40px
  container-max: 1200px
  gutter: 24px
  margin-mobile: 16px
---

## Brand & Style
The design system focuses on "user traction"—creating a feedback loop that feels rewarding, responsive, and tactile. The brand personality is professional yet energetic, bridging the gap between a dependable publishing platform and a modern social interaction layer.

The aesthetic utilizes **Modern Minimalism with Tactile Feedback**. It leverages heavy whitespace and a refined typography scale to ensure content remains the hero, while interactive elements use springy animations and subtle depth to invite engagement. The goal is to make every "reaction" feel significant through micro-interactions that mimic physical momentum.

## Colors
The palette is rooted in a "WordPress-native" utility but boosted with higher saturation for digital vibrancy. 

- **Primary (Vibrant Blue):** Reserved for primary actions, active states, and focus indicators.
- **Success (Emerald):** Used for positive affirmations, "upvotes," or completion states.
- **Heart/Love (Rose):** Dedicated specifically to emotional connection points and "like" reactions.
- **Warning/Shock (Amber):** Utilized for high-energy reactions (e.g., "Wow") and cautionary UI hints.
- **Neutrals:** A range of Cool Grays (Slate) provides the structural foundation, ensuring the vibrant reaction colors pop against the background.

## Typography
This design system uses **Inter** for its exceptional legibility and neutral character, allowing the emoji and iconography of reactions to take center stage. 

Headlines use tighter letter spacing and heavier weights to feel "anchored." Body text maintains a generous line height to ensure readability within long-form WordPress posts. Labels are slightly tracked out to distinguish them from prose, providing a clear hierarchical distinction for metadata like reaction counts and timestamps.

## Layout & Spacing
The layout follows a **Fluid Grid** model with a soft 4px baseline rhythm. For post reaction components, spacing is tight and grouped (8px–12px) to signify a singular interactive unit.

- **Desktop:** 12-column grid with 24px gutters. Content is centered with a max-width of 1200px for readability.
- **Mobile:** Single column with 16px side margins. Interactive elements (like reaction bars) should span the full width or be pinned to a floating container for easy thumb access.
- **Reaction Groups:** Elements should use `gap: 8px` for desktop and `gap: 12px` for mobile to accommodate larger touch targets.

## Elevation & Depth
To achieve a modern, elevated feel, the design system uses **Ambient Shadows** and **Tonal Layers**. 

Depth is communicated through three levels:
1. **Base:** Surface color (White or Deep Slate).
2. **Raised:** Subtle 1px border (#E2E8F0 in light / #334155 in dark) with a soft, 4px blur shadow. Used for buttons and unselected chips.
3. **Floating:** Larger 12px blur shadow with 10% opacity. This is triggered during hover states or when a reaction popover is active.

Shadows should be slightly tinted with the Primary color (#3B82F6) at 5% opacity to maintain a cohesive atmospheric glow.

## Shapes
The shape language is friendly and approachable, utilizing a **Rounded** (Level 2) logic. 

- **Standard Elements:** Buttons, input fields, and reaction chips use an 8px (0.5rem) radius.
- **Large Elements:** Cards and reaction containers use a 16px (1rem) radius.
- **Interactive Triggers:** Small chips and "pills" for reaction counts use a full circle/pill radius to emphasize their "clickable" nature.

## Components

### Reaction Chips
Individual reaction units. When inactive, they feature a subtle gray border and transparent background. On "hover," they scale up by 5% with a springy transition. On "active," they fill with a 10% opacity version of their respective semantic color (e.g., Rose for Heart) and a 1px solid border.

### Floating Reaction Bar
A horizontal container that appears above or below content. It uses a high elevation (Floating) and a 16px radius. The background should be slightly translucent (90% opacity) with a backdrop blur of 8px for a glassmorphic touch.

### Primary Buttons
Large, 8px rounded corners. Uses the Primary Blue with a subtle gradient (top-to-bottom, 5% shift). Text is bold and white.

### Success/Warning Alerts
Minimalist banners using 1px borders and matching 5% opacity backgrounds. Icons should be used to ensure accessibility for color-blind users.

### Input Fields
Clean, 8px rounded borders with a 2px blue focus ring. Labels sit above the field in `label-sm` style.

### Animation Logic
All interactive components must use a `cubic-bezier(0.34, 1.56, 0.64, 1)` transition for "scaling" to create the requested "springy" effect. Color fades should remain smooth at `200ms ease-out`.