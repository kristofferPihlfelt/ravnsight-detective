<?php
/**
 * Local correlator: for a critical error, rank recent change signals as
 * suspects — same rules as the platform (HIGH needs a component match,
 * time alone caps at medium, changes after the error count against).
 * Free and Pro alike: the data is local, so the analysis is too.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Support;

use Ravnsight\Detective\Core\Migrator;

defined( 'ABSPATH' ) || exit;

final class Correlator {

	const WINDOW_HOURS     = 72;
	const HIGH_GAP_HOURS   = 24;
	const NEAR_GAP_MINUTES = 60;

	/**
	 * Analyze one signal row. Errors, warnings and performance signals all
	 * get an analysis — change signals are the suspects, not the subjects.
	 *
	 * @param object $row Signal row (from the ravndet_signals table).
	 * @return array{confidence: string, lines: array<int, array{sign: string, text: string}>}|null
	 */
	public static function analyze( $row ) {
		if ( 0 !== strpos( (string) $row->type, 'error.' ) && 0 !== strpos( (string) $row->type, 'perf.' ) ) {
			return null;
		}

		global $wpdb;
		$table    = Migrator::table( 'signals' );
		$since    = (int) $row->first_seen - self::WINDOW_HOURS * HOUR_IN_SECONDS;
		$like     = $wpdb->esc_like( 'change.' ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, admin-only read.
		$changes = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE type LIKE %s AND last_seen >= %d ORDER BY last_seen DESC LIMIT 50', $table, $like, $since ) );
		// phpcs:enable

		$suspect_component = (string) $row->component_id;
		$best              = null;
		$best_rank         = 0;

		$own_slugs = array( dirname( RAVNDET_BASENAME ), RAVNDET_SLUG, RAVNDET_SLUG . '-pro' );

		foreach ( (array) $changes as $change ) {
			// Never list the Detective itself as a suspect: it activates the
			// moment monitoring begins, so it precedes every first-seen error.
			$change_context = json_decode( (string) $change->context, true );
			if ( ! empty( $change_context['self'] ) || in_array( (string) $change->component_id, $own_slugs, true ) ) {
				continue;
			}
			$gap_minutes     = (int) round( ( (int) $row->first_seen - (int) $change->last_seen ) / 60 );
			$component_match = '' !== $suspect_component && (string) $change->component_id === $suspect_component;

			if ( $component_match && $gap_minutes >= 0 && $gap_minutes <= self::HIGH_GAP_HOURS * 60 ) {
				$rank = 3;
			} elseif ( ( $component_match && $gap_minutes >= 0 ) || ( $gap_minutes >= 0 && $gap_minutes <= self::NEAR_GAP_MINUTES ) ) {
				$rank = 2;
			} else {
				$rank = 1;
			}

			if ( $rank > $best_rank || ( $rank === $best_rank && null !== $best && abs( $gap_minutes ) < abs( $best['gap'] ) ) ) {
				$best      = array(
					'change' => $change,
					'gap'    => $gap_minutes,
					'match'  => $component_match,
					'rank'   => $rank,
				);
				$best_rank = $rank;
			}
		}

		$lines = array();

		if ( null === $best ) {
			if ( '' === $suspect_component ) {
				return null;
			}
			$lines[] = array(
				'sign' => '+',
			/* translators: %s: component slug. */
				'text' => sprintf( __( 'The error names %s as the failing code.', 'ravnsight-detective' ), $suspect_component ),
			);
			$lines[] = array(
				'sign' => '-',
			/* translators: %d: number of hours. */
				'text' => sprintf( __( 'No recorded changes in the last %d hours.', 'ravnsight-detective' ), self::WINDOW_HOURS ),
			);

			return array(
				'confidence' => 'low',
				'lines'      => $lines,
				'suspects'   => array( $suspect_component ),
			);
		}

		$change     = $best['change'];
		$confidence = array( 1 => 'low', 2 => 'medium', 3 => 'high' )[ $best['rank'] ];
		$gap_label  = $best['gap'] >= 120 ? sprintf( '%d h', (int) floor( $best['gap'] / 60 ) ) : sprintf( '%d min', max( (int) abs( $best['gap'] ), 1 ) );

		if ( $best['match'] ) {
			$lines[] = array(
				'sign' => '+',
			/* translators: %s: component slug. */
				'text' => sprintf( __( 'The error names %s as the failing code.', 'ravnsight-detective' ), $suspect_component ),
			);
		}
		if ( $best['gap'] >= 0 ) {
			$lines[] = array(
				'sign' => '+',
			/* translators: 1: change description, 2: component, 3: time gap. */
				'text' => sprintf( __( '%1$s (%2$s) %3$s before the error first appeared.', 'ravnsight-detective' ), SignalInfo::label( (string) $change->type ), (string) $change->component_id, $gap_label ),
			);
		} else {
			$confidence = 'low';
			$lines[] = array(
				'sign' => '-',
			/* translators: 1: change description, 2: component, 3: time gap. */
				'text' => sprintf( __( '%1$s (%2$s) happened %3$s AFTER the error appeared — speaks against a link.', 'ravnsight-detective' ), SignalInfo::label( (string) $change->type ), (string) $change->component_id, $gap_label ),
			);
		}

		// Up to two alternative suspects.
		$listed = 0;
		foreach ( (array) $changes as $change_row ) {
			if ( $change_row === $change || $listed >= 2 ) {
				continue;
			}
			$alt_context = json_decode( (string) $change_row->context, true );
			if ( ! empty( $alt_context['self'] ) || in_array( (string) $change_row->component_id, $own_slugs, true ) ) {
				continue;
			}
			$alt_gap = (int) round( ( (int) $row->first_seen - (int) $change_row->last_seen ) / 60 );
			if ( $alt_gap < 0 ) {
				continue;
			}
			$alt_label = $alt_gap >= 120 ? sprintf( '%d h', (int) floor( $alt_gap / 60 ) ) : sprintf( '%d min', max( $alt_gap, 1 ) );
			$lines[] = array(
				'sign' => '?',
			/* translators: 1: change description, 2: component, 3: time gap. */
				'text' => sprintf( __( 'Also %1$s (%2$s) %3$s before — alternative suspect.', 'ravnsight-detective' ), SignalInfo::label( (string) $change_row->type ), (string) $change_row->component_id, $alt_label ),
			);
			++$listed;
		}

		return array(
			'confidence' => $confidence,
			'lines'      => $lines,
			'suspects'   => self::collect_suspects( $row, $change, $changes, $own_slugs ),
		);
	}

	/**
	 * Ordered candidate list for the "what was the cause" dropdown: the
	 * prime suspect first, then alternatives, then the component the error
	 * itself names. Concrete choices beat free text — they are what makes
	 * the hypothesis check ("did we guess right?") possible at all.
	 *
	 * @param object      $row       Signal row.
	 * @param object|null $change    Best change candidate.
	 * @param array       $changes   All change rows in the window.
	 * @param array       $own_slugs Our own slugs (never suspects).
	 * @return array<int, string>
	 */
	private static function collect_suspects( $row, $change, $changes, $own_slugs ) {
		$suspects = array();
		if ( null !== $change && ! empty( $change->component_id ) ) {
			$suspects[] = (string) $change->component_id;
		}
		if ( ! empty( $row->component_id ) ) {
			$suspects[] = (string) $row->component_id;
		}
		foreach ( (array) $changes as $change_row ) {
			if ( count( $suspects ) >= 6 ) {
				break;
			}
			$slug = (string) $change_row->component_id;
			if ( '' === $slug || in_array( $slug, $own_slugs, true ) ) {
				continue;
			}
			$suspects[] = $slug;
		}

		return array_values( array_unique( $suspects ) );
	}

	/**
	 * Human label for a confidence tier.
	 *
	 * @param string $confidence low|medium|high.
	 * @return string
	 */
	public static function confidence_label( $confidence ) {
		$labels = array(
			'low'    => __( 'Low confidence', 'ravnsight-detective' ),
			'medium' => __( 'Medium confidence', 'ravnsight-detective' ),
			'high'   => __( 'High confidence', 'ravnsight-detective' ),
		);

		return $labels[ $confidence ] ?? $confidence;
	}
}
