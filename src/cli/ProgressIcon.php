<?php

namespace hati\cli;

/**
 * A set of constants for setting various progress icons to
 * {@link CLI::setProgressIcon()}
 *
 * @since 5.0.0
 * */

enum ProgressIcon {
	case BRAILLE;
	case SNAKE;
	case DOT_RUNNING_AROUND;
	case HALF_CIRCLE_SPINNING;
	case CIRCLE_QUARTER;
	case SQUARE_QUARTER;
	case SLASHES;
	case BDPQ;
	case TRIANGLE_QUARTER;
	case PULSE_DOT;
	case PULSE_SQUARE;
	case PULSE_CIRCLE;
}