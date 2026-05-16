# Reaction Bar Design Selection

Source exports: `designs/stitch/reaction-bars/`

Goal: use Stitch ideas selectively. The shipped plugin should stay clean, fast, accessible, and useful on WordPress.org. Upcoming workflow extensions can carry richer visual systems, advanced placement, stronger motion, and A/B testing.

## Selection

### Shipped

The shipped plugin should include only two extra designs beyond the current baseline.

Implementation status: `minimalist`, `subtle_text`, `progress_split`, `vertical_rail`, and `clap_counter` are shipped `widget_design` values. The legacy `minimal` and `expressive` values stay available for existing installs.

| Variant | Decision | Why |
| --- | --- | --- |
| `minimalist` | Ship now | Best inline-post fit. Compact, readable, low visual noise, easy to make accessible. |
| `subtle_text_only` | Ship now | Professional and content-first. Good for editorial sites that do not want emoji-heavy UI. |
| `thumbs_progress_bar` | Ship now as `progress_split` | Converts the Stitch progress idea into a multi-reaction percentage/ranking bar. |
| `vertical_sidebar` | Ship now as `vertical_rail` | Uses the vertical engagement rail pattern while keeping the same reaction data model. |
| `clap_counter` | Ship now | Adds a focused single-action celebration pattern powered by the first positive reaction. |

The shipped plugin should not include large card/grid/dashboard-inspired variants. PulsePress should feel production-safe for most blogs and publisher sites.

### Upcoming

These variants remain upcoming variants and are not exposed by the current settings UI.

| Variant | Decision | Why |
| --- | --- | --- |
| `glassmorphic_pills` | Upcoming | Most polished advanced inline treatment without changing the core data model. |

### Skip or archive

| Variant | Decision | Why |
| --- | --- | --- |
| `colorful_grid` | Skip for plugin UI | Too large and loud for an inline post reaction bar. Could inspire a future analytics card, not widget. |
| `floating_cards` | Skip for widget | More dashboard/card layout than reaction bar. Too much vertical footprint. |
| `animated_bar` | Archive | Concept is useful, but exported design includes too much surrounding dashboard. Motion ideas can be reused. |
| `neumorphic_soft` | Archive | Soft tactile idea is usable, but neumorphism has contrast/accessibility risk. |

## Capture UX

Current behavior is directionally right: show the email capture form only after a positive reaction.

Keep this rule:

- Visitor clicks a reaction.
- If the reaction is in `positive_reactions`, show capture.
- If the reaction is negative/neutral, do not interrupt.
- If visitor already submitted capture for the post, do not show again.
- If visitor dismisses, do not immediately reopen during that same render/session.

Refinement:

- The capture form should appear below the reaction bar for standard inline designs.
- For upcoming `vertical_sidebar`, capture should open as a small anchored panel near the sidebar, not below the article body.
- For upcoming A/B tests, capture timing can become a variant: `after_positive_reaction`, `after_second_positive_reaction`, `manual_only`.

Do not show the capture form before a reaction in Free. That would make PulsePress feel like another popup tool instead of a sentiment-first plugin.

## Animation Options

Animation should be a setting, but the default must be restrained.

Research-backed rules:

- Motion must confirm intent, not decorate the article.
- Hover effects must never carry information that tap/mobile users cannot access.
- Selected state must be visible without relying only on color.
- No looping animation in the Free widget.
- Keep interaction timings short: hover/state transitions around 150-200ms; post-click confirmation around 200-300ms.
- `prefers-reduced-motion: reduce` disables transform, pulse, progress, burst, and celebration motion.

### Final interaction model

| Interaction | Shipped effect | Upcoming effect | Notes |
| --- | --- | --- | --- |
| Hover / focus | Border/background/text-color shift only. Focus keeps the visible ring. | Same baseline; upcoming designs can add shadow depth if it does not move layout. | Hover is pointer-only, so it cannot reveal required text. |
| Press / tap down | `subtle`: small scale. `spring`: slightly stronger short scale. `none`: no transform. | Upcoming variants can use stronger tactile movement per variant. | Press feedback must finish quickly and cannot block the saved state. |
| Selected state | Persistent active fill, underline, or inset indicator depending on design. | Variant-specific indicator: ring, progress fill, anchored active chip. | This is the actual state, not a temporary animation. |
| After positive reaction | Show capture form below the inline bar. No extra animation in the shipped widget. | Optional celebration or anchored capture panel. | Capture timing stays tied to positive reactions. |
| After negative/neutral reaction | Save state only. No capture and no celebration. | Same, unless an upcoming experiment explicitly tests another flow. | Avoid making negative feedback feel punished or loud. |

### Shipped animation modes

| Mode | Availability | Behavior |
| --- | --- | --- |
| `none` | Shipped | No transform animation. Still keeps hover, focus, and selected state changes. |
| `subtle` | Shipped default | Small press feedback plus color/border selected state. No decorative effects. |
| `spring` | Shipped | Slightly more tactile press feedback using a short spring-like easing. No loop or celebration. |

### Upcoming animation modes

| Mode | Availability | Behavior |
| --- | --- | --- |
| `pulse` | Upcoming | Active reaction gets a short one-shot pulse/ring after selection. |
| `progress` | Upcoming | Progress-bar or ratio animation for variants like `thumbs_progress_bar`. |
| `celebration` | Upcoming | Small contained burst after positive reactions only. Must never fire for negative/neutral reactions. |

All animation modes must honor `prefers-reduced-motion: reduce`.

## Shipped Implementation

Completed:

1. Added shipped design choices:
   - `minimalist`
   - `subtle_text`
   - `progress_split`
   - `vertical_rail`
   - `clap_counter`
2. Kept existing `minimal` and `expressive` aliases for backward compatibility.
3. Added `animation_mode` setting:
   - shipped choices: `none`, `subtle`, `spring`
   - Default: `subtle`
4. Added widget data:
   - `animationMode`
5. Updated CSS:
   - `data-design="minimalist"`
   - `data-design="subtle_text"`
   - `data-design="progress_split"`
   - `data-design="vertical_rail"`
   - `data-design="clap_counter"`
   - `data-animation="none|subtle|spring"`
6. Updated admin preview to match frontend.

## Upcoming Implementation Notes

Reserve upcoming design keys but do not expose them in the current settings UI:

   - `glassmorphic_pills`

Future extensions can add choices through a filter in a later contract change if needed:

   - add `pulsepress_widget_design_choices`
   - add `pulsepress_animation_mode_choices`

## QA Requirements

- Keyboard navigation works for every design.
- Active state is visible without color alone.
- Counts do not cause layout shift.
- Capture form appears after positive reaction only.
- Reduced motion disables transform/ring/celebration effects.
- Admin live preview matches frontend render.
