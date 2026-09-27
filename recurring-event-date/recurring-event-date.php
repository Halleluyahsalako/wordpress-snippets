<?php
/**
 * Plugin Name:       Recurring Event Date
 * Description:       Rolls a recurring event's date forward to its next occurrence once the current one has passed, so countdowns and "next event" displays stay correct without anyone editing the post.
 * Version:           1.1.0
 * Requires PHP:      7.4
 * Author:            Salako Halleluyah
 * License:           GPL-2.0-or-later
 * Text Domain:       recurring-event-date
 *
 * @package Recurring_Event_Date
 *
 * ---------------------------------------------------------------------------
 * THE PROBLEM
 *
 * Most WordPress events plugins handle "every week" and "every month on the
 * 5th". They tend not to handle the rules real organisations actually use:
 *
 *   "the last Saturday of every month"
 *   "the second Sunday"
 *   "the last day of the month if it is a Saturday, otherwise the first
 *    Saturday of the following month"
 *
 * The usual workaround is that somebody edits the event by hand every month,
 * and forgets, and the homepage advertises a date that has already passed.
 *
 * This computes the next occurrence from a rule and writes it back to the
 * event's own meta fields, so whatever widget, countdown or shortcode already
 * reads those fields keeps working with no changes.
 *
 * ---------------------------------------------------------------------------
 * CONFIGURING IT
 *
 * Events and their meta keys are declared through a filter, because every
 * events plugin stores dates differently. Put something like this in your
 * theme's functions.php or a site specific plugin:
 *
 *   add_filter( 'red_events', function () {
 *       return array(
 *           array(
 *               'post_id'    => 123,                        // your event post ID
 *               'rule'       => 'last saturday',
 *               'time'       => '10:00',
 *               'date_key'   => 'event_start_date',      // stored as d-m-Y
 *               'stamp_keys' => array( '_event_start_date', 'event_start_ts' ),
 *           ),
 *       );
 *   } );
 *
 * Supported rules:
 *
 *   'last saturday'    'last friday'    'last <weekday>'
 *   'first monday'     'second sunday'  'third tuesday'  'fourth <weekday>'
 *   'last day'                          last calendar day of the month
 *   'last day or first <weekday>'       last day if it falls on that weekday,
 *                                       otherwise the first such weekday next
 *                                       month
 *
 * Find the meta keys your events plugin uses by looking at the postmeta rows
 * for one event before you configure anything.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'red_roll_event_dates' );

/**
 * Check each configured event and roll it forward if its date has passed.
 */
function red_roll_event_dates() {
	$events = apply_filters( 'red_events', array() );

	if ( empty( $events ) || ! is_array( $events ) ) {
		return;
	}

	// Running this on every request would mean a date calculation and possibly
	// several meta writes on every page load. Once an hour is ample for
	// something that changes monthly.
	if ( get_transient( 'red_checked' ) ) {
		return;
	}
	set_transient( 'red_checked', 1, HOUR_IN_SECONDS );

	foreach ( $events as $event ) {
		red_roll_single_event( $event );
	}
}

/**
 * @param array $event Configuration for one event.
 */
function red_roll_single_event( array $event ) {
	$post_id  = isset( $event['post_id'] ) ? absint( $event['post_id'] ) : 0;
	$rule     = isset( $event['rule'] ) ? strtolower( trim( $event['rule'] ) ) : '';
	$date_key = isset( $event['date_key'] ) ? $event['date_key'] : '';

	if ( ! $post_id || ! $rule || ! $date_key || ! get_post( $post_id ) ) {
		return;
	}

	$format   = isset( $event['format'] ) ? $event['format'] : 'd-m-Y';
	$time     = isset( $event['time'] ) ? $event['time'] : '00:00';
	$timezone = wp_timezone();

	$current = get_post_meta( $post_id, $date_key, true );

	if ( ! $current ) {
		return;
	}

	$current_date = DateTime::createFromFormat( $format, $current, $timezone );

	if ( ! $current_date ) {
		return;
	}

	list( $hour, $minute ) = array_pad( explode( ':', $time ), 2, 0 );
	$current_date->setTime( (int) $hour, (int) $minute, 0 );

	$now = new DateTime( 'now', $timezone );

	// Still in the future, nothing to do.
	if ( $current_date > $now ) {
		return;
	}

	$next = red_next_occurrence( $rule, $now, $timezone );

	if ( ! $next ) {
		return;
	}

	$next->setTime( (int) $hour, (int) $minute, 0 );

	// If the computed occurrence is today but already past, step to the one
	// after it. Otherwise an event that finished this morning would be
	// advertised as upcoming for the rest of the day.
	if ( $next <= $now ) {
		$probe = clone $next;
		$probe->modify( 'first day of next month' );
		$next  = red_next_occurrence( $rule, $probe, $timezone ) ?: $next;
		$next->setTime( (int) $hour, (int) $minute, 0 );
	}

	update_post_meta( $post_id, $date_key, $next->format( $format ) );

	foreach ( (array) ( $event['stamp_keys'] ?? array() ) as $key ) {
		update_post_meta( $post_id, $key, $next->getTimestamp() );
	}

	/**
	 * Fires after an event has been rolled forward.
	 *
	 * @param int      $post_id Event post ID.
	 * @param DateTime $next    The new date.
	 */
	do_action( 'red_event_rolled', $post_id, $next );
}

/**
 * Resolve a recurrence rule to the next date on or after $from.
 *
 * @param string       $rule     Recurrence rule.
 * @param DateTime     $from     Reference point.
 * @param DateTimeZone $timezone Site timezone.
 * @return DateTime|null
 */
function red_next_occurrence( $rule, DateTime $from, DateTimeZone $timezone ) {
	$weekdays = 'monday|tuesday|wednesday|thursday|friday|saturday|sunday';

	// "last day or first saturday": the last day of the month when it happens
	// to be that weekday, otherwise the first such weekday next month.
	if ( preg_match( '/^last day or first (' . $weekdays . ')$/', $rule, $m ) ) {
		$last_day = new DateTime( 'last day of this month', $timezone );

		if ( strtolower( $last_day->format( 'l' ) ) === $m[1] && $last_day >= $from ) {
			return $last_day;
		}

		return new DateTime( 'first ' . $m[1] . ' of next month', $timezone );
	}

	if ( 'last day' === $rule ) {
		$candidate = new DateTime( 'last day of this month', $timezone );

		return $candidate >= $from
			? $candidate
			: new DateTime( 'last day of next month', $timezone );
	}

	if ( preg_match( '/^last (' . $weekdays . ')$/', $rule, $m ) ) {
		$candidate = new DateTime( 'last ' . $m[1] . ' of this month', $timezone );

		return $candidate >= $from
			? $candidate
			: new DateTime( 'last ' . $m[1] . ' of next month', $timezone );
	}

	if ( preg_match( '/^(first|second|third|fourth) (' . $weekdays . ')$/', $rule, $m ) ) {
		$candidate = new DateTime( $m[1] . ' ' . $m[2] . ' of this month', $timezone );

		return $candidate >= $from
			? $candidate
			: new DateTime( $m[1] . ' ' . $m[2] . ' of next month', $timezone );
	}

	/**
	 * Resolve a rule this function does not recognise.
	 *
	 * @param DateTime|null $date     Null by default.
	 * @param string        $rule     The unrecognised rule.
	 * @param DateTime      $from     Reference point.
	 * @param DateTimeZone  $timezone Site timezone.
	 */
	return apply_filters( 'red_resolve_rule', null, $rule, $from, $timezone );
}
