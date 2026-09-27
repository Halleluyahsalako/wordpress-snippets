# WordPress snippets

Small, single purpose solutions pulled out of client work. Each one exists because an off the shelf plugin could not do the job and installing a large plugin for one behaviour was the wrong trade.

**These are starting points, not products.** They are documented and configurable rather than plug and play, because each solves a problem whose details differ per site. Read the comment block at the top of each file before using it.

If you want finished plugins rather than snippets, see:

- [Inline Checkout for WooCommerce](https://github.com/Halleluyahsalako/wc-inline-checkout)
- [Resource Library](https://github.com/Halleluyahsalako/wp-resource-library)
- [Cascading Post Filter](https://github.com/Halleluyahsalako/wp-cascading-post-filter)

---

## recurring-event-date

Rolls a recurring event's date forward to its next occurrence once the current one has passed.

Most events plugins handle "every week" and "every month on the 5th". They tend not to handle the rules organisations actually use:

- the last Saturday of every month
- the second Sunday
- the last day of the month if it is a Saturday, otherwise the first Saturday of the following month

The usual workaround is that somebody edits the event by hand every month, forgets, and the homepage advertises a date that has already passed.

This computes the next occurrence and writes it back to the event's own meta fields, so whatever countdown, widget or shortcode already reads those fields keeps working unchanged.

```php
add_filter( 'red_events', function () {
    return array(
        array(
            'post_id'    => 123,
            'rule'       => 'last saturday',
            'time'       => '10:00',
            'date_key'   => 'event_start_date',
            'stamp_keys' => array( '_event_start_date' ),
        ),
    );
} );
```

Rules supported: `last <weekday>`, `first|second|third|fourth <weekday>`, `last day`, and `last day or first <weekday>`. Anything else can be resolved through the `red_resolve_rule` filter.

Two details worth noting. The check is throttled to once an hour, because doing date maths and meta writes on every page load for something that changes monthly is wasteful. And if the computed occurrence is today but already finished, it steps to the next one, so a morning event is not advertised as upcoming all afternoon.

---

## custom-menu-trigger

Points a theme's existing hamburger button at your own menu panel, without editing the theme.

The situation: a client wants a designed full width navigation panel. The theme ships its own hamburger and its own menu, and the theme's menu cannot be made to match the design. Forking the theme means owning its updates forever.

Instead: build the panel you want, hide the theme's menu with CSS, and intercept the theme's hamburger click in the capture phase so you win before the theme's own listener runs.

```php
add_filter( 'cmt_config', function () {
    return array(
        'trigger'     => '.my-theme-menu-opener',
        'hide'        => '.my-theme-nav-panel',
        'panel_id'    => 123,
        'allow_draft' => true,
    );
} );
```

`allow_draft` is the useful part. The panel can live on a page you deliberately keep as a draft, so it never reaches search results or the sitemap, and this still renders it for logged out visitors.

Includes Escape to close, focus returned to the trigger on close, `aria-hidden` and `aria-modal`, body scroll lock, and reduced motion support.

---

## Licence

GPL-2.0-or-later.
